<?php

namespace App\Modules\Selloff\Vendor\Services;

use App\Models\User;
use App\Services\Media\MediaUploadService;

class VendorShopOpeningDocumentService
{
    public function __construct(
        private readonly MediaUploadService $media,
    ) {}

    /**
     * @return array{name: string, path: string, storage?: string|null}|null
     */
    public function findDocument(User $user, string $path): ?array
    {
        foreach ($user->vendor_documents ?? [] as $document) {
            if (! is_array($document)) {
                continue;
            }

            $documentPath = (string) ($document['path'] ?? '');
            if ($documentPath === '' || ! $this->media->supportDocumentPathsMatch($path, $documentPath)) {
                continue;
            }

            return [
                'name' => (string) ($document['name'] ?? basename($documentPath)),
                'path' => $this->media->normalizeSupportDocumentReference($documentPath),
                'storage' => isset($document['storage']) ? (string) $document['storage'] : null,
            ];
        }

        return null;
    }

    /**
     * @param  array{name: string, path: string, storage?: string|null}  $document
     * @return array{disk: string, path: string}|null
     */
    public function resolveReadableLocation(array $document): ?array
    {
        return $this->media->resolveReadableSupportDocument(
            $document['path'],
            $document['storage'] ?? null,
        );
    }
}
