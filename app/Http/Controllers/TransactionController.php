<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of all transactions with search and filtering.
     */
    public function index(Request $request): Response
    {
        $query = Transaction::query()
            ->select(
                'transactions.id',
                'transactions.reference_number',
                'transactions.category',
                'transactions.status',
                'transactions.procurement_id',
                'transactions.created_at',
                'procurements.purpose as procurement_purpose',
                'offices.name as procurement_end_user_name',
                'users.name as created_by_name'
            )
            ->join('procurements', 'transactions.procurement_id', '=', 'procurements.id')
            ->join('offices', 'procurements.end_user_id', '=', 'offices.id')
            ->join('users', 'transactions.created_by_user_id', '=', 'users.id');

        // Filter by reference number (partial match)
        if ($request->filled('reference_number')) {
            $query->where('transactions.reference_number', 'LIKE', '%'.$request->reference_number.'%');
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('transactions.category', $request->category);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('transactions.status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transactions.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transactions.created_at', '<=', $request->date_to);
        }

        // Filter by end user office
        if ($request->filled('end_user_id')) {
            $query->where('procurements.end_user_id', $request->end_user_id);
        }

        // Filter by current office (where transaction currently sits in workflow)
        if ($request->filled('current_office_id')) {
            $query->where('transactions.current_office_id', $request->current_office_id);
        }

        // Filter by created by me
        if ($request->boolean('created_by_me')) {
            $query->where('transactions.created_by_user_id', $request->user()->id);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        // Map frontend sort keys to database columns
        $sortableColumns = [
            'reference_number' => 'transactions.reference_number',
            'category' => 'transactions.category',
            'status' => 'transactions.status',
            'created_at' => 'transactions.created_at',
        ];

        $sortColumn = $sortableColumns[$sortBy] ?? 'transactions.created_at';
        $query->orderBy($sortColumn, $sortDirection);

        // Paginate results
        $transactions = $query->paginate(50)->withQueryString();

        // Get all offices for filter dropdown
        $offices = \App\Models\Office::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Calculate RBAC permissions
        $canManage = $request->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only([
                'reference_number',
                'category',
                'status',
                'date_from',
                'date_to',
                'end_user_id',
                'current_office_id',
                'created_by_me',
                'sort_by',
                'sort_direction',
            ]),
            'offices' => $offices,
            'can' => [
                'manage' => $canManage,
            ],
        ]);
    }
}
