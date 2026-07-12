<?php

function registerPayload(array $overrides = []): array
{
    static $sequence = 0;
    $sequence++;

    return array_merge([
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone_number' => '+2348000'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        'email' => "register.{$sequence}@selloff.test",
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}
