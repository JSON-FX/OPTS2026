<?php

namespace App\Http\Middleware;

use App\Models\Transaction;
use App\Services\ConnectPilotHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'connectWidget' => $request->user()?->is_active && app(ConnectPilotHost::class)->configured()
                ? [
                    'origin' => config('connect_pilot.connect_origin'),
                    'launchUrl' => route('connect.pilot.launch', absolute: false),
                    'identity' => $request->user()->sso_uuid,
                ] : null,
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'office_id' => $request->user()->office_id,
                    'is_active' => $request->user()->is_active,
                    'sso_role' => $request->user()->sso_role,
                    'sso_position' => $request->user()->sso_position,
                    'can_process_transactions' => $request->user()->canProcessTransactions(),
                    'roles' => $request->user()->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name]),
                    'office' => $request->user()->office?->only(['id', 'name', 'abbreviation', 'is_active']),
                ] : null,
            ],
            'pendingReceiptsCount' => fn () => $request->user()?->canProcessTransactions()
                ? Transaction::where('current_office_id', $request->user()->office_id)
                    ->whereNull('received_at')
                    ->where('status', 'In Progress')
                    ->count()
                : 0,
            'selectedYear' => fn () => $request->user()
                ? ($request->user()->selected_year ?? (int) now()->year)
                : (int) now()->year,
            'availableYears' => fn () => $request->user()
                ? (function () {
                    $driver = DB::connection()->getDriverName();
                    $yearExpr = $driver === 'sqlite'
                        ? "CAST(strftime('%Y', created_at) AS INTEGER)"
                        : 'YEAR(created_at)';

                    $years = DB::table('procurements')
                        ->selectRaw("DISTINCT {$yearExpr} as year")
                        ->whereNotNull('created_at')
                        ->orderByDesc('year')
                        ->pluck('year')
                        ->map(fn ($y) => (int) $y);

                    if (! $years->contains((int) now()->year)) {
                        $years = $years->prepend((int) now()->year)->sortDesc()->values();
                    }

                    return $years->values()->all();
                })()
                : [(int) now()->year],
            'notifications' => fn () => $request->user()
                ? [
                    'unread_count' => $request->user()->unreadNotifications()->count(),
                    'recent' => $request->user()->notifications()
                        ->take(10)
                        ->get()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'type' => $n->data['type'] ?? 'general',
                            'message' => $n->data['message'] ?? '',
                            'read_at' => $n->read_at?->toISOString(),
                            'created_at' => $n->created_at->diffForHumans(),
                            'data' => $n->data,
                        ]),
                ]
                : ['unread_count' => 0, 'recent' => []],
        ];
    }
}
