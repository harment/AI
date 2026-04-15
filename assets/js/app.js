// =============================================
// المساعد الذّكاليّ - JavaScript رئيسي
// =============================================

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initSidebar();
  initChatbot();
  initToasts();
  initModals();
  initForms();
});

/* ---- Tab switching ---- */
function initTabs() {
  document.querySelectorAll('[data-tab-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tabTarget;
      const tabsContainer = btn.closest('[data-tabs]') || document;

      // Deactivate all buttons in this tab group
      tabsContainer.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // Find the target pane via document (panes may live outside [data-tabs])
      const pane = document.getElementById(target);
      if (pane) {
        // Hide all sibling panes explicitly (belt-and-suspenders with CSS class)
        pane.parentElement?.querySelectorAll('.tab-pane').forEach(p => {
          p.classList.remove('active');
          p.style.display = 'none';
        });
        // Show the target pane
        pane.classList.add('active');
        pane.style.display = '';  // clear inline style, let CSS .tab-pane.active take over

        // On mobile, scroll the tabs strip into view so user sees the content
        if (window.innerWidth < 900) {
          setTimeout(() => tabsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }
      }
    });
  });

  // Tab groups (login tabs)
  document.querySelectorAll('.tab-group .tab').forEach(btn => {
    btn.addEventListener('click', () => {
      const group = btn.closest('.tab-group');
      group.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const targetId = btn.dataset.target;
      const container = document.querySelector(btn.dataset.container || 'body');
      container.querySelectorAll('[data-tab-content]').forEach(c => {
        c.style.display = c.id === targetId ? 'block' : 'none';
      });
    });
  });
}

/* ---- Sidebar toggle (mobile) ---- */
function initSidebar() {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }
}

/* ---- Chatbot ---- */
function initChatbot() {
  const toggle = document.getElementById('chatbotToggle');
  const box    = document.getElementById('chatbotBox');
  const close  = document.getElementById('chatbotClose');
  const form   = document.getElementById('chatbotForm');
  const input  = document.getElementById('chatbotInput');
  const msgs   = document.getElementById('chatbotMsgs');
  if (!toggle || !box) return;

  toggle.addEventListener('click', () => box.classList.toggle('open'));
  if (close) close.addEventListener('click', () => box.classList.remove('open'));

  // Welcome message
  appendMsg('bot', 'مرحباً! أنا مساعدك الذّكاليّ � كيف يمكنني مساعدتك اليوم؟');

  const faq = {
    'كيف': 'يمكنك البدء بتسجيل الدخول ثم اختيار مقررك من القائمة الرئيسية.',
    'نقاط': 'النقاط تُكتسب من خلال إكمال ألعاب المغامرة التعليمية في كل درس.',
    'لعبة': 'اختر أي درس مفتوح وانتقل إلى ركن "الألعاب التعليمية".',
    'بودكاست': 'ستجد البودكاست في ركن "البودكاست الصوتي" داخل صفحة الدرس.',
    'pdf': 'يمكنك مشاهدة العرض التقديمي في ركن "العرض التقديمي" داخل الدرس.',
    'عالم': 'اكسب علماء النحو من خلال الفوز في ألعاب المغامرة وستجدهم في حسابك.',
    'مساعدة': 'يمكنك التواصل مع أستاذك أو مراجعة قسم المساعدة في صفحتك.',
  };

  if (form) {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;
      appendMsg('user', text);
      input.value = '';
      // Try FAQ first
      let reply = null;
      for (const [key, val] of Object.entries(faq)) {
        if (text.includes(key)) { reply = val; break; }
      }
      if (reply) {
        setTimeout(() => appendMsg('bot', reply), 600);
      } else {
        // Call AI API
        appendMsg('bot', '⏳ جاري معالجة سؤالك…');
        try {
          const res = await fetch('/api/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text }),
          });
          const data = await res.json();
          // Remove last loading msg
          msgs.lastElementChild?.remove();
          appendMsg('bot', data.reply || 'عذراً، لم أستطع الإجابة على سؤالك.');
        } catch {
          msgs.lastElementChild?.remove();
          appendMsg('bot', 'عذراً، حدث خطأ في الاتصال. حاول مرة أخرى.');
        }
      }
    });
  }

  function appendMsg(type, text) {
    if (!msgs) return;
    const div = document.createElement('div');
    div.className = `msg msg-${type}`;
    div.textContent = text;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }
}

/* ---- Toast notifications ---- */
function initToasts() {
  window.showToast = (message, type = 'info') => {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  };
}

/* ---- Modals ---- */
function initModals() {
  document.querySelectorAll('[data-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.getElementById(btn.dataset.modal);
      if (modal) openModal(modal);
    });
  });

  window.openModal = (modal) => {
    if (typeof modal === 'string') modal = document.getElementById(modal);
    if (!modal) return;
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.appendChild(modal.cloneNode(true));
    document.body.appendChild(backdrop);
    backdrop.querySelector('.modal-close')?.addEventListener('click', () => backdrop.remove());
    backdrop.addEventListener('click', e => { if (e.target === backdrop) backdrop.remove(); });
  };

  window.closeModal = () => {
    document.querySelector('.modal-backdrop')?.remove();
  };
}

/* ---- Form helpers ---- */
function initForms() {
  // Confirm delete
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'هل أنت متأكد؟')) e.preventDefault();
    });
  });

  // Character counter
  document.querySelectorAll('[data-maxlength]').forEach(el => {
    const max = parseInt(el.dataset.maxlength);
    const counter = document.createElement('small');
    counter.className = 'text-muted';
    el.parentNode.insertBefore(counter, el.nextSibling);
    const update = () => { counter.textContent = `${el.value.length}/${max}`; };
    el.addEventListener('input', update);
    update();
  });
}

/* ---- AJAX helper ---- */
async function apiFetch(url, method = 'GET', data = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (data) opts.body = JSON.stringify(data);
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error(await res.text());
  return res.json();
}

/* ---- Time tracker ---- */
let _activityStart = Date.now();
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    const duration = Math.round((Date.now() - _activityStart) / 1000);
    const lessonId = document.body.dataset.lessonId;
    if (lessonId && duration > 5) {
      navigator.sendBeacon('/api/activity.php', JSON.stringify({ lesson_id: lessonId, duration }));
    }
  } else {
    _activityStart = Date.now();
  }
});
