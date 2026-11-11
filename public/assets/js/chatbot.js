/**
 * MedCore AI Chatbot Agent Widget
 * Powered by Groq AI LPUs
 */

(function () {
  // Prevent duplicate initialization
  if (window.__MEDCORE_CHATBOT_INITIALIZED__) return;
  window.__MEDCORE_CHATBOT_INITIALIZED__ = true;

  const API_ENDPOINT = '/api/chat.php';

  // Inject Widget HTML into DOM
  const widgetHtml = `
    <button class="medcore-chat-btn" id="mc-chat-launch-btn" aria-label="Open MedCore AI Assistant">
      <span class="chat-btn-pulse"></span>
      <i class="bi bi-robot"></i>
      <span>MedCore AI</span>
    </button>

    <div class="medcore-chat-window" id="mc-chat-window" role="dialog" aria-modal="true" aria-label="MedCore AI Chat">
      <!-- Header -->
      <div class="medcore-chat-header">
        <div class="medcore-chat-header-left">
          <div class="chat-bot-avatar">
            <i class="bi bi-heart-pulse-fill"></i>
          </div>
          <div class="chat-bot-titles">
            <h4>MedCore AI সহকারী <span style="background:#10B981; width:7px; height:7px; border-radius:50%; display:inline-block;"></span></h4>
            <p>লক্ষণ বিশ্লেষণ · ডাক্তার নির্বাচন · বুকিং</p>
          </div>
        </div>
        <div class="chat-header-actions">
          <button class="chat-action-btn" id="mc-chat-refresh" title="নতুন চ্যাট শুরু করুন">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
          <button class="chat-action-btn" id="mc-chat-close" title="বন্ধ করুন">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <!-- Messages Area -->
      <div class="medcore-chat-messages" id="mc-chat-messages">
        <!-- Welcome Message -->
        <div class="chat-bubble bot">
          আসসালামু আলাইকুম! আমি <strong>মেডকোর এআই স্বাস্থ্য সহকারী</strong>।<br><br>
          আপনার বা রোগীর শারীরিক সমস্যা/লক্ষণ বলুন, আমি সঠিক <strong>বিশেষজ্ঞ ডাক্তার</strong> খুঁজে দেব এবং সরাসরি সিরিয়াল বুকিংয়ে সহায়তা করব।
          
          <div class="chat-quick-suggestions">
            <button class="chat-prompt-pill" data-query="আমার ২ দিন ধরে খুব বুকে ব্যথা এবং শ্বাসকষ্ট হচ্ছে। কোন ডাক্তার দেখাব?">
              <span>🩺 বুকে ব্যথা ও শ্বাসকষ্ট হচ্ছে</span>
              <i class="bi bi-chevron-right"></i>
            </button>
            <button class="chat-prompt-pill" data-query="বাচ্চার ঠান্ডা, জ্বর ও কাশি। শিশু বিশেষজ্ঞ ডাক্তারের সিরিয়াল কীভাবে পাব?">
              <span>👶 বাচ্চার জ্বর ও ঠান্ডা</span>
              <i class="bi bi-chevron-right"></i>
            </button>
            <button class="chat-prompt-pill" data-query="পেটে প্রচণ্ড গ্যাস ও অ্যাসিডিটি হচ্ছে। ভালো গ্যাস্ট্রো ডাক্তার কে আছেন?">
              <span>💊 পেটের সমস্যা ও গ্যাস্ট্রো ডাক্তার</span>
              <i class="bi bi-chevron-right"></i>
            </button>
            <button class="chat-prompt-pill" data-query="মেডকোর অ্যাপে ডাক্তার সিরিয়াল বুক করার পুরো নিয়ম বুঝিয়ে বলুন।">
              <span>📅 ডাক্তার সিরিয়াল বুক করার নিয়ম</span>
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>

        <!-- Typing Indicator -->
        <div class="chat-typing-indicator" id="mc-typing-indicator">
          <span class="chat-typing-dot"></span>
          <span class="chat-typing-dot"></span>
          <span class="chat-typing-dot"></span>
        </div>
      </div>

      <!-- Footer & Input -->
      <div class="medcore-chat-footer">
        <form id="mc-chat-form" class="chat-input-row" onsubmit="return false;">
          <input type="text" id="mc-chat-input" class="chat-input-field" placeholder="আপনার সমস্যা বা প্রশ্ন লিখুন..." autocomplete="off">
          <button type="submit" id="mc-chat-send" class="chat-send-btn" aria-label="Send">
            <i class="bi bi-send-fill"></i>
          </button>
        </form>
        <div class="chat-disclaimer">
          ⚠️ এআই কোনো প্রেসক্রিপশন প্রদান করে না। এটি শুধুমাত্র লক্ষণ বিশ্লেষণ ও ক্লিনিক্যাল সহায়িকা।
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', widgetHtml);

  // Elements
  const launchBtn = document.getElementById('mc-chat-launch-btn');
  const chatWindow = document.getElementById('mc-chat-window');
  const closeBtn = document.getElementById('mc-chat-close');
  const refreshBtn = document.getElementById('mc-chat-refresh');
  const messagesBox = document.getElementById('mc-chat-messages');
  const chatForm = document.getElementById('mc-chat-form');
  const chatInput = document.getElementById('mc-chat-input');
  const typingIndicator = document.getElementById('mc-typing-indicator');

  // Conversation history in memory
  let conversationHistory = [];

  // Toggle Window
  launchBtn.addEventListener('click', () => {
    chatWindow.classList.toggle('active');
    if (chatWindow.classList.contains('active')) {
      chatInput.focus();
      scrollToBottom();
    }
  });

  closeBtn.addEventListener('click', () => {
    chatWindow.classList.remove('active');
  });

  // Reset Chat
  refreshBtn.addEventListener('click', () => {
    conversationHistory = [];
    const botBubbles = messagesBox.querySelectorAll('.chat-bubble');
    botBubbles.forEach((b, idx) => {
      if (idx > 0) b.remove();
    });
    const cards = messagesBox.querySelectorAll('.chat-doctor-card');
    cards.forEach((c) => c.remove());
    scrollToBottom();
  });

  // Quick Prompt Pill Clicks
  messagesBox.addEventListener('click', (e) => {
    const pill = e.target.closest('.chat-prompt-pill');
    if (pill) {
      const query = pill.getAttribute('data-query');
      if (query) {
        sendMessage(query);
      }
    }
  });

  // Form Submit
  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (text) {
      sendMessage(text);
      chatInput.value = '';
    }
  });

  function scrollToBottom() {
    setTimeout(() => {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }, 50);
  }

  function formatMarkdown(text) {
    if (!text) return '';
    // Bold
    let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Bullet items
    formatted = formatted.replace(/^\s*-\s+(.*)$/gm, '• $1');
    // Line breaks
    formatted = formatted.replace(/\n/g, '<br>');
    return formatted;
  }

  async function sendMessage(userText) {
    // Append User Bubble
    const userBubble = document.createElement('div');
    userBubble.className = 'chat-bubble user';
    userBubble.textContent = userText;
    messagesBox.insertBefore(userBubble, typingIndicator);

    conversationHistory.push({ role: 'user', content: userText });
    scrollToBottom();

    // Show Typing Indicator
    typingIndicator.classList.add('active');
    scrollToBottom();

    try {
      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          messages: conversationHistory,
        }),
      });

      const data = await res.json();
      typingIndicator.classList.remove('active');

      if (data.success && data.reply) {
        conversationHistory.push({ role: 'assistant', content: data.reply });

        // Append Bot Bubble
        const botBubble = document.createElement('div');
        botBubble.className = 'chat-bubble bot';
        botBubble.innerHTML = formatMarkdown(data.reply);

        // Check if there are Action Cards (Doctor Booking)
        if (data.action_cards && data.action_cards.length > 0) {
          data.action_cards.forEach((card) => {
            const cardEl = document.createElement('div');
            cardEl.className = 'chat-doctor-card';
            
            if (card.is_logged_in) {
              cardEl.innerHTML = `
                <div class="chat-doctor-header">
                  <div>
                    <h5 class="chat-doctor-name">${card.doctor_name}</h5>
                    <div class="chat-doctor-spec">সরাসরি সিরিয়াল বুকিং কনফার্মেশন</div>
                  </div>
                  <span class="chat-doctor-rating">VERIFIED</span>
                </div>
                <div class="chat-doctor-meta">
                  আপনি পেশেন্ট হিসেবে লগইন আছেন। এখনই আপনার পছন্দের তারিখ ও সময় স্লট বেছে নিন।
                </div>
                <a href="${card.booking_url}" class="chat-booking-btn">
                  <i class="bi bi-calendar2-check-fill"></i> এই ডাক্তারের সিরিয়াল নিন
                </a>
              `;
            } else {
              cardEl.innerHTML = `
                <div class="chat-doctor-header">
                  <div>
                    <h5 class="chat-doctor-name">${card.doctor_name}</h5>
                    <div class="chat-doctor-spec">সিরিয়াল বুকিং কনফার্মেশন</div>
                  </div>
                  <span class="chat-doctor-rating">LOGIN REQUIRED</span>
                </div>
                <div class="chat-login-notice">
                  <i class="bi bi-lock-fill"></i> আপনার অ্যাকাউন্টে সিরিয়াল ও টোকেন যুক্ত করতে একবার পেশেন্ট লগইন করুন।
                </div>
                <div style="display:flex; gap:8px;">
                  <a href="${card.login_url}" class="chat-booking-btn" style="flex:1;">
                    <i class="bi bi-box-arrow-in-right"></i> লগইন করুন
                  </a>
                  <a href="${card.register_url}" class="chat-booking-btn" style="flex:1; background:#FFFFFF; color:#0284C7 !important; border:1px solid #BAE6FD;">
                    <i class="bi bi-person-plus-fill"></i> রেজিস্ট্রেশন
                  </a>
                </div>
              `;
            }
            botBubble.appendChild(cardEl);
          });
        }

        messagesBox.insertBefore(botBubble, typingIndicator);
      } else {
        const errorBubble = document.createElement('div');
        errorBubble.className = 'chat-bubble bot';
        errorBubble.style.borderColor = '#FECACA';
        errorBubble.style.color = '#991B1B';
        errorBubble.textContent = data.message || 'দুঃখিত, সংযোগে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
        messagesBox.insertBefore(errorBubble, typingIndicator);
      }
    } catch (err) {
      typingIndicator.classList.remove('active');
      const errorBubble = document.createElement('div');
      errorBubble.className = 'chat-bubble bot';
      errorBubble.style.color = '#991B1B';
      errorBubble.textContent = 'নেটওয়ার্ক সংযোগ বিঘ্নিত হয়েছে। অনুগ্রহ করে ইন্টারনেট সংযোগ চেক করুন।';
      messagesBox.insertBefore(errorBubble, typingIndicator);
    }

    scrollToBottom();
  }
})();
