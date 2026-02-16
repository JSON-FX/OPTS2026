<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = $user->notifications();

        // Filter by type
        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('data->type', $request->input('type'));
        }

        // Filter by read status
        if ($request->input('status') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->input('status') === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate(20)->through(fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'general',
            'message' => $n->data['message'] ?? '',
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at->diffForHumans(),
            'created_at_raw' => $n->created_at->toISOString(),
            'data' => $n->data,
        ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'type' => $request->input('type', 'all'),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back();
    }
}
