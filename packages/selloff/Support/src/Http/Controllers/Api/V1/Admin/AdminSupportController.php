<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Models\SupportMessage;
use App\Modules\Selloff\Support\Models\SupportTicket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    public function tickets(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);

        $tickets = SupportTicket::query()
            ->with(['user', 'messages'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($perPage);

        $payload = $tickets->toArray();
        $payload['counts'] = [
            'open' => SupportTicket::query()->where('status', 'open')->count(),
            'pending' => SupportTicket::query()->where('status', 'pending')->count(),
            'closed' => SupportTicket::query()->where('status', 'closed')->count(),
        ];

        return ApiResponse::success($payload);
    }

    public function show(SupportTicket $supportTicket): JsonResponse
    {
        return ApiResponse::success($supportTicket->load(['user', 'messages.user']));
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:open,pending,closed'],
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => true,
        ]);

        if (! empty($data['status'])) {
            $supportTicket->update(['status' => $data['status']]);
        } elseif ($supportTicket->status === 'open') {
            $supportTicket->update(['status' => 'pending']);
        }

        return ApiResponse::success($supportTicket->fresh()->load(['user', 'messages.user']));
    }

    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,pending,closed'],
        ]);

        $supportTicket->update(['status' => $data['status']]);

        return ApiResponse::success($supportTicket->fresh()->load(['user', 'messages.user']));
    }

    public function destroy(SupportTicket $supportTicket): JsonResponse
    {
        $supportTicket->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
