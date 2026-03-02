<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SupportTicketReplyAttachment;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketWebController extends Controller
{
    public function index(Request $request)
    {
        $q = SupportTicket::query()->with('user:id,name,email,phone');

        if ($request->filled('status')) {
            $q->where('status', (string) $request->get('status'));
        }
        if ($request->filled('priority')) {
            $q->where('priority', (string) $request->get('priority'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $q->where(function ($w) use ($search) {
                $w->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $tickets = $q->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function show(int $id)
    {
        $ticket = SupportTicket::with([
            'user:id,name,email,phone',
            'replies.user:id,name,email,phone',
            'replies.attachments',
        ])->findOrFail($id);

        return view('admin.support-tickets.show', compact('ticket'));
    }

    public function reply(Request $request, int $id)
    {
        $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480', // 20MB per file
            'voice' => 'nullable|file|mimes:mp3,wav,ogg,m4a,webm|max:10240', // 10MB voice
        ]);

        $ticket = SupportTicket::with('user')->findOrFail($id);
        $message = trim((string) $request->input('message', ''));
        $hasFiles = $request->hasFile('attachments') && count($request->file('attachments')) > 0;
        $hasVoice = $request->hasFile('voice');

        if ($message === '' && ! $hasFiles && ! $hasVoice) {
            return back()->withErrors(['message' => 'Please enter a message or attach a file/voice.'])->withInput();
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $message ?: '(Attachment)',
            'is_admin' => true,
        ]);

        $basePath = 'support-ticket-attachments/'.$reply->id;

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store($basePath, 'local');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'file');
                SupportTicketReplyAttachment::create([
                    'support_ticket_reply_id' => $reply->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'type' => $type,
                    'size' => $file->getSize(),
                ]);
            }
        }

        if ($request->hasFile('voice')) {
            $voice = $request->file('voice');
            $path = $voice->store($basePath, 'local');
            SupportTicketReplyAttachment::create([
                'support_ticket_reply_id' => $reply->id,
                'path' => $path,
                'original_name' => $voice->getClientOriginalName(),
                'mime_type' => $voice->getMimeType(),
                'type' => 'voice',
                'size' => $voice->getSize(),
            ]);
        }

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        if ($ticket->user) {
            $ticket->user->notify(new AdminNotification(
                'Support Ticket Reply',
                "Admin replied to your ticket {$ticket->ticket_number}.",
                [
                    'entity' => 'support_ticket',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'action' => 'open_ticket_reply',
                ]
            ));
        }

        return redirect()->route('admin.support-tickets.show', $ticket->id)
            ->with('success', 'Reply sent successfully.');
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        return back()->with('success', 'Support ticket status updated.');
    }

    public function destroy(int $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->delete();

        return redirect()->route('admin.support-tickets.index')
            ->with('success', 'Support ticket deleted successfully.');
    }

    /**
     * Download or display a reply attachment (admin only). Images inline for display, others as download.
     */
    public function downloadAttachment(int $attachmentId): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $attachment = SupportTicketReplyAttachment::findOrFail($attachmentId);
        $path = Storage::disk('local')->path($attachment->path);
        if (! is_file($path)) {
            return redirect()->back()->with('error', 'File not found.');
        }
        $mime = $attachment->mime_type ?? 'application/octet-stream';
        $disposition = ($attachment->type === 'image' || str_starts_with($mime, 'image/')) ? 'inline' : 'attachment';
        return response()->streamDownload(function () use ($path) {
            echo file_get_contents($path);
        }, $attachment->original_name, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }
}

