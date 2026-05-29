<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController
{
    /**
     * Free plan activation only.
     */
    public function select(Request $request, string $plan): RedirectResponse
    {
        $plans = $this->plans();

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        if (!Auth::check()) {
            return redirect()->guest(route('auth.redirect', ['provider' => 'google']));
        }

        $selectedPlan = $plans[$plan];
        if ($selectedPlan['price'] > 0) {
            return redirect('/#pricing')->withErrors('Please choose Paystack, Flutterwave, or TgiPay to continue checkout.');
        }

        $user = Auth::user();
        $this->activatePlan($user, $plan, 'free', null);

        return redirect('/#pricing')->with('status', 'Starter plan activated.');
    }

    public function checkout(Request $request, string $plan, string $gateway): RedirectResponse
    {
        $plans = $this->plans();
        $gateways = $this->gateways();

        if (!array_key_exists($plan, $plans) || !array_key_exists($gateway, $gateways)) {
            abort(404);
        }

        if (!Auth::check()) {
            return redirect()->guest(route('auth.redirect', ['provider' => 'google']));
        }

        $selectedPlan = $plans[$plan];
        $user = Auth::user();

        if ($selectedPlan['price'] === 0) {
            $this->activatePlan($user, $plan, 'free', null);
            return redirect('/#pricing')->with('status', 'Starter plan activated.');
        }

        $reference = strtoupper($gateway).'-'.Str::upper(Str::random(6)).'-'.time();
        $this->persistPendingCheckout($user, $plan, $gateway, $reference);

        try {
            $checkoutUrl = match ($gateway) {
                'paystack' => $this->initializePaystackCheckout($user, $selectedPlan, $reference),
                'flutterwave' => $this->initializeFlutterwaveCheckout($user, $selectedPlan, $reference),
                'tgipay' => $this->initializeTgiPayCheckout($user, $selectedPlan, $reference),
            };
        } catch (\Throwable $exception) {
            Log::error('Checkout initialization failed', [
                'gateway' => $gateway,
                'plan' => $plan,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect('/#pricing')->withErrors(ucfirst($gateway).' checkout failed to initialize. Configure API keys and try again.');
        }

        return redirect()->away($checkoutUrl);
    }

    public function callback(Request $request, string $gateway): RedirectResponse
    {
        if (!array_key_exists($gateway, $this->gateways())) {
            abort(404);
        }

        if (!Auth::check()) {
            return redirect('/#pricing')->withErrors('Please sign in and retry checkout verification.');
        }

        $user = Auth::user();
        $reference = $this->extractReferenceFromCallback($request, $gateway);
        $verification = $this->verifyGatewayPayment($gateway, $request, $reference);

        if (!$verification['success']) {
            $this->markCheckoutFailed($user, $gateway, $reference);
            return redirect('/#pricing')->withErrors($verification['message']);
        }

        $billing = (array) (($user->settings ?? [])['billing'] ?? []);
        $plan = (string) ($billing['plan'] ?? '');

        if (!array_key_exists($plan, $this->plans())) {
            return redirect('/#pricing')->withErrors('Could not resolve plan after payment verification.');
        }

        $this->activatePlan($user, $plan, $gateway, $verification['transaction_id']);

        return redirect('/#pricing')->with('status', ucfirst($gateway).' payment verified and '.$this->plans()[$plan]['name'].' activated.');
    }

    public function webhook(Request $request, string $gateway): JsonResponse
    {
        if (!array_key_exists($gateway, $this->gateways())) {
            abort(404);
        }

        if (!$this->isValidWebhookSignature($request, $gateway)) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if ($gateway === 'tgipay') {
            return $this->handleTgiPayWebhook($request, $payload);
        }

        $reference = $payload['data']['reference']
            ?? $payload['data']['tx_ref']
            ?? $payload['reference']
            ?? $payload['tx_ref']
            ?? null;

        if (!$reference) {
            return response()->json(['ok' => false, 'message' => 'Missing reference'], 422);
        }

        $user = $this->findUserByCheckoutReference($reference);
        if (!$user) {
            return response()->json(['ok' => true, 'message' => 'Reference received']);
        }

        $billing = (array) (($user->settings ?? [])['billing'] ?? []);
        $plan = (string) ($billing['plan'] ?? '');
        if (!array_key_exists($plan, $this->plans())) {
            return response()->json(['ok' => false, 'message' => 'Invalid plan state'], 422);
        }

        $verification = $this->verifyGatewayPayment($gateway, $request, $reference);
        if (!$verification['success']) {
            $this->markCheckoutFailed($user, $gateway, $reference);
            return response()->json(['ok' => true, 'message' => 'Verification failed']);
        }

        $this->activatePlan($user, $plan, $gateway, $verification['transaction_id']);

        return response()->json(['ok' => true]);
    }

    private function handleTgiPayWebhook(Request $request, array $payload): JsonResponse
    {
        $mapping = $this->mapTgiPayWebhookPayload($payload);
        $reference = $mapping['reference'];

        if (!$reference) {
            return response()->json(['ok' => false, 'message' => 'Missing reference'], 422);
        }

        $user = $this->findUserByCheckoutReference($reference);
        if (!$user) {
            return response()->json(['ok' => true, 'message' => 'Reference received']);
        }

        $billing = (array) (($user->settings ?? [])['billing'] ?? []);
        $plan = (string) ($billing['plan'] ?? '');
        if (!array_key_exists($plan, $this->plans())) {
            return response()->json(['ok' => false, 'message' => 'Invalid plan state'], 422);
        }

        $state = $mapping['state'];
        if ($state === 'pending') {
            $this->markCheckoutPending($user, 'tgipay', $reference, $mapping['transaction_id']);
            return response()->json(['ok' => true, 'message' => 'Pending payment event received']);
        }

        if ($state === 'failed') {
            $this->markCheckoutFailed($user, 'tgipay', $reference);
            return response()->json(['ok' => true, 'message' => 'Failure event received']);
        }

        if ($state !== 'active') {
            Log::info('Ignoring unrecognized TgiPay webhook event', [
                'reference' => $reference,
                'event' => $mapping['event'],
                'status' => $mapping['status'],
            ]);
            return response()->json(['ok' => true, 'message' => 'Ignored event']);
        }

        $enforceVerify = (bool) config('services.tgipay.enforce_verify_on_success', true);
        if ($enforceVerify) {
            $verification = $this->verifyTgiPayPayment($reference);
            if (!$verification['success']) {
                $this->markCheckoutFailed($user, 'tgipay', $reference);
                return response()->json(['ok' => true, 'message' => 'Verification failed']);
            }

            $transactionId = $verification['transaction_id'] ?? $mapping['transaction_id'];
        } else {
            $transactionId = $mapping['transaction_id'];
        }

        $this->activatePlan($user, $plan, 'tgipay', $transactionId);

        return response()->json(['ok' => true, 'message' => 'Payment activated']);
    }

    private function plans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'price' => 0,
                'billing_cycle' => 'forever',
                'post_limit' => 10,
            ],
            'creator_pro' => [
                'name' => 'Creator Pro',
                'price' => 3000,
                'billing_cycle' => 'monthly',
                'post_limit' => 20,
            ],
            'lifetime' => [
                'name' => 'Lifetime Access',
                'price' => 18000,
                'billing_cycle' => 'one_time',
                'post_limit' => null,
            ],
        ];
    }

    private function gateways(): array
    {
        return [
            'paystack' => true,
            'flutterwave' => true,
            'tgipay' => true,
        ];
    }

    private function initializePaystackCheckout(User $user, array $plan, string $reference): string
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            throw new \RuntimeException('Missing PAYSTACK_SECRET_KEY');
        }

        $endpoint = (string) config('services.paystack.initialize_url', 'https://api.paystack.co/transaction/initialize');
        $response = Http::withToken($secret)
            ->acceptJson()
            ->post($endpoint, [
                'email' => $user->email,
                'amount' => (int) ($plan['price'] * 100),
                'reference' => $reference,
                'currency' => 'NGN',
                'callback_url' => route('billing.callback', ['gateway' => 'paystack']),
                'metadata' => [
                    'plan' => $plan['name'],
                    'user_id' => $user->id,
                ],
            ])
            ->throw()
            ->json();

        $url = $response['data']['authorization_url'] ?? null;
        if (!$url) {
            throw new \RuntimeException('Missing authorization URL from Paystack');
        }

        return $url;
    }

    private function initializeFlutterwaveCheckout(User $user, array $plan, string $reference): string
    {
        $secret = (string) config('services.flutterwave.secret_key');
        if ($secret === '') {
            throw new \RuntimeException('Missing FLUTTERWAVE_SECRET_KEY');
        }

        $endpoint = (string) config('services.flutterwave.payment_url', 'https://api.flutterwave.com/v3/payments');
        $response = Http::withToken($secret)
            ->acceptJson()
            ->post($endpoint, [
                'tx_ref' => $reference,
                'amount' => $plan['price'],
                'currency' => 'NGN',
                'redirect_url' => route('billing.callback', ['gateway' => 'flutterwave']),
                'customer' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'customizations' => [
                    'title' => 'Clippipeline '.$plan['name'],
                    'description' => 'Subscription payment',
                ],
            ])
            ->throw()
            ->json();

        $url = $response['data']['link'] ?? null;
        if (!$url) {
            throw new \RuntimeException('Missing checkout link from Flutterwave');
        }

        return $url;
    }

    private function initializeTgiPayCheckout(User $user, array $plan, string $reference): string
    {
        $secret = (string) config('services.tgipay.secret_key');
        if ($secret === '') {
            throw new \RuntimeException('Missing TGIPAY_SECRET_KEY');
        }

        $endpoint = rtrim((string) config('services.tgipay.base_url'), '/').'/'.ltrim((string) config('services.tgipay.initialize_path', '/payments/initialize'), '/');
        $response = Http::withToken($secret)
            ->withHeaders([
                'X-API-KEY' => (string) config('services.tgipay.public_key', ''),
            ])
            ->acceptJson()
            ->post($endpoint, [
                'reference' => $reference,
                'amount' => $plan['price'],
                'currency' => 'NGN',
                'callback_url' => route('billing.callback', ['gateway' => 'tgipay']),
                'email' => $user->email,
                'meta' => [
                    'plan' => $plan['name'],
                    'user_id' => $user->id,
                ],
            ])
            ->throw()
            ->json();

        $url = $response['data']['authorization_url']
            ?? $response['data']['checkout_url']
            ?? $response['data']['link']
            ?? $response['authorization_url']
            ?? $response['checkout_url']
            ?? $response['link']
            ?? null;

        if (!$url) {
            throw new \RuntimeException('Missing checkout URL from TgiPay');
        }

        return $url;
    }

    private function verifyGatewayPayment(string $gateway, Request $request, ?string $reference): array
    {
        if (!$reference) {
            return [
                'success' => false,
                'transaction_id' => null,
                'message' => 'Missing transaction reference.',
            ];
        }

        return match ($gateway) {
            'paystack' => $this->verifyPaystackPayment($reference),
            'flutterwave' => $this->verifyFlutterwavePayment($request, $reference),
            'tgipay' => $this->verifyTgiPayPayment($reference),
        };
    }

    private function verifyPaystackPayment(string $reference): array
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return ['success' => false, 'transaction_id' => null, 'message' => 'Paystack not configured.'];
        }

        try {
            $endpoint = rtrim((string) config('services.paystack.verify_url', 'https://api.paystack.co/transaction/verify'), '/').'/'.$reference;
            $response = Http::withToken($secret)->acceptJson()->get($endpoint)->throw()->json();
            $paid = ($response['data']['status'] ?? '') === 'success';

            return [
                'success' => $paid,
                'transaction_id' => isset($response['data']['id']) ? (string) $response['data']['id'] : null,
                'message' => $paid ? 'Payment successful.' : 'Payment not successful on Paystack.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Paystack verification failed', ['reference' => $reference, 'message' => $exception->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => 'Unable to verify Paystack payment.'];
        }
    }

    private function verifyFlutterwavePayment(Request $request, string $reference): array
    {
        $secret = (string) config('services.flutterwave.secret_key');
        if ($secret === '') {
            return ['success' => false, 'transaction_id' => null, 'message' => 'Flutterwave not configured.'];
        }

        try {
            $transactionId = (string) $request->query('transaction_id', '');
            if ($transactionId !== '') {
                $endpoint = rtrim((string) config('services.flutterwave.verify_url', 'https://api.flutterwave.com/v3/transactions'), '/').'/'.$transactionId.'/verify';
                $response = Http::withToken($secret)->acceptJson()->get($endpoint)->throw()->json();
            } else {
                $endpoint = (string) config('services.flutterwave.verify_reference_url', 'https://api.flutterwave.com/v3/transactions/verify_by_reference');
                $response = Http::withToken($secret)->acceptJson()->get($endpoint, ['tx_ref' => $reference])->throw()->json();
                $transactionId = isset($response['data']['id']) ? (string) $response['data']['id'] : null;
            }

            $paid = ($response['status'] ?? '') === 'success' && in_array(($response['data']['status'] ?? ''), ['successful', 'success'], true);

            return [
                'success' => $paid,
                'transaction_id' => $transactionId,
                'message' => $paid ? 'Payment successful.' : 'Payment not successful on Flutterwave.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Flutterwave verification failed', ['reference' => $reference, 'message' => $exception->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => 'Unable to verify Flutterwave payment.'];
        }
    }

    private function verifyTgiPayPayment(string $reference): array
    {
        $secret = (string) config('services.tgipay.secret_key');
        if ($secret === '') {
            return ['success' => false, 'transaction_id' => null, 'message' => 'TgiPay not configured.'];
        }

        try {
            $endpoint = rtrim((string) config('services.tgipay.base_url'), '/').'/'.ltrim((string) config('services.tgipay.verify_path', '/payments/verify'), '/');
            $response = Http::withToken($secret)
                ->withHeaders(['X-API-KEY' => (string) config('services.tgipay.public_key', '')])
                ->acceptJson()
                ->get($endpoint, ['reference' => $reference])
                ->throw()
                ->json();

            $paymentStatus = strtolower((string) ($response['data']['status'] ?? $response['status'] ?? ''));
            $paid = in_array($paymentStatus, ['success', 'successful', 'paid', 'completed'], true);

            return [
                'success' => $paid,
                'transaction_id' => isset($response['data']['id']) ? (string) $response['data']['id'] : null,
                'message' => $paid ? 'Payment successful.' : 'Payment not successful on TgiPay.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('TgiPay verification failed', ['reference' => $reference, 'message' => $exception->getMessage()]);
            return ['success' => false, 'transaction_id' => null, 'message' => 'Unable to verify TgiPay payment.'];
        }
    }

    private function extractReferenceFromCallback(Request $request, string $gateway): ?string
    {
        return match ($gateway) {
            'paystack' => $request->query('reference'),
            'flutterwave' => $request->query('tx_ref') ?? $request->query('reference'),
            'tgipay' => $request->query('reference') ?? $request->query('tx_ref'),
        };
    }

    private function activatePlan(User $user, string $plan, string $gateway, ?string $transactionId): void
    {
        $selectedPlan = $this->plans()[$plan];
        $settings = (array) ($user->settings ?? []);

        $settings['billing'] = [
            'plan' => $plan,
            'plan_name' => $selectedPlan['name'],
            'price' => $selectedPlan['price'],
            'billing_cycle' => $selectedPlan['billing_cycle'],
            'post_limit' => $selectedPlan['post_limit'],
            'status' => 'active',
            'gateway' => $gateway,
            'checkout_reference' => $settings['billing']['checkout_reference'] ?? null,
            'transaction_id' => $transactionId,
            'paid_at' => now()->toIso8601String(),
            'renews_at' => $selectedPlan['billing_cycle'] === 'monthly' ? now()->addMonth()->toIso8601String() : null,
            'updated_at' => now()->toIso8601String(),
        ];

        $user->settings = $settings;
        $user->save();
    }

    private function persistPendingCheckout(User $user, string $plan, string $gateway, string $reference): void
    {
        $selectedPlan = $this->plans()[$plan];
        $settings = (array) ($user->settings ?? []);

        $settings['billing'] = [
            'plan' => $plan,
            'plan_name' => $selectedPlan['name'],
            'price' => $selectedPlan['price'],
            'billing_cycle' => $selectedPlan['billing_cycle'],
            'post_limit' => $selectedPlan['post_limit'],
            'status' => 'pending_checkout',
            'gateway' => $gateway,
            'checkout_reference' => $reference,
            'updated_at' => now()->toIso8601String(),
        ];

        $user->settings = $settings;
        $user->save();
    }

    private function markCheckoutFailed(User $user, string $gateway, ?string $reference): void
    {
        $settings = (array) ($user->settings ?? []);
        $billing = (array) ($settings['billing'] ?? []);

        $billing['status'] = 'failed';
        $billing['gateway'] = $gateway;
        $billing['checkout_reference'] = $reference ?? ($billing['checkout_reference'] ?? null);
        $billing['updated_at'] = now()->toIso8601String();

        $settings['billing'] = $billing;
        $user->settings = $settings;
        $user->save();
    }

    private function markCheckoutPending(User $user, string $gateway, string $reference, ?string $transactionId): void
    {
        $settings = (array) ($user->settings ?? []);
        $billing = (array) ($settings['billing'] ?? []);

        $billing['status'] = 'pending_checkout';
        $billing['gateway'] = $gateway;
        $billing['checkout_reference'] = $reference;
        if ($transactionId) {
            $billing['transaction_id'] = $transactionId;
        }
        $billing['updated_at'] = now()->toIso8601String();

        $settings['billing'] = $billing;
        $user->settings = $settings;
        $user->save();
    }

    private function isValidWebhookSignature(Request $request, string $gateway): bool
    {
        $rawBody = $request->getContent();

        return match ($gateway) {
            'paystack' => hash_equals(
                hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret_key')),
                (string) $request->header('x-paystack-signature', '')
            ),
            'flutterwave' => hash_equals(
                (string) config('services.flutterwave.secret_hash', ''),
                (string) $request->header('verif-hash', '')
            ),
            'tgipay' => $this->isValidTgiPaySignature($request, $rawBody),
        };
    }

    private function isValidTgiPaySignature(Request $request, string $rawBody): bool
    {
        $secret = (string) config('services.tgipay.secret_key');
        $header = (string) config('services.tgipay.signature_header', 'x-tgipay-signature');
        $algo = (string) config('services.tgipay.signature_algo', 'sha256');
        $incomingSignature = (string) $request->header($header, '');

        if ($secret === '' || $incomingSignature === '') {
            return false;
        }

        $calculated = hash_hmac($algo, $rawBody, $secret);
        return hash_equals($calculated, $incomingSignature);
    }

    private function mapTgiPayWebhookPayload(array $payload): array
    {
        $eventKey = (string) config('services.tgipay.webhook_event_key', 'event');
        $statusKey = (string) config('services.tgipay.webhook_status_key', 'data.status');
        $referenceKey = (string) config('services.tgipay.webhook_reference_key', 'data.reference');
        $transactionIdKey = (string) config('services.tgipay.webhook_transaction_id_key', 'data.id');

        $event = strtolower((string) data_get($payload, $eventKey, ''));
        $status = strtolower((string) data_get($payload, $statusKey, ''));
        $reference = (string) data_get($payload, $referenceKey, '');
        if ($reference === '') {
            $reference = (string) data_get($payload, 'data.tx_ref', data_get($payload, 'reference', data_get($payload, 'tx_ref', '')));
        }

        $transactionId = data_get($payload, $transactionIdKey);
        $successEvents = array_map('strtolower', (array) config('services.tgipay.success_events', ['payment.success', 'charge.success', 'transaction.success']));
        $failedEvents = array_map('strtolower', (array) config('services.tgipay.failed_events', ['payment.failed', 'charge.failed', 'transaction.failed']));
        $pendingEvents = array_map('strtolower', (array) config('services.tgipay.pending_events', ['payment.pending', 'charge.pending', 'transaction.pending']));
        $successStatuses = array_map('strtolower', (array) config('services.tgipay.success_statuses', ['success', 'successful', 'paid', 'completed']));
        $failedStatuses = array_map('strtolower', (array) config('services.tgipay.failed_statuses', ['failed', 'error', 'declined', 'cancelled']));
        $pendingStatuses = array_map('strtolower', (array) config('services.tgipay.pending_statuses', ['pending', 'processing', 'initiated']));

        $state = 'ignored';
        if (($event !== '' && in_array($event, $successEvents, true)) || ($status !== '' && in_array($status, $successStatuses, true))) {
            $state = 'active';
        } elseif (($event !== '' && in_array($event, $failedEvents, true)) || ($status !== '' && in_array($status, $failedStatuses, true))) {
            $state = 'failed';
        } elseif (($event !== '' && in_array($event, $pendingEvents, true)) || ($status !== '' && in_array($status, $pendingStatuses, true))) {
            $state = 'pending';
        }

        return [
            'event' => $event,
            'status' => $status,
            'reference' => $reference !== '' ? $reference : null,
            'transaction_id' => $transactionId ? (string) $transactionId : null,
            'state' => $state,
        ];
    }

    private function findUserByCheckoutReference(string $reference): ?User
    {
        return User::query()
            ->where('settings', 'like', '%"checkout_reference":"'.$reference.'"%')
            ->first();
    }
}
