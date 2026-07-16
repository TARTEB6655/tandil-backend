<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Faq;
use App\Models\SupportTicketReply;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages
    ) {}

    /**
     * GET /api/support/help-center - Full Help Center payload for the app screen.
     * Returns: heading, tagline, get_support options, contact_info (phone, email, address, support_hours),
     * submit_ticket (endpoint, method, fields), social_links, faqs. Auth required.
     */
    public function helpCenter(Request $request)
    {
        $locale = $request->query('lang');
        $contact = $this->cmsPages->contactForHelpCenter(is_string($locale) ? $locale : null);
        $contactPhone = $contact['phone'];
        $contactEmail = $contact['email'];
        $contactAddress = $contact['address'] ?? '';
        $supportHours = $contact['support_hours'];
        $whatsapp = $contact['whatsapp'] ?? null;

        $getSupport = [
            ['type' => 'call', 'title' => 'Call Support', 'subtitle' => 'Speak with our team', 'value' => $contactPhone],
            ['type' => 'email', 'title' => 'Email Support', 'subtitle' => 'Send us an email', 'value' => $contactEmail],
        ];
        if ($whatsapp) {
            $getSupport[] = ['type' => 'whatsapp', 'title' => 'WhatsApp', 'subtitle' => 'Chat on WhatsApp', 'value' => $whatsapp];
        }
        $getSupport[] = ['type' => 'live_chat', 'title' => 'Live Chat', 'subtitle' => 'Chat with us now', 'value' => null];
        $getSupport[] = ['type' => 'submit_ticket', 'title' => 'Submit Ticket', 'subtitle' => 'Create a support ticket', 'value' => null];

        $contactInfo = [
            'phone' => $contactPhone,
            'whatsapp' => $whatsapp,
            'email' => $contactEmail,
            'address' => $contactAddress ?: null,
            'support_hours' => $supportHours,
            'service_areas' => $contact['service_areas'] ?? null,
        ];

        $submitTicket = [
            'endpoint' => '/api/support/tickets',
            'method' => 'POST',
            'fields' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'placeholder' => 'Brief subject of your request'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => 'Enter your email address'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Describe your issue in detail'],
            ],
        ];

        $socialLinks = array_filter([
            'facebook' => Setting::get('facebook_url'),
            'twitter' => Setting::get('twitter_url'),
            'instagram' => Setting::get('instagram_url'),
            'linkedin' => Setting::get('linkedin_url'),
            'youtube' => Setting::get('youtube_url'),
        ]);

        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer', 'sort_order']);

        return response()->json([
            'success' => true,
            'data' => [
                'heading' => 'How can we help you?',
                'tagline' => 'Find answers to common questions or get in touch with our support team',
                'get_support' => $getSupport,
                'contact_info' => $contactInfo,
                'submit_ticket' => $submitTicket,
                'social_links' => $socialLinks,
                'faqs' => $faqs,
            ],
        ]);
    }

    /**
     * GET /api/support/faqs - FAQ content for Help & Support (all roles).
     */
    public function faqs(Request $request)
    {
        $items = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer', 'sort_order']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * POST /api/support/tickets - Submit support ticket.
     * Body: subject (required), email (required), description (required).
     * Backward compatible body keys: message -> description.
     */
    public function storeTicket(Request $request)
    {
        // Keep backward compatibility with older clients still sending "message".
        $description = $request->input('description', $request->input('message'));
        if ($description !== null) {
            $request->merge(['description' => $description]);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'description' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->input('subject'),
            'email' => $request->input('email'),
            'message' => $request->input('description'),
            'status' => 'open',
            'priority' => 'medium',
            'category' => 'general',
        ]);

        // Notify all admins about newly submitted support ticket.
        // Keep ticket creation successful even if notification delivery fails.
        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Support Ticket',
                    "A client submitted ticket {$ticket->ticket_number} from {$ticket->email}: {$ticket->subject}",
                    [
                        'entity' => 'support_ticket',
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'email' => $ticket->email,
                        'subject' => $ticket->subject,
                        'description' => $ticket->message,
                        'action' => 'open_ticket',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Support ticket admin notification failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket submitted successfully.',
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'email' => $ticket->email,
                'description' => $ticket->message,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/support/tickets - List my support tickets (chat threads with admin).
     * For authenticated user (any role). Query: status (open|in_progress|resolved|closed), per_page.
     */
    public function indexMyTickets(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::where('user_id', $user->id)->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $tickets = $query->paginate($perPage);

        $items = $tickets->getCollection()->map(fn (SupportTicket $t) => [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'status' => $t->status,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ]);

        return ApiResponse::success('Support tickets retrieved successfully.', [
            'data' => $items->values()->all(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * GET /api/support/tickets/{id} - Get my ticket with full chat (replies).
     * Only allowed for the ticket owner. Returns ticket + replies as chat thread.
     */
    public function showMyTicket(Request $request, int $id)
    {
        $ticket = SupportTicket::with(['replies.user:id,name,email'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (! $ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $replies = $ticket->replies->map(fn ($r) => [
            'id' => $r->id,
            'message' => $r->message,
            'is_admin' => $r->is_admin,
            'user_name' => $r->user?->name,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->values()->all();

        return ApiResponse::success('Ticket retrieved successfully.', [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'replies' => $replies,
        ]);
    }

    /**
     * POST /api/support/tickets/{id}/reply - Send a message (reply) on my ticket (chat with admin).
     * Only ticket owner. Body: message (required). Not allowed when status is resolved or closed.
     */
    public function replyToMyTicket(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $ticket = SupportTicket::where('user_id', $request->user()->id)->find($id);
        if (! $ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return ApiResponse::error('Cannot reply: ticket is already ' . $ticket->status . '.', 422);
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
            'is_admin' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        // Notify all admins so they get every user message in the ticket.
        try {
            $admins = User::whereRaw('LOWER(role) = ?', ['admin'])
                ->orWhereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['admin']))
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'User Replied on Support Ticket',
                    $request->user()->name . ' replied on ticket ' . $ticket->ticket_number . '.',
                    [
                        'entity' => 'support_ticket',
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'action' => 'open_ticket_reply',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Support ticket user-reply admin notification failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }

        return ApiResponse::success('Message sent successfully.', [
            'id' => $reply->id,
            'message' => $reply->message,
            'is_admin' => false,
            'created_at' => $reply->created_at?->toIso8601String(),
        ], 201);
    }
}
