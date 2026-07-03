<?php

namespace App\Modules\Selloff\Content\Actions;

use App\Modules\Selloff\Content\Models\AdSpace;
use App\Services\Media\MediaUploadService;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;

class UpdateAdSpaceAction
{
    public function __construct(
        private readonly MediaUploadService $mediaUpload,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AdSpace $adSpace, array $data): AdSpace
    {
        $payload = [
            'ad_code_desktop' => $data['ad_code_desktop'] ?? $adSpace->ad_code_desktop,
            'ad_code_mobile' => $data['ad_code_mobile'] ?? $adSpace->ad_code_mobile,
            'desktop_width' => (int) ($data['desktop_width'] ?? $adSpace->desktop_width ?? 728),
            'desktop_height' => (int) ($data['desktop_height'] ?? $adSpace->desktop_height ?? 90),
            'mobile_width' => (int) ($data['mobile_width'] ?? $adSpace->mobile_width ?? 300),
            'mobile_height' => (int) ($data['mobile_height'] ?? $adSpace->mobile_height ?? 250),
            'ad_code' => $data['ad_code_desktop'] ?? $adSpace->ad_code_desktop ?? $adSpace->ad_code,
        ];

        if (! empty($data['file_ad_code_desktop']) && $data['file_ad_code_desktop'] instanceof UploadedFile) {
            $upload = $this->mediaUpload->upload($data['file_ad_code_desktop'], 'ad');
            $payload['ad_code_desktop'] = $this->createAdCode(
                (string) ($data['url_ad_code_desktop'] ?? ''),
                (string) $upload['path'],
                $payload['desktop_width'],
                $payload['desktop_height'],
            );
            $payload['ad_code'] = $payload['ad_code_desktop'];
        }

        if (! empty($data['file_ad_code_mobile']) && $data['file_ad_code_mobile'] instanceof UploadedFile) {
            $upload = $this->mediaUpload->upload($data['file_ad_code_mobile'], 'ad');
            $payload['ad_code_mobile'] = $this->createAdCode(
                (string) ($data['url_ad_code_mobile'] ?? ''),
                (string) $upload['path'],
                $payload['mobile_width'],
                $payload['mobile_height'],
            );
        }

        $adSpace->update($payload);

        return $adSpace->fresh();
    }

    public function createAdCode(string $url, string $imagePath, int $width, int $height): string
    {
        $imageUrl = MediaUrl::resolve($imagePath);

        return sprintf(
            '<a href="%s" aria-label="link-bn"><img data-src="%s" width="%d" height="%d" alt="" class="lazyload"></a>',
            e($url),
            e($imageUrl),
            $width,
            $height,
        );
    }
}
