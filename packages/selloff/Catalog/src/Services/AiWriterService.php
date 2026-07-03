<?php

namespace App\Modules\Selloff\Catalog\Services;

use Illuminate\Support\Facades\Http;

class AiWriterService
{
    /**
     * @param  array{topic: string, content_type?: string, tone?: string, length?: string, model?: string}  $input
     * @return array{text: string, source: string}
     */
    public function generate(array $input): array
    {
        $topic = trim((string) ($input['topic'] ?? ''));
        if ($topic === '') {
            return ['text' => '', 'source' => 'validation'];
        }

        $apiKey = (string) (config('services.openai.key') ?? env('OPENAI_API_KEY', ''));
        if ($apiKey !== '') {
            $remote = $this->generateWithOpenAi($apiKey, $input);
            if ($remote !== null) {
                return $remote;
            }
        }

        return $this->generateStub($input);
    }

    /**
     * @param  array{topic: string, content_type?: string, tone?: string, length?: string, model?: string}  $input
     */
    private function generateStub(array $input): array
    {
        $topic = (string) $input['topic'];
        $tone = (string) ($input['tone'] ?? 'professional');
        $contentType = (string) ($input['content_type'] ?? 'product');

        $intro = match ($contentType) {
            'blog' => "This article explores {$topic}.",
            'page' => "Welcome to our page about {$topic}.",
            default => "Discover {$topic} — a quality listing on Selloff.",
        };

        $body = match ($tone) {
            'casual' => "Whether you are browsing or ready to buy, {$topic} offers great value for everyday shoppers.",
            'persuasive' => "Don't miss out on {$topic}. Act now while availability lasts.",
            default => "{$topic} is presented with clear details to help buyers make a confident decision.",
        };

        return [
            'text' => "{$intro}\n\n{$body}",
            'source' => 'stub',
        ];
    }

    /**
     * @param  array{topic: string, content_type?: string, tone?: string, length?: string, model?: string}  $input
     * @return array{text: string, source: string}|null
     */
    private function generateWithOpenAi(string $apiKey, array $input): ?array
    {
        $contentType = match ($input['content_type'] ?? 'product') {
            'page' => 'page description',
            'blog' => 'blog article',
            default => 'product description',
        };

        $prompt = sprintf(
            'Write a %s about the topic: %s. It should be %s in length. Use a %s tone. Write it in English. Only return the plain text without any introductions, explanations, or formatting.',
            $contentType,
            $input['topic'],
            $input['length'] ?? 'medium',
            $input['tone'] ?? 'professional',
        );

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $input['model'] ?? 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $text = (string) data_get($response->json(), 'choices.0.message.content', '');
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return ['text' => $text, 'source' => 'openai'];
    }
}
