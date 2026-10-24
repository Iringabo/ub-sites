<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\ContactMessage;
use App\Models\ContactMessageModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Psr\Log\LoggerInterface;

class MessageController extends BaseController
{
    private ContactMessageModel $messages;

    public function initController($request, $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->messages = model(ContactMessageModel::class, false)->forSite();
    }

    public function index(): string|ResponseInterface
    {
        $filters = $this->filters();
        $model   = $this->applyFilters(model(ContactMessageModel::class, false)->forSite(), $filters);

        $messages = $model
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(15, 'admin_messages');

        $pager = $model->pager;
        $queryString = $this->queryString($filters);
        if ($pager !== null) {
            $pager->setPath(site_url('admin/messages') . $queryString);
        }

        return view('admin/messages/index', [
            'title'       => 'Messages de contact | Administration',
            'activeAdmin' => 'messages',
            'messages'    => $messages,
            'pager'       => $pager,
            'filters'     => $filters,
            'statuses'    => $this->statuses(),
        ]);
    }

    public function show(int $id): string|ResponseInterface
    {
        $message = $this->findMessage($id);

        if ($message === null) {
            return $this->notFound('Message introuvable.');
        }

        return view('admin/messages/show', [
            'title'       => 'Message de contact | Administration',
            'activeAdmin' => 'messages',
            'message'     => $message,
            'statuses'    => $this->statuses(),
            'processedBy' => $this->processedBy($message->processed_by),
        ]);
    }

    public function markRead(int $id): RedirectResponse|ResponseInterface
    {
        return $this->changeStatus($id, 'read', false, 'Le message a été marqué comme lu.');
    }

    public function markHandled(int $id): RedirectResponse|ResponseInterface
    {
        return $this->changeStatus($id, 'handled', true, 'Le message a été marqué comme traité.');
    }

    public function archive(int $id): RedirectResponse|ResponseInterface
    {
        return $this->changeStatus($id, 'archived', true, 'Le message a été archivé.');
    }

    public function delete(int $id): RedirectResponse|ResponseInterface
    {
        $message = $this->findMessage($id);

        if ($message === null) {
            return $this->notFound('Message introuvable.');
        }

        $payload = [];

        if ($message->status !== 'archived') {
            $payload['status'] = 'archived';
        }

        if ($message->read_at === null) {
            $payload['read_at'] = Time::now()->toDateTimeString();
        }

        $payload['processed_by'] = auth()->id();

        $this->messages->skipValidation(true);

        if ($payload !== []) {
            $updated = $this->messages->update($id, $payload);
            if ($updated === false) {
                $this->messages->skipValidation(false);

                return redirect()->back()->with('error', 'La suppression n’a pas pu être préparée.');
            }
        }

        $deleted = $this->messages->delete($id);
        $this->messages->skipValidation(false);

        if ($deleted === false) {
            return redirect()->back()->with('error', 'La suppression n’a pas pu être effectuée.');
        }

        return redirect()->to('/admin/messages')->with('message', 'Le message a été supprimé.');
    }

    /**
     * @return array{q: string, status: string, from: string, to: string}
     */
    private function filters(): array
    {
        return [
            'q'      => trim((string) $this->request->getGet('q')),
            'status' => (string) $this->request->getGet('status'),
            'from'   => (string) $this->request->getGet('from'),
            'to'     => (string) $this->request->getGet('to'),
        ];
    }

    /**
     * @param array{q: string, status: string, from: string, to: string} $filters
     */
    private function applyFilters(ContactMessageModel $model, array $filters): ContactMessageModel
    {
        $statuses = array_keys($this->statuses());

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('name', $filters['q'])
                ->orLike('email', $filters['q'])
                ->orLike('subject', $filters['q'])
                ->orLike('message', $filters['q'])
                ->groupEnd();
        }

        if (in_array($filters['status'], $statuses, true)) {
            $model->where('status', $filters['status']);
        }

        $from = $this->boundary($filters['from'], false);
        if ($from !== null) {
            $model->where('created_at >=', $from);
        }

        $to = $this->boundary($filters['to'], true);
        if ($to !== null) {
            $model->where('created_at <=', $to);
        }

        return $model;
    }

    private function changeStatus(int $id, string $status, bool $touchProcessedBy, string $successMessage): RedirectResponse|ResponseInterface
    {
        $message = $this->findMessage($id);

        if ($message === null) {
            return $this->notFound('Message introuvable.');
        }

        $payload = ['status' => $status];

        if ($message->read_at === null || $status !== 'read') {
            $payload['read_at'] = $message->read_at ?? Time::now()->toDateTimeString();
        }

        if ($touchProcessedBy) {
            $payload['processed_by'] = auth()->id();
        }

        $this->messages->skipValidation(true);
        $saved = $this->messages->update($id, $payload);
        $this->messages->skipValidation(false);

        if ($saved === false) {
            return redirect()->back()->with('error', 'Le statut n’a pas pu être mis à jour.');
        }

        return redirect()->to('/admin/messages/' . $id)->with('message', $successMessage);
    }

    private function findMessage(int $id): ?ContactMessage
    {
        $message = model(ContactMessageModel::class, false)->forSite()->find($id);

        return $message instanceof ContactMessage ? $message : null;
    }

    /**
     * @param array<string, string> $filters
     */
    private function queryString(array $filters): string
    {
        $query = http_build_query(array_filter(
            $filters,
            static fn (string $value): bool => $value !== '',
        ));

        return $query === '' ? '' : '?' . $query;
    }

    private function boundary(string $value, bool $endOfDay): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp) . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'new'      => 'Nouveau',
            'read'     => 'Lu',
            'handled'  => 'Traité',
            'archived' => 'Archivé',
        ];
    }

    private function processedBy(?int $userId): ?User
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        /** @var User|null $user */
        $user = model(UserModel::class)->findById($userId);

        return $user;
    }

    private function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }
}
