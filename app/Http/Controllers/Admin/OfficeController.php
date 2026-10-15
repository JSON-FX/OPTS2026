<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SSOUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Services\SSOOfficeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request, SSOOfficeService $offices): Response
    {
        $query = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($query['search'] ?? '');

        try {
            $offices->refreshIfStale();
        } catch (SSOUnavailableException $exception) {
            abort(503, 'The SSO office directory is unavailable. Please try again.', $exception->retryHeaders());
        }

        return Inertia::render('Admin/Repositories/Offices/Index', [
            'offices' => Office::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('abbreviation', 'like', '%'.$search.'%')))
                ->orderBy('abbreviation')
                ->paginate(50, ['id', 'sso_id', 'name', 'abbreviation', 'is_active', 'sso_synced_at'])
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }
}
