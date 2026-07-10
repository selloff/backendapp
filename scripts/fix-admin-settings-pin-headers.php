#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Add superAdminPinHeaders() to settings/language/gateway admin writes missing PIN.
 */

$root = dirname(__DIR__).'/tests/Feature/Api/V1';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

$patterns = [
    // putJson('/api/v1/settings', [...]) without headers
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/settings[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->postJson\(\s*([\'"]\/api\/v1\/admin\/email\/test[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->postJson($1, $2, superAdminPinHeaders())',
    '/\$this->postJson\(\s*([\'"]\/api\/v1\/admin\/languages[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->postJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/languages[^\'"]*[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/routes[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/featured-pricing[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/payments\/gateways[^\'"]*[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->postJson\(\s*([\'"]\/api\/v1\/admin\/tax-rules[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->postJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/tax-rules[^\'"]*[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->putJson\(\s*([\'"]\/api\/v1\/admin\/platform\/[^\'"]+[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->putJson($1, $2, superAdminPinHeaders())',
    '/\$this->postJson\(\s*([\'"]\/api\/v1\/admin\/platform\/[^\'"]+[\'"])\s*,\s*(\[[\s\S]*?\])\s*\)/' => '$this->postJson($1, $2, superAdminPinHeaders())',
];

foreach ($files as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $original = $content;

    foreach ($patterns as $pattern => $replacement) {
        if (str_contains($content, 'superAdminPinHeaders')) {
            // still patch lines missing it
        }
        $content = preg_replace($pattern, $replacement, $content) ?? $content;
    }

    // Avoid double-appending headers.
    $content = preg_replace(
        '/, superAdminPinHeaders\(\)\s*, superAdminPinHeaders\(\)/',
        ', superAdminPinHeaders()',
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Patched super-admin PIN in {$path}\n";
    }
}
