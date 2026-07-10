<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Services\AdminPinService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

describe('AdminPinService', function () {
    beforeEach(function () {
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
        Cache::flush();
    });

    test('verifies super admin login pin', function () {
        $user = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

        app(AdminPinService::class)->verifyLoginPin($user, '196001');

        expect(true)->toBeTrue();
    });

    test('rejects invalid super admin login pin', function () {
        $user = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

        expect(fn () => app(AdminPinService::class)->verifyLoginPin($user, '000000'))
            ->toThrow(ValidationException::class);
    });

    test('locks out login after repeated failures', function () {
        $user = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $service = app(AdminPinService::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $service->verifyLoginPin($user, '000000');
            } catch (ValidationException) {
                // expected invalid pin
            }
        }

        expect(fn () => $service->verifyLoginPin($user, '196001'))
            ->toThrow(ValidationException::class, 'Too many failed attempts');
    });
});
