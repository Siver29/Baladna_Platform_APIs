<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\ReportStatusHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The in-app notification feed.
 *
 * There is no notifications table: the feed is the existing report status
 * history read from the recipient's side, and "read" is a single watermark
 * timestamp on the user.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's notifications, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 15), 50);

        $notifications = ReportStatusHistory::query()
            ->forRecipient($user)
            ->when($request->boolean('unread_only'), fn ($q) => $q->unreadFor($user))
            ->with(['report:id,reference_number,title,status', 'user:id,name'])
            ->orderBy('report_status_histories.created_at', 'desc')
            ->orderBy('report_status_histories.id', 'desc')
            ->paginate($perPage);

        return $this->successCollection(
            NotificationResource::collection($notifications),
            200,
            [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $this->unreadCountFor($user),
            ]
        );
    }

    /**
     * Return just the unread badge count, for cheap polling.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $this->unreadCountFor($request->user())]);
    }

    /**
     * Mark every notification up to now as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill(['notifications_read_at' => now()])->save();

        return $this->success(
            [
                'notifications_read_at' => $user->notifications_read_at->toISOString(),
                'unread_count' => 0,
            ],
            'Notifications marked as read.'
        );
    }

    /**
     * Count the events the user has not seen yet.
     */
    protected function unreadCountFor(User $user): int
    {
        return ReportStatusHistory::query()
            ->forRecipient($user)
            ->unreadFor($user)
            ->count();
    }
}
