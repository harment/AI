// =============================================
// محرك لعبة المغامرة التعليمية المحسّن
// =============================================

class AdventureGame {
  constructor(options) {
    this.lessonId         = options.lessonId;
    this.gameType         = options.gameType || 'mountain'; // mountain | maze | ship | island
    this.allQuestions     = options.questions || [];
    this.questions        = this._selectRandomQuestions(this.allQuestions, 5); // اختيار 5 أسئلة عشوائياً
    this.scholars         = options.scholars  || [];
    this.container        = document.getElementById(options.containerId || 'gameContainer');
    this.current          = 0;
    this.errors           = 0;
    this.score            = 0;
    this.questionAttempts = 0;
    this.completed        = false;
    this.BASE_PTS         = options.basePoints || 350;
    this.questionResults  = []; // تتبع نتائج كل سؤال
    
    this.sounds           = {
      correct : new Audio('/assets/sounds/correct.mp3'),
      wrong   : new Audio('/assets/sounds/wrong.mp3'),
      win     : new Audio('/assets/sounds/win.mp3'),
      lose    : new Audio('/assets/sounds/lose.mp3'),
      progress: new Audio('/assets/sounds/progress.mp3'),
    };
    
    this.render();
  }

  _selectRandomQuestions(allQuestions, count) {
    // اختيار عدد محدد من الأسئلة بشكل عشوائي
    const shuffled = AdventureGame.shuffle(allQuestions);
    return shuffled.slice(0, Math.min(count, shuffled.length));
  }

  render() {
    if (!this.container) return;
    this.container.innerHTML = this._buildGameUI();
    this._updateProgress();
  }

  _buildGameUI() {
    const bgClass = { 
      mountain: 'mountain-bg', 
      maze: 'maze-bg', 
      ship: 'ship-bg',
      island: 'island-bg'
    }[this.gameType] || 'mountain-bg';
    
    const characterIcon = {
      mountain: '🧗‍♂️',
      maze: '🚶‍♂️',
      ship: '⛵',
      island: '🏃‍♂️'
    }[this.gameType] || '🧗‍♂️';
    
    return `
    <div class="${bgClass} game-bg-layer"></div>
    <div class="game-journey-path" id="journeyPath">
      ${this._buildJourneyStations()}
    </div>
    <div class="game-character" id="gameCharacter">${characterIcon}</div>
    <div class="game-question-box" id="questionBox">
      <div class="question-header">
        <div class="question-counter" id="qCounter">السؤال 1 من ${this.questions.length}</div>
        <div class="question-score">النقاط: <span id="currentScore">0</span></div>
      </div>
      <div class="question-text"  id="qText"></div>
      <div class="options-grid"   id="optionsGrid"></div>
      <div id="feedbackBox" style="display:none;padding:.85rem;border-radius:8px;margin-top:.85rem;font-weight:600;"></div>
      <button class="btn btn-primary btn-block" id="nextBtn" style="display:none;margin-top:1rem;">التالي ←</button>
    </div>
    <div class="game-overlay" id="gameOverlay" style="display:none"></div>`;
  }

  _buildJourneyStations() {
    const totalStations = this.questions.length + 1; // محطات الأسئلة + القمة
    let stations = '';
    
    for (let i = 0; i < totalStations; i++) {
      const isGoal = i === totalStations - 1;
      const icon = isGoal ? '🏆' : (i + 1);
      const label = isGoal ? 'الهدف' : `محطة ${i + 1}`;
      
      stations += `
        <div class="journey-station ${i === 0 ? 'active' : ''}" id="station-${i}" data-index="${i}">
          <div class="station-icon">${icon}</div>
          <div class="station-label">${label}</div>
        </div>`;
    }
    
    return stations;
  }

  start() {
    this._showQuestion(0);
  }

  _showQuestion(idx) {
    const q = this.questions[idx];
    if (!q) return;

    this.questionAttempts = 0;

    const qText = document.getElementById('qText');
    const opts  = document.getElementById('optionsGrid');
    const fb    = document.getElementById('feedbackBox');
    const next  = document.getElementById('nextBtn');
    const ctr   = document.getElementById('qCounter');

    ctr.textContent   = `السؤال ${idx + 1} من ${this.questions.length}`;
    qText.textContent = q.question_text;
    fb.style.display  = 'none';
    next.style.display = 'none';
    next.textContent  = 'التالي ←';

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
    const opts      = document.getElementById('optionsGrid');
    const fb        = document.getElementById('feedbackBox');
    const next      = document.getElementById('nextBtn');
    const chosenBtn = opts.querySelector(`[data-key="${chosen}"]`);

    if (chosen === q.correct_option) {
      // إجابة صحيحة
      opts.querySelectorAll('.option-btn').forEach(b => {
        b.disabled = true;
        if (b.dataset.key === q.correct_option) b.classList.add('correct');
      });
      this._playSound('correct');
      fb.style.background = '#E8F5E9';
      fb.style.color      = '#2E7D32';
      fb.innerHTML        = '✅ ' + (q.feedback_correct || 'إجابة صحيحة! أحسنت.');
      fb.style.display    = 'block';
      this.score++;
      
      // حفظ نتيجة السؤال
      this.questionResults.push({
        question_id: q.id,
        is_correct: true,
        attempts: this.questionAttempts + 1
      });
      
      this._updateScore();
      this._markDoneAndAdvance(next);
    } else {
      this.questionAttempts++;
      this._playSound('wrong');
      chosenBtn.classList.add('wrong');
      chosenBtn.disabled = true;

      if (this.questionAttempts >= 2) {
        // المحاولة الثانية خاطئة: إظهار الإجابة الصحيحة مع التغذية الراجعة
        opts.querySelectorAll('.option-btn').forEach(b => {
          b.disabled = true;
          if (b.dataset.key === q.correct_option) b.classList.add('correct');
        });
        fb.style.background = '#FFEBEE';
        fb.style.color      = '#C62828';
        
        // تغذية راجعة تفصيلية
        const correctAnswerText = q['option_' + q.correct_option];
        fb.innerHTML = `
          ❌ <strong>إجابة خاطئة</strong><br>
          <div style="margin-top:0.5rem;padding:0.5rem;background:rgba(46,125,50,0.1);border-radius:6px;">
            الإجابة الصحيحة: <strong>${correctAnswerText}</strong>
          </div>
          ${q.feedback_correct ? `<div style="margin-top:0.5rem;font-size:0.9em;">${q.feedback_correct}</div>` : ''}
        `;
        fb.style.display    = 'block';
        this.errors++;
        
        // حفظ نتيجة السؤال
        this.questionResults.push({
          question_id: q.id,
          is_correct: false,
          attempts: 2
        });
        
        this._markDoneAndAdvance(next);
      } else {
        // المحاولة الأولى خاطئة: تشجيع على المحاولة مرة أخرى
        fb.style.background = '#FFF3E0';
        fb.style.color      = '#E65100';
        fb.innerHTML        = '⚠️ <strong>إجابة خاطئة!</strong> لديك محاولة أخرى. فكر جيداً...';
        fb.style.display    = 'block';
      }
    }
  }

  _updateScore() {
    const scoreElem = document.getElementById('currentScore');
    if (scoreElem) {
      scoreElem.textContent = this.score;
    }
  }

  _markDoneAndAdvance(nextBtn) {
    // تحديث محطة الرحلة
    const currentStation = document.getElementById(`station-${this.current}`);
    if (currentStation) {
      currentStation.classList.add('completed');
      currentStation.classList.remove('active');
    }
    
    // تحريك الشخصية
    this._animateCharacter(this.current, this.current + 1);
    
    this.current++;
    
    if (this.current >= this.questions.length) {
      nextBtn.textContent = '🏆 انهاء المغامرة';
      nextBtn.onclick     = () => this._showResult(true);
    } else {
      // تفعيل المحطة التالية
      const nextStation = document.getElementById(`station-${this.current}`);
      if (nextStation) {
        nextStation.classList.add('active');
      }
      
      nextBtn.textContent = 'التالي ←';
      nextBtn.onclick     = () => this._nextQuestion();
    }
    nextBtn.style.display = 'block';
  }

  _animateCharacter(fromIndex, toIndex) {
    const character = document.getElementById('gameCharacter');
    if (!character) return;
    
    // تشغيل صوت التقدم
    this._playSound('progress');
    
    // إضافة تأثير الحركة
    character.style.transition = 'all 0.5s ease-in-out';
    character.classList.add('moving');
    
    setTimeout(() => {
      character.classList.remove('moving');
    }, 500);
  }

  _nextQuestion() {
    if (this.current < this.questions.length) {
      this._showQuestion(this.current);
    }
  }

  _updateProgress() {}

  _showResult(won) {
    this._playSound(won ? 'win' : 'lose');
    const accuracy = this.score / this.questions.length;
    const pts = won ? Math.round(this.BASE_PTS * accuracy * (1 + 0.1 * (this.questions.length - this.errors))) : 0;
    const scholar = won && accuracy >= 0.8 ? this.scholars[Math.floor(Math.random() * this.scholars.length)] : null;

    const overlay = document.getElementById('gameOverlay');
    overlay.innerHTML = `
    <div class="game-result-box">
      <div class="result-icon">${won ? '🏆' : '😢'}</div>
      <div class="result-title" style="color:${won ? 'var(--primary)' : 'var(--danger)'}">
        ${won ? 'مبروك! أكملت المغامرة بنجاح' : 'حاول مرة أخرى!'}
      </div>
      <div class="result-stats">
        <div class="result-stat">
          <div class="stat-value">${this.score}</div>
          <div class="stat-label">من ${this.questions.length}</div>
          <div class="stat-desc">إجابات صحيحة</div>
        </div>
        <div class="result-stat">
          <div class="stat-value">${Math.round(accuracy * 100)}%</div>
          <div class="stat-label">نسبة النجاح</div>
        </div>
        ${pts > 0 ? `<div class="result-stat">
          <div class="stat-value">${pts}</div>
          <div class="stat-label">نقطة</div>
          <div class="stat-desc">تم إضافتها</div>
        </div>` : ''}
      </div>
      ${scholar ? `<div class="scholar-card">
        <div class="scholar-icon">👨‍🏫</div>
        <div class="scholar-name">🎉 اكتشفت عالماً جديداً: ${scholar.name}</div>
        <div class="scholar-bio">${scholar.short_bio}</div>
      </div>` : ''}
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem;">
        <button class="btn btn-primary" onclick="window.location.reload()">🔄 العودة للدرس</button>
      </div>
    </div>`;
    overlay.style.display = 'flex';

    // حفظ النتيجة على الخادم
    this._saveResult(won, pts, scholar?.id || null);
  }

  restart() {
    this.current          = 0;
    this.errors           = 0;
    this.score            = 0;
    this.questionAttempts = 0;
    this.questionResults  = [];
    this.questions        = this._selectRandomQuestions(this.allQuestions, 5);
    this.render();
    this.start();
  }

  _saveResult(won, pts, scholarId) {
    fetch('/api/games-enhanced.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        lesson_id: this.lessonId, 
        points: pts, 
        scholar_id: scholarId, 
        completed: won ? 1 : 0,
        game_mode: this.gameType,
        question_results: this.questionResults
      }),
    }).catch(err => console.error('Error saving game result:', err));
  }

  _playSound(key) {
    try {
      const s = this.sounds[key];
      if (s) {
        s.currentTime = 0;
        s.play().catch(() => {});
      }
    } catch {}
  }

  static shuffle(arr) {
    return arr.slice().sort(() => Math.random() - 0.5);
  }
}

window.AdventureGame = AdventureGame;
