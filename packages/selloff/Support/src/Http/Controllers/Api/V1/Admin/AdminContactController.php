<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Modules\Selloff\Support\Models\ContactMessageReply;
use App\Modules\Selloff\Support\Services\ContactMessageNotificationService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContactController extends Controller
{
    public function __construct(private readonly ContactMessageNotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 5)), 100);

        $messages = $this->filteredQuery($request)
            ->withCount('replies')
            ->orderByDesc('id')
            ->paginate($perPage);

        $payload = $messages->toArray();
        $payload['data'] = collect($payload['data'])->map(function (array $row) {
            return array_merge($row, [
                'reply_count' => (int) ($row['replies_count'] ?? 0),
            ]);
        })->all();
        $payload['counts'] = [
            'all' => ContactMessage::query()->count(),
            'pending' => ContactMessage::query()->where('status', 'pending')->count(),
            'read' => ContactMessage::query()->where('status', 'read')->count(),
            'archived' => ContactMessage::query()->where('status', 'archived')->count(),
        ];

        return ApiResponse::success($payload);
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->load([
            'replies.admin:id,first_name,last_name,email,username',
        ]);

        return ApiResponse::success($this->formatThread($contactMessage));
    }

    public function update(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,read,archived'],
        ]);

        $contactMessage->update(['status' => $data['status']]);

        $contactMessage->load([
            'replies.admin:id,first_name,last_name,email,username',
        ]);

        return ApiResponse::success($this->formatThread($contactMessage));
    }

    public function reply(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:pending,read,archived'],
        ]);

        if (trim((string) $contactMessage->email) === '') {
            return ApiResponse::error('This message has no sender email address.', 422);
        }

        $replySubject = $this->notifications->buildReplySubject($contactMessage);
        $sentTo = trim((string) $contactMessage->email);

        $from = $this->notifications->resolveReplyFrom();

        try {
            $this->notifications->sendAdminReply($contactMessage, $data['message'], $request->user());
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (\RuntimeException) {
            return ApiResponse::error('Failed to send the email reply. Check mail settings and try again.', 502);
        }

        ContactMessageReply::query()->create([
            'contact_message_id' => $contactMessage->id,
            'admin_user_id' => $request->user()->id,
            'message' => $data['message'],
            'email_subject' => $replySubject,
            'sent_to' => $sentTo,
            'sent_from' => $from['address'] !== '' ? $from['address'] : null,
        ]);

        $contactMessage->update([
            'status' => $data['status'] ?? 'read',
        ]);

        $contactMessage->load([
            'replies.admin:id,first_name,last_name,email,username',
        ]);

        return ApiResponse::success([
            'thread' => $this->formatThread($contactMessage),
            'sent_to' => $sentTo,
            'reply_subject' => $replySubject,
        ], message: 'Reply sent.');
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * @return Builder<ContactMessage>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('q')->trim();
        $status = $request->string('status')->trim();

        return ContactMessage::query()
            ->when($status->isNotEmpty() && $status->toString() !== 'all', fn ($q) => $q->where('status', $status->toString()))
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%')
                        ->orWhere('subject', 'ilike', '%'.$search.'%')
                        ->orWhere('message', 'ilike', '%'.$search.'%');
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatThread(ContactMessage $contactMessage): array
    {
        $from = $this->notifications->resolveReplyFrom();

        return [
            'id' => $contactMessage->id,
            'name' => $contactMessage->name,
            'email' => $contactMessage->email,
            'subject' => $contactMessage->subject,
            'message' => $contactMessage->message,
            'status' => $contactMessage->status,
            'reply_subject' => $this->notifications->buildReplySubject($contactMessage),
            'reply_from_email' => $from['address'],
            'reply_from_name' => $from['name'],
            'reply_count' => $contactMessage->relationLoaded('replies')
                ? $contactMessage->replies->count()
                : $contactMessage->replies()->count(),
            'replies' => $contactMessage->relationLoaded('replies')
                ? $contactMessage->replies->map(fn (ContactMessageReply $reply) => $this->formatReply($reply))->values()->all()
                : [],
            'created_at' => $contactMessage->created_at,
            'updated_at' => $contactMessage->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReply(ContactMessageReply $reply): array
    {
        $admin = $reply->relationLoaded('admin') ? $reply->admin : null;

        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'email_subject' => $reply->email_subject,
            'sent_to' => $reply->sent_to,
            'sent_from' => $reply->sent_from,
            'admin' => $this->formatAdmin($admin),
            'created_at' => $reply->created_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatAdmin(?User $admin): ?array
    {
        if ($admin === null) {
            return null;
        }

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'username' => $admin->username,
        ];
    }
}
