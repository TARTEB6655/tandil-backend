<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpSupportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Help Center – same data as API GET /api/support/help-center (heading, tagline, get_support, contact_info, faqs).
     */
    public function index()
    {
        $contactPhone = Setting::get('contact_phone', '+1 (234) 567-8900');
        $contactEmail = Setting::get('contact_email', 'support@tandil.com');
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
            'support_hours' => $supportHours,
        ];

        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $heading = 'How can we help you?';
        $tagline = 'Find answers to common questions or get in touch with our support team';

        return view('client.help-support.index', compact('heading', 'tagline', 'getSupport', 'contactInfo', 'faqs'));
    }

    /**
     * Submit ticket – same as API POST /api/support/tickets.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);
        SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);
        return redirect()->route('client.help-support.index')->with('success', 'Support ticket submitted. We will get back to you soon.');
    }
}
