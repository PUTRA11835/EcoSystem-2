<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject'    => config('webpush.vapid_subject'),
                'publicKey'  => config('webpush.vapid_public_key'),
                'privateKey' => config('webpush.vapid_private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
        $this->webPush->setReuseVAPIDHeaders(true);
        $this->webPush->setAutomaticPadding(false);
    }

    /**
     * Send a push notification to all subscriptions of an employee.
     */
    public function sendToEmployee(int $employeeId, array $payload): void
    {
        $subscriptions = PushSubscription::where('employee_id', $employeeId)->get();
        if ($subscriptions->isEmpty()) return;

        $json = json_encode($payload);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => [
                        'p256dh' => $sub->public_key,
                        'auth'   => $sub->auth_token,
                    ],
                ]);
                // Urgency "high" tells FCM/Android to wake the device promptly even
                // under Doze/battery optimization, instead of batching delivery for
                // whenever the device is next active (e.g. app opened).
                $this->webPush->queueNotification($subscription, $json, ['urgency' => 'high']);
            } catch (\Throwable $e) {
                Log::warning('WebPush: failed to queue for employee ' . $employeeId, ['error' => $e->getMessage()]);
            }
        }

        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                    Log::info('WebPush: removed expired subscription', ['endpoint' => substr($endpoint, 0, 60)]);
                } else {
                    Log::warning('WebPush: delivery failed', [
                        'reason'   => $report->getReason(),
                        'endpoint' => substr($endpoint, 0, 60),
                    ]);
                }
            }
        }
    }
}
