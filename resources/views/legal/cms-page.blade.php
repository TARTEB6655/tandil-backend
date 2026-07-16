@extends('layouts.legal')

@section('title', $title)

@section('content')
    <article class="legal-prose">
        <h1>{{ $title }}</h1>
        <div class="mt-6">
            {!! $body !!}
        </div>

        @if(!empty($contactDetails))
            <div class="mt-10 border-t border-slate-200 pt-6">
                <h2>Contact details</h2>
                <ul class="mt-4 space-y-2">
                    @if(!empty($contactDetails['phone']))
                        <li><strong>Phone:</strong> {{ $contactDetails['phone'] }}</li>
                    @endif
                    @if(!empty($contactDetails['whatsapp']))
                        <li><strong>WhatsApp:</strong> {{ $contactDetails['whatsapp'] }}</li>
                    @endif
                    @if(!empty($contactDetails['email']))
                        <li><strong>Email:</strong> {{ $contactDetails['email'] }}</li>
                    @endif
                    @if(!empty($contactDetails['working_hours'][$locale] ?? $contactDetails['working_hours']['en'] ?? null))
                        <li><strong>Working hours:</strong> {{ $contactDetails['working_hours'][$locale] ?? $contactDetails['working_hours']['en'] }}</li>
                    @endif
                    @if(!empty($contactDetails['service_areas'][$locale] ?? $contactDetails['service_areas']['en'] ?? null))
                        <li><strong>Service areas:</strong> {{ $contactDetails['service_areas'][$locale] ?? $contactDetails['service_areas']['en'] }}</li>
                    @endif
                </ul>
            </div>
        @endif
    </article>
@endsection
