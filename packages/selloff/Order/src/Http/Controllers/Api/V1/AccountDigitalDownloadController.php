<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\DigitalFile;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Services\Media\MediaUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountDigitalDownloadController extends Controller
{
    public function download(Request $request, DigitalSale $digitalSale): JsonResponse|StreamedResponse
    {
        abort_unless((int) $digitalSale->buyer_id === (int) $request->user()->id, 403);

        $file = DigitalFile::query()
            ->where('product_id', $digitalSale->product_id)
            ->orderBy('id')
            ->first();

        if ($file === null) {
            return ApiResponse::error('No downloadable file found for this purchase.', 404);
        }

        $disk = $file->storage ?: config('selloff.media_disk', 'public');
        $path = $file->file_name;

        if (! Storage::disk($disk)->exists($path)) {
            $url = app(MediaUploadService::class)->urlFor($path, $disk);
            if ($url) {
                return ApiResponse::success(['download_url' => $url]);
            }

            return ApiResponse::error('File is not available.', 404);
        }

        return Storage::disk($disk)->download($path, basename($path));
    }
}
