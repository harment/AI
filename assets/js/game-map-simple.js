// =============================================
// لعبة الخريطة البسيطة - 5 نقاط مع خلفية مرسومة
// =============================================

class MapAdventureGame {
  constructor(options) {
    this.lessonId         = options.lessonId;
    this.mapTheme         = options.mapTheme || 'island'; // island | mountain | lake | forest
    this.allQuestions     = options.questions || [];
    this.questions        = this._selectRandomQuestions(this.allQuestions, 5); // 5 أسئلة فقط
    this.scholars         = options.scholars  || [];
    this.container        = document.getElementById(options.containerId || 'gameContainer');
    this.current          = 0; // النقطة الحالية (0-4)
    this.score            = 0; // عدد الإجابات الصحيحة
    this.questionAttempts = 0;
    this.completed        = false;
    this.showingQuestion  = false;
    this.questionResults  = [];
    this.BASE_PTS         = options.basePoints || 350;
    this.eventListenersAttached = false; // Track if listeners are attached
    
    this.sounds           = {
      correct : new Audio('/assets/sounds/correct.mp3'),
      wrong   : new Audio('/assets/sounds/wrong.mp3'),
      win     : new Audio('/assets/sounds/win.mp3'),
      lose    : new Audio('/assets/sounds/lose.mp3'),
      progress: new Audio('/assets/sounds/progress.mp3'),
    };
    
    // مواقع النقاط الخمس على الخريطة
    this.mapPoints = this._getMapPoints();
    
    this.render();
  }

  _selectRandomQuestions(allQuestions, count) {
    const shuffled = MapAdventureGame.shuffle(allQuestions);
    return shuffled.slice(0, Math.min(count, shuffled.length));
  }

  _getMapPoints() {
    // مواقع 5 نقاط على الخريطة بناءً على النمط
    const themes = {
      island: [
        { x: 15, y: 80, label: 'الشاطئ' },
        { x: 30, y: 60, label: 'الغابة' },
        { x: 50, y: 45, label: 'الجبل' },
        { x: 70, y: 55, label: 'الشلال' },
        { x: 85, y: 75, label: 'الكنز' }
      ],
      mountain: [
        { x: 50, y: 90, label: 'القاعدة' },
        { x: 40, y: 70, label: 'المخيم 1' },
        { x: 50, y: 50, label: 'المخيم 2' },
        { x: 60, y: 30, label: 'المخيم 3' },
        { x: 50, y: 10, label: 'القمة' }
      ],
      lake: [
        { x: 20, y: 70, label: 'المرسى' },
        { x: 35, y: 50, label: 'الجزيرة 1' },
        { x: 50, y: 35, label: 'الجزيرة 2' },
        { x: 65, y: 50, label: 'الجزيرة 3' },
        { x: 80, y: 65, label: 'الميناء' }
      ],
      forest: [
        { x: 10, y: 85, label: 'بوابة الغابة' },
        { x: 25, y: 65, label: 'الطريق المضيء' },
        { x: 50, y: 50, label: 'قلب الغابة' },
        { x: 70, y: 40, label: 'البحيرة السحرية' },
        { x: 85, y: 25, label: 'شجرة الحكمة' }
      ]
    };
    return themes[this.mapTheme] || themes.island;
  }

  render() {
    if (!this.container) {
      console.error('Game container not found');
      return;
    }
    
    console.log('Rendering game UI...');
    this.container.innerHTML = this._buildGameUI();
    
    if (!this.eventListenersAttached) {
      console.log('Attaching event listeners...');
      this._attachEventListeners();
      this.eventListenersAttached = true;
    }
  }

  _buildGameUI() {
    const characterIcon = {
      island: '🏃‍♂️',
      mountain: '🧗‍♂️',
      lake: '🏊‍♂️',
      forest: '🚶‍♂️'
    }[this.mapTheme] || '🏃‍♂️';

    const themeColors = {
      island: { primary: '#06b6d4', secondary: '#fbbf24', bg: 'linear-gradient(135deg, #0ea5e9 0%, #06b6d4 50%, #0891b2 100%)' },
      mountain: { primary: '#92400e', secondary: '#b45309', bg: 'linear-gradient(135deg, #b45309 0%, #92400e 50%, #78350f 100%)' },
      lake: { primary: '#0284c7', secondary: '#0ea5e9', bg: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0369a1 100%)' },
      forest: { primary: '#10b981', secondary: '#34d399', bg: 'linear-gradient(135deg, #059669 0%, #10b981 50%, #047857 100%)' }
    };
    const theme = themeColors[this.mapTheme] || themeColors.island;

    return `
    <div class="map-game-container">
      <!-- Header -->
      <div class="map-game-header" style="background: ${theme.bg}; color: white; padding: 1rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-size: 1.1rem; font-weight: 700;">
          <span style="font-size: 1.5rem; margin-left: 0.5rem;">${characterIcon}</span>
          رحلة المعرفة
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
          <div style="background: rgba(255,255,255,0.2); padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.9rem;">
            النقطة ${this.current + 1} / ${this.questions.length}
          </div>
          <div style="background: rgba(255,255,255,0.2); padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.9rem;">
            النقاط: <span id="currentScore">0</span> / ${this.questions.length}
          </div>
        </div>
      </div>

      <!-- Map Canvas -->
      <div class="map-canvas" style="position: relative; width: 100%; height: 500px; overflow: hidden; border-radius: 0 0 12px 12px;">
        ${this._buildMapSVG()}
        ${this._buildMapPoints(characterIcon)}
      </div>

      <!-- Question Popup -->
      <div id="questionPopup" class="question-popup" style="display: none;">
        <div class="question-popup-content">
          <div class="question-header" style="background: ${theme.bg}; color: white; padding: 1rem; border-radius: 12px 12px 0 0;">
            <div class="question-counter" id="qCounter">السؤال ${this.current + 1} من ${this.questions.length}</div>
          </div>
          <div class="question-body" style="padding: 1.5rem;">
            <div class="question-text" id="qText"></div>
            <div class="options-grid" id="optionsGrid"></div>
            <div id="feedbackBox" style="display:none; padding: 1rem; border-radius: 8px; margin-top: 1rem; font-weight: 600;"></div>
            <button class="btn btn-primary btn-block" id="nextBtn" style="display:none; margin-top: 1rem;">التالي ←</button>
          </div>
        </div>
      </div>

      <!-- Result Overlay -->
      <div class="game-overlay" id="gameOverlay" style="display:none"></div>
    </div>`;
  }

  _buildMapSVG() {
    const svgMaps = {
      island: `
        <svg viewBox="0 0 100 100" style="position: absolute; width: 100%; height: 100%; z-index: 1;">
          <!-- Sea -->
          <rect x="0" y="0" width="100" height="100" fill="#0ea5e9" opacity="0.3"/>
          <!-- Island -->
          <ellipse cx="50" cy="50" rx="35" ry="30" fill="#fbbf24" opacity="0.4"/>
          <ellipse cx="50" cy="50" rx="30" ry="25" fill="#10b981" opacity="0.5"/>
          <!-- Trees -->
          <circle cx="40" cy="45" r="3" fill="#059669"/>
          <circle cx="55" cy="48" r="3" fill="#059669"/>
          <circle cx="48" cy="40" r="3" fill="#059669"/>
          <!-- Mountain peak -->
          <polygon points="50,30 45,45 55,45" fill="#94a3b8" opacity="0.6"/>
          <!-- Beach -->
          <ellipse cx="30" cy="75" rx="15" ry="8" fill="#fef3c7" opacity="0.7"/>
          <ellipse cx="70" cy="70" rx="12" ry="6" fill="#fef3c7" opacity="0.7"/>
        </svg>
      `,
      mountain: `
        <svg viewBox="0 0 100 100" style="position: absolute; width: 100%; height: 100%; z-index: 1;">
          <!-- Sky -->
          <rect x="0" y="0" width="100" height="70" fill="#93c5fd" opacity="0.3"/>
          <!-- Main mountain - brown -->
          <polygon points="50,10 15,70 85,70" fill="#92400e" opacity="0.7"/>
          <polygon points="50,10 25,70 75,70" fill="#b45309" opacity="0.6"/>
          <!-- Snow peak - white -->
          <polygon points="50,10 42,25 58,25" fill="#ffffff" opacity="0.95"/>
          <polygon points="50,10 45,20 55,20" fill="#f0f9ff" opacity="1"/>
          <!-- Ground -->
          <rect x="0" y="70" width="100" height="30" fill="#22c55e" opacity="0.4"/>
          <!-- Trees at base -->
          <circle cx="20" cy="82" r="4" fill="#15803d" opacity="0.7"/>
          <circle cx="30" cy="85" r="3" fill="#15803d" opacity="0.7"/>
          <circle cx="70" cy="85" r="3" fill="#15803d" opacity="0.7"/>
          <circle cx="80" cy="82" r="4" fill="#15803d" opacity="0.7"/>
          <!-- Rocks -->
          <ellipse cx="40" cy="75" rx="5" ry="3" fill="#78716c" opacity="0.5"/>
          <ellipse cx="60" cy="75" rx="4" ry="2" fill="#78716c" opacity="0.5"/>
        </svg>
      `,
      lake: `
        <svg viewBox="0 0 100 100" style="position: absolute; width: 100%; height: 100%; z-index: 1;">
          <!-- Blue sea water -->
          <rect x="0" y="0" width="100" height="100" fill="#0284c7" opacity="0.5"/>
          <ellipse cx="50" cy="50" rx="48" ry="45" fill="#0ea5e9" opacity="0.4"/>
          
          <!-- Winding water path - lighter blue -->
          <path d="M 20,70 Q 30,55 35,50 T 50,35 T 65,50 T 80,65" 
                fill="none" 
                stroke="#38bdf8" 
                stroke-width="8" 
                opacity="0.6"/>
          
          <!-- Small islands along the path -->
          <ellipse cx="30" cy="60" rx="6" ry="5" fill="#16a34a" opacity="0.7"/>
          <circle cx="31" cy="59" r="2" fill="#15803d" opacity="0.8"/>
          
          <ellipse cx="50" cy="35" rx="5" ry="4" fill="#16a34a" opacity="0.7"/>
          <circle cx="50" cy="34" r="1.5" fill="#15803d" opacity="0.8"/>
          
          <ellipse cx="65" cy="50" rx="6" ry="4" fill="#16a34a" opacity="0.7"/>
          <circle cx="66" cy="49" r="2" fill="#15803d" opacity="0.8"/>
          
          <!-- Shore at start and end -->
          <ellipse cx="20" cy="70" rx="16" ry="10" fill="#15803d" opacity="0.6"/>
          <ellipse cx="80" cy="65" rx="14" ry="9" fill="#15803d" opacity="0.6"/>
          
          <!-- Water waves -->
          <path d="M 5,40 Q 15,35 25,40 T 45,40 T 65,40 T 85,40 T 100,40" 
                fill="none" stroke="white" stroke-width="0.8" opacity="0.25"/>
          <path d="M 0,60 Q 10,55 20,60 T 40,60 T 60,60 T 80,60 T 100,60" 
                fill="none" stroke="white" stroke-width="0.8" opacity="0.25"/>
          
          <!-- Boat icon (will be animated by character) -->
          <g id="boat-icon" opacity="0.4">
            <ellipse cx="50" cy="50" rx="4" ry="2" fill="#78350f"/>
            <polygon points="50,48 49,50 51,50" fill="#fef3c7"/>
          </g>
        </svg>
      `,
      forest: `
        <svg viewBox="0 0 100 100" style="position: absolute; width: 100%; height: 100%; z-index: 1;">
          <!-- Forest background -->
          <rect x="0" y="0" width="100" height="100" fill="#059669" opacity="0.2"/>
          <!-- Trees -->
          <circle cx="20" cy="30" r="8" fill="#10b981" opacity="0.5"/>
          <circle cx="35" cy="40" r="10" fill="#10b981" opacity="0.6"/>
          <circle cx="50" cy="25" r="9" fill="#10b981" opacity="0.5"/>
          <circle cx="65" cy="35" r="11" fill="#10b981" opacity="0.6"/>
          <circle cx="80" cy="28" r="8" fill="#10b981" opacity="0.5"/>
          <circle cx="30" cy="60" r="9" fill="#10b981" opacity="0.6"/>
          <circle cx="55" cy="65" r="10" fill="#10b981" opacity="0.5"/>
          <circle cx="75" cy="55" r="9" fill="#10b981" opacity="0.6"/>
          <!-- Path -->
          <path d="M 10,85 Q 30,70 50,75 T 85,60" fill="none" stroke="#fef3c7" stroke-width="3" opacity="0.4"/>
          <!-- Special tree at end -->
          <circle cx="85" cy="25" r="12" fill="#fbbf24" opacity="0.7"/>
        </svg>
      `
    };
    return svgMaps[this.mapTheme] || svgMaps.island;
  }

  _buildMapPoints(characterIcon) {
    let html = '';
    
    // Draw path connecting points
    let pathPoints = this.mapPoints.map(p => `${p.x}%,${p.y}%`).join(' ');
    html += `<svg viewBox="0 0 100 100" style="position: absolute; width: 100%; height: 100%; z-index: 2; pointer-events: none;">
      <polyline points="${pathPoints}" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="0.3" stroke-dasharray="1,1"/>
    </svg>`;
    
    // Draw points
    this.mapPoints.forEach((point, index) => {
      const isCompleted = index < this.current;
      const isCurrent = index === this.current;
      const isLocked = index > this.current;
      
      const bgColor = isCompleted ? '#10b981' : isCurrent ? '#fbbf24' : '#94a3b8';
      const borderColor = isCurrent ? 'white' : 'rgba(255,255,255,0.5)';
      const cursor = isCurrent ? 'pointer' : 'default';
      const animation = isCurrent ? 'map-point-pulse 2s infinite' : 'none';
      const pointerEvents = isCurrent ? 'auto' : 'none';
      
      html += `
        <div class="map-point ${isCurrent ? 'current' : ''}" 
             data-index="${index}"
             style="position: absolute; 
                    left: ${point.x}%; 
                    top: ${point.y}%; 
                    transform: translate(-50%, -50%);
                    width: 40px; 
                    height: 40px; 
                    background: ${bgColor}; 
                    border: 3px solid ${borderColor}; 
                    border-radius: 50%; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    font-weight: 700; 
                    color: white; 
                    font-size: 1.1rem;
                    cursor: ${cursor};
                    pointer-events: ${pointerEvents};
                    z-index: ${isCurrent ? 20 : 10};
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    animation: ${animation};
                    transition: all 0.3s ease;">
          ${isCompleted ? '✓' : index + 1}
        </div>
        <div style="position: absolute; 
                    left: ${point.x}%; 
                    top: calc(${point.y}% + 30px); 
                    transform: translateX(-50%);
                    background: rgba(0,0,0,0.7); 
                    color: white; 
                    padding: 0.3rem 0.6rem; 
                    border-radius: 6px; 
                    font-size: 0.75rem;
                    white-space: nowrap;
                    z-index: 15;
                    pointer-events: none;">
          ${point.label}
        </div>
      `;
    });
    
    // Character position
    const charPoint = this.mapPoints[this.current];
    html += `
      <div id="gameCharacter" 
           style="position: absolute; 
                  left: ${charPoint.x}%; 
                  top: calc(${charPoint.y}% - 50px); 
                  transform: translateX(-50%);
                  font-size: 2.5rem; 
                  z-index: 25;
                  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
                  transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);">
        ${characterIcon}
      </div>
    `;
    
    return html;
  }

  _attachEventListeners() {
    console.log('_attachEventListeners called');
    // Click on current point to show question using event delegation
    this.container.addEventListener('click', (e) => {
      console.log('Container clicked', e.target);
      const point = e.target.closest('.map-point');
      if (point) {
        const index = parseInt(point.dataset.index);
        console.log('Point clicked:', index, 'Current:', this.current);
        if (index === this.current && !this.showingQuestion) {
          this._showQuestion(this.current);
        }
      }
    });
  }

  start() {
    // Game is ready - user clicks on first point to start
  }

  _showQuestion(idx) {
    if (this.showingQuestion) return;
    
    const q = this.questions[idx];
    if (!q) return;

    this.showingQuestion = true;
    this.questionAttempts = 0;

    const popup = document.getElementById('questionPopup');
    const qText = document.getElementById('qText');
    const opts = document.getElementById('optionsGrid');
    const fb = document.getElementById('feedbackBox');
    const next = document.getElementById('nextBtn');
    const ctr = document.getElementById('qCounter');

    ctr.textContent = `السؤال ${idx + 1} من ${this.questions.length}`;
    qText.textContent = q.question_text;
    fb.style.display = 'none';
    next.style.display = 'none';

    const labels = { a: 'أ', b: 'ب', c: 'ج', d: 'د' };
    opts.innerHTML = ['a', 'b', 'c', 'd'].map(k => `
      <button class="option-btn" data-key="${k}" style="background: white; border: 2px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin: 0.5rem 0; text-align: right; cursor: pointer; transition: all 0.2s; font-size: 1rem;">
        <span class="opt-label" style="display: inline-block; width: 30px; height: 30px; background: #f1f5f9; border-radius: 50%; text-align: center; line-height: 30px; margin-left: 0.5rem; font-weight: 700;">${labels[k]}</span>
        ${q['option_' + k]}
      </button>`).join('');

    opts.querySelectorAll('.option-btn').forEach(btn => {
      btn.addEventListener('click', () => this._handleAnswer(btn.dataset.key, q));
      btn.addEventListener('mouseenter', function() {
        if (!this.disabled) {
          this.style.borderColor = '#3b82f6';
          this.style.background = '#eff6ff';
        }
      });
      btn.addEventListener('mouseleave', function() {
        if (!this.disabled && !this.classList.contains('correct') && !this.classList.contains('wrong')) {
          this.style.borderColor = '#e2e8f0';
          this.style.background = 'white';
        }
      });
    });

    popup.style.display = 'flex';
  }

  _handleAnswer(chosen, q) {
    const opts = document.getElementById('optionsGrid');
    const fb = document.getElementById('feedbackBox');
    const next = document.getElementById('nextBtn');
    const chosenBtn = opts.querySelector(`[data-key="${chosen}"]`);

    if (chosen === q.correct_option) {
      // Correct answer
      opts.querySelectorAll('.option-btn').forEach(b => {
        b.disabled = true;
        if (b.dataset.key === q.correct_option) {
          b.classList.add('correct');
          b.style.background = '#dcfce7';
          b.style.borderColor = '#10b981';
          b.style.color = '#166534';
        }
      });
      this._playSound('correct');
      fb.style.background = '#dcfce7';
      fb.style.color = '#166534';
      fb.innerHTML = '✅ ' + (q.feedback_correct || 'إجابة صحيحة! أحسنت.');
      fb.style.display = 'block';
      this.score++;
      
      this.questionResults.push({
        question_id: q.id,
        is_correct: true,
        attempts: this.questionAttempts + 1
      });
      
      this._updateScore();
      next.style.display = 'block';
      next.onclick = () => this._nextQuestion();
    } else {
      this.questionAttempts++;
      this._playSound('wrong');
      chosenBtn.classList.add('wrong');
      chosenBtn.style.background = '#fee2e2';
      chosenBtn.style.borderColor = '#ef4444';
      chosenBtn.disabled = true;

      if (this.questionAttempts >= 2) {
        // Second wrong attempt - show correct answer
        opts.querySelectorAll('.option-btn').forEach(b => {
          b.disabled = true;
          if (b.dataset.key === q.correct_option) {
            b.classList.add('correct');
            b.style.background = '#dcfce7';
            b.style.borderColor = '#10b981';
          }
        });
        fb.style.background = '#fee2e2';
        fb.style.color = '#991b1b';
        
        const correctAnswerText = q['option_' + q.correct_option];
        fb.innerHTML = `
          ❌ <strong>إجابة خاطئة</strong><br>
          <div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(16,185,129,0.1); border-radius: 6px;">
            الإجابة الصحيحة: <strong>${correctAnswerText}</strong>
          </div>
          ${q.feedback_correct ? `<div style="margin-top: 0.5rem; font-size: 0.9em;">${q.feedback_correct}</div>` : ''}
        `;
        fb.style.display = 'block';
        
        this.questionResults.push({
          question_id: q.id,
          is_correct: false,
          attempts: 2
        });
        
        next.style.display = 'block';
        next.textContent = 'التالي ←';
        next.onclick = () => this._nextQuestion();
      } else {
        // First wrong attempt
        fb.style.background = '#fef3c7';
        fb.style.color = '#92400e';
        fb.innerHTML = '⚠️ <strong>إجابة خاطئة!</strong> لديك محاولة أخرى. فكر جيداً...';
        fb.style.display = 'block';
      }
    }
  }

  _nextQuestion() {
    const popup = document.getElementById('questionPopup');
    popup.style.display = 'none';
    this.showingQuestion = false;
    
    this._playSound('progress');
    this.current++;
    
    if (this.current >= this.questions.length) {
      // Game completed
      this.completed = true;
      this._showResult(true);
    } else {
      // Move character and re-render
      this._moveCharacter();
      this.render();
    }
  }

  _moveCharacter() {
    const char = document.getElementById('gameCharacter');
    const charPoint = this.mapPoints[this.current];
    if (char) {
      char.style.left = `${charPoint.x}%`;
      char.style.top = `calc(${charPoint.y}% - 50px)`;
    }
  }

  _updateScore() {
    const scoreEl = document.getElementById('currentScore');
    if (scoreEl) {
      scoreEl.textContent = this.score;
    }
  }

  _showResult(won) {
    this._playSound(won ? 'win' : 'lose');
    const accuracy = this.score / this.questions.length;
    const pts = won ? Math.round(this.BASE_PTS * accuracy) : 0;
    const scholar = won && accuracy >= 0.8 ? this.scholars[Math.floor(Math.random() * this.scholars.length)] : null;

    const overlay = document.getElementById('gameOverlay');
    overlay.innerHTML = `
    <div class="game-result-box" style="background: white; border-radius: 16px; padding: 2rem; max-width: 500px; text-align: center;">
      <div class="result-icon" style="font-size: 4rem; margin-bottom: 1rem;">${won ? '🏆' : '😢'}</div>
      <div class="result-title" style="font-size: 1.5rem; font-weight: 700; color: ${won ? '#10b981' : '#ef4444'}; margin-bottom: 1rem;">
        ${won ? 'مبروك! أكملت الرحلة بنجاح' : 'حاول مرة أخرى!'}
      </div>
      <div class="result-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1.5rem 0;">
        <div class="result-stat">
          <div class="stat-value" style="font-size: 2rem; font-weight: 700; color: #1e293b;">${this.score}</div>
          <div class="stat-label" style="font-size: 0.9rem; color: #64748b;">من ${this.questions.length}</div>
          <div class="stat-desc" style="font-size: 0.8rem; color: #94a3b8;">إجابات صحيحة</div>
        </div>
        <div class="result-stat">
          <div class="stat-value" style="font-size: 2rem; font-weight: 700; color: #1e293b;">${Math.round(accuracy * 100)}%</div>
          <div class="stat-label" style="font-size: 0.9rem; color: #64748b;">نسبة النجاح</div>
        </div>
        ${pts > 0 ? `<div class="result-stat">
          <div class="stat-value" style="font-size: 2rem; font-weight: 700; color: #fbbf24;">${pts}</div>
          <div class="stat-label" style="font-size: 0.9rem; color: #64748b;">نقطة</div>
          <div class="stat-desc" style="font-size: 0.8rem; color: #94a3b8;">تم إضافتها</div>
        </div>` : ''}
      </div>
      ${scholar ? `<div class="scholar-card" style="background: #fef3c7; border-radius: 12px; padding: 1rem; margin: 1rem 0;">
        <div class="scholar-icon" style="font-size: 2rem; margin-bottom: 0.5rem;">👨‍🏫</div>
        <div class="scholar-name" style="font-weight: 700; color: #92400e; margin-bottom: 0.3rem;">🎉 اكتشفت عالماً جديداً: ${scholar.name}</div>
        <div class="scholar-bio" style="font-size: 0.9rem; color: #78350f;">${scholar.short_bio}</div>
      </div>` : ''}
      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
        <button class="btn btn-primary" onclick="window.location.reload()" style="background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 0.75rem 1.5rem; font-size: 1rem; cursor: pointer; font-weight: 600;">🔄 العودة للدرس</button>
      </div>
    </div>`;
    overlay.style.display = 'flex';

    // Save result
    this._saveResult(won, pts, scholar?.id || null);
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
        game_mode: this.mapTheme,
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

// CSS Styles
const mapGameStyles = document.createElement('style');
mapGameStyles.textContent = `
  .map-game-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
  }

  .map-canvas {
    position: relative;
  }

  .question-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }

  .question-popup-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
  }

  .options-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 1rem;
  }

  .option-btn {
    display: flex;
    align-items: center;
    width: 100%;
    text-align: right;
  }

  .option-btn:hover:not(:disabled) {
    transform: translateX(-4px);
  }

  .game-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 150;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }

  @keyframes slideUp {
    from {
      transform: translateY(30px);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  @keyframes map-point-pulse {
    0%, 100% {
      box-shadow: 0 4px 12px rgba(0,0,0,0.3), 0 0 0 0 rgba(251, 191, 36, 0.7);
    }
    50% {
      box-shadow: 0 4px 12px rgba(0,0,0,0.3), 0 0 0 10px rgba(251, 191, 36, 0);
    }
  }
`;
document.head.appendChild(mapGameStyles);

window.MapAdventureGame = MapAdventureGame;
