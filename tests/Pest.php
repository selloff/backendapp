<?php

require_once __DIR__.'/Helpers/AdminPin.php';
require_once __DIR__.'/Helpers/MonorepoPath.php';

/*
|--------------------------------------------------------------------------
| Test Case bindings
|--------------------------------------------------------------------------
|
| Pest binds each test closure to a PHPUnit test case class. Feature tests
| use Laravel's TestCase; pure unit tests under Shipping use PHPUnit only.
|
*/

use Tests\Concerns\InteractsWithAdminPin;
use Tests\Feature\Api\V1\Concerns\InteractsWithDemoEscrow;
use Tests\TestCase;

uses(TestCase::class, InteractsWithAdminPin::class)->in('Feature');

uses(InteractsWithDemoEscrow::class)->in('Feature/Api/V1');

uses()->beforeEach(function () {
    skip_unless_monorepo_checkout();
})->in('Feature/Coverage');

uses(TestCase::class)->in(
    'Unit/Support',
    'Unit/User',
    'Unit/LegacyImport',
    'Unit/Modules/Selloff/Media',
    'Unit/Modules/Selloff/Payment',
    'Unit/Modules/Selloff/Catalog',
    'Unit/Modules/Selloff/Admin',
    'Unit/Modules/Selloff/Auth',
    'Unit/Modules/Selloff/Notification',
);

uses()->in('Unit/Modules/Selloff/Shipping');

/*
|--------------------------------------------------------------------------
| Conversion cookbook (PHPUnit → Pest)
|--------------------------------------------------------------------------
|
| 1. Class wrapper → remove; keep `use` imports at file top.
| 2. setUp() → beforeEach(function () { ... }); drop parent::setUp() (TestCase
|    is applied via uses() above).
| 3. test_foo_bar() → it('foo bar', function () { ... });
| 4. Assertions:
|      $this->assertSame($expected, $actual)  →  expect($actual)->toBe($expected)
|      $this->assertTrue($x)                  →  expect($x)->toBeTrue()
|      $this->postJson(...)->assertOk()       →  unchanged ($this still works)
| 5. Per-file config/Http::fake overrides stay in that file's beforeEach().
|    Do not add global config overrides here.
| 6. DB-heavy feature tests: call migrate in each file's beforeEach(), or use
|    the MigratesFreshDemoDatabase trait from tests/Helpers/.
|
| Run: ./vendor/bin/pest   or   composer test
|
*/
