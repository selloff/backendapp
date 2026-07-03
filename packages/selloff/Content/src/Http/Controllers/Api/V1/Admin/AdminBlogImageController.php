<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Models\BlogImage;
use App\Services\Media\MediaUploadService;
use App\Services\Media\Upload\MediaUploadRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminBlogImageController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $mediaUpload,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('blog_images')) {
            return ApiResponse::success([]);
        }

        $limit = min(max((int) $request->input('limit', 60), 1), 100);
        $beforeId = $request->input('before_id');

        $images = BlogImage::query()
            ->when(
                $beforeId !== null && $beforeId !== '',
                fn ($query) => $query->where('id', '<', (int) $beforeId),
            )
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BlogImage $image) => $this->formatImage($image))
            ->values()
            ->all();

        return ApiResponse::success($images);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('blog_images')) {
            return ApiResponse::error('Blog image library is not available.', 503);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.MediaUploadRegistry::maxUploadKilobytes('blog'),
                'mimes:'.implode(',', MediaUploadRegistry::allowedExtensions('blog', 'large')),
            ],
        ]);

        $large = $this->mediaUpload->upload($validated['file'], 'blog', 'large');

        $image = BlogImage::query()->create([
            'image_path' => $large['path'],
            'image_path_thumb' => $large['path'],
            'storage' => $large['disk'] ?? config('selloff.media_disk', 'public'),
            'user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($this->formatImage($image), 201);
    }

    public function destroy(BlogImage $blogImage): JsonResponse
    {
        $disk = $blogImage->storage ?: config('selloff.media_disk', 'public');

        foreach (array_filter([$blogImage->image_path, $blogImage->image_path_thumb]) as $path) {
            if ($path !== '') {
                Storage::disk($disk)->delete($path);
            }
        }

        $blogImage->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    private function formatImage(BlogImage $image): array
    {
        $disk = $image->storage ?: null;

        return [
            'id' => $image->id,
            'image_path' => $image->image_path,
            'image_path_thumb' => $image->image_path_thumb,
            'image_url' => $this->mediaUpload->urlFor($image->image_path, $disk),
            'thumb_url' => $image->image_path_thumb
                ? $this->mediaUpload->urlFor($image->image_path_thumb, $disk)
                : $this->mediaUpload->urlFor($image->image_path, $disk),
        ];
    }
}
