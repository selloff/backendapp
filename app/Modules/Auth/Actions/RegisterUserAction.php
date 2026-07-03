<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Selloff\Referral\Actions\ApplyReferralCodeOnRegisterAction;
use App\Modules\Selloff\Referral\Actions\EnsureReferralProfileAction;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RegisterUserAction
{
    public function __construct(
        private readonly EmailVerificationService $emailVerification,
        private readonly EnsureReferralProfileAction $ensureReferralProfile,
        private readonly ApplyReferralCodeOnRegisterAction $applyReferralCode,
    ) {}
    /**
     * @return array{user: User, token: string}
     */
    public function execute(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $deviceName = 'spa',
        ?string $phoneNumber = null,
        ?string $referralCode = null,
    ): array {
        $slugBase = Str::slug(trim("{$firstName}-{$lastName}")) ?: Str::slug(Str::before($email, '@'));
        $slug = $slugBase;
        $suffix = 1;

        while (User::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        $this->applyReferralCode->validateForNewRegistration($referralCode);

        $user = User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'slug' => $slug,
            'email' => $email,
            'phone_number' => $phoneNumber,
            'password' => Hash::make($password),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => null,
        ]);

        Role::findOrCreate('member', 'web');
        $user->assignRole('member');

        $this->ensureReferralProfile->execute($user);
        $this->applyReferralCode->execute($user, $referralCode);

        $verification = $this->emailVerification->issueToken($user);
        $this->emailVerification->queueVerificationEmail($user, $verification);

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
            'email_verification_required' => true,
        ];
    }
}
