// =============================================
// لعبة الخريطة الرسومية – MapAdventureGame
// =============================================

/* ── محرك المؤثرات الصوتية ── */
const GameSFX = {
  _ctx: null,
  _getCtx() {
    if (!this._ctx) {
      try { this._ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch (_) {}
    }
    if (this._ctx && this._ctx.state === 'suspended') this._ctx.resume();
    return this._ctx;
  },
  _tone(freq, type, dur, vol, delay) {
    const ctx = this._getCtx();
    if (!ctx) return;
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.type = type;
    o.frequency.setValueAtTime(freq, ctx.currentTime + delay);
    g.gain.setValueAtTime(0, ctx.currentTime + delay);
    g.gain.linearRampToValueAtTime(vol, ctx.currentTime + delay + 0.01);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + dur);
    o.start(ctx.currentTime + delay);
    o.stop(ctx.currentTime + delay + dur + 0.05);
  },
  click()   { this._tone(880, 'sine', 0.08, 0.2, 0); this._tone(1100, 'sine', 0.06, 0.15, 0.06); },
  correct() { this._tone(523, 'sine', 0.15, 0.3, 0); this._tone(659, 'sine', 0.15, 0.3, 0.12); this._tone(784, 'sine', 0.2, 0.35, 0.24); },
  wrong()   { this._tone(300, 'square', 0.1, 0.25, 0); this._tone(220, 'square', 0.1, 0.25, 0.12); },
  move()    { this._tone(600, 'sine', 0.1, 0.15, 0); this._tone(800, 'sine', 0.08, 0.12, 0.08); },
  win()     { [523,659,784,1047].forEach((f,i) => this._tone(f, 'sine', 0.3, 0.35, i*0.13)); this._tone(1047, 'sine', 0.6, 0.4, 0.55); },
  lose()    { [440,370,277,185].forEach((f,i) => this._tone(f, 'sawtooth', 0.15, 0.2, i*0.15)); }
};

class MapAdventureGame extends AdventureGame {
  constructor(options = {}) {
    const mapTheme = options.mapTheme || 'mountain';
    const modeMap = { island: 'maze', mountain: 'mountain', lake: 'ship', forest: 'maze' };
    super({ ...options, gameType: modeMap[mapTheme] || 'mountain' });
    this.mapTheme = mapTheme;
    this.playerPos = -1;
    this._questionVisible = false;
    this._waitingForClick = false;
  }

  /* ── مواقع المحطات حسب النمط (نسبة مئوية من الحاوية) ── */
  /* الجبل: تسلق من أسفل اليمين متمايلاً إلى القمة */
  /* البحر: متعرج عبر الأمواج */
  /* الجزيرة: مسار منحنٍ حول الشاطئ */
  /* الغابة: ممر ملتوي بين الأشجار */
  static getStationPositions(theme, count) {
    const positions = {
      mountain: [
        { x: 72, y: 88 }, { x: 50, y: 75 }, { x: 68, y: 58 },
        { x: 42, y: 45 }, { x: 58, y: 30 }, { x: 48, y: 15 }, { x: 52, y: 5 }
      ],
      island: [
        { x: 80, y: 82 }, { x: 60, y: 78 }, { x: 38, y: 70 },
        { x: 25, y: 55 }, { x: 35, y: 38 }, { x: 55, y: 25 }, { x: 70, y: 12 }
      ],
      lake: [
        { x: 15, y: 80 }, { x: 38, y: 70 }, { x: 62, y: 78 },
        { x: 80, y: 62 }, { x: 55, y: 48 }, { x: 30, y: 35 }, { x: 50, y: 15 }
      ],
      forest: [
        { x: 75, y: 85 }, { x: 45, y: 78 }, { x: 22, y: 65 },
        { x: 48, y: 50 }, { x: 72, y: 38 }, { x: 35, y: 22 }, { x: 55, y: 8 }
      ]
    };
    const pts = positions[theme] || positions.mountain;
    return pts.slice(0, Math.min(count, pts.length));
  }

  /* ── ثيمات الخريطة ── */
  static get THEMES() {
    return {
      mountain: {
        label: 'مغامرة الجبل', emoji: '⛰️',
        bgClass: 'mountain-bg',
        playerEmoji: '🧗',
        finishEmoji: '🏔️',
        decorations: [
          { emoji: '🌤️', x: '12%', y: '8%', size: '1.8rem' },
          { emoji: '🦅', x: '25%', y: '12%', size: '1.5rem' },
          { emoji: '🌿', x: '8%', y: '70%', size: '1.3rem' },
          { emoji: '🪨', x: '18%', y: '80%', size: '1.1rem' },
          { emoji: '🌲', x: '12%', y: '88%', size: '1.4rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <rect width="400" height="300" fill="transparent"/>
            <!-- جبل بعيد -->
            <polygon points="30,300 120,150 210,300" fill="#6d4c22" opacity="0.2"/>
            <polygon points="100,170 120,150 140,170" fill="#fff" opacity="0.25"/>
            <!-- جبل رئيسي بني كبير -->
            <polygon points="100,300 250,35 400,300" fill="#8B6914" opacity="0.45"/>
            <!-- طبقة جبلية داكنة -->
            <polygon points="130,300 250,55 370,300" fill="#7a5c10" opacity="0.25"/>
            <!-- رأس أبيض ثلجي -->
            <polygon points="228,70 250,35 272,70" fill="#fff" opacity="0.7"/>
            <polygon points="235,80 250,50 265,80" fill="#e8e8e8" opacity="0.5"/>
            <!-- جبل صغير يسار -->
            <polygon points="0,300 60,200 130,300" fill="#a0782c" opacity="0.25"/>
            <!-- عشب أسفل -->
            <ellipse cx="200" cy="298" rx="200" ry="8" fill="#4ade80" opacity="0.2"/>
          </svg>`
      },
      island: {
        label: 'مغامرة الجزيرة', emoji: '🏝️',
        bgClass: 'island-bg',
        playerEmoji: '🚶',
        finishEmoji: '🏖️',
        decorations: [
          { emoji: '☀️', x: '15%', y: '6%', size: '2rem' },
          { emoji: '🌴', x: '22%', y: '50%', size: '1.8rem' },
          { emoji: '🐚', x: '12%', y: '82%', size: '1.2rem' },
          { emoji: '🦀', x: '85%', y: '75%', size: '1rem' },
          { emoji: '🐠', x: '88%', y: '88%', size: '1.1rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- جزيرة رملية -->
            <ellipse cx="200" cy="260" rx="160" ry="45" fill="#f4d35e" opacity="0.35"/>
            <ellipse cx="180" cy="270" rx="120" ry="30" fill="#fbbf24" opacity="0.2"/>
            <!-- نخلة -->
            <line x1="160" y1="260" x2="145" y2="180" stroke="#8B6914" stroke-width="5" opacity="0.45"/>
            <ellipse cx="125" cy="175" rx="30" ry="12" fill="#22c55e" opacity="0.4" transform="rotate(-25 125 175)"/>
            <ellipse cx="160" cy="168" rx="30" ry="12" fill="#16a34a" opacity="0.4" transform="rotate(20 160 168)"/>
            <ellipse cx="140" cy="185" rx="20" ry="8" fill="#15803d" opacity="0.3" transform="rotate(-10 140 185)"/>
            <!-- أمواج -->
            <path d="M0,285 Q50,275 100,285 Q150,295 200,285 Q250,275 300,285 Q350,295 400,285 L400,300 L0,300 Z" fill="#38bdf8" opacity="0.25"/>
          </svg>`
      },
      lake: {
        label: 'مغامرة البحر', emoji: '⛵',
        bgClass: 'ship-bg',
        playerEmoji: '⛵',
        finishEmoji: '🏴‍☠️',
        decorations: [
          { emoji: '🌅', x: '10%', y: '6%', size: '1.8rem' },
          { emoji: '🐬', x: '82%', y: '45%', size: '1.5rem' },
          { emoji: '⚓', x: '8%', y: '55%', size: '1.4rem' },
          { emoji: '🐟', x: '88%', y: '82%', size: '1.1rem' },
          { emoji: '🌊', x: '5%', y: '90%', size: '1.3rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- أمواج متعددة متحركة -->
            <path d="M0,200 Q30,188 60,200 Q90,212 120,200 Q150,188 180,200 Q210,212 240,200 Q270,188 300,200 Q330,212 360,200 Q390,188 400,200 L400,300 L0,300 Z" fill="#0ea5e9" opacity="0.15">
              <animate attributeName="d" dur="3s" repeatCount="indefinite"
                values="M0,200 Q30,188 60,200 Q90,212 120,200 Q150,188 180,200 Q210,212 240,200 Q270,188 300,200 Q330,212 360,200 Q390,188 400,200 L400,300 L0,300 Z;
                        M0,200 Q30,212 60,200 Q90,188 120,200 Q150,212 180,200 Q210,188 240,200 Q270,212 300,200 Q330,188 360,200 Q390,212 400,200 L400,300 L0,300 Z;
                        M0,200 Q30,188 60,200 Q90,212 120,200 Q150,188 180,200 Q210,212 240,200 Q270,188 300,200 Q330,212 360,200 Q390,188 400,200 L400,300 L0,300 Z"/>
            </path>
            <path d="M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z" fill="#0284c7" opacity="0.12">
              <animate attributeName="d" dur="4s" repeatCount="indefinite"
                values="M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z;
                        M0,240 Q40,255 80,240 Q120,225 160,240 Q200,255 240,240 Q280,225 320,240 Q360,255 400,240 L400,300 L0,300 Z;
                        M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z"/>
            </path>
            <!-- سفينة خشبية -->
            <polygon points="160,175 240,175 250,195 150,195" fill="#8B6914" opacity="0.3"/>
            <line x1="200" y1="145" x2="200" y2="175" stroke="#666" stroke-width="2" opacity="0.3"/>
            <polygon points="200,145 220,158 200,175" fill="#fff" opacity="0.35"/>
          </svg>`
      },
      forest: {
        label: 'مغامرة الغابة', emoji: '🌲',
        bgClass: 'maze-bg',
        playerEmoji: '🏃',
        finishEmoji: '🏆',
        decorations: [
          { emoji: '🍃', x: '85%', y: '8%', size: '1.5rem' },
          { emoji: '🦊', x: '10%', y: '72%', size: '1.4rem' },
          { emoji: '🍄', x: '82%', y: '82%', size: '1.1rem' },
          { emoji: '🌿', x: '5%', y: '45%', size: '1.2rem' },
          { emoji: '🦉', x: '88%', y: '30%', size: '1.3rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- أشجار كبيرة -->
            <polygon points="50,300 75,140 100,300" fill="#15803d" opacity="0.3"/>
            <polygon points="30,300 75,165 120,300" fill="#166534" opacity="0.2"/>
            <rect x="70" y="240" width="10" height="60" fill="#92400e" opacity="0.3"/>
            <!-- شجرة وسط -->
            <polygon points="150,300 180,110 210,300" fill="#22c55e" opacity="0.25"/>
            <rect x="175" y="240" width="10" height="60" fill="#78350f" opacity="0.3"/>
            <!-- شجرة يمين -->
            <polygon points="300,300 330,130 360,300" fill="#15803d" opacity="0.25"/>
            <rect x="325" y="250" width="10" height="50" fill="#92400e" opacity="0.25"/>
            <!-- شجيرات صغيرة -->
            <ellipse cx="240" cy="290" rx="25" ry="12" fill="#22c55e" opacity="0.2"/>
            <ellipse cx="380" cy="292" rx="20" ry="10" fill="#16a34a" opacity="0.2"/>
            <!-- عشب -->
            <rect x="0" y="285" width="400" height="15" fill="#166534" opacity="0.15" rx="5"/>
          </svg>`
      }
    };
  }

  /* ── الرسم الرئيسي ── */
  render() {
    if (!this.container) return;
    const theme = MapAdventureGame.THEMES[this.mapTheme] || MapAdventureGame.THEMES.mountain;
    const stationPositions = MapAdventureGame.getStationPositions(this.mapTheme, this.questions.length);

    const decorationsHTML = theme.decorations.map(d =>
      `<div class="map-decoration" style="left:${d.x};top:${d.y};font-size:${d.size};">${d.emoji}</div>`
    ).join('');

    // رسم مسار متصل بين المحطات عبر SVG
    const pathPoints = stationPositions.map(p => `${p.x * 4},${p.y * 3}`).join(' ');

    // بناء المحطات بمواقع مطلقة على الخريطة
    const stationsHTML = this.questions.map((_, i) => {
      const pos = stationPositions[i] || { x: 50, y: 50 };
      const stateClass = i === 0 ? 'map-station-current' : '';
      return `<div class="map-station map-station-abs ${stateClass}" id="mapStation-${i}" data-index="${i}"
                style="left:${pos.x}%;top:${pos.y}%;">
        <div class="map-station-circle">${i + 1}</div>
      </div>`;
    }).join('');

    // الموقع الأولي للاعب (قبل أول محطة)
    const startPos = stationPositions[0] || { x: 50, y: 85 };

    this.container.innerHTML = `
      <div class="map-game-wrapper">
        <div class="map-bg-layer ${theme.bgClass}"></div>
        ${theme.sceneSVG || ''}
        <!-- مسار خط منقط بين المحطات -->
        <svg class="map-path-svg" viewBox="0 0 400 300" preserveAspectRatio="none">
          <polyline points="${pathPoints}" fill="none" stroke="rgba(255,255,255,0.45)" stroke-width="2.5" stroke-dasharray="8,5" stroke-linecap="round"/>
        </svg>
        ${decorationsHTML}
        ${stationsHTML}
        <div class="map-player" id="mapPlayer" style="left:${startPos.x + 5}%;top:${startPos.y - 5}%;">
          ${theme.playerEmoji}
        </div>
        <div class="map-header-bar">
          <span class="map-badge">${theme.emoji} ${theme.label}</span>
          <span class="map-counter" id="mapCounter">انقر على النقطة للبدء</span>
        </div>
        <!-- شريط التقدم -->
        <div class="map-progress-bar">
          <div class="map-progress-fill" id="mapProgressFill" style="width:0%"></div>
        </div>
        <div class="map-question-modal" id="mapQuestionModal" style="display:none;">
          <div class="map-question-box">
            <div class="map-question-header">
              <div class="map-q-counter" id="mapQCounter"></div>
              <div class="map-q-score" id="mapQScore">⭐ 0</div>
            </div>
            <h3 class="map-q-text" id="mapQText"></h3>
            <div class="map-q-options" id="mapQOptions"></div>
            <div class="map-q-feedback" id="mapQFeedback" style="display:none;"></div>
            <button class="btn btn-primary" id="mapNextBtn" style="display:none;">التالي ←</button>
          </div>
        </div>
        <div class="map-overlay" id="mapOverlay" style="display:none;"></div>
      </div>
    `;

    this._theme = theme;
    this._stationPositions = stationPositions;
  }

  /* ── بدء اللعبة – انتظار النقر ── */
  start() {
    if (!this.questions.length || !this.container) {
      this._showError('لا توجد أسئلة متاحة لهذا الدرس.');
      return;
    }
    this.playerPos = -1;
    this._waitForStationClick(0);
  }

  /* ── انتظار نقر الطالب على المحطة الحالية ── */
  _waitForStationClick(index) {
    this._waitingForClick = true;
    this._syncStations(index);

    const counter = document.getElementById('mapCounter');
    if (counter) counter.textContent = `انقر على النقطة ${index + 1} للبدء`;

    const station = document.getElementById(`mapStation-${index}`);
    if (!station) return;

    const handler = () => {
      station.removeEventListener('click', handler);
      this._waitingForClick = false;
      GameSFX.click();
      this._movePlayerToStation(index, () => {
        this._showQuestion(index);
      });
    };

    station.addEventListener('click', handler);
  }

  /* ── تحريك اللاعب إلى المحطة ── */
  _movePlayerToStation(index, callback) {
    const player = document.getElementById('mapPlayer');
    if (!player) { if (callback) callback(); return; }

    const pos = this._stationPositions[index];
    if (!pos) { if (callback) callback(); return; }

    GameSFX.move();
    player.classList.add('moving');

    player.style.transition = 'left 0.6s cubic-bezier(0.4,0,0.2,1), top 0.6s cubic-bezier(0.4,0,0.2,1)';
    player.style.left = (pos.x + 5) + '%';
    player.style.top = (pos.y - 5) + '%';
    this.playerPos = index;

    // تحديث شريط التقدم
    const progressFill = document.getElementById('mapProgressFill');
    if (progressFill) {
      progressFill.style.width = ((index + 1) / this.questions.length * 100) + '%';
    }

    setTimeout(() => {
      player.classList.remove('moving');
      if (callback) callback();
    }, 650);
  }

  /* ── تحديث حالة المحطات ── */
  _syncStations(activeIndex) {
    for (let i = 0; i < this.questions.length; i++) {
      const el = document.getElementById(`mapStation-${i}`);
      if (!el) continue;
      el.classList.remove('map-station-current', 'map-station-done');
      if (i < activeIndex) {
        el.classList.add('map-station-done');
      } else if (i === activeIndex) {
        el.classList.add('map-station-current');
      }
    }
  }

  /* ── عرض السؤال ── */
  _showQuestion(index) {
    const question = this.questions[index];
    if (!question) return;
    this.questionAttempts = 0;

    const modal = document.getElementById('mapQuestionModal');
    const counter = document.getElementById('mapCounter');
    const qCounter = document.getElementById('mapQCounter');
    const qScore = document.getElementById('mapQScore');
    const text = document.getElementById('mapQText');
    const options = document.getElementById('mapQOptions');
    const feedback = document.getElementById('mapQFeedback');
    const nextBtn = document.getElementById('mapNextBtn');
    if (!modal || !text || !options || !feedback || !nextBtn) return;

    if (counter) counter.textContent = `السؤال ${index + 1} من ${this.questions.length}`;
    if (qCounter) qCounter.textContent = `السؤال ${index + 1} من ${this.questions.length}`;
    if (qScore) qScore.textContent = `⭐ ${this.score}`;
    text.textContent = question.question_text || '';
    feedback.style.display = 'none';
    feedback.className = 'map-q-feedback';
    nextBtn.style.display = 'none';
    nextBtn.textContent = 'التالي ←';

    const labels = { a: 'أ', b: 'ب', c: 'ج', d: 'د' };
    options.innerHTML = ['a', 'b', 'c', 'd'].map(key => `
      <button class="map-option-btn" data-key="${key}">
        <span class="map-opt-label">${labels[key]}</span>
        <span>${question['option_' + key] ?? ''}</span>
      </button>
    `).join('');

    options.querySelectorAll('.map-option-btn').forEach(btn => {
      btn.addEventListener('click', () => this._answerQuestion(btn.dataset.key, question));
    });

    modal.style.display = 'flex';
    this._questionVisible = true;
  }

  /* ── معالجة الإجابة ── */
  _answerQuestion(choice, question) {
    const options = document.getElementById('mapQOptions');
    const feedback = document.getElementById('mapQFeedback');
    const nextBtn = document.getElementById('mapNextBtn');
    if (!options || !feedback || !nextBtn) return;

    const choiceBtn = options.querySelector(`[data-key="${choice}"]`);
    const isCorrect = choice === question.correct_option;

    if (isCorrect) {
      options.querySelectorAll('.map-option-btn').forEach(btn => {
        btn.disabled = true;
        if (btn.dataset.key === question.correct_option) btn.classList.add('correct');
      });
      this.score += 1;
      this._pushUnique(this.correctQuestionNumbers, this.current + 1);
      this._recordAttempt(question.id, true, this.questionAttempts + 1);
      GameSFX.correct();
      this._setMapFeedback('correct', question.feedback_correct || '✅ إجابة صحيحة! أحسنت.');
      this._showVisualEffect('✅');
      this._finalizeMapQuestion('correct', this.questionAttempts + 1, nextBtn);
      return;
    }

    this.questionAttempts += 1;
    this.totalWrongAnswers += 1;
    GameSFX.wrong();
    if (choiceBtn) {
      choiceBtn.classList.add('wrong');
      choiceBtn.disabled = true;
    }
    this._recordAttempt(question.id, false, this.questionAttempts);

    if (this.totalWrongAnswers >= this.maxWrongAnswers) {
      options.querySelectorAll('.map-option-btn').forEach(btn => {
        btn.disabled = true;
        if (btn.dataset.key === question.correct_option) btn.classList.add('correct');
      });
      this.errors += 1;
      this._pushUnique(this.wrongQuestionNumbers, this.current + 1);
      this._setMapFeedback('wrong', '😵‍💫 أخطأت مرتين! انتهت المغامرة.');
      this._finalizeMapQuestion('wrong', this.questionAttempts, null, true);
      setTimeout(() => this._showFailureResult(), 1200);
      return;
    }

    if (this.questionAttempts >= 2) {
      options.querySelectorAll('.map-option-btn').forEach(btn => {
        btn.disabled = true;
        if (btn.dataset.key === question.correct_option) btn.classList.add('correct');
      });
      this.errors += 1;
      this._pushUnique(this.wrongQuestionNumbers, this.current + 1);
      this._setMapFeedback('wrong', question.feedback_correct || '❌ الإجابة الصحيحة مميزة باللون الأخضر.');
      this._finalizeMapQuestion('wrong', this.questionAttempts, nextBtn);
    } else {
      this._setMapFeedback('warning', '⚠️ إجابة غير صحيحة، لديك محاولة ثانية.');
    }
  }

  _setMapFeedback(type, message) {
    const feedback = document.getElementById('mapQFeedback');
    if (!feedback) return;
    feedback.style.display = 'block';
    feedback.className = `map-q-feedback ${type}`;
    feedback.textContent = message;
  }

  _showVisualEffect(emoji) {
    const el = document.createElement('div');
    el.className = 'sound-effect';
    el.textContent = emoji;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1100);
  }

  _finalizeMapQuestion(status, attemptsUsed, nextBtn, stopGame = false) {
    const question = this.questions[this.current];
    this.questionOutcomes.push({
      question_number: this.current + 1,
      question_id: Number(question?.id || 0),
      status,
      attempts_used: attemptsUsed
    });

    const station = document.getElementById(`mapStation-${this.current}`);
    if (station) {
      station.classList.remove('map-station-current');
      station.classList.add('map-station-done');
      if (status === 'correct') station.classList.add('map-station-correct');
      else station.classList.add('map-station-wrong');
    }

    // تحديث النقاط
    const qScore = document.getElementById('mapQScore');
    if (qScore) qScore.textContent = `⭐ ${this.score}`;

    if (stopGame) return;

    this.current += 1;
    if (this.current >= this.questions.length) {
      if (nextBtn) {
        nextBtn.textContent = '🏁 إنهاء المغامرة';
        nextBtn.onclick = () => {
          this._hideQuestionModal();
          this._showResult();
        };
        nextBtn.style.display = 'inline-flex';
      }
    } else {
      if (nextBtn) {
        nextBtn.textContent = 'التالي ←';
        nextBtn.onclick = () => {
          this._hideQuestionModal();
          // انتظار نقر الطالب على المحطة التالية
          this._waitForStationClick(this.current);
        };
        nextBtn.style.display = 'inline-flex';
      }
    }
  }

  _hideQuestionModal() {
    const modal = document.getElementById('mapQuestionModal');
    if (modal) modal.style.display = 'none';
    this._questionVisible = false;
  }

  /* ── نتيجة النجاح ── */
  _showResult() {
    const total = this.questions.length;
    const completedCount = this.questionOutcomes.length;
    const incompleteCount = Math.max(0, total - completedCount);
    const won = this.score >= total && total > 0;
    const points = won ? Math.round(350 * (this.score / Math.max(1, total))) : 0;
    const scholar = (won && this.scholars.length)
      ? this.scholars[Math.floor(Math.random() * this.scholars.length)]
      : null;

    this.completed = true;
    this._saveResult({
      completed: won ? 1 : 0, points,
      scholarId: scholar?.id || null,
      completedQuestions: completedCount,
      incompleteQuestions: incompleteCount
    });

    if (won) GameSFX.win(); else GameSFX.lose();

    const overlay = document.getElementById('mapOverlay');
    if (!overlay) return;
    overlay.innerHTML = `
      <div class="map-result-box">
        <div class="map-result-icon">${won ? '🏆' : '✨'}</div>
        <h3>${won ? 'مبروك! اجتزت المغامرة' : 'مغامرة غير مكتملة'}</h3>
        <div class="map-result-stats">
          <div class="map-result-stat">
            <div class="stat-value">${this.score}/${total}</div>
            <div class="stat-label">إجابات صحيحة</div>
          </div>
          <div class="map-result-stat">
            <div class="stat-value">${points}</div>
            <div class="stat-label">نقاط مكتسبة</div>
          </div>
        </div>
        ${scholar ? `<div class="map-scholar-box">
          <div class="scholar-icon">📜</div>
          <div class="scholar-name">اكتشفت: ${scholar.name}</div>
          ${scholar.short_bio ? `<div class="scholar-bio">${scholar.short_bio}</div>` : ''}
        </div>` : ''}
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">
          <button class="btn btn-primary" id="mapReplayBtn">🔄 إعادة المحاولة</button>
        </div>
      </div>
    `;
    overlay.style.display = 'flex';
    document.getElementById('mapReplayBtn')?.addEventListener('click', () => this.restart());
  }

  /* ── نتيجة الفشل ── */
  _showFailureResult() {
    const total = this.questions.length;
    const completedCount = this.questionOutcomes.length;
    const incompleteCount = Math.max(0, total - completedCount);

    this.completed = true;
    this._saveResult({
      completed: 0, points: 0, scholarId: null,
      completedQuestions: completedCount,
      incompleteQuestions: incompleteCount
    });
    this._playFallSound();
    GameSFX.lose();

    this._hideQuestionModal();
    const overlay = document.getElementById('mapOverlay');
    if (!overlay) return;
    overlay.innerHTML = `
      <div class="map-result-box">
        <div class="map-result-icon">😵‍💫💥</div>
        <h3>سقطت في المغامرة!</h3>
        <p>لقد أخطأت مرتين، لذلك انتهت المغامرة مباشرة.</p>
        <div class="map-result-stats">
          <div class="map-result-stat">
            <div class="stat-value">${this.score}/${total}</div>
            <div class="stat-label">إجابات صحيحة</div>
          </div>
          <div class="map-result-stat">
            <div class="stat-value">0</div>
            <div class="stat-label">نقاط مكتسبة</div>
          </div>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">
          <button class="btn btn-primary" id="mapReplayBtn">🔄 إعادة المحاولة</button>
          <button class="btn btn-outline" id="mapExitBtn">🚪 خروج من اللعبة</button>
        </div>
      </div>
    `;
    overlay.style.display = 'flex';
    document.getElementById('mapReplayBtn')?.addEventListener('click', () => this.restart());
    document.getElementById('mapExitBtn')?.addEventListener('click', () => window.location.reload());
  }

  /* ── إعادة اللعبة ── */
  restart() {
    this.current = 0;
    this.score = 0;
    this.errors = 0;
    this.totalWrongAnswers = 0;
    this.questionAttempts = 0;
    this.questions = AdventureGame.pickRandomQuestions(this.questionsPool, 5);
    this.correctQuestionNumbers = [];
    this.wrongQuestionNumbers = [];
    this.questionOutcomes = [];
    this.startedAt = Date.now();
    this.resultSaved = false;
    this.completed = false;
    this.playerPos = -1;
    this._questionVisible = false;
    this._waitingForClick = false;
    this.render();
    this.start();
  }

  /* ── التوافق: لا نستخدم عناصر enhanced ── */
  _syncSteps() {}
  _setFeedback() {}
}

window.MapAdventureGame = MapAdventureGame;
