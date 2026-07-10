<?php

/**
 * Resolve paths to monorepo-root assets (docs/, scripts/, app.selloff/) from api.selloff tests.
 */
function monorepo_root(): ?string
{
    static $root = null;
    static $resolved = false;

    if ($resolved) {
        return $root;
    }

    $resolved = true;

    $candidates = array_filter([
        getenv('SELLOFF_MONOREPO_ROOT') ?: null,
        realpath(dirname(base_path())),
        realpath(base_path('..')),
    ]);

    foreach ($candidates as $candidate) {
        if ($candidate && is_file($candidate.'/docs/spa-parity-matrix.csv')) {
            $root = $candidate;

            return $root;
        }
    }

    $fallback = realpath(dirname(base_path()));

    return $fallback ?: null;
}

function monorepo_path(string $relative = ''): string
{
    $root = monorepo_root() ?? dirname(base_path());
    $relative = ltrim($relative, '/');
    $relative = preg_replace('#^\.\./#', '', $relative) ?? $relative;

    return $relative === '' ? $root : $root.'/'.$relative;
}

function monorepo_file_exists(string $relative): bool
{
    return is_file(monorepo_path($relative));
}

function skip_unless_monorepo_checkout(): void
{
    if (! monorepo_file_exists('docs/spa-parity-matrix.csv')) {
        test()->markTestSkipped(
            'Monorepo docs not found. Clone the full selloff repository (docs/, scripts/, app.selloff/) '
            .'or set SELLOFF_MONOREPO_ROOT to the repository root.'
        );
    }
}
