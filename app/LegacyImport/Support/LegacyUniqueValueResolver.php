<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves collisions when legacy MySQL allowed duplicate username/slug/email values
 * but PostgreSQL enforces unique constraints.
 */
class LegacyUniqueValueResolver
{
    /** @var array<string, array<string, int>> */
    private array $claims = [];

    public function uniqueUsername(?string $value, int $legacyId): ?string
    {
        $candidate = trim((string) ($value ?? ''));
        if ($candidate === '') {
            return null;
        }

        return $this->resolve('users.username', $candidate, $legacyId, 'user-'.$legacyId);
    }

    public function uniqueUserSlug(?string $value, int $legacyId): string
    {
        return $this->resolve('users.slug', trim((string) ($value ?? '')), $legacyId, 'user-'.$legacyId);
    }

    public function uniqueEmail(?string $value, int $legacyId): string
    {
        $candidate = trim((string) ($value ?? ''));
        if ($candidate === '') {
            return 'legacy-'.$legacyId.'@import.local';
        }

        return $this->resolve('users.email', $candidate, $legacyId, 'legacy-'.$legacyId.'@import.local');
    }

    public function uniqueVendorSlug(?string $value, int $legacyId): string
    {
        return $this->resolve('vendor_profiles.slug', trim((string) ($value ?? '')), $legacyId, 'shop-'.$legacyId);
    }

    private function resolve(string $namespace, string $candidate, int $ownerId, string $fallback): string
    {
        if ($candidate === '') {
            $candidate = $fallback;
        }

        if (! $this->isTaken($namespace, $candidate, $ownerId)) {
            $this->claim($namespace, $candidate, $ownerId);

            return $candidate;
        }

        $suffixed = $this->suffix($candidate, $ownerId);
        if (! $this->isTaken($namespace, $suffixed, $ownerId)) {
            $this->claim($namespace, $suffixed, $ownerId);

            return $suffixed;
        }

        if (! $this->isTaken($namespace, $fallback, $ownerId)) {
            $this->claim($namespace, $fallback, $ownerId);

            return $fallback;
        }

        $lastResort = $this->suffix($fallback, $ownerId);
        $this->claim($namespace, $lastResort, $ownerId);

        return $lastResort;
    }

    private function suffix(string $value, int $ownerId): string
    {
        $suffix = '-'.$ownerId;
        $maxLength = 255 - strlen($suffix);

        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value.$suffix;
    }

    private function isTaken(string $namespace, string $value, int $ownerId): bool
    {
        if (isset($this->claims[$namespace][$value]) && $this->claims[$namespace][$value] !== $ownerId) {
            return true;
        }

        return $this->existsInDatabase($namespace, $value, $ownerId);
    }

    private function claim(string $namespace, string $value, int $ownerId): void
    {
        $this->claims[$namespace][$value] = $ownerId;
    }

    private function existsInDatabase(string $namespace, string $value, int $ownerId): bool
    {
        return match ($namespace) {
            'users.username' => DB::table('users')->where('username', $value)->where('id', '!=', $ownerId)->exists(),
            'users.slug' => DB::table('users')->where('slug', $value)->where('id', '!=', $ownerId)->exists(),
            'users.email' => DB::table('users')->where('email', $value)->where('id', '!=', $ownerId)->exists(),
            'vendor_profiles.slug' => DB::table('vendor_profiles')->where('slug', $value)->where('user_id', '!=', $ownerId)->exists(),
            default => false,
        };
    }
}
