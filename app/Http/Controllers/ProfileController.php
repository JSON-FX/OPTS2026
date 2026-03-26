<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'offices' => Office::where('is_active', true)->get(),
        ]);
    }

    public function updateSelectedYear(Request $request): RedirectResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
        ]);

        $request->user()->update(['selected_year' => $request->integer('year')]);

        return back();
    }
}
