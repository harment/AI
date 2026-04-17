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
  }

  /* ── ثيمات الخريطة ── */
  static get THEMES() {
    return {
      mountain: {
        label: 'مغامرة الجبل', emoji: '⛰️',
        bg: 'linear-gradient(180deg,#1a365d 0%,#2d6a4f 40%,#52b788 70%,#95d5b2 100%)',
        pathColor: '#fbbf24', stationColor: '#f59e0b', stationText: '#78350f',
        playerEmoji: '🧗', doneColor: '#22c55e',
        decorations: [
          { emoji: '🏔️', x: '10%', y: '18%', size: '2.5rem' },
          { emoji: '⛰️', x: '80%', y: '25%', size: '2rem' },
          { emoji: '🌤️', x: '85%', y: '8%', size: '1.8rem' },
          { emoji: '🦅', x: '15%', y: '8%', size: '1.5rem' },
          { emoji: '🌿', x: '5%', y: '75%', size: '1.3rem' },
          { emoji: '🪨', x: '90%', y: '70%', size: '1.2rem' },
        ]
      },
      island: {
        label: 'مغامرة الجزيرة', emoji: '🏝️',
        bg: 'linear-gradient(180deg,#0ea5e9 0%,#38bdf8 30%,#7dd3fc 60%,#e0f2fe 85%,#fef9c3 100%)',
        pathColor: '#f97316', stationColor: '#ea580c', stationText: '#fff',
        playerEmoji: '🚶', doneColor: '#10b981',
        decorations: [
          { emoji: '🏝️', x: '8%', y: '20%', size: '2.5rem' },
          { emoji: '🌴', x: '85%', y: '30%', size: '2rem' },
          { emoji: '☀️', x: '90%', y: '6%', size: '2rem' },
          { emoji: '🐚', x: '12%', y: '80%', size: '1.3rem' },
          { emoji: '🦀', x: '88%', y: '75%', size: '1.2rem' },
          { emoji: '⛵', x: '5%', y: '45%', size: '1.5rem' },
        ]
      },
      lake: {
        label: 'مغامرة البحر', emoji: '⛵',
        bg: 'linear-gradient(180deg,#0c4a6e 0%,#075985 25%,#0284c7 50%,#38bdf8 75%,#bae6fd 100%)',
        pathColor: '#fbbf24', stationColor: '#d97706', stationText: '#fff',
        playerEmoji: '⛵', doneColor: '#34d399',
        decorations: [
          { emoji: '🌊', x: '8%', y: '60%', size: '2rem' },
          { emoji: '🐬', x: '85%', y: '45%', size: '1.8rem' },
          { emoji: '🌅', x: '90%', y: '8%', size: '2rem' },
          { emoji: '⚓', x: '10%', y: '80%', size: '1.5rem' },
          { emoji: '🐟', x: '50%', y: '85%', size: '1.2rem' },
          { emoji: '🦈', x: '15%', y: '35%', size: '1.3rem' },
        ]
      },
      forest: {
        label: 'مغامرة الغابة', emoji: '🌲',
        bg: 'linear-gradient(180deg,#064e3b 0%,#065f46 25%,#047857 50%,#10b981 75%,#6ee7b7 100%)',
        pathColor: '#a16207', stationColor: '#92400e', stationText: '#fff',
        playerEmoji: '🏃', doneColor: '#fbbf24',
        decorations: [
          { emoji: '🌲', x: '6%', y: '15%', size: '2.5rem' },
          { emoji: '🌳', x: '88%', y: '22%', size: '2.2rem' },
          { emoji: '🍃', x: '82%', y: '8%', size: '1.5rem' },
          { emoji: '🦊', x: '10%', y: '75%', size: '1.5rem' },
          { emoji: '🌿', x: '90%', y: '70%', size: '1.3rem' },
          { emoji: '🍄', x: '15%', y: '50%', size: '1.2rem' },
        ]
      }
    };
  }

  /* ── حساب مواقع المحطات على مسار متعرج ── */
  _stationPositions() {
    const count = this.questions.length || 5;
    const positions = [];
    const padX = 15;
    const padY = 18;
    const usableW = 100 - padX * 2;
    const usableH = 100 - padY * 2;
    for (let i = 0; i < count; i++) {
      const t = count > 1 ? i / (count - 1) : 0.5;
      const x = padX + (usableW * (1 - t));
      const y = padY + (usableH * t) + Math.sin(t * Math.PI * 2) * 8;
      positions.push({ x, y });
    }
    return positions;
  }

  /* ── بناء مسار SVG ── */
  _buildPath(positions) {
    if (positions.length < 2) return '';
    let d = `M ${positions[0].x} ${positions[0].y}`;
    for (let i = 1; i < positions.length; i++) {
      const prev = positions[i - 1];
      const curr = positions[i];
      const cpx1 = prev.x - (curr.x - prev.x) * 0.3;
      const cpy1 = prev.y + (curr.y - prev.y) * 0.5;
      const cpx2 = curr.x + (curr.x - prev.x) * 0.3;
      const cpy2 = curr.y - (curr.y - prev.y) * 0.5;
      d += ` C ${cpx1} ${cpy1}, ${cpx2} ${cpy2}, ${curr.x} ${curr.y}`;
    }
    return d;
  }

  /* ── الرسم الرئيسي ── */
  render() {
    if (!this.container) return;
    const theme = MapAdventureGame.THEMES[this.mapTheme] || MapAdventureGame.THEMES.mountain;
    const positions = this._stationPositions();
    const pathD = this._buildPath(positions);

    const decorationsHTML = theme.decorations.map(d =>
      `<div class="map-decoration" style="left:${d.x};top:${d.y};font-size:${d.size};">${d.emoji}</div>`
    ).join('');

    const stationsHTML = positions.map((pos, i) => {
      const stateClass = i === 0 ? 'map-station-current' : '';
      return `<div class="map-station ${stateClass}" id="mapStation-${i}"
        style="left:${pos.x}%;top:${pos.y}%;"
        data-index="${i}">
        <div class="map-station-circle">${i + 1}</div>
      </div>`;
    }).join('');

    const startPos = positions[0] || { x: 50, y: 50 };

    this.container.innerHTML = `
      <div class="map-game-wrapper map-theme-${this.mapTheme}">
        <div class="map-bg-layer" style="background:${theme.bg};"></div>
        ${decorationsHTML}
        <svg class="map-path-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
          <path d="${pathD}" class="map-path-line" stroke="${theme.pathColor}" />
          <path d="${pathD}" class="map-path-line-glow" stroke="${theme.pathColor}" />
        </svg>
        ${stationsHTML}
        <div class="map-player" id="mapPlayer" style="left:${startPos.x}%;top:${startPos.y}%;">
          ${theme.playerEmoji}
        </div>
        <div class="map-header-bar">
          <span class="map-badge">${theme.emoji} ${theme.label}</span>
          <span class="map-counter" id="mapCounter">السؤال 1 من ${this.questions.length}</span>
        </div>
        <div class="map-question-modal" id="mapQuestionModal" style="display:none;">
          <div class="map-question-box">
            <div class="map-q-counter" id="mapQCounter"></div>
            <h3 class="map-q-text" id="mapQText"></h3>
            <div class="map-q-options" id="mapQOptions"></div>
            <div class="map-q-feedback" id="mapQFeedback" style="display:none;"></div>
            <button class="btn btn-primary" id="mapNextBtn" style="display:none;">التالي ←</button>
          </div>
        </div>
        <div class="map-overlay" id="mapOverlay" style="display:none;"></div>
      </div>
    `;

    this._positions = positions;
    this._theme = theme;
  }

  /* ── بدء اللعبة ── */
  start() {
    if (!this.questions.length || !this.container) {
      this._showError('لا توجد أسئلة متاحة لهذا الدرس.');
      return;
    }
    this.playerPos = -1;
    this._movePlayerToStation(0, () => {
      this._showQuestion(0);
    });
  }

  /* ── تحريك اللاعب ── */
  _movePlayerToStation(index, callback) {
    const player = document.getElementById('mapPlayer');
    const pos = this._positions[index];
    if (!player || !pos) { if (callback) callback(); return; }

    player.style.transition = 'left 0.6s ease, top 0.6s ease';
    player.style.left = pos.x + '%';
    player.style.top = pos.y + '%';
    this.playerPos = index;

    // تنشيط المحطة
    this._syncStations(index);

    setTimeout(() => { if (callback) callback(); }, 650);
  }

  /* ── تحديث حالة المحطات ── */
  _syncStations(activeIndex) {
    const theme = this._theme || {};
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
    const text = document.getElementById('mapQText');
    const options = document.getElementById('mapQOptions');
    const feedback = document.getElementById('mapQFeedback');
    const nextBtn = document.getElementById('mapNextBtn');
    if (!modal || !text || !options || !feedback || !nextBtn) return;

    if (counter) counter.textContent = `السؤال ${index + 1} من ${this.questions.length}`;
    if (qCounter) qCounter.textContent = `السؤال ${index + 1} من ${this.questions.length}`;
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
      this._setMapFeedback('correct', question.feedback_correct || 'إجابة صحيحة! أحسنت. ✅');
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
      this._setMapFeedback('wrong', 'أخطأت مرتين! انتهت المغامرة. 😵‍💫');
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
      this._setMapFeedback('wrong', question.feedback_correct || 'الإجابة الصحيحة مميزة باللون الأخضر.');
      this._finalizeMapQuestion('wrong', this.questionAttempts, nextBtn);
    } else {
      this._setMapFeedback('warning', 'إجابة غير صحيحة، لديك محاولة ثانية. ⚠️');
    }
  }

  _setMapFeedback(type, message) {
    const feedback = document.getElementById('mapQFeedback');
    if (!feedback) return;
    feedback.style.display = 'block';
    feedback.className = `map-q-feedback ${type}`;
    feedback.textContent = message;
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
          this._movePlayerToStation(this.current, () => {
            this._showQuestion(this.current);
          });
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
        <h3>${won ? 'أحسنت! أتممت المغامرة 100%' : 'مغامرة غير مكتملة 100%'}</h3>
        <p>الإجابات الصحيحة: <strong>${this.score}</strong> من <strong>${total}</strong></p>
        <p>الحالة: <strong>${won ? 'مكتملة 100%' : 'غير مكتملة 100%'}</strong></p>
        <p>النقاط المكتسبة: <strong>${points}</strong></p>
        ${scholar ? `<div class="map-scholar-box">📜 اكتشفت: <strong>${scholar.name}</strong></div>` : ''}
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
        <p>الحالة: <strong>غير مكتملة 100%</strong></p>
        <p>النقاط المكتسبة: <strong>0</strong></p>
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
    this.render();
    this.start();
  }

  /* ── التوافق: لا نستخدم عناصر enhanced ── */
  _syncSteps() {}
  _setFeedback() {}
}

window.MapAdventureGame = MapAdventureGame;
