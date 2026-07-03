<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Messaging\Services\MessageGtmService;
use App\Modules\Selloff\Messaging\Services\MessageService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMessagingController extends Controller
{
    public function latestConversations(Request $request, MessageService $service): JsonResponse
    {
        $conversations = $service->conversationsForUser($request->user());

        if ($conversations->isEmpty()) {
            return MobileResponse::success([], 200, 'No conversations found.');
        }

        return MobileResponse::success($conversations);
    }

    public function messages(Request $request, int $conversationId, MessageService $service): JsonResponse
    {
        $afterId = $request->query('after_message_id');
        $afterId = $afterId !== null && $afterId !== '' ? (int) $afterId : null;

        return MobileResponse::success(
            $service->messagesForConversation($conversationId, $request->user(), $afterId),
        );
    }

    public function unreadCount(Request $request, MessageService $service): JsonResponse
    {
        return MobileResponse::success(['count' => $service->unreadCount($request->user())]);
    }

    public function markRead(Request $request, int $conversationId, MessageService $service): JsonResponse
    {
        $service->markConversationRead($conversationId, $request->user());

        return MobileResponse::success(['conversation_id' => $conversationId]);
    }

    public function sendConversationMessage(Request $request, MessageService $service, MessageGtmService $gtm): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $message = $service->sendToConversation(
            $data['conversation_id'],
            $request->user(),
            $data['message'],
        );

        return MobileResponse::success($service->messagePayload($message, $gtm), 201);
    }

    public function sendNewConversationMessage(Request $request, MessageService $service, MessageGtmService $gtm): JsonResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:10000'],
            'subject' => ['nullable', 'string', 'max:500'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $message = $service->sendNewConversation(
            $request->user(),
            $data['receiver_id'],
            $data['message'],
            $data['subject'] ?? null,
            $data['product_id'] ?? null,
        );

        return MobileResponse::success($service->messagePayload($message, $gtm), 201);
    }
}
