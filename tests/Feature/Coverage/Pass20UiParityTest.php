<?php

namespace Tests\Feature\Coverage;

const PHASE_20_FULL_PATHS = [
    'auth/confirm_email.php',
    'auth/register_success.php',
    'cart/payment_completed_service.php',
    'product/start_selling.php',
    'blog/post.php',
    'blog/tag.php',
    'page.php',
    'support/index.php',
    'support/ticket.php',
    'support/tickets.php',
    'chat/chat.php',
    'escrow/escrow_started.php',
    'profile/reviews.php',
    'profile/shop_policies.php',
    'affiliate_program.php',
    'order/refund_requests.php',
    'order/refund.php',
    'order/downloads.php',
    'order/quote_requests.php',
    'profile/my_coupons.php',
    'profile/my_reviews.php',
    'profile/followers.php',
    'settings/change_password.php',
    'settings/location.php',
    'settings/social_media.php',
    'settings/delete_account.php',
    'wallet/_set_payout_account.php',
    'dashboard/product/add_product.php',
    'dashboard/product/edit_product.php',
    'dashboard/product/edit_product_details.php',
    'dashboard/sales/sale.php',
    'dashboard/coupon/coupons.php',
    'dashboard/refund/refund_requests.php',
    'dashboard/shipping/shipping_settings.php',
    'dashboard/shop_policies.php',
    'dashboard/quote_requests.php',
    'dashboard/reviews.php',
    'dashboard/comments.php',
    'dashboard/feedbacks.php',
    'dashboard/product/bulk_product_upload.php',
];

test('phase 20 paths are ui parity full in matrix', function () {
    $byPath = [];
    foreach (matrixRows_in_Pass20UiParity() as $row) {
        $byPath[$row['legacy_path']] = $row;
    }

    $missing = [];
    foreach (PHASE_20_FULL_PATHS as $legacyPath) {
        $row = $byPath[$legacyPath] ?? null;
        if ($row === null) {
            $missing[] = $legacyPath.' (not in matrix)';
            continue;
        }
        if ($row['ui_parity'] !== 'full') {
            $missing[] = $legacyPath.' ('.$row['ui_parity'].')';
        }
    }

    expect($missing)->toBe([]);
});

test('registry has at least seventy four full paths', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $fullPaths = $registry['ui_parity']['full_legacy_paths'] ?? [];

    expect(count($fullPaths))->toBeGreaterThanOrEqual(74);
});

test('buyer vendor partial count reduced by phase 20', function () {
    $prefixes = [
        'product/', 'cart/', 'order/', 'settings/', 'wallet/', 'wishlist', 'auth/',
        'profile/', 'blog/', 'support/', 'escrow/', 'chat/', 'dashboard/',
        'affiliate_program.php', 'page.php', 'unsubscribe.php',
    ];

    $partialBuyerVendor = 0;
    foreach (matrixRows_in_Pass20UiParity() as $row) {
        if ($row['status'] !== 'done' || $row['ui_parity'] !== 'partial') {
            continue;
        }
        foreach ($prefixes as $prefix) {
            if (str_starts_with($row['legacy_path'], $prefix) || $row['legacy_path'] === $prefix) {
                $partialBuyerVendor++;
                break;
            }
        }
    }

    expect($partialBuyerVendor)->toBeLessThanOrEqual(0);
});

/**
 * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
 */
function matrixRows_in_Pass20UiParity(): array
{
    $path = monorepo_path('docs/spa-parity-matrix.csv');
    $rows = [];
    $lines = array_slice(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))), 1);

    foreach ($lines as $line) {
        if (! preg_match('/^"([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)"$/', $line, $matches)) {
            continue;
        }

        $rows[] = [
            'legacy_path' => $matches[2],
            'spa_path' => $matches[3],
            'status' => $matches[4],
            'ui_parity' => $matches[5],
        ];
    }

    return $rows;
}
