<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\UserAdministrationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Psr\Log\LoggerInterface;
use Throwable;

class UserController extends BaseController
{
    private BaseConnection $db;

    private UserModel $users;

    private UserAdministrationService $userAdmin;

    public function initController($request, $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->db = db_connect();
        $this->users = model(UserModel::class);
        $this->userAdmin = service('userAdministrationService');
    }

    public function index(): string|RedirectResponse
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $filters = [
            'q'       => trim((string) $this->request->getGet('q')),
            'status'  => (string) $this->request->getGet('status'),
            'site_id' => (string) $this->request->getGet('site_id'),
        ];
        $siteIdProvided = $this->request->getGet('site_id') !== null;

        $actor = auth()->user();
        $access = service('adminAccess');
        $model = $this->users->withGroups()->withPermissions()->withIdentities();
        $usersTable = config('Auth')->tables['users'];
        $identitiesTable = config('Auth')->tables['identities'];

        $model->select($usersTable . '.*')
            ->join(
                $identitiesTable . ' identities',
                'identities.user_id = ' . $usersTable . '.id AND identities.type = ' . $this->db->escape(Session::ID_TYPE_EMAIL_PASSWORD),
                'left',
            );

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like($usersTable . '.username', $filters['q'])
                ->orLike('identities.secret', $filters['q'])
                ->groupEnd();
        }

        if ($filters['status'] === 'active') {
            $model->where($usersTable . '.active', 1);
        } elseif ($filters['status'] === 'inactive') {
            $model->where($usersTable . '.active', 0);
        }

        $filterSites = [];
        if ($access->isSuperAdmin($actor)) {
            $filterSites = service('siteResolver')->availableSitesForUser($actor);
            $siteFilter = (int) $filters['site_id'];
            // Default to the active faculty when none was chosen; keep « Toutes » when site_id= is submitted empty.
            if (! $siteIdProvided && $siteFilter <= 0 && service('siteResolver')->hasExplicitAdminSiteSelection()) {
                $siteFilter = service('siteResolver')->activeSiteId();
                $filters['site_id'] = (string) $siteFilter;
            }
            if ($siteFilter > 0 && $this->db->tableExists('user_sites')) {
                $assigned = $this->db->table('user_sites')->select('user_id')->where('site_id', $siteFilter)->get()->getResultArray();
                $ids = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['user_id'], $assigned)));
                $ids === []
                    ? $model->where($usersTable . '.id', 0)
                    : $model->whereIn($usersTable . '.id', $ids);
            }
        } else {
            $visibleUserIds = $access->visibleUserIdsForFaculty(service('siteResolver')->activeSiteId());
            $visibleUserIds === []
                ? $model->where($usersTable . '.id', 0)
                : $model->whereIn($usersTable . '.id', $visibleUserIds);
        }

        return view('admin/users/index', [
            'title'       => 'Comptes & accès | Administration',
            'activeAdmin' => 'users',
            'users'       => $users = $model
                ->groupBy($usersTable . '.id')
                ->orderBy($usersTable . '.created_at', 'DESC')
                ->orderBy($usersTable . '.id', 'DESC')
                ->paginate(15, 'admin_users'),
            'pager'       => $model->pager,
            'filters'     => $filters,
            'groups'      => $this->groupsForActor(),
            'permissions' => $this->permissionsForActor(),
            'siteLabelsByUserId' => $this->siteLabelsByUserId($users),
            'currentUser' => auth()->user(),
            'currentActorIsSuperAdmin' => $this->currentActorIsSuperAdmin(),
            'editableUserIds' => $this->editableUserIds($users),
            'filterSites' => $filterSites,
        ]);
    }

    public function new(): string|RedirectResponse
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $sites = $this->sitesForActor();
        $selectedSiteIds = array_map(static fn (object $site): int => (int) $site->id, $sites);
        if ($this->currentActorIsSuperAdmin() && $selectedSiteIds !== []) {
            $selectedSiteIds = [service('siteResolver')->activeSiteId()];
        }

        return view('admin/users/form', [
            'title'          => 'Créer un utilisateur | Administration',
            'activeAdmin'    => 'users',
            'user'           => $this->emptyUser(),
            'groups'         => $this->groupsForActor(),
            'permissions'    => $this->permissionsForActor(),
            'action'         => site_url('admin/users'),
            'isNew'          => true,
            'passwordAction' => null,
            'currentActorIsSuperAdmin' => $this->currentActorIsSuperAdmin(),
            'sites'          => $sites,
            'selectedSiteIds' => $selectedSiteIds,
            'selectedSiteRoles' => $this->defaultSiteRoles($selectedSiteIds, 'editor'),
            'siteRoleOptions' => $this->siteRoleOptions(),
        ]);
    }

    public function create(): RedirectResponse
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $input = $this->sanitizedInput(true);
        $errors = $this->validateInput($input['data'], $input['groups'], $input['permissions'], $input['siteIds'], $input['siteRoles'], true);
        $errors = array_merge($errors, $this->unauthorizedAssignmentAttemptErrors(), $this->superAdminBoundaryErrors(null, $input['groups'], $input['permissions']));

        if ($errors !== []) {
            return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', $errors);
        }

        $user = new User([
            'username' => $input['data']['username'],
            'email'    => $input['data']['email'],
            'password' => $input['data']['password'],
            'active'   => $input['data']['active'],
        ]);

        $this->db->transStart();

        try {
            if ($this->users->save($user) === false) {
                $this->db->transRollback();

                return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', $this->users->errors() ?: ['form' => 'La création a échoué.']);
            }

            $created = $this->users->findById($this->users->getInsertID());
            if (! $created instanceof User) {
                $this->db->transRollback();

                return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', ['form' => 'Le compte créé est introuvable.']);
            }

            $created->syncGroups(...$input['groups']);
            $created->syncPermissions(...$input['permissions']);
            $this->persistSiteRoles((int) $created->id, $input['siteRoles']);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', ['form' => 'La création a échoué.']);
            }

            return redirect()->to('/admin/users')->with('message', 'L’utilisateur a été créé.');
        } catch (Throwable $exception) {
            $this->db->transRollback();
            return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', ['form' => 'La création a échoué.']);
        }
    }

    public function edit(int $id): string|RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if (! $this->canEditTarget($user)) {
            return redirect()->to('/admin/users')->with('error', 'Vous ne pouvez pas modifier ce compte dans ce contexte.');
        }

        return view('admin/users/form', [
            'title'          => 'Modifier un utilisateur | Administration',
            'activeAdmin'    => 'users',
            'user'           => $user,
            'groups'         => $this->groupsForActor(),
            'permissions'    => $this->permissionsForActor(),
            'action'         => site_url('admin/users/' . $user->id),
            'isNew'          => false,
            'passwordAction' => site_url('admin/users/' . $user->id . '/password'),
            'currentActorIsSuperAdmin' => $this->currentActorIsSuperAdmin(),
            'sites'          => $this->sitesForActor(),
            'selectedSiteIds' => service('siteResolver')->assignedSiteIds((int) $user->id) ?: [service('siteResolver')->activeSiteId()],
            'selectedSiteRoles' => $this->siteRolesForUser((int) $user->id),
            'siteRoleOptions' => $this->siteRoleOptions(),
        ]);
    }

    public function update(int $id): RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if (! $this->canEditTarget($user)) {
            return redirect()->to('/admin/users')->with('error', 'Vous ne pouvez pas modifier ce compte dans ce contexte.');
        }

        $input = $this->sanitizedInput(false, $id);
        $errors = $this->validateInput($input['data'], $input['groups'], $input['permissions'], $input['siteIds'], $input['siteRoles'], false, $id);
        $errors = array_merge($errors, $this->unauthorizedAssignmentAttemptErrors($id), $this->superAdminBoundaryErrors($user, $input['groups'], $input['permissions']));

        if ((int) $user->id === (int) auth()->id() && $this->userAdmin->wouldLockOutSelf($user, $input['groups'], $input['permissions'], $input['data']['active'])) {
            $errors['groups'] = 'Vous ne pouvez pas retirer votre propre accès à l’administration.';
        }

        if ($this->wouldDisableLastSuperAdmin($id, $input['data']['active'] === 1, $input['groups'])) {
            $errors['active'] = 'Vous ne pouvez pas désactiver le dernier superadministrateur.';
            $errors['groups'] ??= 'Le dernier superadministrateur doit rester dans le groupe Superadministrateur.';
        }

        if ($errors !== []) {
            return redirect()->to(site_url('admin/users/' . $id . '/edit'))->withInput()->with('errors', $errors);
        }

        $user->username = $input['data']['username'];
        $user->email    = $input['data']['email'];
        $user->active   = $input['data']['active'];

        $this->db->transStart();

        try {
            if ($this->users->save($user) === false) {
                $this->db->transRollback();

                return redirect()->to(site_url('admin/users/' . $id . '/edit'))->withInput()->with('errors', $this->users->errors() ?: ['form' => 'La mise à jour a échoué.']);
            }

            $user->syncGroups(...$input['groups']);
            $user->syncPermissions(...$input['permissions']);
            $this->persistSiteRoles((int) $user->id, $input['siteRoles']);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return redirect()->to(site_url('admin/users/' . $id . '/edit'))->withInput()->with('errors', ['form' => 'La mise à jour a échoué.']);
            }
        } catch (Throwable) {
            $this->db->transRollback();
            return redirect()->to(site_url('admin/users/' . $id . '/edit'))->withInput()->with('errors', ['form' => 'La mise à jour a échoué.']);
        }

        return redirect()->to('/admin/users')->with('message', 'L’utilisateur a été mis à jour.');
    }

    public function activate(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setActiveState($id, true);
    }

    public function deactivate(int $id): RedirectResponse|ResponseInterface
    {
        return $this->setActiveState($id, false);
    }

    public function password(int $id): string|RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if (! $this->canEditTarget($user)) {
            return redirect()->to('/admin/users')->with('error', 'Vous ne pouvez pas réinitialiser ce mot de passe dans ce contexte.');
        }

        return view('admin/users/password', [
            'title'       => 'Réinitialiser le mot de passe | Administration',
            'activeAdmin' => 'users',
            'user'        => $user,
            'action'      => site_url('admin/users/' . $user->id . '/password'),
        ]);
    }

    public function resetPassword(int $id): RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if (! $this->canEditTarget($user)) {
            return redirect()->to('/admin/users')->with('error', 'Vous ne pouvez pas réinitialiser ce mot de passe dans ce contexte.');
        }

        $validation = service('validation');
        $validation->setRules([
            'password' => 'required|min_length[12]',
            'confirm'   => 'required|matches[password]',
        ], [
            'password' => [
                'required'   => 'Le nouveau mot de passe est obligatoire.',
                'min_length' => 'Le mot de passe doit contenir au moins 12 caractères.',
            ],
            'confirm' => [
                'required' => 'La confirmation du mot de passe est obligatoire.',
                'matches'  => 'Les deux mots de passe doivent correspondre.',
            ],
        ]);

        $payload = [
            'password' => trim((string) $this->request->getPost('password')),
            'confirm'   => trim((string) $this->request->getPost('confirm')),
        ];

        if (! $validation->run($payload)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $user->password = $payload['password'];

        try {
            if ($this->users->save($user) === false) {
                return redirect()->back()->withInput()->with('errors', $this->users->errors() ?: ['form' => 'La réinitialisation a échoué.']);
            }
        } catch (Throwable) {
            return redirect()->back()->withInput()->with('errors', ['form' => 'La réinitialisation a échoué.']);
        }

        return redirect()->to('/admin/users/' . $user->id . '/edit')->with('message', 'Le mot de passe a été réinitialisé.');
    }

    /**
     * Supprime un compte utilisateur. Réservé aux superadministrateurs :
     * toutes les associations (groupes, permissions, identités, sessions,
     * jetons et rattachements aux sites) sont retirées avant la suppression
     * douce du compte.
     */
    public function delete(int $id): RedirectResponse|ResponseInterface
    {
        if (! $this->currentActorIsSuperAdmin()) {
            return redirect()->to('/admin/users')->with('error', 'Seul un superadministrateur peut supprimer un compte.');
        }

        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if ((int) $user->id === (int) auth()->id()) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($this->userHasGroup($id, 'superadmin') && $this->userAdmin->countActiveSuperAdmins($id) <= 0) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('error', 'Vous ne pouvez pas supprimer le dernier superadministrateur.');
        }

        $authConfig = config('Auth');
        $this->db->transStart();

        try {
            $this->db->table($authConfig->tables['groups_users'])->where('user_id', $id)->delete();
            $this->db->table($authConfig->tables['permissions_users'])->where('user_id', $id)->delete();

            foreach (['identities', 'logins', 'token_logins', 'remember_tokens'] as $tableKey) {
                $table = $authConfig->tables[$tableKey] ?? null;
                if ($table !== null && $this->db->tableExists($table)) {
                    $this->db->table($table)->where('user_id', $id)->delete();
                }
            }

            if ($this->db->tableExists('user_sites')) {
                $this->db->table('user_sites')->where('user_id', $id)->delete();
            }

            $this->users->delete($id);
            $this->db->transComplete();
        } catch (Throwable) {
            $this->db->transRollback();

            return redirect()->to('/admin/users')->with('error', 'La suppression du compte a échoué.');
        }

        return redirect()->to('/admin/users')->with('message', 'Le compte a été supprimé définitivement.');
    }

    /**
     * @return array{data: array{username: string, email: string, password: string, confirm: string, active: int}, groups: list<string>, permissions: list<string>, siteIds: list<int>, siteRoles: array<int, string>}
     */
    private function sanitizedInput(bool $withPassword, ?int $targetUserId = null): array
    {
        $groups = $this->userAdmin->normalizeSelections((array) $this->request->getPost('groups'));
        $permissions = $this->userAdmin->normalizeSelections((array) $this->request->getPost('permissions'));

        if (! $this->currentActorIsSuperAdmin()) {
            $siteId = service('siteResolver')->activeSiteId();
            // Faculty admins may only assign the editor role. Self-edit of an
            // existing site_admin keeps the admin role so they are not demoted.
            $groups = ['editor'];
            $role   = 'editor';
            if ($targetUserId !== null) {
                $existingRole = service('adminAccess')->siteRole($targetUserId, $siteId);
                if ($existingRole === 'site_admin' && $targetUserId === (int) (auth()->id() ?? 0)) {
                    $groups = ['admin'];
                    $role   = 'site_admin';
                }
            }
            $permissions = [];
            $siteIds = [$siteId];
            $siteRoles = [$siteId => $role];
        } else {
            $siteIds = service('siteResolver')->siteIdsFromRequest($this->request->getPost('site_ids'));
            if ($siteIds === [] && ! in_array('superadmin', $groups, true)) {
                $siteIds = [service('siteResolver')->activeSiteId()];
            }

            $siteRoles = $this->siteRolesFromRequest($siteIds, service('adminAccess')->roleForGroups($groups));
        }

        return [
            'data' => [
                'username' => trim((string) $this->request->getPost('username')),
                'email'    => strtolower(trim((string) $this->request->getPost('email'))),
                'password' => $withPassword ? (string) $this->request->getPost('password') : '',
                'confirm'  => $withPassword ? trim((string) $this->request->getPost('confirm')) : '',
                'active'   => $this->request->getPost('active') === '1' ? 1 : 0,
            ],
            'groups'      => $groups,
            'permissions' => $permissions,
            'siteIds'     => $siteIds,
            'siteRoles'   => $siteRoles,
        ];
    }

    /**
     * @param array<int, string> $siteRoles
     */
    private function persistSiteRoles(int $userId, array $siteRoles): void
    {
        if ($this->currentActorIsSuperAdmin()) {
            service('siteResolver')->syncUserSiteRoles($userId, $siteRoles);

            return;
        }

        foreach ($siteRoles as $siteId => $role) {
            service('siteResolver')->upsertUserSiteRole($userId, (int) $siteId, (string) $role);
        }
    }

    /**
     * @param array{username: string, email: string, password: string, confirm: string, active: int} $data
     * @param list<string> $groups
     * @param list<string> $permissions
     * @param list<int> $siteIds
     *
     * @return array<string, string>
     */
    private function validateInput(array $data, array $groups, array $permissions, array $siteIds, array $siteRoles, bool $withPassword, ?int $ignoreId = null): array
    {
        $validation = service('validation');
        $rules = [
            'username' => $ignoreId === null
                ? 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]|is_unique[users.username]'
                : 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]|is_unique[users.username,id,' . $ignoreId . ']',
            'email'  => 'required|valid_email|max_length[254]',
            'active' => 'required|in_list[0,1]',
        ];

        if ($withPassword) {
            $rules['password'] = 'required|min_length[12]';
            $rules['confirm']  = 'required|matches[password]';
        }

        $validation->setRules($rules, $this->messages());

        $errors = $validation->run($data) ? [] : $validation->getErrors();
        $errors = array_merge($errors, $this->selectionErrors($groups, $permissions), $this->siteSelectionErrors($siteIds, $siteRoles, $groups));

        if ($this->emailAlreadyExists($data['email'], $ignoreId)) {
            $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
        }

        return $errors;
    }

    /**
     * @param list<int> $siteIds
     *
     * @return array<string, string>
     */
    private function siteSelectionErrors(array $siteIds, array $siteRoles, array $groups): array
    {
        if ($siteIds === [] && in_array('superadmin', $groups, true)) {
            return [];
        }

        if ($siteIds === []) {
            return ['site_ids' => 'Sélectionnez au moins un site facultaire.'];
        }

        $available = [];
        foreach (service('siteResolver')->availableSitesForUser(auth()->user()) as $site) {
            $available[(int) $site->id] = true;
        }

        foreach ($siteIds as $siteId) {
            if (! isset($available[$siteId])) {
                return ['site_ids' => 'Un site sélectionné est invalide ou non autorisé.'];
            }
        }

        foreach ($siteIds as $siteId) {
            $role = $siteRoles[$siteId] ?? '';
            if (! array_key_exists($role, $this->siteRoleOptionsForValidation($role))) {
                return ['site_ids' => 'Un rôle facultaire sélectionné est invalide.'];
            }

            if (! $this->currentActorIsSuperAdmin() && $role !== 'editor') {
                $actorId = (int) (auth()->id() ?? 0);
                $isSelfAdmin = $actorId > 0
                    && service('adminAccess')->siteRole($actorId, (int) $siteId) === 'site_admin'
                    && $role === 'site_admin';
                if (! $isSelfAdmin) {
                    return ['site_ids' => 'Un administrateur de faculté peut uniquement créer ou gérer des éditeurs. Seul un superadministrateur peut attribuer le rôle Administrateur.'];
                }
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function siteRoleOptionsForValidation(string $role): array
    {
        $options = $this->siteRoleOptions();
        // Self-admin edit may keep site_admin even though the create form only offers editor.
        if ($role === 'site_admin') {
            $options['site_admin'] = 'Administrateur de cette faculté';
        }

        return $options;
    }

    /**
     * @param list<string> $groups
     * @param list<string> $permissions
     *
     * @return array<string, string>
     */
    private function selectionErrors(array $groups, array $permissions): array
    {
        return $this->userAdmin->validateSelections($groups, $permissions);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function messages(): array
    {
        return [
            'username' => [
                'required'   => 'Le nom d’utilisateur est obligatoire.',
                'min_length' => 'Le nom d’utilisateur doit contenir au moins 3 caractères.',
                'max_length' => 'Le nom d’utilisateur ne peut pas dépasser 30 caractères.',
                'regex_match' => 'Le nom d’utilisateur ne peut contenir que des lettres, des chiffres et des points.',
                'is_unique'  => 'Ce nom d’utilisateur est déjà utilisé.',
            ],
            'email' => [
                'required'    => 'L’adresse électronique est obligatoire.',
                'valid_email' => 'L’adresse électronique doit être valide.',
                'max_length'  => 'L’adresse électronique ne peut pas dépasser 254 caractères.',
            ],
            'active' => [
                'required' => 'L’état du compte est obligatoire.',
                'in_list'  => 'L’état du compte est invalide.',
            ],
            'password' => [
                'required'   => 'Le mot de passe est obligatoire.',
                'min_length' => 'Le mot de passe doit contenir au moins 12 caractères.',
            ],
            'confirm' => [
                'required' => 'La confirmation du mot de passe est obligatoire.',
                'matches'  => 'Les deux mots de passe doivent correspondre.',
            ],
        ];
    }

    private function setActiveState(int $id, bool $active): RedirectResponse|ResponseInterface
    {
        if ($redirect = $this->guardUserManagement()) {
            return $redirect;
        }

        $user = $this->findUser($id);

        if ($user === null) {
            return $this->notFound('Utilisateur introuvable.');
        }

        if (! $this->canEditTarget($user)) {
            return redirect()->to('/admin/users')->with('error', 'Vous ne pouvez pas modifier l’état de ce compte dans ce contexte.');
        }

        if (! $active && $this->wouldDisableLastSuperAdmin($id, false)) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('error', 'Vous ne pouvez pas désactiver le dernier superadministrateur.');
        }

        if ((int) $user->id === (int) auth()->id() && ! $active) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $active ? $user->activate() : $user->deactivate();

        return redirect()->to('/admin/users/' . $id . '/edit')->with('message', $active ? 'Le compte a été activé.' : 'Le compte a été désactivé.');
    }

    /**
     * @param list<string> $proposedGroups
     */
    private function wouldDisableLastSuperAdmin(int $id, bool $active, array $proposedGroups = []): bool
    {
        if (! $this->userHasGroup($id, 'superadmin')) {
            return false;
        }

        if ($active && in_array('superadmin', $proposedGroups, true)) {
            return false;
        }

        return $this->userAdmin->countActiveSuperAdmins($id) <= 0;
    }

    /**
     * @param list<string> $proposedGroups
     *
     * @return array<string, string>
     */
    private function superAdminBoundaryErrors(?User $target, array $proposedGroups, array $proposedPermissions = []): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return [];
        }

        if (in_array('superadmin', $proposedGroups, true)) {
            return ['groups' => 'Seul un superadministrateur peut attribuer le groupe Superadministrateur.'];
        }

        if (in_array('sites.manage', $proposedPermissions, true)) {
            return ['permissions' => 'Seul un superadministrateur peut attribuer la gestion des sites facultaires.'];
        }

        if ($target instanceof User && $this->userHasGroup((int) $target->id, 'superadmin')) {
            return ['groups' => 'Seul un superadministrateur peut modifier un compte superadministrateur.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function unauthorizedAssignmentAttemptErrors(?int $targetUserId = null): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return [];
        }

        $postedGroups = $this->userAdmin->normalizeSelections((array) $this->request->getPost('groups'));
        $postedPermissions = $this->userAdmin->normalizeSelections((array) $this->request->getPost('permissions'));
        $postedRoles = (array) $this->request->getPost('site_roles');
        $errors = [];
        $siteId = service('siteResolver')->activeSiteId();
        $isSelfAdminPreserve = $targetUserId !== null
            && $targetUserId === (int) (auth()->id() ?? 0)
            && service('adminAccess')->siteRole($targetUserId, $siteId) === 'site_admin';

        foreach ($postedGroups as $group) {
            if ($group === 'admin') {
                if ($isSelfAdminPreserve) {
                    continue;
                }

                $errors['groups'] = 'Seul un superadministrateur peut créer un administrateur de faculté. Vous pouvez uniquement créer un éditeur.';
                break;
            }

            if ($group !== 'editor') {
                $errors['groups'] = 'Un administrateur de faculté peut uniquement attribuer le groupe Éditeur.';
                break;
            }
        }

        foreach ($postedRoles as $role) {
            if ((string) $role !== 'site_admin') {
                continue;
            }

            if ($isSelfAdminPreserve) {
                continue;
            }

            $errors['site_ids'] = 'Seul un superadministrateur peut attribuer le rôle Administrateur de faculté.';
            break;
        }

        if ($postedPermissions !== []) {
            $errors['permissions'] = 'Un administrateur de faculté ne peut pas attribuer de permissions directes.';
        }

        return $errors;
    }

    private function currentActorIsSuperAdmin(): bool
    {
        $actorId = (int) (auth()->id() ?? 0);

        return $actorId > 0 && $this->userHasGroup($actorId, 'superadmin');
    }

    private function userHasGroup(int $userId, string $group): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->db->table(config('Auth')->tables['groups_users'])
            ->where('user_id', $userId)
            ->where('group', $group)
            ->countAllResults() > 0;
    }

    private function findUser(int $id): ?User
    {
        $user = model(UserModel::class, false)
            ->withGroups()
            ->withPermissions()
            ->withIdentities()
            ->findById($id);

        return $user instanceof User ? $user : null;
    }

    private function guardUserManagement(): ?RedirectResponse
    {
        $access = service('adminAccess');
        $actor = auth()->user();

        if ($access->isCentralAdminHost($this->request)) {
            return $access->isSuperAdmin($actor)
                ? null
                : redirect()->to('/admin')->with('error', 'Seul un superadministrateur peut gérer les utilisateurs depuis l’administration centrale.');
        }

        return $access->canManageFacultyUsers(service('siteResolver')->activeSiteId(), $actor)
            ? null
            : redirect()->to('/admin')->with('error', 'Vous ne pouvez pas gérer les utilisateurs de cette faculté.');
    }

    private function canEditTarget(User $target): bool
    {
        if ($this->currentActorIsSuperAdmin()) {
            return true;
        }

        return service('adminAccess')->canEditUserForSite($target, service('siteResolver')->activeSiteId(), auth()->user());
    }

    /**
     * @param list<User> $users
     *
     * @return list<int>
     */
    private function editableUserIds(array $users): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return array_values(array_filter(array_map(static fn (User $user): int => (int) $user->id, $users)));
        }

        $siteId = service('siteResolver')->activeSiteId();
        $ids = [];

        foreach ($users as $user) {
            if (service('adminAccess')->canEditUserForSite($user, $siteId, auth()->user())) {
                $ids[] = (int) $user->id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function groupsForActor(): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return $this->userAdmin->groups();
        }

        $groups = $this->userAdmin->groups();

        return [
            'editor' => $groups['editor'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function permissionsForActor(): array
    {
        return $this->currentActorIsSuperAdmin() ? $this->userAdmin->permissions() : [];
    }

    /**
     * @return list<object>
     */
    private function sitesForActor(): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return service('siteResolver')->availableSitesForUser(auth()->user());
        }

        return [service('siteResolver')->activeSite()];
    }

    /**
     * @return array<string, string>
     */
    private function siteRoleOptions(): array
    {
        if ($this->currentActorIsSuperAdmin()) {
            return [
                'site_admin' => 'Administrateur de cette faculté',
                'editor'     => 'Éditeur de cette faculté',
            ];
        }

        return [
            'editor' => 'Éditeur de cette faculté',
        ];
    }

    /**
     * @param list<int> $siteIds
     *
     * @return array<int, string>
     */
    private function defaultSiteRoles(array $siteIds, string $role): array
    {
        $roles = [];

        foreach ($siteIds as $siteId) {
            $roles[(int) $siteId] = $role;
        }

        return $roles;
    }

    /**
     * @param list<int> $siteIds
     *
     * @return array<int, string>
     */
    private function siteRolesFromRequest(array $siteIds, string $defaultRole): array
    {
        $postedRoles = (array) $this->request->getPost('site_roles');
        $roles = [];

        foreach ($siteIds as $siteId) {
            $siteId = (int) $siteId;
            $role = (string) ($postedRoles[$siteId] ?? $defaultRole);
            $roles[$siteId] = array_key_exists($role, $this->siteRoleOptions()) ? $role : $defaultRole;
        }

        return $roles;
    }

    /**
     * @return array<int, string>
     */
    private function siteRolesForUser(int $userId): array
    {
        if ($userId <= 0 || ! $this->db->tableExists('user_sites')) {
            return [];
        }

        $rows = $this->db->table('user_sites')
            ->select('site_id, role')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $roles = [];
        foreach ($rows as $row) {
            $roles[(int) $row['site_id']] = (string) $row['role'];
        }

        return $roles;
    }

    /**
     * @param list<User> $users
     *
     * @return array<int, list<string>>
     */
    private function siteLabelsByUserId(array $users): array
    {
        $userIds = array_values(array_filter(array_map(
            static fn (User $user): int => (int) $user->id,
            $users,
        )));

        if ($userIds === [] || ! $this->db->tableExists('user_sites')) {
            return [];
        }

        $rows = $this->db->table('user_sites')
            ->select('user_sites.user_id, user_sites.role, sites.name, sites.slug, sites.identifier')
            ->join('sites', 'sites.id = user_sites.site_id')
            ->whereIn('user_sites.user_id', $userIds)
            ->orderBy('sites.name', 'ASC')
            ->get()
            ->getResultArray();

        $labels = [];
        foreach ($rows as $row) {
            $short = strtoupper(trim((string) ($row['identifier'] ?? $row['slug'] ?? '')));
            if ($short === '') {
                $short = trim((string) ($row['name'] ?? ''));
            }
            if ($short === '') {
                continue;
            }
            $uid = (int) $row['user_id'];
            if (! isset($labels[$uid])) {
                $labels[$uid] = [];
            }
            if (! in_array($short, $labels[$uid], true)) {
                $labels[$uid][] = $short;
            }
        }

        return $labels;
    }

    private function emailAlreadyExists(string $email, ?int $ignoreId = null): bool
    {
        $usersTable     = config('Auth')->tables['users'];
        $identitiesTable = config('Auth')->tables['identities'];

        $normalized = strtolower(trim($email));
        $original   = trim($email);

        $builder = $this->db->table($usersTable . ' users')
            ->select('users.id')
            ->join(
                $identitiesTable . ' identities',
                'identities.user_id = users.id AND identities.type = ' . $this->db->escape(Session::ID_TYPE_EMAIL_PASSWORD),
                'inner',
            )
            ->groupStart()
                ->where('identities.secret', $normalized)
                ->orWhere('identities.secret', $original)
            ->groupEnd();

        if ($ignoreId !== null) {
            $builder->where('users.id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    private function emptyUser(): User
    {
        return new User([
            'username' => '',
            'email'    => '',
            'active'   => true,
        ]);
    }

    private function notFound(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => $message]));
    }
}
