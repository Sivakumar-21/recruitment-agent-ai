<div class="chat-layout">
    <!-- Chat Messages -->
    <div class="portal-body" id="chat-body" style="display: flex; flex-direction: column;">
        @foreach($messages as $msg)
            <div class="chat-bubble {{ $msg['sender'] === 'bot' ? 'bubble-bot' : 'bubble-user' }}">
                {!! nl2br(e($msg['text'])) !!}
            </div>
        @endforeach

        @if($currentStep === 'scheduling')
            <div style="margin-top: 0.5rem; width: 100%;">
                <h4 style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 700;">Select an Interview Slot:</h4>
                @foreach($availableSlots as $slot)
                    <button type="button" wire:click="selectSlot('{{ $slot['value'] }}')" class="slot-button">
                        📅 {{ $slot['label'] }}
                    </button>
                @endforeach
            </div>
        @endif

        @if($currentStep === 'confirmed' && $bookedInterview)
            <div class="confirmed-card">
                <div class="confirmed-icon">✓</div>
                <h4 style="text-align: center; color: #10b981; font-weight: 700; margin-bottom: 1rem; font-size: 1.1rem;">Interview Confirmed!</h4>
                
                <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                    <div>
                        <strong style="color: #94a3b8;">Job Posting:</strong>
                        <span style="color: #f8fafc; font-weight: 500;">{{ $job->title }}</span>
                    </div>
                    <div>
                        <strong style="color: #94a3b8;">Date & Time:</strong>
                        <span style="color: #f8fafc; font-weight: 500;">{{ $bookedInterview->scheduled_at->format('l, F d, Y \a\t h:i A') }}</span>
                    </div>
                    <div>
                        <strong style="color: #94a3b8;">Interviewer:</strong>
                        <span style="color: #f8fafc; font-weight: 500;">{{ $bookedInterview->interviewer_name }}</span>
                    </div>
                    <div style="margin-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.75rem;">
                        <strong style="color: #94a3b8; display: block; margin-bottom: 0.25rem;">Meeting Link:</strong>
                        <a href="{{ $bookedInterview->meeting_link }}" target="_blank" style="color: #06b6d4; text-decoration: underline; font-weight: 500; word-break: break-all;">
                            {{ $bookedInterview->meeting_link }}
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Chat Input Form -->
    @if($currentStep !== 'scheduling' && $currentStep !== 'confirmed')
        <form wire:submit.prevent="sendMessage" class="chat-input-area">
            <input type="text" wire:model.defer="userInput" class="form-control" placeholder="Type your answer here..." style="flex-grow: 1;" autofocus required>
            <button type="submit" class="btn btn-primary" style="padding: 0 1.25rem;">
                Send
            </button>
        </form>
    @else
        <div style="padding: 1.25rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); background: rgba(8, 12, 21, 0.4); border-top: 1px solid rgba(255, 255, 255, 0.08);">
            @if($currentStep === 'scheduling')
                Please select a time slot above to book your interview.
            @else
                Interview scheduled. You can close this window now. Thank you!
            @endif
        </div>
    @endif

    <script>
        // Auto scroll chat to bottom when message arrives
        document.addEventListener('livewire:initialized', () => {
            const chatBody = document.getElementById('chat-body');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
                
                Livewire.hook('message.processed', (message, component) => {
                    chatBody.scrollTop = chatBody.scrollHeight;
                });
            }
        });
    </script>
</div>
