<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class MobileResponse
{
    public static function success(mixed $data = null, int $status = 200, ?string $message = null, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'status' => '1',
            'error' => false,
            'message' => $message ?? 'OK',
            'data' => $data,
            'meta' => array_merge([
                'auth' => 'sanctum',
                'image_url_prefix' => MediaUrl::prefix(),
            ], $meta),
        ];

        return response()
            ->json($payload, $status)
            ->header('X-Selloff-Auth', 'sanctum');
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'status' => '0',
            'error' => true,
            'message' => $message,
            'data' => null,
            'meta' => [
                'auth' => 'sanctum',
                'image_url_prefix' => MediaUrl::prefix(),
            ],
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function deprecated(string $replacement): array
    {
        return [
            'X-Selloff-Deprecated' => 'true',
            'X-Selloff-Replacement' => $replacement,
        ];
    }
}
