<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

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
        return array_merge(parent::share($request), [
            'auth.user.permissions' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->getAllPermissions()->pluck('name');
                }
                return [];
            },
            'auth.user.nextAttendance' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->getNextAttendance();
                }

                return null;
            },
            // NUEVO: Compartir la entrada de tiempo activa (si existe)
            'auth.user.active_entry' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->activeTimeEntry()
                        ->with('project:id,name') // Solo traemos id y nombre del proyecto
                        ->first();
                }
                return null;
            },
        ]);
    }
}