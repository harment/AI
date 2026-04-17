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
  /* الجبل: تسلق من أسفل يمين الجبل متمايلاً حتى القمة */
  /* البحيرة: متعرج عبر الأمواج كأنه يبحر */
  /* الغابة: ممر ملتوي بين الأشجار */
  /* المتاهة: مسار حلزوني من الخارج إلى المركز */
  static getStationPositions(theme, count) {
    const positions = {
      mountain: [
        { x: 70, y: 88 }, { x: 45, y: 74 }, { x: 62, y: 58 },
        { x: 38, y: 44 }, { x: 55, y: 28 }, { x: 48, y: 12 }, { x: 50, y: 4 }
      ],
      island: [
        { x: 80, y: 82 }, { x: 60, y: 78 }, { x: 38, y: 70 },
        { x: 25, y: 55 }, { x: 35, y: 38 }, { x: 55, y: 25 }, { x: 70, y: 12 }
      ],
      lake: [
        { x: 12, y: 82 }, { x: 35, y: 68 }, { x: 65, y: 78 },
        { x: 82, y: 58 }, { x: 55, y: 42 }, { x: 28, y: 30 }, { x: 50, y: 12 }
      ],
      forest: [
        { x: 78, y: 88 }, { x: 42, y: 76 }, { x: 18, y: 62 },
        { x: 50, y: 48 }, { x: 75, y: 35 }, { x: 32, y: 20 }, { x: 55, y: 6 }
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
          { emoji: '🌤️', x: '10%', y: '6%', size: '1.8rem' },
          { emoji: '🦅', x: '22%', y: '10%', size: '1.5rem' },
          { emoji: '🌿', x: '6%', y: '65%', size: '1.3rem' },
          { emoji: '🪨', x: '15%', y: '82%', size: '1.1rem' },
          { emoji: '🌲', x: '10%', y: '90%', size: '1.4rem' },
          { emoji: '🏳️', x: '50%', y: '2%', size: '1.2rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <rect width="400" height="300" fill="transparent"/>
            <!-- جبل بعيد يسار -->
            <polygon points="0,300 70,170 150,300" fill="#6d4c22" opacity="0.18"/>
            <polygon points="50,185 70,170 90,185" fill="#fff" opacity="0.2"/>
            <!-- جبل رئيسي بني كبير -->
            <polygon points="80,300 250,30 400,300" fill="#8B6914" opacity="0.5"/>
            <!-- طبقة جبلية داكنة -->
            <polygon points="120,300 250,50 380,300" fill="#7a5c10" opacity="0.22"/>
            <!-- تفاصيل صخرية -->
            <polygon points="160,300 200,200 240,300" fill="#6d4c22" opacity="0.12"/>
            <polygon points="280,300 310,220 350,300" fill="#8B6914" opacity="0.15"/>
            <!-- رأس أبيض ثلجي كبير -->
            <polygon points="222,72 250,30 278,72" fill="#fff" opacity="0.75"/>
            <polygon points="230,85 250,45 270,85" fill="#e8e8e8" opacity="0.45"/>
            <polygon points="238,65 250,38 262,65" fill="#f0f0f0" opacity="0.55"/>
            <!-- جبل صغير يمين -->
            <polygon points="300,300 360,220 400,300" fill="#a0782c" opacity="0.2"/>
            <!-- عشب أسفل -->
            <ellipse cx="200" cy="298" rx="200" ry="10" fill="#4ade80" opacity="0.25"/>
            <ellipse cx="100" cy="296" rx="60" ry="6" fill="#22c55e" opacity="0.15"/>
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
        label: 'مغامرة البحيرة', emoji: '⛵',
        bgClass: 'ship-bg',
        playerEmoji: '🚣',
        finishEmoji: '🏴‍☠️',
        decorations: [
          { emoji: '🌅', x: '8%', y: '4%', size: '1.8rem' },
          { emoji: '🐬', x: '85%', y: '40%', size: '1.5rem' },
          { emoji: '⚓', x: '6%', y: '50%', size: '1.4rem' },
          { emoji: '🐟', x: '90%', y: '85%', size: '1.1rem' },
          { emoji: '🌊', x: '3%', y: '92%', size: '1.3rem' },
          { emoji: '🦈', x: '88%', y: '65%', size: '1.2rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- بحيرة/بحر بأمواج متعددة -->
            <path d="M0,180 Q30,168 60,180 Q90,192 120,180 Q150,168 180,180 Q210,192 240,180 Q270,168 300,180 Q330,192 360,180 Q390,168 400,180 L400,300 L0,300 Z" fill="#0ea5e9" opacity="0.12">
              <animate attributeName="d" dur="3s" repeatCount="indefinite"
                values="M0,180 Q30,168 60,180 Q90,192 120,180 Q150,168 180,180 Q210,192 240,180 Q270,168 300,180 Q330,192 360,180 Q390,168 400,180 L400,300 L0,300 Z;
                        M0,180 Q30,192 60,180 Q90,168 120,180 Q150,192 180,180 Q210,168 240,180 Q270,192 300,180 Q330,168 360,180 Q390,192 400,180 L400,300 L0,300 Z;
                        M0,180 Q30,168 60,180 Q90,192 120,180 Q150,168 180,180 Q210,192 240,180 Q270,168 300,180 Q330,192 360,180 Q390,168 400,180 L400,300 L0,300 Z"/>
            </path>
            <path d="M0,220 Q40,208 80,220 Q120,232 160,220 Q200,208 240,220 Q280,232 320,220 Q360,208 400,220 L400,300 L0,300 Z" fill="#0284c7" opacity="0.1">
              <animate attributeName="d" dur="4s" repeatCount="indefinite"
                values="M0,220 Q40,208 80,220 Q120,232 160,220 Q200,208 240,220 Q280,232 320,220 Q360,208 400,220 L400,300 L0,300 Z;
                        M0,220 Q40,232 80,220 Q120,208 160,220 Q200,232 240,220 Q280,208 320,220 Q360,232 400,220 L400,300 L0,300 Z;
                        M0,220 Q40,208 80,220 Q120,232 160,220 Q200,208 240,220 Q280,232 320,220 Q360,208 400,220 L400,300 L0,300 Z"/>
            </path>
            <path d="M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z" fill="#0369a1" opacity="0.08">
              <animate attributeName="d" dur="5s" repeatCount="indefinite"
                values="M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z;
                        M0,260 Q50,272 100,260 Q150,248 200,260 Q250,272 300,260 Q350,248 400,260 L400,300 L0,300 Z;
                        M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z"/>
            </path>
            <!-- قارب صغير خشبي -->
            <polygon points="170,155 230,155 240,175 160,175" fill="#8B6914" opacity="0.25"/>
            <line x1="200" y1="125" x2="200" y2="155" stroke="#666" stroke-width="2" opacity="0.25"/>
            <polygon points="200,125 218,140 200,155" fill="#fff" opacity="0.3"/>
          </svg>`
      },
      forest: {
        label: 'مغامرة الغابة', emoji: '🌲',
        bgClass: 'forest-bg',
        playerEmoji: '🧭',
        finishEmoji: '🏆',
        decorations: [
          { emoji: '🍃', x: '88%', y: '6%', size: '1.5rem' },
          { emoji: '🦊', x: '8%', y: '75%', size: '1.4rem' },
          { emoji: '🍄', x: '85%', y: '85%', size: '1.1rem' },
          { emoji: '🌿', x: '4%', y: '42%', size: '1.2rem' },
          { emoji: '🦉', x: '90%', y: '28%', size: '1.3rem' },
          { emoji: '🦋', x: '15%', y: '15%', size: '1.1rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- أشجار كبيرة يسار -->
            <polygon points="40,300 65,120 90,300" fill="#15803d" opacity="0.32"/>
            <polygon points="25,300 65,145 105,300" fill="#166534" opacity="0.18"/>
            <rect x="60" y="235" width="10" height="65" fill="#92400e" opacity="0.3" rx="2"/>
            <!-- شجرة وسط -->
            <polygon points="155,300 185,95 215,300" fill="#22c55e" opacity="0.28"/>
            <polygon points="140,300 185,115 230,300" fill="#16a34a" opacity="0.15"/>
            <rect x="180" y="230" width="10" height="70" fill="#78350f" opacity="0.3" rx="2"/>
            <!-- شجرة يمين -->
            <polygon points="300,300 325,110 350,300" fill="#15803d" opacity="0.28"/>
            <polygon points="285,300 325,130 365,300" fill="#166534" opacity="0.15"/>
            <rect x="320" y="245" width="10" height="55" fill="#92400e" opacity="0.25" rx="2"/>
            <!-- شجيرات صغيرة -->
            <ellipse cx="120" cy="288" rx="22" ry="14" fill="#22c55e" opacity="0.22"/>
            <ellipse cx="260" cy="290" rx="28" ry="12" fill="#16a34a" opacity="0.2"/>
            <ellipse cx="380" cy="292" rx="18" ry="10" fill="#22c55e" opacity="0.18"/>
            <!-- ممر أرضي -->
            <path d="M380,298 Q300,285 250,290 Q180,296 120,286 Q60,278 0,292 L0,300 L400,300 Z" fill="#92400e" opacity="0.12"/>
            <!-- عشب -->
            <rect x="0" y="288" width="400" height="12" fill="#166534" opacity="0.12" rx="5"/>
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

    // رسم مسار منحنٍ بين المحطات عبر SVG (bezier curve)
    let pathD = '';
    if (stationPositions.length > 0) {
      const pts = stationPositions.map(p => ({ x: p.x * 4, y: p.y * 3 }));
      pathD = `M${pts[0].x},${pts[0].y}`;
      for (let i = 1; i < pts.length; i++) {
        const prev = pts[i - 1];
        const curr = pts[i];
        const cpx1 = prev.x + (curr.x - prev.x) * 0.5;
        const cpy1 = prev.y;
        const cpx2 = prev.x + (curr.x - prev.x) * 0.5;
        const cpy2 = curr.y;
        pathD += ` C${cpx1},${cpy1} ${cpx2},${cpy2} ${curr.x},${curr.y}`;
      }
    }

    // بناء المحطات بمواقع مطلقة على الخريطة
    const stationsHTML = this.questions.map((_, i) => {
      const pos = stationPositions[i] || { x: 50, y: 50 };
      const stateClass = i === 0 ? 'map-station-current' : '';
      const isLast = i === this.questions.length - 1;
      return `<div class="map-station map-station-abs ${stateClass}" id="mapStation-${i}" data-index="${i}"
                style="left:${pos.x}%;top:${pos.y}%;">
        <div class="map-station-circle">${isLast ? theme.finishEmoji : (i + 1)}</div>
      </div>`;
    }).join('');

    // الموقع الأولي للاعب (قبل أول محطة)
    const startPos = stationPositions[0] || { x: 50, y: 85 };

    this.container.innerHTML = `
      <div class="map-game-wrapper">
        <div class="map-bg-layer ${theme.bgClass}"></div>
        ${theme.sceneSVG || ''}
        <!-- مسار منحنٍ بين المحطات -->
        <svg class="map-path-svg" viewBox="0 0 400 300" preserveAspectRatio="none">
          <path d="${pathD}" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="3" stroke-dasharray="10,6" stroke-linecap="round"/>
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
