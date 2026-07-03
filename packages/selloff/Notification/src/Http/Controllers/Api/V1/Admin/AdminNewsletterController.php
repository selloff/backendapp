<?php

namespace App\Modules\Selloff\Notification\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\Notification\Models\NewsletterSubscriber;
use App\Modules\Selloff\Notification\Services\NewsletterSettingsService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminNewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterSettingsService $newsletterSettings,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function settings(): JsonResponse
    {
        return ApiResponse::success($this->newsletterSettings->settings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'newsletter_status' => ['sometimes', 'boolean'],
            'newsletter_popup_active' => ['sometimes', 'boolean'],
            'newsletter_image_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'newsletter_image_storage' => ['sometimes', 'nullable', 'string', 'max:50'],
            'newsletter_image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($this->newsletterSettings->update($data));
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 500)), 500);
        $search = $request->string('q')->trim();

        $users = User::query()
            ->select(['id', 'username', 'email', 'first_name', 'last_name'])
            ->where('is_disable', false)
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereLike('email', $term, caseSensitive: false)
                        ->orWhereLike('username', $term, caseSensitive: false)
                        ->orWhereLike('first_name', $term, caseSensitive: false)
                        ->orWhereLike('last_name', $term, caseSensitive: false);
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::success($this->paginatedPayload($users));
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 500)), 500);
        $search = $request->string('q')->trim();

        $subscribers = NewsletterSubscriber::query()
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($search->isNotEmpty(), fn ($q) => $q->whereLike('email', '%'.$search.'%', caseSensitive: false))
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::success($this->paginatedPayload($subscribers));
    }

    public function resolveRecipients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', Rule::in(['users', 'subscribers'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $emails = $data['source'] === 'users'
            ? User::query()->whereIn('id', $data['ids'])->orderBy('id')->pluck('email')->filter()->values()->all()
            : NewsletterSubscriber::query()->whereIn('id', $data['ids'])->orderBy('id')->pluck('email')->filter()->values()->all();

        if ($emails === []) {
            return ApiResponse::error('No valid email addresses were found for the selected recipients.', 422);
        }

        return ApiResponse::success([
            'source' => $data['source'],
            'emails' => $emails,
        ]);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'recipient_type' => ['required', 'string', Rule::in(['users', 'subscribers'])],
        ]);

        $fromName = (string) ($this->settings->all()['mail_from_name'] ?? config('app.name', 'Selloff'));
        $fromAddress = (string) ($this->settings->all()['mail_from_address'] ?? config('mail.from.address'));

        if ($data['recipient_type'] === 'subscribers') {
            $subscriber = NewsletterSubscriber::query()->where('email', $data['email'])->first();
            $body = $data['body'];

            if ($subscriber?->token) {
                $unsubscribeUrl = rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/unsubscribe?email='.urlencode($data['email']).'&token='.urlencode($subscriber->token);
                $body .= '<p style="margin-top:24px;font-size:12px;color:#888;"><a href="'.e($unsubscribeUrl).'">Unsubscribe</a></p>';
            }

            Mail::html($body, function ($message) use ($data, $fromName, $fromAddress): void {
                $message->to($data['email'])
                    ->subject($data['subject'])
                    ->from($fromAddress, $fromName);
            });
        } else {
            Mail::html($data['body'], function ($message) use ($data, $fromName, $fromAddress): void {
                $message->to($data['email'])
                    ->subject($data['subject'])
                    ->from($fromAddress, $fromName);
            });
        }

        return ApiResponse::success(['sent' => true, 'email' => $data['email']]);
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber): JsonResponse
    {
        $newsletterSubscriber->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private function paginatedPayload($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
