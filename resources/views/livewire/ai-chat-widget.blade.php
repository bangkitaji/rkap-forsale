<div class="position-fixed rkap-chat-container">
    <!-- Chat Button -->
    @if(!$isOpen)
    <button wire:click="toggleChat" class="btn btn-primary rounded-circle shadow-lg p-3 d-flex align-items-center justify-content-center rkap-chat-btn">
        <i class="bx bx-message-square-dots fs-3"></i>
    </button>
    @else

    <!-- Chat Window -->
    <div class="card shadow-lg border-0 rkap-chat-window">
        <!-- Header -->
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <div class="d-flex align-items-center">
                <i class="bx bx-bot fs-4 me-2"></i>
                <h6 class="mb-0 text-white">RKAP AI Assistant</h6>
            </div>
            <div>
                <button wire:click="clearChat" class="btn btn-sm btn-link text-white p-0 me-2" title="Clear Chat">
                    <i class="bx bx-trash"></i>
                </button>
                <button wire:click="toggleChat" class="btn btn-sm btn-link text-white p-0" title="Close">
                    <i class="bx bx-x fs-4"></i>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="card-body overflow-auto p-3 bg-lighter rkap-chat-messages" id="chat-messages-container">
            @if(count($messages) === 0)
            <div class="text-center text-muted mt-5">
                <i class="bx bx-chat fs-1 mb-2"></i>
                <p>Hello! I'm your RKAP AI Assistant.<br>How can I help you today?</p>
            </div>
            @endif

            @foreach($messages as $msg)
            <div class="mb-3 d-flex {{ $msg['role'] === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                <div class="p-2 rounded {{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-white border' }} rkap-chat-msg-bubble">
                    <small class="d-block mb-1 opacity-75 rkap-font-07">
                        {{ $msg['role'] === 'user' ? 'You' : 'AI Assistant' }}
                    </small>
                    <div class="message-content">
                        {!! nl2br(e($msg['content'])) !!}
                    </div>
                </div>
            </div>
            @endforeach

            <div wire:loading wire:target="sendMessage" class="mb-3 d-flex justify-content-start">
                <div class="p-2 rounded bg-white border">
                    <div class="spinner-grow spinner-grow-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="spinner-grow spinner-grow-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="spinner-grow spinner-grow-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="card-footer bg-white border-top p-2">
            <form wire:submit.prevent="sendMessage">
                <div class="input-group">
                    <input type="text" wire:model.defer="newMessage" class="form-control border-0 shadow-none" placeholder="Ask something..." autocomplete="off">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sendMessage">
                        <i class="bx bx-send"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', ({
                component,
                el
            }) => {
                let container = document.getElementById('chat-messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });
    </script>
    @endif
</div>