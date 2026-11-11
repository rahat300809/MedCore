/**
 * MedCore AI Chatbot Agent Widget
 * Powered by Groq AI LPUs
 * Features:
 *  - Persistent chat history across page navigation and logins via localStorage
 *  - Real-time citizen session synchronization
 *  - Seamless doctor recommendation and 1-click booking confirmation
 */

(function () {
  // Prevent duplicate initialization
  if (window.__MEDCORE_CHATBOT_INITIALIZED__) return;
  window.__MEDCORE_CHATBOT_INITIALIZED__ = true;

  const API_ENDPOINT = '/api/chat.php';
  const STORAGE_KEY = 'medcore_ai_chat_history_v2';
  const SESSION_WELCOMED_KEY = 'medcore_login_welcomed_v2';

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
            <h4>MedCore AI সহকারী <span id="mc-chat-pulse-indicator" style="background:#10B981; width:7px; height:7px; border-radius:50%; display:inline-block;"></span></h4>
            <p id="mc-chat-status-text">লক্ষণ বিশ্লেষণ · ডাক্তার নির্বাচন · বুকিং</p>
          </div>
        </div>
        <div class="chat-header-actions">
          <button class="chat-action-btn" id="mc-chat-refresh" title="নতুন চ্যাট শুরু করুন (রিসেট)">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
          <button class="chat-action-btn" id="mc-chat-close" title="চ্যাট উইন্ডো বন্ধ করুন">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <!-- Messages Area -->
      <div class="medcore-chat-messages" id="mc-chat-messages">
        <!-- Default Welcome Template (Restored on Reset) -->
        <div class="chat-bubble bot" id="mc-welcome-bubble">
          আসসালামু আলাইকুম! আমি <strong>মেডকোর এআই স্বাস্থ্য ও বুকিং সহকারী</strong>।<br><br>
          আপনার বা রোগীর শারীরিক সমস্যা/লক্ষণ বলুন। আমি উপযুক্ত <strong>ডাক্তারের ক্যাটাগরি</strong> জানিয়ে দেব এবং সারা দেশের ভেরিফাইড বিশেষজ্ঞদের তালিকা থেকে সরাসরি <strong>সিরিয়াল বুকিং</strong> করে দিতে সহায়তা করব।
          
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
          <input type="text" id="mc-chat-input" class="chat-input-field" placeholder="আপনার লক্ষণ বা প্রশ্ন বাংলায় লিখুন..." autocomplete="off">
          <button type="submit" id="mc-chat-send" class="chat-send-btn" aria-label="Send">
            <i class="bi bi-send-fill"></i>
          </button>
        </form>
        <div class="chat-disclaimer">
          🔒 সেন্ট্রালাইজড রেকর্ড ও বুকিং সহকারী · কোনো প্রেসক্রিপশন বা ওষুধ প্রদান করা হয় না
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', widgetHtml);

  // DOM Elements
  const launchBtn = document.getElementById('mc-chat-launch-btn');
  const chatWindow = document.getElementById('mc-chat-window');
  const closeBtn = document.getElementById('mc-chat-close');
  const refreshBtn = document.getElementById('mc-chat-refresh');
  const messagesBox = document.getElementById('mc-chat-messages');
  const chatForm = document.getElementById('mc-chat-form');
  const chatInput = document.getElementById('mc-chat-input');
  const typingIndicator = document.getElementById('mc-typing-indicator');
  const statusText = document.getElementById('mc-chat-status-text');

  // Application State
  let conversationHistory = [];
  let currentUser = {
    is_logged_in: false,
    patient_name: null,
    patient_uid: null
  };

  // Helper: Scroll messages to bottom smoothly
  function scrollToBottom() {
    setTimeout(() => {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }, 60);
  }

  // Helper: Format Markdown (Bold, Lists, Line breaks)
  function formatMarkdown(text) {
    if (!text) return '';
    let esc = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    esc = esc.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    esc = esc.replace(/^\s*-\s+(.*)$/gm, '• $1');
    esc = esc.replace(/\n/g, '<br>');
    return esc;
  }

  // Save history to localStorage
  function saveHistoryToStorage() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(conversationHistory));
    } catch (e) {
      console.warn('MedCore Chatbot: Could not save to localStorage', e);
    }
  }

  // Build redirect URL for login that returns to current page with chat open
  function buildLoginUrl(card) {
    const currentUrl = window.location.pathname + window.location.search;
    const sep = window.location.search ? '&' : '?';
    const returnUrl = currentUrl.includes('chat=open') ? currentUrl : (currentUrl + sep + 'chat=open');
    return `/patient/login.php?redirect=${encodeURIComponent(returnUrl)}`;
  }

  // Render Action Card HTML
  function createActionCardElement(card) {
    const cardEl = document.createElement('div');
    cardEl.className = 'chat-doctor-card';
    cardEl.setAttribute('data-doc-id', card.doctor_id);

    const isUserAuth = currentUser.is_logged_in || card.is_logged_in;

    if (isUserAuth) {
      const displayName = currentUser.patient_name ? `জনাব ${currentUser.patient_name}` : 'ভেরিফাইড নাগরিক';
      cardEl.innerHTML = `
        <div class="chat-doctor-header">
          <div>
            <h5 class="chat-doctor-name">${card.doctor_name}</h5>
            <div class="chat-doctor-spec">সরাসরি সিরিয়াল বুকিং কনফার্মেশন</div>
          </div>
          <span class="chat-doctor-rating" style="background:#ECFDF5; color:#059669; border:1px solid #A7F3D0;">
            <i class="bi bi-shield-check"></i> ভেরিফাইড রোগী
          </span>
        </div>
        <div class="chat-doctor-meta" style="color:#0F172A; font-size:12px;">
          <span style="color:#059669; font-weight:700;"><i class="bi bi-check-circle-fill"></i> ${displayName}</span> হিসেবে আপনার সেশন সক্রিয় আছে। এখনই সরাসরি ডাক্তারের সময়সূচি ও সিরিয়াল স্লট নিশ্চিত করুন।
        </div>
        <a href="${card.booking_url}" class="chat-booking-btn" style="background:linear-gradient(135deg,#059669,#0284C7);">
          <i class="bi bi-calendar2-check-fill"></i> এই ডাক্তারের সিরিয়াল নিশ্চিত করুন
        </a>
      `;
    } else {
      const loginUrl = buildLoginUrl(card);
      cardEl.innerHTML = `
        <div class="chat-doctor-header">
          <div>
            <h5 class="chat-doctor-name">${card.doctor_name}</h5>
            <div class="chat-doctor-spec">সিরিয়াল বুকিং কনফার্মেশন</div>
          </div>
          <span class="chat-doctor-rating" style="background:#FFFBEB; color:#B45309; border:1px solid #FDE68A;">
            <i class="bi bi-lock-fill"></i> লগইন প্রয়োজন
          </span>
        </div>
        <div class="chat-login-notice">
          <i class="bi bi-info-circle-fill"></i> আপনার স্থায়ী ডিজিটাল স্বাস্থ্য রেকর্ডে সিরিয়াল টোকেন যুক্ত করতে একবার নাগরিক পোর্টালে লগইন করুন। লগইন করার সাথে সাথে স্বয়ংক্রিয়ভাবে এই চ্যাটে ফিরে আসবেন।
        </div>
        <div style="display:flex; gap:8px;">
          <a href="${loginUrl}" class="chat-booking-btn" style="flex:1;">
            <i class="bi bi-box-arrow-in-right"></i> লগইন করুন
          </a>
          <a href="/patient/register.php" class="chat-booking-btn" style="flex:1; background:#FFFFFF; color:#0284C7 !important; border:1px solid #BAE6FD;">
            <i class="bi bi-person-plus-fill"></i> রেজিস্ট্রেশন
          </a>
        </div>
      `;
    }
    return cardEl;
  }

  // Append a message bubble to DOM
  function appendBubbleToDOM(role, content, actionCards = []) {
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role === 'user' ? 'user' : 'bot'}`;

    if (role === 'user') {
      bubble.textContent = content;
    } else {
      bubble.innerHTML = formatMarkdown(content);
      if (Array.isArray(actionCards) && actionCards.length > 0) {
        actionCards.forEach(card => {
          bubble.appendChild(createActionCardElement(card));
        });
      }
    }

    messagesBox.insertBefore(bubble, typingIndicator);
    scrollToBottom();
  }

  // Restore saved messages from localStorage
  function restoreHistory() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed) && parsed.length > 0) {
          conversationHistory = parsed;
          // Clear default welcome bubble if user already has custom history
          const welcome = document.getElementById('mc-welcome-bubble');
          if (welcome) welcome.style.display = 'none';

          parsed.forEach(item => {
            appendBubbleToDOM(item.role, item.content, item.action_cards || []);
          });
          scrollToBottom();
        }
      }
    } catch (e) {
      console.warn('MedCore Chatbot: Could not restore history', e);
    }
  }

  // Update action cards in DOM when user auth status changes
  function refreshActionCardsAuth() {
    if (!currentUser.is_logged_in) return;
    const cards = messagesBox.querySelectorAll('.chat-doctor-card');
    cards.forEach(cardEl => {
      const docId = cardEl.getAttribute('data-doc-id');
      const docName = cardEl.querySelector('.chat-doctor-name')?.textContent || 'ডাক্তার';
      const newCard = createActionCardElement({
        doctor_id: docId,
        doctor_name: docName,
        booking_url: `/book-doctor.php?doctor_id=${docId}`,
        is_logged_in: true
      });
      cardEl.replaceWith(newCard);
    });
  }

  // Sync session status with backend API
  async function syncAuthStatus() {
    try {
      const res = await fetch(API_ENDPOINT, { method: 'GET' });
      const data = await res.json();
      if (data && data.success) {
        currentUser.is_logged_in = !!data.is_logged_in;
        currentUser.patient_name = data.patient_name || null;
        currentUser.patient_uid = data.patient_uid || null;

        if (currentUser.is_logged_in && currentUser.patient_name) {
          statusText.innerHTML = `<span style="color:#34D399; font-weight:700;">● ${currentUser.patient_name} (ভেরিফাইড নাগরিক)</span>`;
          refreshActionCardsAuth();

          // If user just returned from login with chat=open, give an instant confirmation bubble
          const urlParams = new URLSearchParams(window.location.search);
          if (urlParams.get('chat') === 'open' && !sessionStorage.getItem(SESSION_WELCOMED_KEY)) {
            sessionStorage.setItem(SESSION_WELCOMED_KEY, 'true');
            const welcomeBackMsg = `স্বাগতম <strong>জনাব ${currentUser.patient_name}</strong>! আপনার নাগরিক পোর্টাল সফলভাবে কানেক্ট হয়েছে। আপনি কি এইমাত্র যে ডাক্তারের বিষয়ে কথা বলছিলেন, তাঁর সিরিয়াল কনফার্ম করবেন?`;
            appendBubbleToDOM('bot', welcomeBackMsg);
            conversationHistory.push({
              role: 'assistant',
              content: welcomeBackMsg,
              action_cards: [],
              timestamp: Date.now()
            });
            saveHistoryToStorage();
          }
        } else {
          statusText.textContent = 'লক্ষণ বিশ্লেষণ · ডাক্তার নির্বাচন · বুকিং';
        }
      }
    } catch (e) {
      console.warn('MedCore Chatbot: Auth sync failed', e);
    }
  }

  // Send message flow
  async function sendMessage(userText) {
    // Hide welcome bubble on first message
    const welcome = document.getElementById('mc-welcome-bubble');
    if (welcome) welcome.style.display = 'none';

    // Append user message
    appendBubbleToDOM('user', userText);
    conversationHistory.push({
      role: 'user',
      content: userText,
      timestamp: Date.now()
    });
    saveHistoryToStorage();

    // Show Typing Indicator
    typingIndicator.classList.add('active');
    scrollToBottom();

    try {
      // Send history containing only roles and text content to API
      const apiMessages = conversationHistory.map(m => ({
        role: m.role,
        content: m.content
      }));

      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messages: apiMessages })
      });

      const data = await res.json();
      typingIndicator.classList.remove('active');

      if (data.success && data.reply) {
        // Update user state if reported
        if (typeof data.is_logged_in !== 'undefined') {
          currentUser.is_logged_in = !!data.is_logged_in;
          if (data.patient_name) currentUser.patient_name = data.patient_name;
          if (data.patient_uid) currentUser.patient_uid = data.patient_uid;
          if (currentUser.is_logged_in && currentUser.patient_name) {
            statusText.innerHTML = `<span style="color:#34D399; font-weight:700;">● ${currentUser.patient_name} (ভেরিফাইড নাগরিক)</span>`;
          }
        }

        const actionCards = data.action_cards || [];
        appendBubbleToDOM('bot', data.reply, actionCards);

        conversationHistory.push({
          role: 'assistant',
          content: data.reply,
          action_cards: actionCards,
          timestamp: Date.now()
        });
        saveHistoryToStorage();
      } else {
        const errorMsg = data.message || 'দুঃখিত, সংযোগে সাময়িক সমস্যা হয়েছে। আবার চেষ্টা করুন।';
        const errorBubble = document.createElement('div');
        errorBubble.className = 'chat-bubble bot';
        errorBubble.style.borderColor = '#FECACA';
        errorBubble.style.color = '#991B1B';
        errorBubble.textContent = errorMsg;
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

  // Event Listeners
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

  // Reset conversation button
  refreshBtn.addEventListener('click', () => {
    if (confirm('আপনি কি পূর্বের সব কথোপকথন মুছে নতুন চ্যাট শুরু করতে চান?')) {
      conversationHistory = [];
      try {
        localStorage.removeItem(STORAGE_KEY);
        sessionStorage.removeItem(SESSION_WELCOMED_KEY);
      } catch (e) {}

      // Remove bubbles
      const bubbles = messagesBox.querySelectorAll('.chat-bubble');
      bubbles.forEach(b => {
        if (b.id !== 'mc-welcome-bubble') b.remove();
      });

      // Restore welcome bubble
      const welcome = document.getElementById('mc-welcome-bubble');
      if (welcome) welcome.style.display = 'block';

      scrollToBottom();
    }
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

  // Check URL parameters to auto-open if requested
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('chat') === 'open' || urlParams.get('chat_open') === '1') {
    chatWindow.classList.add('active');
    chatInput.focus();
  }

  // Initialize
  restoreHistory();
  syncAuthStatus();
})();
