<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActionTakenRequest;
use App\Http\Requests\UpdateActionTakenRequest;
use App\Models\ActionTaken;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActionTakenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $actionTaken = ActionTaken::orderBy('description')
            ->paginate(50);

        return Inertia::render('Admin/Repositories/ActionTaken/Index', [
            'actionTaken' => $actionTaken,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Repositories/ActionTaken/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActionTakenRequest $request): RedirectResponse
    {
        ActionTaken::create($request->validated());

        return redirect()
            ->route('admin.repositories.action-taken.index')
            ->with('success', 'Action Taken created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ActionTaken $actionTaken): Response
    {
        return Inertia::render('Admin/Repositories/ActionTaken/Edit', [
            'actionTaken' => $actionTaken,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateActionTakenRequest $request, ActionTaken $actionTaken): RedirectResponse
    {
        $actionTaken->update($request->validated());

        return redirect()
            ->route('admin.repositories.action-taken.index')
            ->with('success', 'Action Taken updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActionTaken $actionTaken): RedirectResponse
    {
        $actionTaken->delete();

        return redirect()
            ->route('admin.repositories.action-taken.index')
            ->with('success', 'Action Taken deleted successfully.');
    }
}
