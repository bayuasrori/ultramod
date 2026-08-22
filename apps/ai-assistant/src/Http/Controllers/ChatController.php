<?php

namespace PlatformApps\AiAssistant\Http\Controllers;

use App\Platform\Services\AuditLogger;
use App\Platform\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use PlatformApps\AiAssistant\Http\Requests\SendMessageRequest;
use PlatformApps\AiAssistant\Models\AiConversation;
use PlatformApps\AiAssistant\Models\AiMessage;
use PlatformApps\AiAssistant\Services\AiClient;

class ChatController extends Controller
{
    public function send(SendMessageRequest $request, AiConversation $conversation, AiClient $client)
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
        ]);

        try {
            $history = $conversation->messages()
                ->get()
                ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])
                ->all();

            $reply = $client->chat($history, $conversation->model);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply,
            ]);

            // Use the first user message as a lazy conversation title.
            if ($conversation->title === 'New conversation') {
                $conversation->update([
                    'title' => mb_substr($request->validated('content'), 0, 60),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI chat failed: '.$e->getMessage());

            return redirect()
                ->route('ai-assistant.show', $conversation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('ai-assistant.show', $conversation);
    }

    public function settings(SettingsManager $settings)
    {
        return view('ai-assistant::settings.form', [
            'values' => [
                'provider' => $settings->get('provider', 'openai', 'ai-assistant'),
                'base_url' => $settings->get('base_url', '', 'ai-assistant'),
                'api_key' => $settings->get('api_key', '', 'ai-assistant'),
                'model' => $settings->get('model', 'gpt-4o-mini', 'ai-assistant'),
            ],
        ]);
    }

    public function updateSettings(Request $request, SettingsManager $settings, AuditLogger $audit)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:'.implode(',', AiClient::PROVIDERS)],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, 'ai-assistant');
        }

        $audit->log('ai.settings.updated');

        return redirect()->route('ai-assistant.settings')->with('status', 'AI settings saved.');
    }
}
