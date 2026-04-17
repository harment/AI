class AdventureGame {
  constructor(options = {}) {
    this.lessonId = Number(options.lessonId || 0);
    this.gameType = options.gameType || 'mountain';
    this.questionsPool = Array.isArray(options.questions) ? options.questions : [];
    this.questions = AdventureGame.pickRandomQuestions(this.questionsPool, 5);
    this.scholars = Array.isArray(options.scholars) ? options.scholars : [];
    this.container = document.getElementById(options.containerId || 'gameContainer');
    this.current = 0;
    this.score = 0;
    this.errors = 0;
    this.questionAttempts = 0;
    this.startedAt = Date.now();
    this.resultSaved = false;
    this.completed = false;
    this.correctQuestionNumbers = [];
    this.wrongQuestionNumbers = [];
    this.questionOutcomes = [];
    this._bindUnload();
    this.render();
  }

  static shuffle(arr) {
    return arr.slice().sort(() => Math.random() - 0.5);
  }

  static pickRandomQuestions(questions, count = 5) {
    const list = Array.isArray(questions) ? questions : [];
    const target = Math.max(1, Math.min(count, list.length || count));
    return AdventureGame.shuffle(list).slice(0, target);
  }

  render() {
    if (!this.container) return;
    const themeClass = {
      mountain: 'theme-mountain',
      maze: 'theme-maze',
      ship: 'theme-ship'
    }[this.gameType] || 'theme-mountain';

    this.container.innerHTML = `
      <div class="enhanced-game ${themeClass}">
        <div class="enhanced-game-header">
          <div class="enhanced-game-progress" id="enhancedProgress">
            ${this.questions.map((_, i) => `<span class="step ${i === 0 ? 'active' : ''}" id="step-${i}">${i + 1}</span>`).join('')}
          </div>
          <div class="enhanced-game-counter" id="enhancedCounter"></div>
        </div>
        <div class="enhanced-game-card">
          <h3 class="enhanced-question-text" id="enhancedQuestionText"></h3>
          <div class="enhanced-options" id="enhancedOptions"></div>
          <div class="enhanced-feedback" id="enhancedFeedback" style="display:none;"></div>
          <button class="btn btn-primary" id="enhancedNextBtn" style="display:none;">التالي ←</button>
        </div>
      </div>
    `;
  }

  start() {
    if (!this.questions.length || !this.container) {
      this._showError('لا توجد أسئلة متاحة لهذا الدرس.');
      return;
    }
    this._showQuestion(0);
  }

  _showQuestion(index) {
    const question = this.questions[index];
    if (!question) return;
    this.questionAttempts = 0;

    const counter = document.getElementById('enhancedCounter');
    const text = document.getElementById('enhancedQuestionText');
    const options = document.getElementById('enhancedOptions');
    const feedback = document.getElementById('enhancedFeedback');
    const nextBtn = document.getElementById('enhancedNextBtn');
    if (!counter || !text || !options || !feedback || !nextBtn) return;

    counter.textContent = `السؤال ${index + 1} من ${this.questions.length}`;
    text.textContent = question.question_text || '';
    feedback.style.display = 'none';
    feedback.className = 'enhanced-feedback';
    nextBtn.style.display = 'none';
    nextBtn.textContent = 'التالي ←';

    const labels = { a: 'أ', b: 'ب', c: 'ج', d: 'د' };
    options.innerHTML = ['a', 'b', 'c', 'd']
      .map((key) => `
        <button class="enhanced-option" data-key="${key}">
          <span class="opt-label">${labels[key]}</span>
          <span>${question['option_' + key] ?? ''}</span>
        </button>
      `).join('');

    options.querySelectorAll('.enhanced-option').forEach((btn) => {
      btn.addEventListener('click', () => this._answerQuestion(btn.dataset.key, question));
    });

    this._syncSteps(index);
  }

  _answerQuestion(choice, question) {
    const options = document.getElementById('enhancedOptions');
    const feedback = document.getElementById('enhancedFeedback');
    const nextBtn = document.getElementById('enhancedNextBtn');
    if (!options || !feedback || !nextBtn) return;

    const choiceBtn = options.querySelector(`[data-key="${choice}"]`);
    const isCorrect = choice === question.correct_option;

    if (isCorrect) {
      options.querySelectorAll('.enhanced-option').forEach((btn) => {
        btn.disabled = true;
        if (btn.dataset.key === question.correct_option) btn.classList.add('correct');
      });
      this.score += 1;
      this._pushUnique(this.correctQuestionNumbers, this.current + 1);
      this._recordAttempt(question.id, true, this.questionAttempts + 1);
      this._setFeedback('correct', question.feedback_correct || 'إجابة صحيحة! أحسنت.');
      this._finalizeQuestion('correct', this.questionAttempts + 1, nextBtn);
      return;
    }

    this.questionAttempts += 1;
    if (choiceBtn) {
      choiceBtn.classList.add('wrong');
      choiceBtn.disabled = true;
    }
    this._recordAttempt(question.id, false, this.questionAttempts);

    if (this.questionAttempts >= 2) {
      options.querySelectorAll('.enhanced-option').forEach((btn) => {
        btn.disabled = true;
        if (btn.dataset.key === question.correct_option) btn.classList.add('correct');
      });
      this.errors += 1;
      this._pushUnique(this.wrongQuestionNumbers, this.current + 1);
      this._setFeedback('wrong', question.feedback_correct || 'الإجابة الصحيحة مميزة باللون الأخضر.');
      this._finalizeQuestion('wrong', this.questionAttempts, nextBtn);
    } else {
      this._setFeedback('warning', 'إجابة غير صحيحة، لديك محاولة ثانية.');
    }
  }

  _finalizeQuestion(status, attemptsUsed, nextBtn) {
    const question = this.questions[this.current];
    this.questionOutcomes.push({
      question_number: this.current + 1,
      question_id: Number(question?.id || 0),
      status,
      attempts_used: attemptsUsed
    });

    const step = document.getElementById(`step-${this.current}`);
    if (step) step.classList.add('done');

    this.current += 1;
    if (this.current >= this.questions.length) {
      nextBtn.textContent = '🏁 إنهاء المغامرة';
      nextBtn.onclick = () => this._showResult();
    } else {
      nextBtn.textContent = 'التالي ←';
      nextBtn.onclick = () => this._showQuestion(this.current);
    }
    nextBtn.style.display = 'inline-flex';
  }

  _syncSteps(activeIndex) {
    for (let i = 0; i < this.questions.length; i += 1) {
      const el = document.getElementById(`step-${i}`);
      if (!el) continue;
      el.classList.toggle('active', i === activeIndex);
    }
  }

  _setFeedback(type, message) {
    const feedback = document.getElementById('enhancedFeedback');
    if (!feedback) return;
    feedback.style.display = 'block';
    feedback.className = `enhanced-feedback ${type}`;
    feedback.textContent = message;
  }

  _showResult() {
    const total = this.questions.length;
    const completedCount = this.questionOutcomes.length;
    const incompleteCount = Math.max(0, total - completedCount);
    const points = Math.round(350 * (this.score / Math.max(1, total)));
    const won = this.score >= total && total > 0;
    const scholar = (won && this.scholars.length)
      ? this.scholars[Math.floor(Math.random() * this.scholars.length)]
      : null;

    this.completed = true;
    this._saveResult({
      completed: 1,
      points,
      scholarId: scholar?.id || null,
      completedQuestions: completedCount,
      incompleteQuestions: incompleteCount
    });

    if (!this.container) return;
    this.container.innerHTML = `
      <div class="enhanced-result">
        <div class="emoji">${won ? '🏆' : '✨'}</div>
        <h3>${won ? 'أحسنت! أتممت المغامرة 100%' : 'مغامرة غير مكتملة 100%'}</h3>
        <p>الإجابات الصحيحة: <strong>${this.score}</strong> من <strong>${total}</strong></p>
        <p>الحالة: <strong>${won ? 'مكتملة 100%' : 'غير مكتملة 100%'}</strong></p>
        <p>النقاط المكتسبة: <strong>${points}</strong></p>
        ${scholar ? `<div class="scholar-box">اكتشفت: <strong>${scholar.name}</strong></div>` : ''}
        <button class="btn btn-primary" id="enhancedReplayBtn">🔄 إعادة المحاولة</button>
      </div>
    `;
    document.getElementById('enhancedReplayBtn')?.addEventListener('click', () => this.restart());
  }

  restart() {
    this.current = 0;
    this.score = 0;
    this.errors = 0;
    this.questionAttempts = 0;
    this.questions = AdventureGame.pickRandomQuestions(this.questionsPool, 5);
    this.correctQuestionNumbers = [];
    this.wrongQuestionNumbers = [];
    this.questionOutcomes = [];
    this.startedAt = Date.now();
    this.resultSaved = false;
    this.completed = false;
    this.render();
    this.start();
  }

  _recordAttempt(questionId, isCorrect, attemptsCount) {
    fetch('/api/answers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        lesson_id: this.lessonId,
        question_id: questionId,
        is_correct: isCorrect ? 1 : 0,
        attempts_count: attemptsCount
      })
    }).catch(() => {});
  }

  _saveResult({ completed, points, scholarId, completedQuestions, incompleteQuestions }) {
    if (this.resultSaved) return;
    this.resultSaved = true;

    const payload = {
      lesson_id: this.lessonId,
      points: Number(points || 0),
      scholar_id: scholarId,
      completed: completed ? 1 : 0,
      game_mode: this.gameType,
      total_questions: this.questions.length,
      completed_questions: Number(completedQuestions || 0),
      incomplete_questions: Number(incompleteQuestions || 0),
      correct_question_numbers: this.correctQuestionNumbers,
      wrong_question_numbers: this.wrongQuestionNumbers,
      selected_question_ids: this.questions.map((q) => Number(q.id || 0)).filter(Boolean),
      question_outcomes: this.questionOutcomes,
      duration_seconds: Math.max(1, Math.round((Date.now() - this.startedAt) / 1000))
    };

    fetch('/api/games.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(() => {});
  }

  _bindUnload() {
    window.addEventListener('beforeunload', () => {
      if (this.resultSaved || this.completed || this.current === 0) return;
      const total = this.questions.length;
      const completedCount = this.questionOutcomes.length;
      const incompleteCount = Math.max(0, total - completedCount);
      const payload = {
        lesson_id: this.lessonId,
        points: 0,
        completed: 0,
        game_mode: this.gameType,
        total_questions: total,
        completed_questions: completedCount,
        incomplete_questions: incompleteCount,
        correct_question_numbers: this.correctQuestionNumbers,
        wrong_question_numbers: this.wrongQuestionNumbers,
        selected_question_ids: this.questions.map((q) => Number(q.id || 0)).filter(Boolean),
        question_outcomes: this.questionOutcomes,
        duration_seconds: Math.max(1, Math.round((Date.now() - this.startedAt) / 1000)),
        ended_early: 1
      };
      try {
        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        navigator.sendBeacon('/api/games.php', blob);
      } catch (_) {}
    });
  }

  _pushUnique(arr, value) {
    if (!arr.includes(value)) arr.push(value);
  }

  _showError(message) {
    if (!this.container) return;
    this.container.innerHTML = `<div class="alert alert-warning">${message}</div>`;
  }
}

window.AdventureGame = AdventureGame;
