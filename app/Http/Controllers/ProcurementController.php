<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcurementRequest;
use App\Http\Requests\UpdateProcurementRequest;
use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware('role:Endorser|Administrator')->except(['index', 'show']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $endUserId = $request->integer('end_user_id');
        $particularId = $request->integer('particular_id');
        $dateFrom = $request->string('date_from')->trim()->value();
        $dateTo = $request->string('date_to')->trim()->value();
        $myProcurements = $request->boolean('my_procurements');

        $query = Procurement::query()
            ->with([
                'endUser:id,name,abbreviation',
                'particular:id,description',
                'creator:id,name',
            ])
            ->withCount('transactions');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('endUser', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('particular', fn ($sub) => $sub->where('description', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($endUserId) {
            $query->where('end_user_id', $endUserId);
        }

        if ($particularId) {
            $query->where('particular_id', $particularId);
        }

        if ($dateFrom) {
            $query->whereDate('date_of_entry', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date_of_entry', '<=', $dateTo);
        }

        if ($myProcurements) {
            $query->where('created_by_user_id', $user->id);
        }

        $procurements = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Procurements/Index', [
            'procurements' => $procurements,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'end_user_id' => $endUserId ?: null,
                'particular_id' => $particularId ?: null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'my_procurements' => $myProcurements,
            ],
            'options' => [
                'statuses' => Procurement::STATUSES,
                'offices' => Office::query()->orderBy('name')->get(['id', 'name']),
                'particulars' => Particular::query()->orderBy('description')->get(['id', 'description']),
            ],
            'can' => [
                'manage' => $user->hasAnyRole(['Endorser', 'Administrator']),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Procurements/Create', [
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'particulars' => Particular::query()->where('is_active', true)->orderBy('description')->get(['id', 'description']),
        ]);
    }

    public function store(StoreProcurementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = Procurement::STATUS_CREATED;
        $data['created_by_user_id'] = $request->user()->id;

        Procurement::create($data);

        return redirect()
            ->route('procurements.index')
            ->with('success', 'Procurement created successfully.');
    }

    public function show(Procurement $procurement): Response
    {
        $procurement->load([
            'endUser:id,name,abbreviation',
            'particular:id,description',
            'creator:id,name',
            'purchaseRequest',
            'purchaseOrder',
            'voucher',
            'statusHistory' => fn ($query) => $query->with('changedBy:id,name')->orderByDesc('created_at'),
        ])->loadCount('transactions');

        return Inertia::render('Procurements/Show', [
            'procurement' => $procurement,
            'can' => [
                'manage' => request()->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false,
            ],
        ]);
    }

    public function edit(Procurement $procurement): Response
    {
        $procurement->load(['endUser:id,name', 'particular:id,description'])->loadCount('transactions');

        return Inertia::render('Procurements/Edit', [
            'procurement' => $procurement,
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'particulars' => Particular::query()->where('is_active', true)->orderBy('description')->get(['id', 'description']),
            'locked' => $procurement->transactions_count > 0,
        ]);
    }

    public function update(UpdateProcurementRequest $request, Procurement $procurement): RedirectResponse
    {
        $data = $request->validated();

        $procurement->fill([
            'purpose' => $data['purpose'],
            'abc_amount' => $data['abc_amount'],
            'date_of_entry' => $data['date_of_entry'],
        ]);

        if (! $procurement->hasTransactions()) {
            $procurement->fill([
                'end_user_id' => $data['end_user_id'],
                'particular_id' => $data['particular_id'],
            ]);
        }

        $procurement->save();

        return redirect()
            ->route('procurements.index')
            ->with('success', 'Procurement updated successfully.');
    }

    public function destroy(Procurement $procurement): RedirectResponse
    {
        $hasTransactions = $procurement->hasTransactions();

        $procurement->delete();

        $message = $hasTransactions
            ? 'Procurement archived. Linked transactions remain available.'
            : 'Procurement archived successfully.';

        return redirect()
            ->route('procurements.index')
            ->with('success', $message);
    }
}
