<?php

namespace App\Http\Controllers;

use App\Events\ConversationListUpdated;
use App\Events\NewChatMessage;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use App\Notifications\ChatConversationNotification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ====================================================================
       PAGE + LIST
       ==================================================================== */

    public function index()
    {
        return view('pages.chat', [
            'employees' => Admin::orderBy('name')->get(['id', 'name', 'role']),
            'pusherKey' => config('broadcasting.connections.pusher.key'),
            'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
        ]);
    }

    public function data()
    {
        $conversations = ChatConversation::with(['handlingAdmin:id,name', 'assignedAdmin:id,name', 'lockedByAdmin:id,name'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (ChatConversation $c) => $this->transform($c));

        return response()->json(['success' => true, 'conversations' => $conversations]);
    }

    public function show($id)
    {
       $conversation = ChatConversation::with([
    'handlingAdmin:id,name', 'assignedAdmin:id,name', 'lockedByAdmin:id,name', 'lead:id,customer_name',
    'messages.senderAdmin:id,name', 'notes.admin:id,name',
])->findOrFail($id);

        // Opening a conversation clears its unread badge for the admin viewing it.
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return response()->json([
            'success' => true,
            'conversation' => $this->transform($conversation, true),
        ]);
    }

    /* ====================================================================
       MESSAGING
       ==================================================================== */

    public function send(Request $request, $id, WhatsAppService $whatsapp)
    {
        $validator = Validator::make($request->all(), ['body' => 'required|string|max:4096']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = ChatConversation::findOrFail($id);
        $admin = $this->currentAdmin();

        $waMessageId = $whatsapp->sendText($conversation->wa_phone_number, $request->body);

        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'sender_type' => 'admin',
            'sender_admin_id' => $admin->id,
            'type' => 'text',
            'body' => $request->body,
            'wa_message_id' => $waMessageId,
            'wa_status' => $waMessageId ? 'sent' : 'failed',
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($request->body, 80),
        ])->save();

        broadcast(new NewChatMessage($message))->toOthers();
        event(new ConversationListUpdated($conversation->fresh()));

        return response()->json(['success' => true, 'message' => $this->transformMessage($message)]);
    }

    /* ====================================================================
       HUMAN TAKEOVER WORKFLOW
       ==================================================================== */

    public function takeover($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $admin = $this->currentAdmin();

        if ($conversation->is_locked && $conversation->locked_by_admin_id !== $admin->id) {
            return response()->json([
                'success' => false,
                'message' => "This chat is currently locked by {$conversation->lockedByAdmin?->name}.",
            ], 409);
        }

        $conversation->forceFill([
            'ai_active' => false,
            'handling_admin_id' => $admin->id,
            'locked_by_admin_id' => $admin->id,
            'locked_at' => now(),
        ])->save();

        $this->logSystemMessage($conversation, "{$admin->name} took over this chat from the AI.");

        return $this->workflowResponse($conversation, 'You are now handling this chat.');
    }

    public function resumeAi($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $admin = $this->currentAdmin();

        $conversation->forceFill([
            'ai_active' => true,
            'handling_admin_id' => null,
            'escalated_at' => null,
            'locked_by_admin_id' => null,
            'locked_at' => null,
        ])->save();

        $this->logSystemMessage($conversation, "{$admin->name} resumed the AI for this chat.");

        return $this->workflowResponse($conversation, 'AI resumed.');
    }

    public function transfer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['admin_id' => 'required|exists:admins,id']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = ChatConversation::findOrFail($id);
        $from = $this->currentAdmin();
        $to = Admin::findOrFail($request->admin_id);

        $conversation->forceFill([
            'ai_active' => false,
            'handling_admin_id' => $to->id,
            'assigned_admin_id' => $to->id,
            'locked_by_admin_id' => $to->id,
            'locked_at' => now(),
        ])->save();

        $this->logSystemMessage($conversation, "{$from->name} transferred this chat to {$to->name}.");
        Notification::send($to, new ChatConversationNotification($conversation, 'transferred'));

        return $this->workflowResponse($conversation, "Transferred to {$to->name}.");
    }

    public function assign(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['admin_id' => 'nullable|exists:admins,id']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = ChatConversation::findOrFail($id);
        $conversation->update(['assigned_admin_id' => $request->admin_id]);

        if ($request->admin_id) {
            $to = Admin::findOrFail($request->admin_id);
            $this->logSystemMessage($conversation, "Assigned to {$to->name}.");
            Notification::send($to, new ChatConversationNotification($conversation, 'assigned'));
        } else {
            $this->logSystemMessage($conversation, 'Unassigned.');
        }

        return $this->workflowResponse($conversation, 'Assignment updated.');
    }

    public function lock($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $admin = $this->currentAdmin();

        if ($conversation->is_locked && $conversation->locked_by_admin_id !== $admin->id) {
            return response()->json([
                'success' => false,
                'message' => "Already locked by {$conversation->lockedByAdmin?->name}.",
            ], 409);
        }

        $conversation->update(['locked_by_admin_id' => $admin->id, 'locked_at' => now()]);

        return $this->workflowResponse($conversation, 'Chat locked to you.');
    }

    public function unlock($id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $admin = $this->currentAdmin();

        // Anyone can force-unlock a super_admin aside; regular admins can
        // only unlock their own lock. Adjust to taste.
        if ($conversation->locked_by_admin_id !== $admin->id && $admin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Only a super admin can force-unlock another agent\'s chat.'], 403);
        }

        $conversation->update(['locked_by_admin_id' => null, 'locked_at' => null]);

        return $this->workflowResponse($conversation, 'Chat unlocked.');
    }

    /** Quick per-conversation AI mute/unmute — distinct from full takeover (no lock, no ownership change). */
    public function aiToggle(Request $request, $id)
    {
        $conversation = ChatConversation::findOrFail($id);
        $enable = $request->boolean('ai_active');

        $conversation->forceFill([
            'ai_active' => $enable,
            'escalated_at' => $enable ? null : $conversation->escalated_at,
        ])->save();

        $this->logSystemMessage($conversation, $enable ? 'AI turned ON for this chat.' : 'AI turned OFF for this chat.');

        return $this->workflowResponse($conversation, $enable ? 'AI is now ON.' : 'AI is now OFF.');
    }

    /* ====================================================================
       NOTES
       ==================================================================== */

    public function storeNote(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['note' => 'required|string|max:2000']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversation = ChatConversation::findOrFail($id);
        $note = $conversation->notes()->create([
            'admin_id' => $this->currentAdmin()->id,
            'note' => $request->note,
        ]);
        $note->load('admin:id,name');

        return response()->json(['success' => true, 'message' => 'Note added.', 'note' => [
            'id' => $note->id,
            'note' => $note->note,
            'admin_name' => $note->admin->name,
            'created_at' => $note->created_at->toDateTimeString(),
        ]]);
    }

    /* ====================================================================
       LEAD CONVERSION (manual only)
       ==================================================================== */

    public function convertToLead($id)
    {
        $conversation = ChatConversation::with('lead')->findOrFail($id);

        if ($conversation->lead_id) {
            return response()->json(['success' => false, 'message' => 'Already linked to a lead.'], 422);
        }

        // NOTE: adjust these fields to match your actual Leads table —
        // this only fills columns that exist so it won't fail on a schema
        // mismatch, but you should still confirm required/NOT NULL columns
        // on your Lead model are covered here.
        $payload = array_filter([
            'name' => Schema::hasColumn('leads', 'name') ? ($conversation->contact_name ?: $conversation->wa_phone_number) : null,
            'phone' => Schema::hasColumn('leads', 'phone') ? $conversation->wa_phone_number : null,
            'source' => Schema::hasColumn('leads', 'source') ? 'whatsapp_chatbot' : null,
        ], fn ($v) => ! is_null($v));

        $lead = Lead::create($payload);

        $conversation->update(['lead_id' => $lead->id]);
        $this->logSystemMessage($conversation, "Converted to Lead #{$lead->id} by {$this->currentAdmin()->name}.");

        return response()->json(['success' => true, 'message' => 'Converted to lead.', 'lead_id' => $lead->id]);
    }

    /* ====================================================================
       EMPLOYEES (for assign / transfer dropdowns)
       ==================================================================== */

public function employees()
{
    // NOTE: no status filter here on purpose — a status='active' filter
    // was silently returning zero rows because the admins table doesn't
    // reliably have that exact value set. Add a filter back in only
    // once you've confirmed what your real status values look like.
    $employees = Admin::orderBy('name')->get(['id', 'name', 'role']);

    return response()->json(['success' => true, 'employees' => $employees]);
}

    /* ====================================================================
       Internals
       ==================================================================== */

    private function logSystemMessage(ChatConversation $conversation, string $text): void
    {
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'sender_type' => 'system',
            'type' => 'system',
            'body' => $text,
        ]);

        broadcast(new NewChatMessage($message))->toOthers();
    }

    private function workflowResponse(ChatConversation $conversation, string $message)
    {
        $conversation->refresh()->load(['handlingAdmin:id,name', 'assignedAdmin:id,name', 'lockedByAdmin:id,name']);
        event(new ConversationListUpdated($conversation));

        return response()->json([
            'success' => true,
            'message' => $message,
            'conversation' => $this->transform($conversation, true),
        ]);
    }

    private function transformMessage(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'sender_type' => $m->sender_type,
            'sender_admin_name' => $m->senderAdmin->name ?? null,
            'type' => $m->type,
            'body' => $m->body,
            'media_url' => $m->media_url,
            'ai_confidence' => $m->ai_confidence,
            'kb_article_ids' => $m->kb_article_ids,
            'language' => $m->language,
            'wa_status' => $m->wa_status,
            'created_at' => optional($m->created_at)->toDateTimeString(),
        ];
    }

    private function transform(ChatConversation $c, bool $detailed = false): array
    {
        $data = [
            'id' => $c->id,
            'wa_phone_number' => $c->wa_phone_number,
            'contact_name' => $c->contact_name,
            'language' => $c->language,
            'display_status' => $c->display_status,
            'ai_active' => $c->ai_active,
            'is_locked' => $c->is_locked,
            'locked_by_admin_name' => $c->lockedByAdmin->name ?? null,
            'handling_admin_id' => $c->handling_admin_id,
            'handling_admin_name' => $c->handlingAdmin->name ?? null,
            'assigned_admin_id' => $c->assigned_admin_id,
            'assigned_admin_name' => $c->assignedAdmin->name ?? null,
            'lead_id' => $c->lead_id,
            'escalated_at' => optional($c->escalated_at)->toDateTimeString(),
            'last_message_at' => optional($c->last_message_at)->toDateTimeString(),
            'last_message_preview' => $c->last_message_preview,
            'unread_count' => $c->unread_count,
        ];

        if ($detailed) {
            $data['messages'] = $c->messages->map(fn (ChatMessage $m) => $this->transformMessage($m));
            $data['notes'] = $c->notes->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'admin_name' => $n->admin->name ?? null,
                'created_at' => optional($n->created_at)->toDateTimeString(),
            ]);
        }

        return $data;
    }public function sendMedia(Request $request, $id, WhatsAppService $whatsapp)
{
    $validator = Validator::make($request->all(), [
        'file' => 'required|file|max:16384', // 16MB — WhatsApp's own per-type limits vary (image 5MB, doc 100MB, audio/video 16MB); tighten if needed
        'caption' => 'nullable|string|max:1024',
    ]);
    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    $conversation = ChatConversation::findOrFail($id);
    $admin = $this->currentAdmin();
    $file = $request->file('file');
    $mime = $file->getMimeType();
    $waType = $this->resolveWaMediaType($mime);

    $path = $file->store("chat-media/{$conversation->id}", 'public');
    $fullPath = storage_path('app/public/' . $path);
    $publicUrl = asset('storage/' . $path); // used only for your own admin UI preview

    $waMessageId = $whatsapp->sendMedia($conversation->wa_phone_number, $waType, $fullPath, $mime, $request->caption);

    $message = $conversation->messages()->create([
        'direction' => 'outbound',
        'sender_type' => 'admin',
        'sender_admin_id' => $admin->id,
        'type' => $waType,
        'body' => $request->caption,
        'media_url' => $publicUrl,
        'media_mime_type' => $mime,
        'media_filename' => $file->getClientOriginalName(),
        'media_size' => $file->getSize(),
        'wa_message_id' => $waMessageId,
        'wa_status' => $waMessageId ? 'sent' : 'failed',
    ]);

    $conversation->forceFill([
        'last_message_at' => now(),
        'last_message_preview' => $this->previewForType($waType, $request->caption),
    ])->save();

    broadcast(new NewChatMessage($message))->toOthers();
    event(new ConversationListUpdated($conversation->fresh()));

    return response()->json(['success' => true, 'message' => $this->transformMessage($message)]);
}

private function resolveWaMediaType(string $mime): string
{
    if (str_starts_with($mime, 'image/')) return 'image';
    if (str_starts_with($mime, 'audio/')) return 'audio';
    if (str_starts_with($mime, 'video/')) return 'video';
    return 'document';
}

private function previewForType(string $type, ?string $caption): string
{
    if ($caption) return Str::limit($caption, 60);
    return ['image' => '📷 Photo', 'audio' => '🎤 Audio', 'video' => '🎬 Video', 'document' => '📄 Document'][$type] ?? 'Attachment';
}
}
