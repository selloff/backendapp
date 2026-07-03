<?php

namespace App\Modules\Selloff\Content\Services;

use App\Modules\Selloff\Content\Models\BlogComment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AdminBlogCommentsListService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);
        $search = $request->string('q')->trim();
        $status = $request->string('status')->trim();

        return BlogComment::query()
            ->with(['post:id,title,slug', 'user:id,first_name,last_name,email,username'])
            ->when($status->isNotEmpty(), fn ($query) => $query->where('status', $status))
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereLike('comment', $term, caseSensitive: false)
                        ->orWhereLike('name', $term, caseSensitive: false)
                        ->orWhereLike('email', $term, caseSensitive: false)
                        ->orWhereLike('ip_address', $term, caseSensitive: false)
                        ->orWhereHas('post', function ($postQuery) use ($term): void {
                            $postQuery->where(function ($postSearch) use ($term): void {
                                $postSearch->whereLike('title', $term, caseSensitive: false)
                                    ->orWhereLike('slug', $term, caseSensitive: false);
                            });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (BlogComment $comment): array => $this->transform($comment));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(BlogComment $comment): array
    {
        return [
            'id' => $comment->id,
            'name' => $comment->name,
            'email' => $comment->email,
            'comment' => $comment->comment,
            'status' => $comment->status,
            'ip_address' => $comment->ip_address,
            'created_at' => $comment->created_at,
            'post' => $comment->post ? [
                'id' => $comment->post->id,
                'title' => $comment->post->title,
                'slug' => $comment->post->slug,
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
}
