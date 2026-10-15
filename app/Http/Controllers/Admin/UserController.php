<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SSOUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\SSOService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request, SSOService $sso): Response
    {
        $query = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $directory = $sso->directory([
                'search' => trim($query['search'] ?? ''),
                'page' => (int) ($query['page'] ?? 1),
                'per_page' => 20,
            ]);
        } catch (SSOUnavailableException $exception) {
            abort(503, 'The SSO user directory is unavailable. Please try again.');
        }

        $users = collect($directory['data'])->map(fn (array $user) => [
            ...Arr::only($user, [
                'uuid', 'full_name', 'initials', 'role', 'office_id', 'office_name',
                'office_abbreviation', 'position_id', 'position_title',
            ]),
            'opts_role' => $sso->mapSsoRoleToOptsRole($user['role']),
        ])->all();

        $response = Inertia::render('Admin/Users/Index', [
            'directory' => ['data' => $users, 'meta' => $directory['meta']],
            'filters' => ['search' => trim($query['search'] ?? '')],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
