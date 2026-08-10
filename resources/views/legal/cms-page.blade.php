@extends('layouts.legal')

@section('title', $payload['title'] ?? 'CMS Page')

@section('content')
    <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 d-none">
        Preview for <strong>{{ ucfirst($audience) }} App</strong> · locale <strong>{{ strtoupper($locale) }}</strong>
    </div>

    @if($page->isPrivacyPage())
        <article class="legal-prose space-y-6">
            <header>
                <h1>{{ $payload['title'] ?? '' }}</h1>
                @if(!empty($payload['subtitle']))
                    <p class="text-slate-600 mt-2">{{ $payload['subtitle'] }}</p>
                @endif
            </header>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                {!! $payload['body'] ?? '' !!}
            </div>
        </article>
    @elseif($page->isTermsPage())
        <article class="legal-prose space-y-6">
            <header>
                <h1>{{ $payload['title'] ?? '' }}</h1>
                @if(!empty($payload['effective_date']))
                    <p class="text-slate-600 mt-2">Effective Date: {{ $payload['effective_date'] }}</p>
                @endif
            </header>
            @if(!empty($payload['intro']))
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    {!! $payload['intro'] !!}
                </div>
            @endif
            @foreach($payload['sections'] ?? [] as $section)
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-white text-sm font-semibold">{{ $section['number'] ?? '' }}</span>
                        <div class="flex-1">
                            @if(!empty($section['title']))
                                <h2 class="text-lg font-semibold text-slate-900">{{ $section['title'] }}</h2>
                            @endif
                            <div class="mt-3 prose max-w-none">{!! $section['body'] ?? '' !!}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </article>
    @else
        <article class="space-y-6">
            <header>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $payload['title'] ?? '' }}</h1>
                @if(!empty($payload['subtitle']))
                    <p class="text-slate-600 mt-2">{{ $payload['subtitle'] }}</p>
                @endif
            </header>

            <div class="rounded-2xl bg-emerald-800 text-white p-6">
                <h2 class="text-xl font-semibold">{{ $payload['hero']['title'] ?? '' }}</h2>
                @if(!empty($payload['hero']['description']))
                    <p class="mt-3 text-emerald-100">{{ $payload['hero']['description'] }}</p>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-emerald-700 text-white flex items-center justify-center text-xl font-bold">T</div>
                <div>
                    <p class="font-semibold text-slate-900">{{ $payload['company']['name'] ?? 'Tandil' }}</p>
                    <p class="text-sm text-slate-600">{{ $payload['company']['location'] ?? '' }}</p>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-xs font-semibold tracking-wide text-slate-500">REACH US</p>
                @foreach($payload['reach_us'] ?? [] as $item)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold tracking-wide text-slate-500">{{ $item['label'] ?? strtoupper($item['type'] ?? '') }}</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $item['value'] ?? '' }}</p>
                        @if(!empty($item['subtitle']))
                            <p class="text-sm text-slate-600 mt-1">{{ $item['subtitle'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(!empty($payload['response_notice']))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                    {{ $payload['response_notice'] }}
                </div>
            @endif
        </article>
    @endif
@endsection
