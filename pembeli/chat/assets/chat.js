// Auto scroll to bottom
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if(container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Scroll on load
window.addEventListener('load', scrollToBottom);

// Auto refresh messages
setInterval(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const penjualId = urlParams.get('penjual_id');
    
    if(penjualId) {
        fetch(`ajax/get_messages.php?penjual_id=${penjualId}&last_check=${Date.now()}`)
            .then(response => response.text())
            .then(html => {
                if(html.trim() !== '') {
                    const container = document.getElementById('messagesContainer');
                    container.insertAdjacentHTML('beforeend', html);
                    
                    // Scroll if near bottom
                    const scrollThreshold = 100;
                    const shouldScroll = (container.scrollHeight - container.scrollTop - container.clientHeight) < scrollThreshold;
                    if(shouldScroll) scrollToBottom();
                }
            });
    }
}, 3000);

// Form submission with AJAX
document.getElementById('chatForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const messageInput = this.querySelector('input[name="pesan"]');
    const message = messageInput.value.trim();
    
    if(message === '') return;
    
    // Add to UI immediately
    const container = document.getElementById('messagesContainer');
    const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    container.insertAdjacentHTML('beforeend', `
        <div class="message-bubble pembeli">
            <div class="message-content">${message.replace(/\n/g, '<br>')}</div>
            <div class="message-time">${time}</div>
        </div>
    `);
    
    scrollToBottom();
    messageInput.value = '';
    
    // Send via AJAX
    fetch('ajax/send.php', {
        method: 'POST',
        body: formData
    });
});