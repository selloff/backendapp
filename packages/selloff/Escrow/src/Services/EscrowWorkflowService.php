<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Models\User;
use App\Modules\Selloff\Escrow\Models\EscrowEvent;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Services\Platform\PlatformSettingsService;

class EscrowWorkflowService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function recordEvent(
        EscrowTransaction $transaction,
        string $event,
        string $actorType = 'system',
        ?int $actorId = null,
        array $payload = [],
    ): EscrowEvent {
        return EscrowEvent::query()->create([
            'escrow_transaction_id' => $transaction->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event' => $event,
            'payload' => $payload !== [] ? $payload : null,
        ]);
    }

    public function transition(EscrowTransaction $transaction, string $status, array $extra = []): EscrowTransaction
    {
        $transaction->update(array_merge(['status' => $status], $extra));

        return $transaction->fresh();
    }

    public function inspectionDays(): int
    {
        $settings = $this->platformSettings->all();

        return max(0, (int) ($settings['escrow_inspection_days'] ?? 2));
    }

    public function autoCancelUnfundedDays(): int
    {
        $settings = $this->platformSettings->all();

        return max(0, (int) ($settings['escrow_auto_cancel_unfunded_days'] ?? 0));
    }

    public function assertDeliveryConfigured(EscrowTransaction $transaction): void
    {
        abort_if((float) $transaction->delivery_cost <= 0, 422, 'Delivery cost must be set before payment.');
        abort_if(trim((string) ($transaction->delivery_address ?? '')) === '', 422, 'Delivery address must be set before payment.');
    }

    public function assertBothAgreed(EscrowTransaction $transaction): void
    {
        abort_unless($transaction->buyer_agreed && $transaction->seller_agreed, 422, 'Both parties must agree before continuing.');
    }

    public function assertNotTerminal(EscrowTransaction $transaction): void
    {
        abort_if(EscrowStatus::isTerminal($transaction->status), 422, 'This escrow transaction is already closed.');
        abort_if($transaction->status === EscrowStatus::DISPUTED, 422, 'This escrow transaction is under dispute.');
    }

    public function actorTypeForUser(EscrowTransaction $transaction, User $user): ?string
    {
        if ($user->id === $transaction->buyer_id) {
            return 'buyer';
        }

        if ($user->id === $transaction->seller_id) {
            return 'seller';
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formatEvents(EscrowTransaction $transaction): array
    {
        return EscrowEvent::query()
            ->where('escrow_transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(fn (EscrowEvent $event) => [
                'id' => $event->id,
                'event' => $event->event,
                'actor_type' => $event->actor_type,
                'actor_id' => $event->actor_id,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
