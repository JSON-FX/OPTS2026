<?php

namespace App\Http\Middleware;

use App\Models\Transaction;
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
            'auth' => [
                'user' => $request->user()?->load(['roles', 'office']),
            ],
            'pendingReceiptsCount' => fn () => $request->user()
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
