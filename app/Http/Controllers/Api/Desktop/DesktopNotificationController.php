<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\NotificationFeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopNotificationController extends Controller
{
    public function __construct(private readonly NotificationFeedBuilder $feedBuilder)
    {
        //
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        return response()->json($this->feedBuilder->buildForUser($user));
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $databaseNotification = $user->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($databaseNotification->read_at === null) {
            $databaseNotification->markAsRead();
        }

        return response()->json($this->feedBuilder->buildForUser($user));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $user->unreadNotifications->markAsRead();

        return response()->json($this->feedBuilder->buildForUser($user));
    }
}
