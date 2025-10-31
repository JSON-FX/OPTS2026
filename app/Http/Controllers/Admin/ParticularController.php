<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticularRequest;
use App\Http\Requests\UpdateParticularRequest;
use App\Models\Particular;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ParticularController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $particulars = Particular::orderBy('description')
            ->paginate(50);

        return Inertia::render('Admin/Repositories/Particulars/Index', [
            'particulars' => $particulars,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Repositories/Particulars/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreParticularRequest $request): RedirectResponse
    {
        Particular::create($request->validated());

        return redirect()
            ->route('admin.repositories.particulars.index')
            ->with('success', 'Particular created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Particular $particular): Response
    {
        return Inertia::render('Admin/Repositories/Particulars/Edit', [
            'particular' => $particular,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParticularRequest $request, Particular $particular): RedirectResponse
    {
        $particular->update($request->validated());

        return redirect()
            ->route('admin.repositories.particulars.index')
            ->with('success', 'Particular updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Particular $particular): RedirectResponse
    {
        $particular->delete();

        return redirect()
            ->route('admin.repositories.particulars.index')
            ->with('success', 'Particular deleted successfully.');
    }
}
