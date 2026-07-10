<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Notification\Services\SupportEmailService;
use App\Modules\Selloff\Support\Models\SupportMessage;
use App\Modules\Selloff\Support\Models\SupportTicket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportEmailService $supportEmails,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with('messages')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(15);

        return ApiResponse::success($tickets);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        return ApiResponse::success($supportTicket->load('messages'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'status' => 'open',
        ]);

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => false,
        ]);

        $this->supportEmails->queueTicketOpened($ticket, $message);

        return ApiResponse::success($ticket->fresh()->load('messages'), 201);
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => false,
        ]);

        $this->supportEmails->queueUserReply($supportTicket, $message);

        return ApiResponse::success($supportTicket->fresh()->load('messages'));
    }

    public function close(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);
        abort_if($supportTicket->status === 'closed', 422, 'Ticket is already closed.');

        $supportTicket->update(['status' => 'closed']);

        return ApiResponse::success($supportTicket->fresh()->load('messages'));
    }
}
