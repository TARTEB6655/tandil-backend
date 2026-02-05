<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * List published tips for the client dashboard.
     */
    public function index()
    {
        $tips = Tip::where('status', 'published')
            ->latest()
            ->paginate(15);

        return view('client.tips.index', compact('tips'));
    }

    /**
     * Show a single published tip.
     */
    public function show($id)
    {
        $tip = Tip::where('status', 'published')->findOrFail($id);

        return view('client.tips.show', compact('tip'));
    }
}
