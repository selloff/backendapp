<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaUploadService;
use App\Services\Media\Upload\MediaUploadRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $mediaUpload,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $context = $this->mediaUpload->normalizeContext((string) $request->input('context', 'temp'));
        $variant = $request->input('variant');

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.MediaUploadRegistry::maxUploadKilobytes($context),
                $this->mimeRuleFor($context, is_string($variant) ? $variant : null),
            ],
            'context' => ['sometimes', 'string', Rule::in($this->mediaUpload->allowedContexts())],
            'variant' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $result = $this->mediaUpload->upload(
                $validated['file'],
                $validated['context'] ?? 'temp',
                is_string($variant) && $variant !== '' ? $variant : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            Log::error('Media upload failed.', [
                'context' => $validated['context'] ?? 'temp',
                'message' => $exception->getMessage(),
            ]);

            return ApiResponse::error('Image upload failed.', 500);
        }

        return ApiResponse::success($result, 201);
    }

    private function mimeRuleFor(string $context, ?string $variant): string
    {
        $extensions = MediaUploadRegistry::allowedExtensions($context, $variant);
        if ($extensions === []) {
            return 'file';
        }

        return 'mimes:'.implode(',', $extensions);
    }
}
