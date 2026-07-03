<?php

namespace App\Console\Commands;

use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairEscrowLegacyParityCommand extends Command
{
    protected $signature = 'selloff:repair-escrow-legacy-parity
                            {--source= : Optional MySQL dump to re-read legacy escrow rows}
                            {--from-metadata : Backfill columns from existing metadata jsonb only}';

    protected $description = 'Backfill escrow_transactions product, pricing, ref, and stage columns from legacy import metadata';

    public function handle(): int
    {
        if ($this->option('from-metadata')) {
            return $this->repairFromMetadata();
        }

        $rawSource = (string) ($this->option('source') ?: config('selloff.legacy_import.default_source'));
        $source = realpath($rawSource) ?: realpath(base_path($rawSource)) ?: '';
        if ($source === '' || ! is_readable($source)) {
            $this->error('Provide --source=PATH to a readable MySQL dump, or use --from-metadata.');

            return self::FAILURE;
        }

        $reader = new MySqlDumpReader($source);
        if (! $reader->hasTable('escrow_transactions')) {
            $this->warn('Dump has no escrow_transactions table.');

            return self::SUCCESS;
        }

        $importer = app(\App\LegacyImport\Importers\EscrowTransactionsLegacyImporter::class);
        $context = new \App\LegacyImport\LegacyImportContext(tableFilter: 'escrow_transactions');
        app(\App\LegacyImport\LegacyImportMapRepository::class)->hydrateContext($context);
        $importer->import($context, $reader);

        $this->info('Re-imported escrow_transactions from dump.');

        return self::SUCCESS;
    }

    private function repairFromMetadata(): int
    {
        $updated = 0;

        DB::table('escrow_transactions')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$updated): void {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) $row->metadata, true);
                    if (! is_array($metadata) || $metadata === []) {
                        continue;
                    }

                    $itemPrice = LegacyValueCoercer::decimal($metadata['item_price'] ?? $row->amount ?? 0);
                    $commissionAmount = LegacyValueCoercer::decimal($metadata['commission_amount'] ?? $metadata['commission'] ?? $row->commission_amount ?? 0);
                    $sellerAmount = LegacyValueCoercer::decimal(
                        $metadata['amount_seller_received'] ?? $row->seller_amount ?? max(0, (float) $itemPrice - (float) $commissionAmount),
                    );

                    $productId = $row->product_id;
                    $itemLegacyId = (int) ($metadata['item_id'] ?? 0);
                    if ($productId === null && $itemLegacyId > 0) {
                        $productId = DB::table('products')->where('id', $itemLegacyId)->value('id');
                    }

                    $payload = [
                        'ref' => $metadata['ref'] ?? $row->ref,
                        'product_id' => $productId,
                        'amount' => $itemPrice,
                        'seller_amount' => $sellerAmount,
                        'commission_amount' => $commissionAmount,
                        'delivery_cost' => LegacyValueCoercer::decimal($metadata['delivery_cost'] ?? $row->delivery_cost ?? 0),
                        'delivery_address' => $metadata['delivery_address'] ?? $row->delivery_address,
                        'buyer_agreement_token' => $metadata['buyer_agreement_token'] ?? $row->buyer_agreement_token,
                        'seller_agreement_token' => $metadata['seller_agreement_token'] ?? $row->seller_agreement_token,
                        'buyer_email' => $metadata['buyer_email'] ?? $row->buyer_email,
                        'seller_email' => $metadata['seller_email'] ?? $row->seller_email,
                        'buyer_agreed' => LegacyValueCoercer::bool($metadata['buyer_agreed_to_escrow'] ?? $row->buyer_agreed ?? 0),
                        'seller_agreed' => LegacyValueCoercer::bool($metadata['seller_agreed_to_escrow'] ?? $row->seller_agreed ?? 0),
                        'payment_link_sent' => LegacyValueCoercer::bool($metadata['payment_link_sent'] ?? $row->payment_link_sent ?? 0),
                        'payment_received' => LegacyValueCoercer::bool($metadata['payment_received'] ?? $row->payment_received ?? 0),
                        'seller_shipped_item' => LegacyValueCoercer::bool($metadata['seller_shipped_item'] ?? $row->seller_shipped_item ?? 0),
                        'buyer_confirmed_item_delivery' => LegacyValueCoercer::bool($metadata['buyer_confirmed_item_delivery'] ?? $row->buyer_confirmed_item_delivery ?? 0),
                        'seller_received_payment' => LegacyValueCoercer::bool($metadata['seller_received_payment'] ?? $row->seller_received_payment ?? 0),
                        'transaction_complete' => LegacyValueCoercer::bool($metadata['transaction_complete'] ?? $row->transaction_complete ?? 0),
                        'updated_at' => now(),
                    ];

                    if (LegacyValueCoercer::bool($metadata['transaction_complete'] ?? 0)) {
                        $payload['status'] = 'completed';
                    } elseif (
                        LegacyValueCoercer::bool($metadata['buyer_agreed_to_escrow'] ?? 0)
                        && LegacyValueCoercer::bool($metadata['seller_agreed_to_escrow'] ?? 0)
                    ) {
                        $payload['status'] = 'processing';
                    }

                    $affected = DB::table('escrow_transactions')->where('id', $row->id)->update($payload);
                    if ($affected > 0) {
                        $updated++;
                    }
                }
            });

        $this->info("Updated {$updated} escrow transaction row(s) from metadata.");

        return self::SUCCESS;
    }
}
