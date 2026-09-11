<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * POST /api/push/subscribe
     * Save or update a push subscription for the current employee.
     */
    public function subscribe(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'endpoint'          => 'required|string',
            'keys.p256dh'       => 'required|string',
            'keys.auth'         => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            [
                'employee_id' => $sessionUser['id'],
                'endpoint'    => $validated['endpoint'],
            ],
            [
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/push/unsubscribe
     * Remove a push subscription.
     */
    public function unsubscribe(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $endpoint = $request->input('endpoint');
        if ($endpoint) {
            PushSubscription::where('employee_id', $sessionUser['id'])
                ->where('endpoint', $endpoint)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/push/vapid-public-key
     * Return the VAPID public key for frontend subscription.
     */
    public function vapidPublicKey()
    {
        return response()->json([
            'success'    => true,
            'public_key' => config('webpush.vapid_public_key'),
        ]);
    }

    /**
     * POST /api/push/test  (admin only)
     * Send a test push to the current user to verify the pipeline works.
     */
    public function test(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $count = PushSubscription::where('employee_id', $sessionUser['id'])->count();
        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No push subscription found. Click the 🔊 button and allow notifications first.',
            ]);
        }

        try {
            app(WebPushService::class)->sendToEmployee((int) $sessionUser['id'], [
                'title' => '✅ Push is working!',
                'body'  => 'Web Push notification from EcoSystem is working correctly.',
                'url'   => '/',
                'tag'   => 'ecosystem-test-' . time(),
                'type'  => 'test',
            ]);
            return response()->json(['success' => true, 'message' => 'Push sent to ' . $count . ' subscription(s).']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Push failed: ' . $e->getMessage()]);
        }
    }
}
