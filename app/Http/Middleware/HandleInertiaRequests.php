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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $this->notifications($request),
        ];
    }

    private function notifications(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['unread_count' => 0, 'recent' => []];
        }

        $recent = $user->notifications()->take(10)->get();
        $transactions = Transaction::query()
            ->whereIn('id', $recent->map(fn ($notice) => $notice->data['transaction_id'] ?? null)->filter())
            ->whereHas('procurement')
            ->with(['purchaseRequest:id,transaction_id', 'purchaseOrder:id,transaction_id', 'voucher:id,transaction_id'])
            ->get()->keyBy('id');

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $recent->map(function ($notice) use ($transactions) {
                $transaction = $transactions->get($notice->data['transaction_id'] ?? null);
                [$routeName, $entityId] = match ($transaction?->category) {
                    'PR' => ['purchase-requests.show', $transaction->purchaseRequest?->id],
                    'PO' => ['purchase-orders.show', $transaction->purchaseOrder?->id],
                    'VCH' => ['vouchers.show', $transaction->voucher?->id],
                    default => [null, null],
                };

                return [
                    'id' => $notice->id,
                    'type' => $notice->data['type'] ?? 'general',
                    'message' => $notice->data['message'] ?? '',
                    'read_at' => $notice->read_at?->toISOString(),
                    'created_at' => $notice->created_at->diffForHumans(),
                    'data' => [...$notice->data, 'document_url' => $entityId ? route($routeName, $entityId) : null],
                ];
            }),
        ];
    }
}
