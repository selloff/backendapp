<?php

namespace App\Modules\Selloff\Vendor\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\Notification\Services\ShopOpeningEmailService;
use App\Modules\Selloff\Vendor\Services\VendorShopOpeningDocumentService;
use App\Services\Auth\RolePermissionSync;
use App\Services\Media\MediaUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminShopOpeningController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->input('show') ?: $request->integer('per_page', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $users = User::query()
            ->with(['vendorProfile', 'city', 'state.country'])
            ->where('shop_opening_status', '>', 0)
            ->when(
                in_array($request->input('status'), ['1', '2', '3'], true),
                fn ($q) => $q->where('shop_opening_status', $request->integer('status')),
            )
            ->orderByDesc('shop_request_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $users->getCollection()->transform(fn (User $user) => $this->formatRequest($user));

        return ApiResponse::success($users);
    }

    public function approve(User $user, ShopOpeningEmailService $emails): JsonResponse
    {
        $user->update([
            'shop_opening_status' => 0,
            'shop_opening_rejection_reason' => null,
        ]);

        app(RolePermissionSync::class)->sync();
        $user->syncRoles(['vendor']);

        $fresh = $user->fresh('vendorProfile');
        $emails->queueApproved($fresh);

        return ApiResponse::success($this->formatRequest($fresh));
    }

    public function reject(Request $request, User $user, ShopOpeningEmailService $emails): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:2,3'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $user->update([
            'shop_opening_status' => (int) $data['status'],
            'shop_opening_rejection_reason' => $data['reason'] ?? null,
        ]);

        $fresh = $user->fresh('vendorProfile');
        $emails->queueRejected($fresh, (int) $data['status'], $data['reason'] ?? null);

        return ApiResponse::success($this->formatRequest($fresh));
    }

    public function viewDocument(
        Request $request,
        User $user,
        VendorShopOpeningDocumentService $documents,
    ): StreamedResponse|Response {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        $document = $documents->findDocument($user, $data['path']);
        abort_if($document === null, 404);

        $resolved = $documents->resolveInlineView($document);
        abort_if($resolved === null, 404);

        $filename = (string) ($document['name'] ?? basename($document['path']));
        $headers = [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ];

        if ($resolved['type'] === 'contents') {
            return response($resolved['content'], 200, [
                ...$headers,
                'Content-Type' => $resolved['mime'],
            ]);
        }

        $mime = Storage::disk($resolved['disk'])->mimeType($resolved['path']) ?? 'application/octet-stream';

        return Storage::disk($resolved['disk'])->response($resolved['path'], $filename, [
            ...$headers,
            'Content-Type' => $mime,
        ]);
    }

    private function formatRequest(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'shop_name' => $user->vendorProfile?->shop_name ?? $user->username,
            'slug' => $user->slug,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'about_me' => $user->about_me,
            'location_label' => $this->locationLabel($user),
            'avatar' => $user->avatar,
            'storage_avatar' => $user->storage_avatar,
            'shop_opening_status' => (int) $user->shop_opening_status,
            'shop_request_date' => $user->shop_request_date?->toIso8601String(),
            'shop_opening_rejection_reason' => $user->shop_opening_rejection_reason,
            'vendor_documents' => $this->formatVendorDocuments($user->vendor_documents),
        ];
    }

    /**
     * @param  mixed  $documents
     * @return list<array{name: string, path: string, url: string}>
     */
    private function formatVendorDocuments(mixed $documents): array
    {
        return collect($documents ?? [])
            ->map(function ($document) {
                if (is_array($document)) {
                    $path = (string) ($document['path'] ?? '');

                    return [
                        'name' => (string) ($document['name'] ?? 'Document'),
                        'path' => $path,
                        'url' => $path !== '' ? $this->media->supportDocumentPublicUrl($path) : '',
                    ];
                }

                $path = (string) $document;

                return [
                    'name' => $path,
                    'path' => $path,
                    'url' => $path !== '' ? $this->media->supportDocumentPublicUrl($path) : '',
                ];
            })
            ->filter(fn (array $document) => $document['path'] !== '')
            ->values()
            ->all();
    }

    private function locationLabel(User $user): ?string
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

        $country = $user->state?->country?->name;
        if ($country) {
            $location = $location !== '' ? "{$location}, {$country}" : $country;
        }

        return $location !== '' ? $location : null;
    }
}
