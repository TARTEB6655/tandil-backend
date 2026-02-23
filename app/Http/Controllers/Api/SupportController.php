<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    /**
     * GET /api/support/help-center - Full Help Center payload for the app screen.
     * Returns: heading, tagline, get_support options, contact_info (phone, email, address, support_hours),
     * submit_ticket (endpoint, method, placeholders), social_links, faqs. Auth required.
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
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Describe your issue or question in detail'],
                ['key' => 'priority', 'label' => 'Priority', 'type' => 'select', 'required' => false, 'options' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'urgent', 'label' => 'Urgent'],
                ], 'default' => 'medium'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => false, 'options' => [
                    ['value' => 'general', 'label' => 'General'],
                    ['value' => 'billing', 'label' => 'Billing'],
                    ['value' => 'technical', 'label' => 'Technical'],
                    ['value' => 'account', 'label' => 'Account'],
                    ['value' => 'order', 'label' => 'Order'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
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
     * Body: subject (required), message (required), priority (optional: low|medium|high|urgent), category (optional).
     */
    public function storeTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'category' => 'nullable|string|in:general,billing,technical,account,order,other',
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
            'message' => $request->input('message'),
            'status' => 'open',
            'priority' => $request->input('priority', 'medium'),
            'category' => $request->input('category'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket submitted successfully.',
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
