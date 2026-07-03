<?php

namespace App\Modules\Selloff\Messaging\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Messaging\Services\AdminMessagePresenter;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMessageController extends Controller
{
    public function __construct(private readonly AdminMessagePresenter $presenter) {}

    public function conversations(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);

        $conversations = $this->filteredConversationsQuery($request)
            ->with([
                'sender:id,first_name,last_name,email,username',
                'receiver:id,first_name,last_name,email,username',
                'product:id,slug,vendor_id',
                'product.translations',
                'latestMessage',
            ])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $conversations->through(fn (Conversation $conversation) => $this->presenter->formatConversationListItem($conversation));

        return ApiResponse::success($conversations);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load([
            'sender:id,first_name,last_name,email,username',
            'receiver:id,first_name,last_name,email,username',
            'product:id,slug,vendor_id',
            'product.translations',
        ]);

        $participants = $this->presenter->resolveParticipants($conversation);
        $buyerId = $participants['buyer']?->id;
        $vendorId = $participants['vendor']?->id;

        $messages = Message::query()
            ->with(['sender:id,first_name,last_name,email,username'])
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->presenter->formatMessage($message, $buyerId, $vendorId));

        return ApiResponse::success([
            'conversation' => $this->presenter->formatConversationDetail($conversation),
            'messages' => $messages,
        ]);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'is_flagged' => ['required', 'boolean'],
        ]);

        $conversation->update(['is_flagged' => $data['is_flagged']]);

        return ApiResponse::success(['id' => $conversation->id, 'is_flagged' => $conversation->is_flagged]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return ApiResponse::success(['deleted' => true]);
    }

  /**
   * @return Builder<Conversation>
   */
    private function filteredConversationsQuery(Request $request): Builder
    {
        $search = $request->string('q')->trim();

        return Conversation::query()
            ->when($request->boolean('flagged'), fn ($q) => $q->where('is_flagged', true))
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('subject', 'ilike', '%'.$search.'%')
                        ->orWhereHas('sender', function ($userQuery) use ($search): void {
                            $userQuery->where('email', 'ilike', '%'.$search.'%')
                                ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%')
                                ->orWhere('username', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('receiver', function ($userQuery) use ($search): void {
                            $userQuery->where('email', 'ilike', '%'.$search.'%')
                                ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%')
                                ->orWhere('username', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('product.translations', function ($productQuery) use ($search): void {
                            $productQuery->where('title', 'ilike', '%'.$search.'%');
                        });
                });
            });
    }
}
