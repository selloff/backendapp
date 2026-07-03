<?php

namespace App\Modules\Selloff\Review\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Models\ProductComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AdminCommentsListService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);
        $search = $request->string('q')->trim();

        $paginator = ProductComment::query()
            ->with([
                'product:id,slug',
                'product.translations:id,product_id,locale,title',
                'user:id,email,first_name,last_name,username',
            ])
            ->when($request->has('approved'), function ($query) use ($request): void {
                $query->where('is_approved', $request->boolean('approved'));
            })
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('comment', 'ilike', '%'.$search.'%')
                        ->orWhere('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%')
                        ->orWhere('ip_address', 'ilike', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('email', 'ilike', '%'.$search.'%')
                                ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%')
                                ->orWhere('username', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('product', function ($productQuery) use ($search): void {
                            $productQuery->where('slug', 'ilike', '%'.$search.'%')
                                ->orWhereHas('translations', function ($translationQuery) use ($search): void {
                                    $translationQuery->where('title', 'ilike', '%'.$search.'%');
                                });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $paginator->through(fn (ProductComment $comment): array => $this->transform($comment));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ProductComment $comment): array
    {
        $userName = trim((string) (($comment->user?->first_name ?? '').' '.($comment->user?->last_name ?? '')));
        if ($userName === '') {
            $userName = (string) ($comment->user?->username ?? '');
        }

        return [
            'id' => $comment->id,
            'name' => $comment->name ?: ($userName !== '' ? $userName : null),
            'email' => $comment->email ?: $comment->user?->email,
            'comment' => $comment->comment,
            'ip_address' => $comment->ip_address,
            'is_approved' => (bool) $comment->is_approved,
            'created_at' => $comment->created_at,
            'product' => $comment->product ? [
                'id' => $comment->product->id,
                'slug' => $comment->product->slug,
                'title' => $this->productTitle($comment->product),
            ] : null,
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'email' => $comment->user->email,
                'first_name' => $comment->user->first_name,
                'last_name' => $comment->user->last_name,
                'username' => $comment->user->username,
            ] : null,
        ];
    }

    private function productTitle(Product $product): ?string
    {
        return $product->translations->firstWhere('locale', 'en')?->title
            ?? $product->translations->first()?->title
            ?? $product->slug;
    }
}
