/**
 * TD RENTALS — AI CHATBOT LOGIC
 * Handles UI interactions and AJAX communication with chat.php
 */

document.addEventListener('DOMContentLoaded', () => {
    const chatbotWidget    = document.querySelector('.chatbot-widget');
    const toggleBtn        = document.querySelector('.chatbot-toggle');
    const chatForm         = document.querySelector('.chat-input-form');
    const chatInput        = document.querySelector('.chat-input-form input');
    const messagesContainer = document.querySelector('.chat-messages');
    const typingIndicator  = document.querySelector('.typing-indicator');

    // 1. Toggle Chat Window
    toggleBtn.addEventListener('click', () => {
        chatbotWidget.classList.toggle('open');
        if (chatbotWidget.classList.contains('open')) {
            chatInput.focus();
        }
    });

    // 2. Handle Message Submission
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        chatInput.value = '';
        addMessage(message, 'user');
        showTyping(true);

        try {
            const response = await fetch('/vehicle_rental_collab_project/public/api/chat.php', {
                method: 'POST',
                credentials: 'include', // Send session cookie so PHP knows who is logged in
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message })
            });

            if (!response.ok) throw new Error('Network error');

            const data = await response.json();
            showTyping(false);
            addMessage(data.reply || "I'm sorry, I couldn't process that.", 'bot');

        } catch (error) {
            console.error('Chat Error:', error);
            showTyping(false);
            addMessage("I'm having trouble connecting. Please try again.", 'bot');
        }
    });

    // 3. Markdown → HTML renderer (handles bold, links, line breaks, vehicle cards)
    function parseMarkdown(text) {
        // Escape any raw HTML first to prevent XSS
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Bold: **text**
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Italic: *text*
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

        // Clean up accidental bullet points before car emojis
        html = html.replace(/-\s*🚗/g, '🚗');

        // Bulletproof fix for squished single-line vehicles (e.g. 🚗 Model | Price | Speed | View & Book → http...)
        // It captures the 3 segments split by pipes (|), then grabs the http link at the end.
        html = html.replace(/🚗\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|.*?(https?:\/\/[^\s<\)]+)/gi, 
            "🚗 **$1**\n💰 $2 | ⚡ $3\n👉 [View & Book →]($4)");

        // Fix raw links that are missing the markdown format but are meant to be buttons
        html = html.replace(/(?:👉\s*)?(?:View & Book →|View & Book)\s*(https?:\/\/[^\s<\)]+)/gi, 
            "👉 [View & Book →]($1)");

        // Markdown links: [text](url)
        html = html.replace(
            /\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g,
            '<a href="$2" target="_blank" class="chat-link">$1</a>'
        );

        // Auto-convert raw URLs to clickable links if they aren't already formatted as markdown links
        // We use a capture group for the preceding character to avoid matching inside existing href="..."
        html = html.replace(/(^|[^"'])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" class="chat-link">$2</a>');

        // Detect 🚗 vehicle card blocks (lines starting with 🚗)
        // Wrap consecutive card lines in a .vehicle-card div
        const lines = html.split('\n');
        const processedLines = [];
        let inCard = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            
            // Clean up unwanted bullet points prefixing the card
            if (line.match(/^-\s*🚗/)) {
                line = line.replace(/^-\s*/, '');
            }

            if (line.startsWith('🚗')) {
                if (!inCard) {
                    processedLines.push('<div class="chat-vehicle-card">');
                    inCard = true;
                } else {
                    processedLines.push('</div><div class="chat-vehicle-card">');
                }
                processedLines.push('<div class="card-title">' + line + '</div>');
            } else if (inCard && (line.startsWith('💰') || line.startsWith('⚡'))) {
                processedLines.push('<div class="card-specs">' + line + '</div>');
            } else if (inCard && line.startsWith('👉')) {
                processedLines.push('<div class="card-link">' + line + '</div>');
            } else {
                if (inCard) {
                    processedLines.push('</div>');
                    inCard = false;
                }
                if (line === '') {
                    processedLines.push('<br>');
                } else {
                    processedLines.push('<span>' + line + '</span><br>');
                }
            }
        }
        if (inCard) processedLines.push('</div>');

        return processedLines.join('');
    }

    // 4. Add a message bubble
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', sender);

        if (sender === 'bot') {
            // Render bot messages with markdown support
            messageDiv.innerHTML = parseMarkdown(text);
        } else {
            // User messages stay as plain text
            messageDiv.textContent = text;
        }

        messagesContainer.insertBefore(messageDiv, typingIndicator);
        scrollToBottom();
    }

    function showTyping(show) {
        typingIndicator.style.display = show ? 'flex' : 'none';
        if (show) scrollToBottom();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Initial Welcome Message
    setTimeout(() => {
        if (!messagesContainer.querySelector('.message.bot')) {
            addMessage("Hello! I'm your **TD Rentals** assistant. Ask me anything about vehicles, bookings, or the wishlist!", 'bot');
        }
    }, 1000);
});
