<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminThemeController extends Controller
{
    /** @var list<string> */
    private const THEME_KEYS = [
        'primary_color',
        'font_family',
        'site_logo',
        'favicon',
        'menu_limit',
        'selected_navigation',
        'fea_categories_design',
        'product_img_display_mode',
    ];

    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function show(): JsonResponse
    {
        $all = $this->settings->all();
        $theme = [];
        foreach (self::THEME_KEYS as $key) {
            $theme[$key] = $all[$key] ?? $this->defaultForKey($key);
        }

        return ApiResponse::success($theme);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'font_family' => ['sometimes', 'nullable', 'string', 'max:100'],
            'site_logo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'menu_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'selected_navigation' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'fea_categories_design' => ['sometimes', 'nullable', 'string', Rule::in(['grid_layout', 'round_boxes', 'round_layout', 'square_layout'])],
            'product_img_display_mode' => ['sometimes', 'nullable', 'string', Rule::in(['cover', 'full_image'])],
        ]);

        if (isset($data['fea_categories_design'])) {
            $data['fea_categories_design'] = $this->normalizeFeaCategoriesDesign($data['fea_categories_design']);
        }

        if (isset($data['product_img_display_mode']) && $data['product_img_display_mode'] !== 'cover') {
            $data['product_img_display_mode'] = 'full_image';
        }

        $this->settings->upsertMany($data, 'theme');

        return $this->show();
    }

    private function defaultForKey(string $key): mixed
    {
        $defaults = config('selloff.platform_settings', []);

        return match ($key) {
            'menu_limit' => (int) ($defaults['menu_limit'] ?? 8),
            'selected_navigation' => (int) ($defaults['selected_navigation'] ?? 1),
            'fea_categories_design' => (string) ($defaults['fea_categories_design'] ?? 'round_boxes'),
            'product_img_display_mode' => (string) ($defaults['product_img_display_mode'] ?? 'cover'),
            default => $defaults[$key] ?? '',
        };
    }

    private function normalizeFeaCategoriesDesign(string $value): string
    {
        if (in_array($value, ['round_layout', 'square_layout'], true)) {
            return 'round_boxes';
        }

        if ($value === 'grid_layout') {
            return 'grid_layout';
        }

        return 'round_boxes';
    }
}
