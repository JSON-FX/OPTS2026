<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFundTypeRequest;
use App\Http\Requests\UpdateFundTypeRequest;
use App\Models\FundType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FundTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $fundTypes = FundType::orderBy('name')
            ->paginate(50);

        return Inertia::render('Admin/Repositories/FundTypes/Index', [
            'fundTypes' => $fundTypes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Repositories/FundTypes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFundTypeRequest $request): RedirectResponse
    {
        FundType::create($request->validated());

        return redirect()
            ->route('admin.repositories.fund-types.index')
            ->with('success', 'Fund Type created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FundType $fundType): Response
    {
        return Inertia::render('Admin/Repositories/FundTypes/Edit', [
            'fundType' => $fundType,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFundTypeRequest $request, FundType $fundType): RedirectResponse
    {
        $fundType->update($request->validated());

        return redirect()
            ->route('admin.repositories.fund-types.index')
            ->with('success', 'Fund Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FundType $fundType): RedirectResponse
    {
        $fundType->delete();

        return redirect()
            ->route('admin.repositories.fund-types.index')
            ->with('success', 'Fund Type deleted successfully.');
    }
}
