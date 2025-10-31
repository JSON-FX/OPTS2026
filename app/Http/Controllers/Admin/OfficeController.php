<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $offices = Office::withCount('users')
            ->orderBy('name')
            ->paginate(50);

        return Inertia::render('Admin/Repositories/Offices/Index', [
            'offices' => $offices,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Repositories/Offices/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        Office::create($request->validated());

        return redirect()
            ->route('admin.repositories.offices.index')
            ->with('success', 'Office created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Office $office): Response
    {
        $office->loadCount('users');

        return Inertia::render('Admin/Repositories/Offices/Edit', [
            'office' => $office,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $office->update($request->validated());

        return redirect()
            ->route('admin.repositories.offices.index')
            ->with('success', 'Office updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Office $office): RedirectResponse
    {
        $userCount = $office->users()->count();

        if ($userCount > 0) {
            $office->users()->update(['office_id' => null]);
        }

        $office->delete();

        $message = $userCount > 0
            ? "Office deleted successfully. {$userCount} user(s) were unassigned from this office."
            : 'Office deleted successfully.';

        return redirect()
            ->route('admin.repositories.offices.index')
            ->with('success', $message);
    }
}
