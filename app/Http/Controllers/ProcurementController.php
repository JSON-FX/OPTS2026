<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcurementRequest;
use App\Http\Requests\UpdateProcurementRequest;
use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Services\ProcurementBusinessRules;
use App\Services\ProcurementPresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function __construct(private readonly ProcurementBusinessRules $businessRules, private readonly ProcurementPresentationService $presentation)
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
        $sort = in_array($request->input('sort'), ['newest', 'oldest', 'amount', 'amount_desc', 'amount_asc'], true) ? $request->input('sort') : 'newest';
        $perPage = in_array($request->integer('per_page'), [6, 12, 24, 50], true) ? $request->integer('per_page') : 6;
        $year = $user->selected_year ?? (int) now()->year;
        $statusCounts = array_fill_keys(Procurement::STATUSES, 0);
        foreach (Procurement::query()->whereYear('created_at', $year)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $count) {
            $statusCounts[$count->status] = (int) $count->aggregate;
        }
        $statusCounts['total'] = array_sum($statusCounts);

        $query = Procurement::query()
            ->with([
                'endUser' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
                'particular:id,description',
                'creator:id,name',
                ...$this->presentation->documentRelations(),
            ])
            ->withCount('transactions');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                    ->when(ctype_digit(ltrim($search, '#')), fn ($idQuery) => $idQuery->orWhere('id', (int) ltrim($search, '#')))
                    ->orWhereHas('endUser', fn ($sub) => $sub->withTrashed()->where(fn ($office) => $office->where('name', 'like', "%{$search}%")->orWhere('abbreviation', 'like', "%{$search}%")))
                    ->orWhereHas('transactions', fn ($sub) => $sub->where('reference_number', 'like', "%{$search}%"))
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

        // Apply system-wide year filter
        $query->whereYear('created_at', $year);

        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'amount', 'amount_desc' => $query->orderByDesc('abc_amount'),
            'amount_asc' => $query->orderBy('abc_amount'),
            default => $query->orderByDesc('created_at'),
        };

        $procurements = $query->orderByDesc('id')->paginate($perPage)->withQueryString()
            ->through(fn (Procurement $procurement) => $this->presentation->procurement($procurement, $user));

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
                'sort' => $sort,
                'per_page' => $perPage,
            ],
            'options' => [
                'statuses' => Procurement::STATUSES,
                'offices' => Office::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
                'particulars' => Particular::query()->orderBy('description')->get(['id', 'description']),
            ],
            'formOptions' => $this->presentation->formOptions(),
            'statusCounts' => $statusCounts,
            'initialModal' => $request->routeIs('procurements.create') ? 'create' : null,
            'can' => [
                'manage' => $user->canProcessTransactions(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->index($request);
    }

    public function store(StoreProcurementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = Procurement::STATUS_CREATED;
        $data['created_by_user_id'] = $request->user()->id;

        $procurement = Procurement::create($data);

        return redirect()
            ->route('procurements.show', $procurement)
            ->with('success', 'Procurement created successfully.');
    }

    public function show(Procurement $procurement): Response
    {
        $procurement->load([
            'endUser' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
            'particular:id,description',
            'creator:id,name',
            'purchaseRequest.transaction:id,reference_number,status,created_by_user_id,created_at',
            'purchaseRequest.transaction.createdBy:id,name',
            'purchaseRequest.fundType:id,name,abbreviation',
            'purchaseOrder.transaction:id,reference_number,status,created_by_user_id,created_at',
            'purchaseOrder.transaction.createdBy:id,name',
            'purchaseOrder.supplier:id,name',
            'voucher.transaction:id,reference_number,status,created_by_user_id,created_at',
            'voucher.transaction.createdBy:id,name',
            'statusHistory' => fn ($query) => $query->with('changedBy:id,name')->orderByDesc('created_at')->orderByDesc('id'),
            ...$this->presentation->documentRelations(),
            'transactions.actions' => fn ($query) => $query
                ->with([
                    'fromUser:id,name',
                    'toUser:id,name',
                    'fromOffice' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
                    'toOffice' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
                    'actionTaken:id,description',
                ])
                ->orderByDesc('created_at'),
        ])->loadCount('transactions');

        $activityTimeline = $procurement->transactions
            ->flatMap(fn ($transaction) => $transaction->actions->map(fn ($action) => [
                'id' => $action->id,
                'transaction_id' => $transaction->id,
                'url' => $this->presentation->documentUrl($transaction),
                'action_type' => $action->action_type,
                'transaction_category' => $transaction->category,
                'transaction_reference_number' => $transaction->reference_number,
                'from_user' => $action->fromUser ? ['id' => $action->fromUser->id, 'name' => $action->fromUser->name] : null,
                'to_user' => $action->toUser ? ['id' => $action->toUser->id, 'name' => $action->toUser->name] : null,
                'from_office' => $action->fromOffice ? ['id' => $action->fromOffice->id, 'name' => $action->fromOffice->name, 'abbreviation' => $action->fromOffice->abbreviation] : null,
                'to_office' => $action->toOffice ? ['id' => $action->toOffice->id, 'name' => $action->toOffice->name, 'abbreviation' => $action->toOffice->abbreviation] : null,
                'action_taken' => $action->actionTaken?->description,
                'notes' => $action->notes,
                'reason' => $action->reason,
                'is_out_of_workflow' => $action->is_out_of_workflow,
                'created_at' => $action->created_at->toISOString(),
            ]))
            ->sortByDesc('created_at')
            ->values()
            ->all();

        return Inertia::render('Procurements/Show', [
            'procurement' => $this->presentation->procurement($procurement, request()->user(), true),
            'formOptions' => $this->presentation->formOptions(),
            'initialModal' => request()->routeIs('procurements.edit') ? 'edit' : null,
            'activityTimeline' => $activityTimeline,
            'can' => [
                'manage' => request()->user()?->canProcessTransactions() ?? false,
            ],
            'canCreatePR' => $this->businessRules->canCreatePR($procurement),
            'canCreatePO' => $this->businessRules->canCreatePO($procurement),
            'canCreateVCH' => $this->businessRules->canCreateVCH($procurement),
        ]);
    }

    public function edit(Procurement $procurement): Response
    {
        return $this->show($procurement);
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
            ->route('procurements.show', $procurement)
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
