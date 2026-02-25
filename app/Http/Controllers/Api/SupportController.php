<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    /**
     * GET /api/support/help-center - Full Help Center payload for the app screen.
     * Returns: heading, tagline, get_support options, contact_info (phone, email, address, support_hours),
     * submit_ticket (endpoint, method, fields), social_links, faqs. Auth required.
     */
    public function helpCenter(Request $request)
    {
        $contactPhone = Setting::get('contact_phone', '+1 (234) 567-8900');
        $contactEmail = Setting::get('contact_email', 'support@tandil.com');
        $contactAddress = Setting::get('contact_address', '');
        $supportHours = Setting::get('support_hours', '24/7 Customer Support');

        $getSupport = [
            ['type' => 'call', 'title' => 'Call Support', 'subtitle' => 'Speak with our team', 'value' => $contactPhone],
            ['type' => 'email', 'title' => 'Email Support', 'subtitle' => 'Send us an email', 'value' => $contactEmail],
            ['type' => 'live_chat', 'title' => 'Live Chat', 'subtitle' => 'Chat with us now', 'value' => null],
            ['type' => 'submit_ticket', 'title' => 'Submit Ticket', 'subtitle' => 'Create a support ticket', 'value' => null],
        ];

        $contactInfo = [
            'phone' => $contactPhone,
            'email' => $contactEmail,
            'address' => $contactAddress ?: null,
            'support_hours' => $supportHours,
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
}
