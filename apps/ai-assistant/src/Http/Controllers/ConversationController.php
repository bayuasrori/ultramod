<?php

namespace PlatformApps\AiAssistant\Http\Controllers;

use App\Platform\Services\AuditLogger;
use Illuminate\Routing\Controller;
use PlatformApps\AiAssistant\Http\Requests\StoreConversationRequest;
use PlatformApps\AiAssistant\Models\AiConversation;
use PlatformApps\AiAssistant\Services\AiClient;

class ConversationController extends Controller
{
    public function index()
    {
        return view('ai-assistant::conversations.index', [
            'conversations' => AiConversation::with('lastMessage')
                ->where('created_by', auth()->id())
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create()
    {
        return view('ai-assistant::conversations.chat', [
            'conversation' => new AiConversation(),
            'messages' => collect(),
        ]);
    }

    public function store(StoreConversationRequest $request)
    {
        $conversation = AiConversation::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('ai-assistant.show', $conversation);
    }

    public function show(AiConversation $conversation)
    {
        return view('ai-assistant::conversations.chat', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get(),
        ]);
    }

    public function destroy(AiConversation $conversation, AuditLogger $audit)
    {
        $audit->log('ai.conversation.deleted', metadata: ['title' => $conversation->title]);
        $conversation->delete();

        return redirect()->route('ai-assistant.index');
    }
}
