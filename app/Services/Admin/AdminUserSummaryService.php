<?php

namespace App\Services\Admin;

use App\Http\Resources\Api\V1\UserAdminResource;
use App\Models\User;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payout\Services\VendorEarningService;
use App\Modules\Selloff\User\Models\LoginActivity;
use App\Services\Platform\PlatformSettingsService;

class AdminUserSummaryService
{
    public function __construct(
        private readonly VendorEarningService $earnings,
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $membershipSubquery = UserMembershipPlan::query()
            ->select('membership_plans.title')
            ->join('membership_plans', 'membership_plans.id', '=', 'user_membership_plans.membership_plan_id')
            ->whereColumn('user_membership_plans.user_id', 'users.id')
            ->where('user_membership_plans.is_active', true)
            ->orderByDesc('user_membership_plans.id')
            ->limit(1);

        $user = User::query()
            ->with(['roles', 'vendorProfile', 'state', 'city'])
            ->select('users.*')
            ->selectSub($membershipSubquery, 'membership_plan_title')
            ->whereKey($user->id)
            ->firstOrFail();

        $country = $user->country_id
            ? Country::query()->find($user->country_id)
            : null;

        $settings = $this->platformSettings->all();
        $defaultCurrency = (string) ($settings['default_currency'] ?? 'NGN');
        $isVendor = $user->hasRole('vendor');

        return [
            'user' => new UserAdminResource($user),
            'display_username' => $this->displayUsername($user),
            'location' => $this->formatLocation($user, $country),
            'default_currency' => $defaultCurrency,
            'stats' => [
                'orders_count' => Order::query()->where('buyer_id', $user->id)->count(),
                'products_count' => $user->products()->count(),
                'wallet_balance' => (string) $user->wallet_balance,
                'number_of_sales' => $isVendor ? $this->earnings->salesCount($user) : 0,
                'commission_debt' => '0',
            ],
            'recent_login_activities' => LoginActivity::query()
                ->where('user_id', $user->id)
                ->orderByDesc('login_at')
                ->limit(10)
                ->get(['id', 'ip_address', 'user_agent', 'login_at']),
        ];
    }

    private function displayUsername(User $user): string
    {
        $primaryRole = $user->roles->first();
        $isMember = ! $primaryRole || ! in_array($primaryRole->name, ['super-admin', 'admin', 'vendor'], true);

        if (! $isMember && ! empty($user->username)) {
            return (string) $user->username;
        }

        $name = trim("{$user->first_name} {$user->last_name}");

        return $name !== '' ? $name : 'user';
    }

    private function formatLocation(User $user, ?Country $country): string
    {
        $parts = [];

        if (! empty($user->address)) {
            $parts[] = trim((string) $user->address);
        }

        if (! empty($user->zip_code)) {
            $parts[] = trim((string) $user->zip_code);
        }

        if ($user->relationLoaded('city') && $user->city?->name) {
            $parts[] = $user->city->name;
        }

        $location = implode(' ', $parts);

        if ($user->relationLoaded('state') && $user->state?->name) {
            $location = $location !== '' ? "{$location}, {$user->state->name}" : $user->state->name;
        }

        if ($country?->name) {
            $location = $location !== '' ? "{$location}, {$country->name}" : $country->name;
        }

        return $location;
    }
}
