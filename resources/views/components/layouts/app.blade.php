<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'AI Recruitment Agent' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body>
    @php
        $isOpenAiConfigured = !app(\App\Services\OpenAIService::class)->isMockMode();
        $isQuotaExceeded = \Illuminate\Support\Facades\Cache::get('openai_quota_exceeded', false);
    @endphp

    <header>
        <div class="app-container header-content">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="logo-text">Recruit<span>Agent</span></div>
            </a>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                @if(!$isOpenAiConfigured)
                    <span class="badge badge-warning" style="font-size: 0.8rem; text-transform: none; letter-spacing: normal;">
                        ⚠️ Running in Mock AI Mode
                    </span>
                @elseif($isQuotaExceeded)
                    <span class="badge badge-danger" style="font-size: 0.8rem; text-transform: none; letter-spacing: normal;">
                        ⚠️ OpenAI Quota Exceeded (Mock Mode)
                    </span>
                @else
                    <span class="badge badge-success" style="font-size: 0.8rem; text-transform: none; letter-spacing: normal;">
                        ⚡ OpenAI Connected
                    </span>
                @endif

                <div class="badge-worker">
                    <div class="dot-pulse"></div>
                    <span>Queue Worker Active</span>
                </div>
            </div>
        </div>
    </header>

    <main class="app-container">
        @if(!$isOpenAiConfigured)
            <div class="alert alert-warning" style="margin-bottom: 2rem;">
                <strong>Notice:</strong> The <code>OPENAI_API_KEY</code> is not set in your <code>.env</code> file. The recruitment agents are running in <strong>Mock AI Fallback Mode</strong> using keyword analysis and regex parsers. Insert a valid API key in your <code>.env</code> file to enable full GPT analysis, semantic embeddings, and automated interview question generation.
            </div>
        @elseif($isQuotaExceeded)
            <div class="alert alert-danger" style="margin-bottom: 2rem;">
                <strong>Billing/Quota Limit Reached:</strong> Your OpenAI API key is configured, but the API returned an <code>insufficient_quota</code> error (billing limit exceeded). The recruitment agents are temporarily running in <strong>Mock AI Fallback Mode</strong>. Please check your OpenAI billing plan and credits.
            </div>
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
