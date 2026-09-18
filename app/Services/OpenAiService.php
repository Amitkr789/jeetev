<?php

namespace App\Services;

use App\Models\ChatbotSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiService
{
    protected string $apiKey;
    protected string $model;
    protected string $embeddingModel;

    public function __construct(?ChatbotSetting $settings = null)
    {
        $settings = $settings ?? ChatbotSetting::current();

        $this->apiKey = $settings->openai_api_key ?: (string) config('services.openai.key');
        $this->model = $settings->openai_model ?: 'gpt-4o-mini';
        $this->embeddingModel = $settings->openai_embedding_model ?: 'text-embedding-3-small';

        if (! $this->apiKey) {
            throw new RuntimeException('OpenAI API key is not configured. Set it in Chatbot Settings or OPENAI_API_KEY in .env.');
        }
    }

    /**
     * Returns the embedding vector for a piece of text.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->embeddingModel,
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding request failed: ' . $response->body());
        }

        return $response->json('data.0.embedding') ?? [];
    }

    /**
     * Sends a chat completion request and forces strict JSON back, so the
     * caller never has to parse free-form text out of the model's reply.
     *
     * PHASE 2 note: the JSON schema this returns is now intentionally
     * minimal (answer/language[/answerable]) — sufficiency and confidence
     * decisions live in ConfidenceService + ChatbotReplyService, not here.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<string, mixed>
     */
    public function chatJson(array $messages): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chat request failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            Log::warning('OpenAI returned non-JSON content', ['content' => $content]);
            return [];
        }

        return $decoded;
    }
}
