<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use Illuminate\Console\Command;

class DiagnoseAdminAuthCommand extends Command
{
    protected $signature = 'selloff:diagnose-admin-auth
                            {email? : Admin user email to inspect}
                            {--id= : Admin user id (alternative to email)}';

    protected $description = 'Diagnose admin_panel permissions and PIN readiness (use after pg_restore 403s)';

    public function handle(SuperAdminPinBootstrap $pinBootstrap): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('User not found. Pass --email= or --id=.');

            return self::FAILURE;
        }

        $user->loadMissing('roles', 'permissions');

        $roles = $user->getRoleNames()->values()->all();
        $permissions = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
        $requiresPin = AdminPinContext::requiresAdminPin($user);
        $pinConfigured = AdminPinContext::adminPinConfigured($user);
        $superPinConfigured = $pinBootstrap->isConfigured();

        $this->info("User #{$user->id} — {$user->email}");
        $this->table(['Check', 'Value'], [
            ['roles', $roles === [] ? '(none)' : implode(', ', $roles)],
            ['permissions', $permissions === [] ? '(none)' : implode(', ', $permissions)],
            ['can admin_panel', $user->can('admin_panel') ? 'yes' : 'NO'],
            ['can general_settings', $user->can('general_settings') ? 'yes' : 'no'],
            ['requires admin PIN', $requiresPin ? 'yes' : 'no'],
            ['admin PIN type', AdminPinContext::pinType($user) ?? 'n/a'],
            ['admin PIN configured', $pinConfigured ? 'yes' : 'NO'],
            ['super admin PIN configured', $superPinConfigured ? 'yes' : 'NO'],
        ]);

        $issues = [];

        if (! $user->can('admin_panel') && ! $user->hasRole('super-admin')) {
            $issues[] = 'Missing admin_panel permission and super-admin role — run selloff:sync-role-permissions or re-import roles_permissions + users.';
        }

        if ($user->hasRole('super-admin') && ! $superPinConfigured) {
            $issues[] = 'Super Admin PIN hash missing — run selloff:bootstrap-super-admin-pin.';
        }

        if ($requiresPin && AdminPinContext::pinType($user) === 'admin' && ! $pinConfigured) {
            $issues[] = 'Admin user has no personal PIN — set via super-admin UI or POST /admin/users/{id}/admin-pin.';
        }

        if ($issues === []) {
            $this->info('No obvious auth blockers. If API still returns 403, sign out/in and complete /admin/pin-verify (token must have admin-pin-verified ability).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Likely causes of 403:');
        foreach ($issues as $issue) {
            $this->line("  • {$issue}");
        }

        return self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        if ($id = $this->option('id')) {
            return User::query()->find((int) $id);
        }

        $email = $this->argument('email');

        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super-admin', 'admin']))
            ->orderBy('id')
            ->first();
    }
}
