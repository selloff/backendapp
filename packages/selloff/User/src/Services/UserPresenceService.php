<?php

namespace App\Modules\Selloff\User\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserPresenceService
{
    public function recordActivity(User $user): void
    {
        $throttleSeconds = $this->dbWriteThrottleSeconds();
        $cacheKey = $this->cacheKey($user->id);

        if (! Cache::add($cacheKey, now()->timestamp, $throttleSeconds)) {
            return;
        }

        $seenAt = now();

        User::query()->whereKey($user->id)->update(['last_seen_at' => $seenAt]);
        $user->setAttribute('last_seen_at', $seenAt);
    }

    public function isOnline(User $user): bool
    {
        if (Cache::has($this->cacheKey($user->id))) {
            return true;
        }

        $lastSeen = $user->last_seen_at;
        if ($lastSeen === null) {
            return false;
        }

        return $lastSeen->greaterThanOrEqualTo(now()->subSeconds($this->onlineWindowSeconds()));
    }

    private function cacheKey(int $userId): string
    {
        return "user_presence:{$userId}";
    }

    private function onlineWindowSeconds(): int
    {
        return max(30, (int) config('selloff.presence.online_window_seconds', 120));
    }

    private function dbWriteThrottleSeconds(): int
    {
        return max(15, (int) config('selloff.presence.db_write_throttle_seconds', 60));
    }
}
