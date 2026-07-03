<?php

namespace App\Modules\Selloff\Vendor\Services;

use App\Models\User;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorShopOpeningService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @return array{
     *   is_active_shop_request: int,
     *   rejection_reason: string|null,
     *   request_documents_required: bool,
     *   documents_explanation: string|null,
     * }
     */
    public function status(User $user): array
    {
        $settings = $this->platformSettings->all();

        return [
            'is_active_shop_request' => (int) $user->shop_opening_status,
            'rejection_reason' => $user->shop_opening_rejection_reason,
            'request_documents_required' => $this->platformBool($settings, 'request_documents_vendors', true),
            'documents_explanation' => $this->nullableString($settings['explanation_documents_vendors'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{is_active_shop_request: int}
     */
    public function submit(User $user, array $data): array
    {
        abort_if($user->can('vendor'), 422, 'You are already a vendor.');

        $status = (int) $user->shop_opening_status;
        abort_if($status === 1, 422, 'You already have a shop opening request pending review.');
        abort_if($status === 3, 422, 'Your shop opening request was permanently rejected.');

        $settings = $this->platformSettings->all();
        $documentsRequired = $this->platformBool($settings, 'request_documents_vendors', true);

        $validated = validator($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'shop_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'about_me' => ['nullable', 'string', 'max:5000'],
            'terms_accepted' => ['accepted'],
            'documents' => [$documentsRequired ? 'required' : 'nullable', 'array', $documentsRequired ? 'min:2' : ''],
            'documents.*.name' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.path' => ['required_with:documents', 'string', 'max:500'],
        ])->validate();

        $slug = Str::slug($validated['shop_name']);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'shop_name' => ['Enter a valid shop name.'],
            ]);
        }

        if ($this->shopSlugTaken($slug, $user->id)) {
            throw ValidationException::withMessages([
                'shop_name' => ['This shop name is already taken.'],
            ]);
        }

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'slug' => $slug,
            'phone_number' => $validated['phone_number'],
            'country_id' => $validated['country_id'],
            'state_id' => $validated['state_id'],
            'city_id' => $validated['city_id'],
            'about_me' => $validated['about_me'] ?? null,
            'shop_opening_status' => 1,
            'shop_request_date' => now(),
            'vendor_documents' => $validated['documents'] ?? [],
            'shop_opening_rejection_reason' => null,
        ]);

        VendorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'shop_name' => $validated['shop_name'],
                'slug' => $slug,
            ],
        );

        return [
            'is_active_shop_request' => 1,
        ];
    }

    private function shopSlugTaken(string $slug, int $userId): bool
    {
        return User::query()
            ->where('slug', $slug)
            ->where('id', '!=', $userId)
            ->exists()
            || VendorProfile::query()
                ->where('slug', $slug)
                ->where('user_id', '!=', $userId)
                ->exists();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function platformBool(array $settings, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
