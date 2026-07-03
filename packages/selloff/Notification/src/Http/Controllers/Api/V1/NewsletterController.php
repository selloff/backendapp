<?php

namespace App\Modules\Selloff\Notification\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Notification\Models\NewsletterSubscriber;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'is_active' => true,
                'token' => Str::random(32),
            ],
        );

        return ApiResponse::success([
            'email' => $subscriber->email,
            'subscribed' => true,
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:64'],
        ]);

        $subscriber = NewsletterSubscriber::query()
            ->where('email', $data['email'])
            ->where('token', $data['token'])
            ->firstOrFail();

        $subscriber->update(['is_active' => false]);

        return ApiResponse::success(['unsubscribed' => true]);
    }
}
