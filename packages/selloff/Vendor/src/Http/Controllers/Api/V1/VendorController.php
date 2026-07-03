<?php

namespace App\Modules\Selloff\Vendor\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductReviewResource;
use App\Modules\Selloff\Support\Http\Requests\Api\V1\StoreVendorFeedbackRequest;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\FeedbackResource;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Services\VendorFeedbackRatingService;
use App\Modules\Selloff\Support\Services\VendorFeedbackService;
use App\Modules\Selloff\Payment\Services\MembershipProductDetailPerksService;
use App\Modules\Selloff\Vendor\Http\Resources\Api\V1\VendorResource;
use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['vendorProfile'])
            ->join('vendor_profiles', 'vendor_profiles.user_id', '=', 'users.id')
            ->select('users.*')
            ->whereHas('products', fn ($q) => $q
                ->where('status', 'published')
                ->where('visibility', 'visible')
                ->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('users.name', 'like', $term)
                        ->orWhere('vendor_profiles.shop_name', 'like', $term)
                        ->orWhere('vendor_profiles.slug', 'like', $term);
                });
            })
            ->orderBy('vendor_profiles.shop_name');

        $paginator = $query->paginate(min($request->integer('per_page', 24), 48));
        $paginator->through(fn (User $vendor) => new VendorResource($vendor->load('vendorProfile')));

        return ApiResponse::success($paginator);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $vendor = User::query()
            ->with(['vendorProfile'])
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        $priority = ProductLocationPriorityQuery::fromRequest($request);

        $productsQuery = Product::query()
            ->listed()
            ->where('vendor_id', $vendor->id)
            ->with(ProductResource::listEagerLoads())
            ->withCount('options');

        ProductLocationPriorityQuery::apply($productsQuery, $priority['priority_state_id'], $priority['priority_city_id']);

        $products = $productsQuery
            ->orderByDesc('created_at')
            ->paginate(
                min($request->integer('per_page', 15), 48),
                ['*'],
                'page',
                max(1, $request->integer('page', 1)),
            );

        $products->through(fn ($product) => new ProductResource($product));

        $reviews = ProductReview::query()
            ->with(['user', 'product.translations'])
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('is_approved', true)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'review' => $review->review,
                'user' => $review->user ? ['name' => $review->user->name] : null,
                'product_title' => $review->product?->translations->first()?->title,
                'created_at' => $review->created_at,
            ]);

        return ApiResponse::success([
            'vendor' => new VendorResource($vendor),
            'products' => $products,
            'reviews' => $reviews,
            'shop_policies' => $vendor->vendorProfile?->shop_policies,
        ]);
    }

    public function reviews(Request $request, string $slug): JsonResponse
    {
        $vendor = User::query()
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        $reviews = ProductReview::query()
            ->with(['user', 'product.translations'])
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('is_approved', true)
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 48));

        $reviews->through(fn (ProductReview $review) => new ProductReviewResource($review));

        return ApiResponse::success($reviews);
    }

    public function feedback(Request $request, string $slug, VendorFeedbackService $feedbackService, MembershipProductDetailPerksService $perks): JsonResponse
    {
        $vendor = User::query()
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        if ($perks->shouldHideSellerFeedback($vendor)) {
            $perPage = min($request->integer('per_page', 15), 48);

            return ApiResponse::success(new LengthAwarePaginator([], 0, $perPage, 1));
        }

        $feedbacks = $feedbackService->listApprovedForVendor(
            $vendor,
            min($request->integer('per_page', 15), 48),
        );

        $feedbacks->through(fn (Feedback $feedback) => new FeedbackResource($feedback));

        return ApiResponse::success($feedbacks);
    }

    public function feedbackSummary(string $slug, VendorFeedbackRatingService $ratings, MembershipProductDetailPerksService $perks): JsonResponse
    {
        $vendor = User::query()
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        if ($perks->shouldHideSellerFeedback($vendor)) {
            return ApiResponse::success([
                'positive_count' => 0,
                'neutral_count' => 0,
                'negative_count' => 0,
                'total_count' => 0,
                'percent_positive' => 0,
                'avg_rating' => null,
            ]);
        }

        return ApiResponse::success($ratings->summaryForVendor($vendor->id));
    }

    public function myFeedback(Request $request, string $slug, VendorFeedbackService $feedbackService): JsonResponse
    {
        $vendor = User::query()
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        $feedback = $feedbackService->mineForVendor($request->user(), $vendor);

        return ApiResponse::success([
            'feedback' => $feedback ? new FeedbackResource($feedback) : null,
        ]);
    }

    public function storeFeedback(
        StoreVendorFeedbackRequest $request,
        string $slug,
        VendorFeedbackService $feedbackService,
    ): JsonResponse {
        $vendor = User::query()
            ->whereHas('vendorProfile', fn ($q) => $q->where('slug', $slug))
            ->firstOrFail();

        $data = $request->validated();
        $feedback = $feedbackService->upsert(
            $request->user(),
            $vendor,
            $data,
            $request->file('image'),
        );

        return ApiResponse::success(
            new FeedbackResource($feedback),
            201,
        );
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $user = $request->user()->load('vendorProfile');

        return ApiResponse::success(new VendorResource($user));
    }

    public function updateMe(Request $request, PlatformSettingsService $platformSettings): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:255'],
            'about_me' => ['nullable', 'string', 'max:5000'],
            'shop_policies' => ['nullable', 'string', 'max:20000'],
            'social_media_data' => ['nullable', 'array'],
            'vacation_mode' => ['sometimes', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:5000'],
            'show_rss_feeds' => ['sometimes', 'boolean'],
            'is_fixed_vat' => ['sometimes', 'boolean'],
            'fixed_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'vat_rates_data' => ['nullable', 'array'],
            'vat_rates_data.*' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'vat_rates_by_state' => ['nullable', 'array'],
            'vat_rates_by_state.*' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $platform = $platformSettings->all();
        $canEditShopName = $request->user()->can('admin_panel')
            || filter_var($platform['vendors_change_shop_name'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $profileData = array_filter([
            'shop_policies' => $data['shop_policies'] ?? null,
            'social_media_data' => $data['social_media_data'] ?? null,
            'vacation_mode' => $data['vacation_mode'] ?? null,
            'vacation_message' => $data['vacation_message'] ?? null,
            'is_fixed_vat' => $data['is_fixed_vat'] ?? null,
            'fixed_vat_rate' => $data['fixed_vat_rate'] ?? null,
            'vat_rates_data' => array_key_exists('vat_rates_data', $data)
                ? $this->normalizeSubmittedVatRates($data['vat_rates_data'] ?? [])
                : null,
            'vat_rates_by_state' => array_key_exists('vat_rates_by_state', $data)
                ? $this->normalizeSubmittedVatRates($data['vat_rates_by_state'] ?? [])
                : null,
        ], fn ($value) => $value !== null);

        if ($canEditShopName && array_key_exists('shop_name', $data)) {
            $profileData['shop_name'] = $data['shop_name'];
        }

        if (array_key_exists('is_fixed_vat', $data) && $data['is_fixed_vat']) {
            $profileData['vat_rates_data'] = [];
            $profileData['vat_rates_by_state'] = [];
            $profileData['fixed_vat_rate'] = $this->normalizeFixedVatRate($data['fixed_vat_rate'] ?? null);
        }

        if (array_key_exists('is_fixed_vat', $data) && ! $data['is_fixed_vat']) {
            $profileData['fixed_vat_rate'] = null;
        }

        if (! empty($profileData)) {
            VendorProfile::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                $profileData,
            );
        }

        $userData = array_filter([
            'about_me' => $data['about_me'] ?? null,
            'show_rss_feeds' => $data['show_rss_feeds'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($userData)) {
            $request->user()->update($userData);
        }

        return ApiResponse::success(new VendorResource($request->user()->fresh()->load('vendorProfile')));
    }

    /**
     * @param  array<int|string, mixed>|null  $rates
     * @return array<string, string>
     */
    private function normalizeSubmittedVatRates(?array $rates): array
    {
        if ($rates === null) {
            return [];
        }

        $normalized = [];
        foreach ($rates as $locationId => $rate) {
            $numericRate = is_numeric($rate) ? (float) $rate : 0;
            if ($numericRate <= 0) {
                continue;
            }

            $normalized[(string) $locationId] = number_format(min($numericRate, 99.99), 2, '.', '');
        }

        return $normalized;
    }

    private function normalizeFixedVatRate(mixed $rate): ?string
    {
        if (! is_numeric($rate)) {
            return null;
        }

        $numericRate = (float) $rate;
        if ($numericRate <= 0 || $numericRate > 99.99) {
            return null;
        }

        return number_format($numericRate, 2, '.', '');
    }
}
