<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicSiteController
{
    /**
     * Root entry point. Akubumdes runs in pure operational mode, so every
     * visitor is routed into the internal flow: Login -> Dashboard.
     *
     * - Guests are sent to the operational login page.
     * - Superadmins go straight to the platform admin panel.
     * - Regency supervisors go to the regency dashboard.
     * - Everyone else lands on the tenant operational dashboard.
     */
    public function home(Request $request): RedirectResponse
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->is_superadmin ?? false) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->is_regency_user ?? false) {
                return redirect()->route('regency.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }

    /**
     * Public blog index. The public blog portal no longer exists; the route
     * is kept so stray links redirect into the operational flow instead of 404.
     */
    public function posts(Request $request): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * Public blog post detail. See {@see self::posts()}.
     */
    public function post(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * Public static page detail. See {@see self::posts()}.
     */
    public function page(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * Public contact page. The marketing contact page no longer exists.
     */
    public function contact(Request $request): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * Public contact-form submission. The form no longer exists; the route is
     * kept (still rate-limited at the route level) so old posts are rejected
     * quietly instead of raising a 404 or 405.
     */
    public function storeMessage(Request $request): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * No public content is exposed anymore, so the sitemap is empty.
     */
    public function sitemap(Request $request): Response
    {
        return response('', 404);
    }

    /**
     * The entire site is internal operational software: nothing may be
     * crawled or indexed.
     */
    public function robots(Request $request): Response
    {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
    }
}
