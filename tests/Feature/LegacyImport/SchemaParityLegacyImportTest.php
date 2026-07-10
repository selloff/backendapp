<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
});

test('wallet expense links order by order number', function () {
    seedTables_in_SchemaParityLegacyImport('users', 'orders', 'order_items');

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--table' => 'wallet_expenses',
        '--skip-verify' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('wallet_transactions', [
        'legacy_id' => 1002,
        'type' => 'expense',
        'order_id' => 401,
        'payment_id' => 'EXP-1002',
        'currency_code' => 'NGN',
        'amount' => '26500.00',
    ]);
});

test('escrow import maps product stages and item price', function () {
    seedTables_in_SchemaParityLegacyImport('users', 'products');

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--table' => 'escrow_transactions',
        '--skip-verify' => true,
    ])->assertSuccessful();

    $row = DB::table('escrow_transactions')->where('legacy_id', 4201)->first();
    expect($row)->not->toBeNull();
    expect($row->ref)->toBe('ESC-4201');
    expect((int) $row->product_id)->toBe(301);
    expect((float) $row->amount)->toEqual(25000.00);
    expect((float) $row->seller_amount)->toEqual(22500.00);
    expect((float) $row->commission_amount)->toEqual(2500.00);
    expect((bool) $row->buyer_agreed)->toBeTrue();
    expect((bool) $row->seller_agreed)->toBeTrue();
    expect((bool) $row->payment_link_sent)->toBeTrue();
    expect((bool) $row->payment_received)->toBeTrue();
    expect((bool) $row->transaction_complete)->toBeFalse();
});

test('vendor earnings imports line item breakdown', function () {
    seedTables_in_SchemaParityLegacyImport('users', 'orders', 'order_items');

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--table' => 'earnings',
        '--skip-verify' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('vendor_earnings', [
        'legacy_id' => 1101,
        'order_id' => 401,
        'order_item_id' => 501,
        'sale_amount' => '25000.00',
        'commission_amount' => '2500.00',
        'earned_amount' => '22500.00',
        'is_refunded' => false,
    ]);
});

test('invoice archival import derives totals from order', function () {
    seedTables_in_SchemaParityLegacyImport('users', 'orders', 'order_items');

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--table' => 'invoices',
        '--skip-verify' => true,
    ])->assertSuccessful();

    $invoice = DB::table('invoices')->where('legacy_id', 4101)->first();
    expect($invoice)->not->toBeNull();
    expect((int) $invoice->order_id)->toBe(401);
    expect((int) $invoice->order_number)->toBe(900001);
    expect((float) $invoice->total_amount)->toEqual(26500.00);
    expect($invoice->currency_code)->toBe('NGN');
    expect((int) $invoice->buyer_id)->toBe(103);

    $client = json_decode((string) $invoice->client_snapshot, true);
    expect($client['client_username'] ?? null)->toBe('legacybuyer');
    expect($invoice->line_items)->not->toBeNull();
});

/**
 * @param  string  ...$tables
 */
function seedTables_in_SchemaParityLegacyImport(string ...$tables): void
{
    foreach ($tables as $table) {
        test()->artisan('selloff:import-legacy-data', [
            '--source' => test()->fixture,
            '--table' => $table,
            '--skip-verify' => true,
        ])->assertSuccessful();
    }
}