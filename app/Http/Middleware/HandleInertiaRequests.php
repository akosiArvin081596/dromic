<?php

namespace App\Http\Middleware;

use App\Services\MessengerService;
use Illuminate\Http\Request;
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
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'role' => $user->role?->value ?? 'lgu',
                    'user_type' => $user->user_type?->value,
                    'user_type_label' => $user->user_type?->label(),
                    'is_view_only' => $user->isViewOnly(),
                    'region_name' => $user->region?->name,
                    'province_name' => $user->province?->name,
                    'city_municipality_name' => $user->cityMunicipality?->name,
                ] : null,
            ],
            'unreadNotificationCount' => $user?->unreadNotifications()->count() ?? 0,
            'unreadMessageCount' => $user ? app(MessengerService::class)->getUnreadCount($user) : 0,
        ];
    }
}
