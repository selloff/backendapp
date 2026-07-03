<?php

namespace App\Modules\Selloff\Support\Http\Resources\Api\V1;

use App\Modules\Selloff\Support\Models\Feedback;
use App\Services\Media\MediaUploadService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feedback */
class FeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaUploadService::class);

        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'user_id' => $this->user_id,
            'rating' => $this->rating,
            'feedback_type' => $this->feedback_type,
            'title' => $this->title,
            'feedback' => $this->feedback,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            'image_url' => $this->image_path
                ? $media->urlFor((string) $this->image_path, $this->image_disk)
                : null,
            'rejection_reason' => $this->when(
                (int) ($request->user()?->id) === (int) $this->user_id || $request->user()?->can('admin_panel'),
                $this->rejection_reason,
            ),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'slug' => $this->user->slug,
                'avatar_url' => MediaUrl::resolve($this->user->avatar),
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'email' => $this->when(
                    $request->user()?->can('admin_panel'),
                    $this->vendor->email,
                ),
            ]),
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'author_role' => $reply->author_role,
                'body' => $reply->body,
                'author' => $reply->relationLoaded('author') ? [
                    'id' => $reply->author->id,
                    'name' => $reply->author->name,
                ] : null,
                'created_at' => $reply->created_at,
            ])),
            'dispute' => $this->whenLoaded('dispute', fn () => $this->dispute ? [
                'id' => $this->dispute->id,
                'status' => $this->dispute->status,
                'reason' => $this->dispute->reason,
                'admin_note' => $this->dispute->admin_note,
                'resolved_at' => $this->dispute->resolved_at,
            ] : null),
            'edited_at' => $this->edited_at,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
