<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BillingController
{
    public function select(Request $request, string $plan): RedirectResponse
    {
        $plans = [
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

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        if (!Auth::check()) {
            return redirect()->guest(route('auth.redirect', ['provider' => 'google']));
        }

        $selectedPlan = $plans[$plan];
        $user = Auth::user();
        $settings = (array) ($user->settings ?? []);

        $settings['billing'] = [
            'plan' => $plan,
            'plan_name' => $selectedPlan['name'],
            'price' => $selectedPlan['price'],
            'billing_cycle' => $selectedPlan['billing_cycle'],
            'post_limit' => $selectedPlan['post_limit'],
            'status' => $selectedPlan['price'] === 0 ? 'active' : 'pending_checkout',
            'checkout_reference' => $selectedPlan['price'] === 0 ? null : (string) Str::uuid(),
            'updated_at' => now()->toIso8601String(),
        ];

        $user->settings = $settings;
        $user->save();

        $message = $selectedPlan['price'] === 0
            ? 'Starter plan activated.'
            : $selectedPlan['name'].' selected. Checkout is marked as pending in your account settings.';

        return redirect('/#pricing')->with('status', $message);
    }
}
