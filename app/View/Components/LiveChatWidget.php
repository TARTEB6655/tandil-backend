<?php

namespace App\View\Components;

use App\Support\LiveChatWidgetConfig;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LiveChatWidget extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $config;

    public function __construct()
    {
        $this->config = LiveChatWidgetConfig::forUser(auth()->user());
    }

    public function shouldRender(): bool
    {
        return $this->config !== null;
    }

    public function render(): View|Closure|string
    {
        return view('components.live-chat-widget');
    }
}
