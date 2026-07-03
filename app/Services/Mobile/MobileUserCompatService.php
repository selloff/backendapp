<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileUserCompatService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['fullname']) && ! isset($data['first_name'])) {
            $parts = preg_split('/\s+/', trim((string) $data['fullname']), 2) ?: [];
            $data['first_name'] = $parts[0] ?? $user->first_name;
            $data['last_name'] = $parts[1] ?? $user->last_name;
        }

        unset($data['fullname']);

        $fillable = array_intersect_key($data, array_flip([
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'avatar',
        ]));

        if ($fillable === []) {
            return $user;
        }

        $user->fill($fillable);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->fresh();
    }

    public function deleteAccount(User $user, ?string $password = null): void
    {
        if ($password !== null && ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->tokens()->delete();

        $user->forceFill([
            'is_disable' => true,
            'is_enable_login' => false,
            'email' => 'deleted+'.$user->id.'+'.now()->timestamp.'@deleted.selloff.local',
        ])->save();
    }

    /** @return array{followed: bool, message: string} */
    public function toggleFollowSeller(User $follower, int $sellerId): array
    {
        if ($follower->id === $sellerId) {
            throw ValidationException::withMessages([
                'seller_id' => ['You cannot follow yourself.'],
            ]);
        }

        User::query()->findOrFail($sellerId);

        $existing = DB::table('followers')
            ->where('user_id', $sellerId)
            ->where('follower_id', $follower->id)
            ->first();

        if ($existing) {
            DB::table('followers')->where('id', $existing->id)->delete();

            return ['followed' => false, 'message' => 'Seller Unfollowed'];
        }

        DB::table('followers')->insert([
            'user_id' => $sellerId,
            'follower_id' => $follower->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['followed' => true, 'message' => 'Seller Followed'];
    }

    public function reportSeller(User $reporter, int $sellerId, ?string $message = null): void
    {
        User::query()->findOrFail($sellerId);

        DB::table('abuse_reports')->insert([
            'reporter_id' => $reporter->id,
            'user_id' => $sellerId,
            'item_id' => $sellerId,
            'report_type' => 'seller',
            'description' => $message,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function reportUser(User $reporter, int $reportedUserId, ?string $message = null): void
    {
        User::query()->findOrFail($reportedUserId);

        DB::table('abuse_reports')->insert([
            'reporter_id' => $reporter->id,
            'user_id' => $reportedUserId,
            'item_id' => $reportedUserId,
            'report_type' => 'user',
            'description' => $message,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function reportProduct(User $reporter, int $productId, ?string $message = null): void
    {
        \App\Modules\Selloff\Catalog\Models\Product::query()->findOrFail($productId);

        DB::table('abuse_reports')->insert([
            'reporter_id' => $reporter->id,
            'product_id' => $productId,
            'item_id' => $productId,
            'report_type' => 'product',
            'description' => $message,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
