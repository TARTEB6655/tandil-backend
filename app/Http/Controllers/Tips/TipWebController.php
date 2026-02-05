<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;

/**
 * Shared web controller for viewing published tips.
 * Used by client, technician, supervisor, area_manager, hr (each has own route prefix).
 */
class TipWebController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getLayoutComponent(): string
    {
        $role = auth()->user()->role ?? 'client';
        return match ($role) {
            'technician' => 'technician-layout',
            'supervisor' => 'supervisor-layout',
            'area_manager' => 'areamanager-layout',
            'hr' => 'hr-layout',
            default => 'client-layout',
        };
    }

    private function getRouteBase(): string
    {
        $name = request()->route()->getName();
        return preg_replace('/\.(index|show)$/', '', $name);
    }

    /**
     * List published tips (paginated).
     */
    public function index()
    {
        $tips = Tip::where('status', 'published')
            ->latest()
            ->paginate(10);

        return view('tips.index', [
            'tips' => $tips,
            'routeBase' => $this->getRouteBase(),
            'layoutComponent' => $this->getLayoutComponent(),
        ]);
    }

    /**
     * Show a single published tip.
     */
    public function show($id)
    {
        $tip = Tip::where('status', 'published')->findOrFail($id);

        return view('tips.show', [
            'tip' => $tip,
            'routeBase' => $this->getRouteBase(),
            'layoutComponent' => $this->getLayoutComponent(),
        ]);
    }
}
