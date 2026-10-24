<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * In the folder-based deployment model, the "admin" folder is a physical
 * instance of this same codebase whose .env sets `app.centralAdminMode =
 * true`. It only exists to serve the central superadmin app; it must never
 * render a faculty's public pages (which is what would otherwise happen,
 * since every instance shares the same code and, without this guard, would
 * fall back to rendering the default/faculty site for unmatched routes).
 *
 * This filter runs globally and is a no-op on every normal faculty
 * instance. On the central admin instance, it lets `/admin/*`, the
 * authentication routes, and a small utility allowlist through, and sends
 * everything else to `/admin` (which itself redirects to the login page
 * when the visitor isn't authenticated).
 */
class CentralAdminOnlyFilter implements FilterInterface
{
    /**
     * First URL segments that remain reachable on the central admin
     * instance in addition to `admin/*`.
     *
     * @var list<string>
     */
    private array $allowedFirstSegments = [
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'verify-email',
        'magic-link',
        'healthz',
        'language',
    ];

    public function before(RequestInterface $request, $arguments = null): RedirectResponse|ResponseInterface|null
    {
        if (! service('adminAccess')->isCentralAdminInstance()) {
            return null;
        }

        $firstSegment = trim((string) $request->getUri()->getSegment(1), '/');

        if ($firstSegment === 'admin' || in_array($firstSegment, $this->allowedFirstSegments, true)) {
            return null;
        }

        return redirect()->to('/admin');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
