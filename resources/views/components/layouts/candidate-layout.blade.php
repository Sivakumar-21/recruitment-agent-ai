<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Application Portal</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        body {
            background-color: #060913;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.06) 0, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .portal-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(16px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .portal-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(8, 12, 21, 0.4);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .portal-logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .portal-logo span {
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .portal-logo-icon {
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .portal-body {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            max-height: 500px;
            min-height: 400px;
        }
        .chat-bubble {
            max-width: 85%;
            padding: 0.85rem 1.1rem;
            border-radius: 14px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            line-height: 1.5;
            white-space: pre-line;
        }
        .bubble-bot {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            align-self: flex-start;
            border-top-left-radius: 2px;
        }
        .bubble-user {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff;
            align-self: flex-end;
            border-top-right-radius: 2px;
            margin-left: auto;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }
        .chat-layout {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(8, 12, 21, 0.4);
            display: flex;
            gap: 0.75rem;
        }
        .slot-button {
            display: block;
            width: 100%;
            text-align: left;
            background: rgba(99, 102, 241, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 0.85rem 1.2rem;
            border-radius: 10px;
            color: #f8fafc;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }
        .slot-button:hover {
            background: rgba(99, 102, 241, 0.15);
            border-color: #6366f1;
            transform: translateY(-1px);
        }
        .slot-button:active {
            transform: translateY(0);
        }
        .confirmed-card {
            background: rgba(16, 185, 129, 0.04);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1rem;
            color: #e2e8f0;
        }
        .confirmed-icon {
            font-size: 2.2rem;
            color: #10b981;
            margin-bottom: 0.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="portal-card">
        <div class="portal-header">
            <div class="portal-logo">
                <div class="portal-logo-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div>Recruit<span>Portal</span></div>
            </div>
            <span class="badge badge-success" style="font-size: 0.75rem; letter-spacing: normal; text-transform: none;">Secure Session</span>
        </div>

        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
