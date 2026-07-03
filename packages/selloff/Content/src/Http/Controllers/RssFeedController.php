<?php

namespace App\Modules\Selloff\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Services\RssFeedService;
use App\Modules\Selloff\Content\Support\RssXmlRenderer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RssFeedController extends Controller
{
    public function __construct(
        private readonly RssFeedService $rss,
    ) {}

    public function index(): View|Response
    {
        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return view('selloff-content::rss.index', [
            'title' => $this->rss->siteName().' RSS Feeds',
            'latestFeedUrl' => $this->rss->latestFeedUrl(),
            'featuredFeedUrl' => $this->rss->featuredFeedUrl(),
            'categories' => $this->rss->parentCategoryFeeds(),
        ]);
    }

    public function directory(): JsonResponse
    {
        if (! $this->rss->isEnabled()) {
            return ApiResponse::error('RSS feeds are disabled.', 403);
        }

        return ApiResponse::success([
            'latest_feed_url' => $this->rss->latestFeedUrl(),
            'featured_feed_url' => $this->rss->featuredFeedUrl(),
            'categories' => $this->rss->parentCategoryFeeds(),
        ]);
    }

    public function latest(): Response
    {
        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->xmlResponse($this->rss->latestFeed());
    }

    public function featured(): Response
    {
        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->xmlResponse($this->rss->featuredFeed());
    }

    public function category(string $slug): Response|RedirectResponse
    {
        if ($response = $this->disabledResponse()) {
            return $response;
        }

        $feed = $this->rss->categoryFeed($slug);
        if ($feed === null) {
            return redirect($this->rss->rssFeedsIndexUrl(), 302);
        }

        return $this->xmlResponse($feed);
    }

    public function seller(string $slug): Response|RedirectResponse
    {
        if ($response = $this->disabledResponse()) {
            return $response;
        }

        $feed = $this->rss->sellerFeed($slug);
        if ($feed['redirectTo'] !== null) {
            return redirect($feed['redirectTo'], 302);
        }

        return $this->xmlResponse($feed);
    }

    /**
     * @param  array{feedName: string, feedUrl: string, pageDescription: string, products: Collection<int, \App\Modules\Selloff\Catalog\Models\Product>, copyright: string|null}  $feed
     */
    private function xmlResponse(array $feed): Response
    {
        $items = $feed['products']->map(function ($product): array {
            $imageUrl = $this->rss->productImageUrl($product);
            $description = '<div class="price"><p>✔ Price: '.$this->rss->formatPrice($product).'</p></div>';
            $description .= '<div class="description">'.$this->rss->productDescription($product).'</div>';

            return [
                'title' => $this->rss->productTitle($product),
                'link' => $this->rss->productUrl($product),
                'guid' => $this->rss->productUrl($product),
                'description' => $description,
                'pubDate' => $product->created_at?->toRfc2822String() ?? now()->toRfc2822String(),
                'creator' => $this->rss->productCreator($product),
                'imageUrl' => $imageUrl,
                'imageSize' => '2500',
                'imageMime' => 'image/jpeg',
            ];
        })->all();

        $xml = RssXmlRenderer::render(
            $feed['feedName'],
            $feed['feedUrl'],
            $feed['pageDescription'],
            $feed['copyright'],
            $items,
        );

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }

    private function disabledResponse(): ?Response
    {
        if ($this->rss->isEnabled()) {
            return null;
        }

        return response('RSS Disabled', 403);
    }
}
