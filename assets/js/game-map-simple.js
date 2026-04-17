// =============================================
// لعبة الخريطة الرسومية – MapAdventureGame
// =============================================
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

  /* ── ثيمات الخريطة ── */
  static get THEMES() {
    return {
      mountain: {
        label: 'مغامرة الجبل', emoji: '⛰️',
        bgClass: 'mountain-bg',
        playerEmoji: '🧗',
        decorations: [
          { emoji: '🌤️', x: '12%', y: '8%', size: '1.8rem' },
          { emoji: '🦅', x: '25%', y: '12%', size: '1.5rem' },
          { emoji: '🌿', x: '8%', y: '70%', size: '1.3rem' },
          { emoji: '🪨', x: '45%', y: '80%', size: '1.1rem' },
        ],
        /* رسم جبل بني برأس أبيض بـ SVG */
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- سماء -->
            <rect width="400" height="300" fill="transparent"/>
            <!-- جبل بعيد -->
            <polygon points="50,300 150,120 250,300" fill="#6d4c22" opacity="0.25"/>
            <polygon points="130,140 150,120 170,140" fill="#fff" opacity="0.3"/>
            <!-- جبل رئيسي بني -->
            <polygon points="120,300 260,60 400,300" fill="#8B6914" opacity="0.4"/>
            <!-- رأس أبيض -->
            <polygon points="235,90 260,60 285,90" fill="#fff" opacity="0.55"/>
            <!-- جبل صغير -->
            <polygon points="0,300 80,180 160,300" fill="#a0782c" opacity="0.3"/>
          </svg>`
      },
      island: {
        label: 'مغامرة الجزيرة', emoji: '🏝️',
        bgClass: 'island-bg',
        playerEmoji: '🚶',
        decorations: [
          { emoji: '☀️', x: '15%', y: '6%', size: '2rem' },
          { emoji: '🌴', x: '30%', y: '55%', size: '1.8rem' },
          { emoji: '🐚', x: '12%', y: '82%', size: '1.2rem' },
          { emoji: '🦀', x: '50%', y: '88%', size: '1rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- جزيرة -->
            <ellipse cx="200" cy="260" rx="140" ry="40" fill="#f4d35e" opacity="0.35"/>
            <!-- نخلة -->
            <line x1="180" y1="260" x2="170" y2="190" stroke="#8B6914" stroke-width="4" opacity="0.4"/>
            <ellipse cx="155" cy="185" rx="25" ry="10" fill="#22c55e" opacity="0.35" transform="rotate(-20 155 185)"/>
            <ellipse cx="185" cy="180" rx="25" ry="10" fill="#16a34a" opacity="0.35" transform="rotate(15 185 180)"/>
            <!-- موج -->
            <path d="M0,280 Q50,270 100,280 Q150,290 200,280 Q250,270 300,280 Q350,290 400,280 L400,300 L0,300 Z" fill="#38bdf8" opacity="0.2"/>
          </svg>`
      },
      lake: {
        label: 'مغامرة البحر', emoji: '⛵',
        bgClass: 'ship-bg',
        playerEmoji: '⛵',
        decorations: [
          { emoji: '🌅', x: '15%', y: '6%', size: '1.8rem' },
          { emoji: '🐬', x: '35%', y: '45%', size: '1.5rem' },
          { emoji: '⚓', x: '10%', y: '78%', size: '1.4rem' },
          { emoji: '🐟', x: '50%', y: '85%', size: '1.1rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- أمواج -->
            <path d="M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z" fill="#0ea5e9" opacity="0.2">
              <animate attributeName="d" dur="3s" repeatCount="indefinite"
                values="M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z;
                        M0,240 Q40,255 80,240 Q120,225 160,240 Q200,255 240,240 Q280,225 320,240 Q360,255 400,240 L400,300 L0,300 Z;
                        M0,240 Q40,225 80,240 Q120,255 160,240 Q200,225 240,240 Q280,255 320,240 Q360,225 400,240 L400,300 L0,300 Z"/>
            </path>
            <path d="M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z" fill="#0284c7" opacity="0.15">
              <animate attributeName="d" dur="4s" repeatCount="indefinite"
                values="M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z;
                        M0,260 Q50,272 100,260 Q150,248 200,260 Q250,272 300,260 Q350,248 400,260 L400,300 L0,300 Z;
                        M0,260 Q50,248 100,260 Q150,272 200,260 Q250,248 300,260 Q350,272 400,260 L400,300 L0,300 Z"/>
            </path>
            <!-- قارب -->
            <polygon points="180,210 220,210 225,225 175,225" fill="#8B6914" opacity="0.3"/>
            <line x1="200" y1="190" x2="200" y2="210" stroke="#666" stroke-width="2" opacity="0.3"/>
            <polygon points="200,190 215,200 200,210" fill="#fff" opacity="0.3"/>
          </svg>`
      },
      forest: {
        label: 'مغامرة الغابة', emoji: '🌲',
        bgClass: 'maze-bg',
        playerEmoji: '🏃',
        decorations: [
          { emoji: '🍃', x: '20%', y: '8%', size: '1.5rem' },
          { emoji: '🦊', x: '10%', y: '72%', size: '1.4rem' },
          { emoji: '🍄', x: '40%', y: '82%', size: '1.1rem' },
          { emoji: '🌿', x: '48%', y: '60%', size: '1.2rem' },
        ],
        sceneSVG: `
          <svg class="map-scene-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMax meet">
            <!-- أشجار -->
            <polygon points="60,300 80,160 100,300" fill="#15803d" opacity="0.3"/>
            <polygon points="40,300 80,180 120,300" fill="#166534" opacity="0.2"/>
            <rect x="75" y="240" width="10" height="60" fill="#92400e" opacity="0.3"/>
            <polygon points="160,300 185,130 210,300" fill="#22c55e" opacity="0.25"/>
            <rect x="180" y="240" width="10" height="60" fill="#78350f" opacity="0.3"/>
            <polygon points="280,300 310,150 340,300" fill="#15803d" opacity="0.2"/>
            <rect x="305" y="250" width="10" height="50" fill="#92400e" opacity="0.25"/>
            <!-- عشب -->
            <rect x="0" y="280" width="400" height="20" fill="#166534" opacity="0.15" rx="5"/>
          </svg>`
      }
    };
  }

  /* ── الرسم الرئيسي ── */
  render() {
    if (!this.container) return;
    const theme = MapAdventureGame.THEMES[this.mapTheme] || MapAdventureGame.THEMES.mountain;

    const decorationsHTML = theme.decorations.map(d =>
      `<div class="map-decoration" style="left:${d.x};top:${d.y};font-size:${d.size};">${d.emoji}</div>`
    ).join('');

    const stationsHTML = this.questions.map((_, i) => {
      const stateClass = i === 0 ? 'map-station-current' : '';
      return `<div class="map-station ${stateClass}" id="mapStation-${i}" data-index="${i}">
        <div class="map-station-circle">${i + 1}</div>
        <div class="map-station-label">سؤال ${i + 1}</div>
      </div>`;
    }).join('');

    this.container.innerHTML = `
      <div class="map-game-wrapper">
        <div class="map-bg-layer ${theme.bgClass}"></div>
        ${theme.sceneSVG || ''}
        ${decorationsHTML}
        <div class="map-journey-path" id="mapJourneyPath">
          ${stationsHTML}
        </div>
        <div class="map-player" id="mapPlayer" style="top:15%;right:12%;">
          ${theme.playerEmoji}
        </div>
        <div class="map-header-bar">
          <span class="map-badge">${theme.emoji} ${theme.label}</span>
          <span class="map-counter" id="mapCounter">انقر على السؤال للبدء</span>
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
    if (counter) counter.textContent = `انقر على السؤال ${index + 1} للبدء`;

    const station = document.getElementById(`mapStation-${index}`);
    if (!station) return;

    const handler = () => {
      station.removeEventListener('click', handler);
      this._waitingForClick = false;
      this._movePlayerToStation(index, () => {
        this._showQuestion(index);
      });
    };

    station.addEventListener('click', handler);
  }

  /* ── تحريك اللاعب إلى المحطة ── */
  _movePlayerToStation(index, callback) {
    const player = document.getElementById('mapPlayer');
    const station = document.getElementById(`mapStation-${index}`);
    if (!player || !station) { if (callback) callback(); return; }

    // حساب موقع المحطة النسبي
    const path = document.getElementById('mapJourneyPath');
    if (!path) { if (callback) callback(); return; }

    // إضافة تأثير القفز
    player.classList.add('moving');

    // تحريك اللاعب إلى جانب المحطة
    const stationRect = station.getBoundingClientRect();
    const wrapperRect = this.container.querySelector('.map-game-wrapper').getBoundingClientRect();
    const targetTop = ((stationRect.top - wrapperRect.top) / wrapperRect.height * 100);
    const targetRight = ((wrapperRect.right - stationRect.right) / wrapperRect.width * 100) + 8;

    player.style.transition = 'top 0.5s ease-in-out, right 0.5s ease-in-out';
    player.style.top = targetTop + '%';
    player.style.right = targetRight + '%';
    this.playerPos = index;

    setTimeout(() => {
      player.classList.remove('moving');
      if (callback) callback();
    }, 550);
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
      this._setMapFeedback('correct', question.feedback_correct || '✅ إجابة صحيحة! أحسنت.');
      this._showVisualEffect('✅');
      this._finalizeMapQuestion('correct', this.questionAttempts + 1, nextBtn);
      return;
    }

    this.questionAttempts += 1;
    this.totalWrongAnswers += 1;
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
