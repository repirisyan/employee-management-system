<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $company = Schema::hasTable('company_profiles') ? CompanyProfile::current() : null;

        return [
            ...parent::share($request),
            'name' => $company?->name ?: config('app.name'),
            'isProduction' => app()->isProduction(),
            'company' => $company ? [
                'name' => $company->name,
                'logo' => $company->logo,
                'logo_url' => $company->logo_url,
                'work_start_time' => $company->work_start_time ? substr($company->work_start_time, 0, 5) : '08:00',
                'work_end_time' => $company->work_end_time ? substr($company->work_end_time, 0, 5) : '17:00',
                'late_tolerance_minutes' => (int) ($company->late_tolerance_minutes ?? 0),
            ] : null,
            'auth' => [
                'user' => $request->user()?->loadMissing(['employee.role', 'employee.department']),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
