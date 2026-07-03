<?php

namespace App\Modules\Selloff\Admin\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AbuseReportsListService
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $paginator = DB::table('abuse_reports as ar')
            ->leftJoin('users as reporters', 'ar.reporter_id', '=', 'reporters.id')
            ->leftJoin('products', 'ar.product_id', '=', 'products.id')
            ->leftJoin('users as sellers', 'ar.user_id', '=', 'sellers.id')
            ->select([
                'ar.id',
                'ar.report_type',
                'ar.description',
                'ar.status',
                'ar.created_at',
                'ar.product_id',
                'ar.user_id',
                'ar.item_id',
                'reporters.username as reporter_username',
                'reporters.slug as reporter_slug',
                'reporters.email as reporter_email',
                'products.slug as product_slug',
                'sellers.slug as seller_slug',
            ])
            ->orderByDesc('ar.created_at')
            ->orderByDesc('ar.id')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => $this->transform($row))
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(object $row): array
    {
        $type = $this->normalizeType($row->report_type);
        $label = match ($type) {
            'seller' => 'Seller',
            'review' => 'Review',
            'comment' => 'Comment',
            default => 'Product',
        };

        $payload = [
            'id' => (int) $row->id,
            'report_type' => $type,
            'content_type_label' => $label,
            'description' => $row->description,
            'status' => $row->status,
            'created_at' => $row->created_at,
            'reporter_username' => $row->reporter_username,
            'reporter_slug' => $row->reporter_slug,
            'reporter_email' => $row->reporter_email,
            'content_url' => null,
            'content_modal' => null,
        ];

        return match ($type) {
            'seller' => $this->withSellerContent($payload, $row),
            'review' => $this->withReviewContent($payload, $row),
            'comment' => $this->withCommentContent($payload, $row),
            default => $this->withProductContent($payload, $row),
        };
    }

    private function normalizeType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, ['product', 'seller', 'review', 'comment'], true)
            ? $normalized
            : 'product';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withProductContent(array $payload, object $row): array
    {
        if (! empty($row->product_slug)) {
            $payload['content_url'] = '/products/'.$row->product_slug;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withSellerContent(array $payload, object $row): array
    {
        $slug = $row->seller_slug;

        if (! $slug && $row->item_id) {
            $slug = DB::table('users')->where('id', $row->item_id)->value('slug');
        }

        if ($slug) {
            $payload['content_url'] = '/shops/'.$slug;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withReviewContent(array $payload, object $row): array
    {
        $reviewId = (int) ($row->item_id ?? 0);
        if ($reviewId <= 0) {
            return $payload;
        }

        $review = DB::table('product_reviews as r')
            ->leftJoin('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.id', $reviewId)
            ->select([
                'r.id',
                'r.review',
                'u.username as author_username',
                'u.slug as author_slug',
            ])
            ->first();

        if (! $review) {
            return $payload;
        }

        $payload['content_modal'] = [
            'title' => 'Review',
            'body' => $review->review,
            'author_username' => $review->author_username,
            'author_slug' => $review->author_slug,
            'content_id' => (int) $review->id,
            'delete_type' => 'review',
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withCommentContent(array $payload, object $row): array
    {
        $commentId = (int) ($row->item_id ?? 0);
        if ($commentId <= 0) {
            return $payload;
        }

        $comment = DB::table('comments as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.id', $commentId)
            ->select([
                'c.id',
                'c.comment',
                'u.username as author_username',
                'u.slug as author_slug',
            ])
            ->first();

        if (! $comment) {
            return $payload;
        }

        $payload['content_modal'] = [
            'title' => 'Comment',
            'body' => $comment->comment,
            'author_username' => $comment->author_username,
            'author_slug' => $comment->author_slug,
            'content_id' => (int) $comment->id,
            'delete_type' => 'comment',
        ];

        return $payload;
    }
}
