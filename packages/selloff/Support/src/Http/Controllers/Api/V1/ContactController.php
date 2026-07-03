<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Requests\Api\V1\StoreContactRequest;
use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        $contact = ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'pending',
        ]);

        return ApiResponse::success([
            'id' => $contact->id,
            'status' => $contact->status,
        ], 201, 'Message received. We will get back to you soon.');
    }
}
