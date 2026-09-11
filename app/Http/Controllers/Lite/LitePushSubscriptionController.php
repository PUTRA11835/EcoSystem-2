<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LitePushSubscriptionController extends Controller
{
    /**
     * POST /api/lite/push-subscriptions
     * Simpan/update push subscription browser milik employee yang sedang login.
     * Upsert berdasarkan endpoint agar device yang sama tidak menghasilkan duplikat.
     */
    public function subscribe(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'endpoint'    => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription data.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        PushSubscription::updateOrCreate(
            [
                'employee_id' => $user['id'],
                'endpoint'    => $validated['endpoint'],
            ],
            [
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
            ]
        );

        return response()->json(['success' => true], 201);
    }

    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }
}
