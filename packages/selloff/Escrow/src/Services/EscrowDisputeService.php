<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Models\User;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use Illuminate\Validation\ValidationException;

class EscrowDisputeService
{
    public function __construct(
        private readonly EscrowWorkflowService $workflow,
        private readonly EscrowFundingService $funding,
        private readonly EscrowReleaseService $release,
    ) {}

    public function resolve(
        EscrowTransaction $transaction,
        User $admin,
        string $resolution,
        ?string $note = null,
    ): EscrowTransaction {
        abort_unless($transaction->status === EscrowStatus::DISPUTED, 422, 'Transaction is not disputed.');

        return match ($resolution) {
            'refund_buyer' => $this->resolveRefund($transaction, $admin, $note),
            'release_seller' => $this->resolveRelease($transaction, $admin, $note),
            default => throw ValidationException::withMessages([
                'resolution' => ['Resolution must be refund_buyer or release_seller.'],
            ]),
        };
    }

    private function resolveRefund(EscrowTransaction $transaction, User $admin, ?string $note): EscrowTransaction
    {
        $metadata = $transaction->metadata ?? [];
        $metadata['dispute_resolution'] = [
            'resolution' => 'refund_buyer',
            'resolved_by' => $admin->id,
            'resolved_at' => now()->toIso8601String(),
            'note' => $note,
        ];

        $transaction->update(['metadata' => $metadata]);

        $this->workflow->recordEvent($transaction, 'dispute_resolved_refund', 'admin', $admin->id, [
            'note' => $note,
        ]);

        return $this->funding->refundToBuyerWallet($transaction->fresh(), $admin, $note);
    }

    private function resolveRelease(EscrowTransaction $transaction, User $admin, ?string $note): EscrowTransaction
    {
        $metadata = $transaction->metadata ?? [];
        $metadata['dispute_resolution'] = [
            'resolution' => 'release_seller',
            'resolved_by' => $admin->id,
            'resolved_at' => now()->toIso8601String(),
            'note' => $note,
        ];

        $transaction->update([
            'metadata' => $metadata,
            'status' => EscrowStatus::AWAITING_ACCEPTANCE,
        ]);

        $this->workflow->recordEvent($transaction, 'dispute_resolved_release', 'admin', $admin->id, [
            'note' => $note,
        ]);

        return $this->release->releaseNow($transaction->fresh(), $admin, true);
    }
}
