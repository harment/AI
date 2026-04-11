// =============================================
// محرك لعبة المغامرة التعليمية
// =============================================

class AdventureGame {
  constructor(options) {
    this.lessonId   = options.lessonId;
    this.gameType   = options.gameType || 'mountain'; // mountain | maze | ship
    this.questions  = options.questions || [];
    this.scholars   = options.scholars  || [];
    this.container  = document.getElementById(options.containerId || 'gameContainer');
    this.current    = 0;
    this.errors     = 0;
    this.score      = 0;
    this.completed  = false;
    this.BASE_PTS   = options.basePoints || 350;
    this.sounds     = {
      correct : new Audio('/assets/sounds/correct.mp3'),
      wrong   : new Audio('/assets/sounds/wrong.mp3'),
      win     : new Audio('/assets/sounds/win.mp3'),
      lose    : new Audio('/assets/sounds/lose.mp3'),
    };
    this.render();
  }

  render() {
    if (!this.container) return;
    this.container.innerHTML = this._buildGameUI();
    this._attachEvents();
    this._updateProgress();
  }

  _buildGameUI() {
    const bgClass = { mountain: 'mountain-bg', maze: 'maze-bg', ship: 'ship-bg' }[this.gameType] || 'mountain-bg';
    return `
    <div class="${bgClass} game-bg-layer"></div>
    <div class="game-progress" id="gameProgress">
      ${this.questions.map((_, i) => `<div class="progress-dot ${i === 0 ? 'current' : ''}" id="dot-${i}"></div>`).join('')}
    </div>
    <div class="game-question-box" id="questionBox">
      <div class="question-counter" id="qCounter">السؤال 1 من ${this.questions.length}</div>
      <div class="question-text"  id="qText"></div>
      <div class="options-grid"   id="optionsGrid"></div>
      <div id="feedbackBox" style="display:none;padding:.85rem;border-radius:8px;margin-top:.85rem;font-weight:600;"></div>
      <button class="btn btn-primary btn-block" id="nextBtn" style="display:none;margin-top:1rem;">التالي ←</button>
    </div>
    <div class="game-overlay" id="gameOverlay" style="display:none"></div>`;
  }

  _attachEvents() {
    document.getElementById('nextBtn')?.addEventListener('click', () => this._nextQuestion());
  }

  start() {
    this._showQuestion(0);
  }

  _showQuestion(idx) {
    const q = this.questions[idx];
    if (!q) return;
    const qText  = document.getElementById('qText');
    const opts   = document.getElementById('optionsGrid');
    const fb     = document.getElementById('feedbackBox');
    const next   = document.getElementById('nextBtn');
    const ctr    = document.getElementById('qCounter');

    ctr.textContent  = `السؤال ${idx + 1} من ${this.questions.length}`;
    qText.textContent = q.question_text;
    fb.style.display = 'none';
    next.style.display = 'none';

    const labels = { a: 'أ', b: 'ب', c: 'ج', d: 'د' };
    opts.innerHTML = ['a','b','c','d'].map(k => `
      <button class="option-btn" data-key="${k}">
        <span class="opt-label">${labels[k]}</span> ${q['option_' + k]}
      </button>`).join('');

    opts.querySelectorAll('.option-btn').forEach(btn => {
      btn.addEventListener('click', () => this._handleAnswer(btn.dataset.key, q));
    });
  }

  _handleAnswer(chosen, q) {
    const opts = document.getElementById('optionsGrid');
    const fb   = document.getElementById('feedbackBox');
    const next = document.getElementById('nextBtn');

    opts.querySelectorAll('.option-btn').forEach(b => {
      b.disabled = true;
      if (b.dataset.key === q.correct_option) b.classList.add('correct');
    });

    if (chosen === q.correct_option) {
      this._playSound('correct');
      fb.style.background = '#E8F5E9';
      fb.style.color = '#2E7D32';
      fb.innerHTML = '✅ ' + (q.feedback_correct || 'إجابة صحيحة! أحسنت.');
      this.score++;
    } else {
      this._playSound('wrong');
      opts.querySelector(`[data-key="${chosen}"]`)?.classList.add('wrong');
      fb.style.background = '#FFEBEE';
      fb.style.color = '#C62828';
      fb.innerHTML = '❌ ' + (q.feedback_wrong || 'إجابة خاطئة.');
      this.errors++;
    }
    fb.style.display = 'block';

    if (this.errors >= 2) {
      setTimeout(() => this._showResult(false), 1200);
      return;
    }

    // Update dot
    document.getElementById(`dot-${this.current}`)?.classList.add('done');
    next.style.display = 'block';
    this.current++;

    if (this.current >= this.questions.length) {
      next.textContent = '🏆 انهاء المغامرة';
      next.removeEventListener('click', () => this._nextQuestion());
      next.addEventListener('click', () => this._showResult(true), { once: true });
    }
  }

  _nextQuestion() {
    if (this.current < this.questions.length) {
      document.getElementById(`dot-${this.current}`)?.classList.add('current');
      this._showQuestion(this.current);
    }
  }

  _showResult(won) {
    this._playSound(won ? 'win' : 'lose');
    const pts = won ? Math.round(this.BASE_PTS * (this.score / this.questions.length) * (1 + 0.1 * (2 - this.errors))) : 0;
    const scholar = won ? this.scholars[Math.floor(Math.random() * this.scholars.length)] : null;

    const overlay = document.getElementById('gameOverlay');
    overlay.innerHTML = `
    <div class="game-result-box">
      <div class="result-icon">${won ? '🏆' : '😔'}</div>
      <div class="result-title" style="color:${won ? 'var(--primary)' : 'var(--danger)'}">
        ${won ? 'مبروك! اجتزت المغامرة' : 'حاول مرة أخرى!'}
      </div>
      ${pts ? `<div class="result-points">+${pts} نقطة</div>` : ''}
      ${scholar ? `<div class="scholar-card">
        <div class="scholar-img">📜</div>
        <div class="scholar-name">اكتشفت: ${scholar.name}</div>
        <div class="scholar-bio">${scholar.short_bio}</div>
      </div>` : ''}
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">
        <button class="btn btn-primary" onclick="window.adventureGame.restart()">🔄 إعادة المحاولة</button>
        <button class="btn btn-outline" onclick="document.getElementById('gameOverlay').style.display='none'">إغلاق</button>
      </div>
    </div>`;
    overlay.style.display = 'flex';

    // Save to server
    this._saveResult(won, pts, scholar?.id || null);
  }

  restart() {
    this.current  = 0;
    this.errors   = 0;
    this.score    = 0;
    this.render();
    this.start();
  }

  _saveResult(won, pts, scholarId) {
    fetch('/api/games.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lesson_id: this.lessonId, points: pts, scholar_id: scholarId, completed: won ? 1 : 0 }),
    }).catch(() => {});
  }

  _playSound(key) {
    try {
      const s = this.sounds[key];
      s.currentTime = 0;
      s.play().catch(() => {});
    } catch {}
  }

  static shuffle(arr) {
    return arr.slice().sort(() => Math.random() - 0.5);
  }
}

window.AdventureGame = AdventureGame;
