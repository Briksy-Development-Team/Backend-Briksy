<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('data', 'like', '%' . $search . '%');
            });
        }

        if ($request->boolean('filter.unread')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('filter.priority')) {
            $query->where('data->priority', $request->string('filter.priority')->toString());
        }

        $notifications = $query
            ->orderByDesc('created_at')
            ->paginate(max(1, min((int) $request->integer('per_page', 15), 100)))
            ->withQueryString();

        return $this->paginated(
            NotificationResource::collection($notifications),
            $notifications,
            'Notifications retrieved successfully.'
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'count' => $this->baseQuery($request)->whereNull('read_at')->count(),
        ], 'Unread count retrieved successfully.');
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->baseQuery($request)->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return $this->success(new NotificationResource($model), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->baseQuery($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success([], 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $model = $this->baseQuery($request)->whereKey($notification)->firstOrFail();
        $model->delete();

        return $this->success([], 'Notification deleted successfully.');
    }

    private function baseQuery(Request $request): Builder
    {
        $user = $request->user();

        abort_unless($user, 401);

        return DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->id);
    }
}
