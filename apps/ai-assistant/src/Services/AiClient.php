<?php

namespace PlatformApps\AiAssistant\Services;

use App\Platform\Services\SettingsManager;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for OpenAI-compatible chat APIs and Ollama.
 * Configured through the platform Settings capability (app settings).
 */
class AiClient
{
    public const PROVIDERS = ['openai', 'ollama'];

    public function __construct(protected SettingsManager $settings)
    {
    }

    public function provider(): string
    {
        return $this->settings->get('provider', 'openai', 'ai-assistant');
    }

    public function model(): string
    {
        return $this->settings->get('model', 'gpt-4o-mini', 'ai-assistant');
    }

    public function baseUrl(): string
    {
        $default = $this->provider() === 'ollama' ? 'http://localhost:11434' : 'https://api.openai.com/v1';

        return rtrim((string) $this->settings->get('base_url', $default, 'ai-assistant'), '/');
    }

    public function isConfigured(): bool
    {
        if ($this->provider() === 'ollama') {
            return true; // local, no key needed
        }

        return (bool) $this->settings->get('api_key', null, 'ai-assistant');
    }

    /**
     * Send a chat history to the provider and return the assistant reply.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return string assistant reply text
     *
     * @throws \RuntimeException on transport or API errors
     */
    public function chat(array $messages, ?string $model = null): string
    {
        $model ??= $this->model();
        $provider = $this->provider();

        $url = $provider === 'ollama'
            ? $this->baseUrl().'/v1/chat/completions'
            : $this->baseUrl().'/chat/completions';

        $request = Http::timeout(60)->acceptJson();

        if ($provider !== 'ollama') {
            $request = $request->withToken((string) $this->settings->get('api_key', '', 'ai-assistant'));
        }

        $response = $request->post($url, [
            'model' => $model,
            'messages' => $messages,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'AI provider error ('.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new \RuntimeException('AI provider returned an empty response.');
        }

        return $content;
    }
}
