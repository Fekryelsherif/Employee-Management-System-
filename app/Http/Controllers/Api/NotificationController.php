<?php
// app/Http/Controllers/Api/NotificationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request) {
        $user = $request->user();
        $perPage = (int)$request->query('per_page', 20);
        $only = $request->query('only', 'all');

        if ($only == 'unread') {
            $items = $user->unreadNotifications()->paginate($perPage);
        } else {
            $items = $user->notifications()->paginate($perPage);
        }

        return response()->json($items);
    }

    public function unreadCount(Request $request) {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, $id) {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if (!$n) return response()->json(['message'=>'Not found'],404);
        $n->markAsRead();
        return response()->json(['message'=>'Marked as read']);
    }

    public function markUnread(Request $request, $id) {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if (!$n) return response()->json(['message'=>'Not found'],404);
        $n->update(['read_at' => null]);
        return response()->json(['message'=>'Marked as unread']);
    }

    public function markAllRead(Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message'=>'All marked as read']);
    }
}
