<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher   = requireTeacher();
$db        = getDB();
$lessonId  = (int)($_GET['lesson_id'] ?? 0);

// ========== تحليل صعوبة الأسئلة لدرس محدد ==========
if ($lessonId) {
    // الحصول على معلومات الدرس
    $lesson = $db->prepare("SELECT * FROM lessons WHERE id = ?");
    $lesson->execute([$lessonId]);
    $lesson = $lesson->fetch();
    
    if (!$lesson) {
        header('Location: /teacher/analytics.php');
        exit;
    }
    
    // تحليل صعوبة الأسئلة
    $questionStats = $db->prepare("
        SELECT 
            q.id,
            q.question_text,
            COUNT(qa.id) as total_attempts,
            SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_attempts,
            SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) as wrong_attempts,
            ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) as success_rate,
            AVG(qa.attempts_count) as avg_attempts_per_student
        FROM questions q
        LEFT JOIN question_attempts qa ON qa.question_id = q.id
        WHERE q.lesson_id = ?
        GROUP BY q.id
        ORDER BY success_rate ASC, total_attempts DESC
    ");
    $questionStats->execute([$lessonId]);
    $questions = $questionStats->fetchAll();
    
    // إحصائيات عامة للدرس
    $lessonOverview = $db->prepare("
        SELECT 
            COUNT(DISTINCT sg.student_id) as total_students,
            COUNT(*) as total_plays,
            SUM(sg.completed) as total_completions,
            AVG(sg.points_earned) as avg_points,
            MAX(sg.points_earned) as max_points
        FROM student_games sg
        WHERE sg.lesson_id = ?
    ");
    $lessonOverview->execute([$lessonId]);
    $overview = $lessonOverview->fetch();
    
    // توزيع أنماط اللعبة المفضلة
    $gameModes = $db->prepare("
        SELECT 
            game_mode,
            COUNT(*) as plays,
            SUM(completed) as completions
        FROM student_games
        WHERE lesson_id = ?
        GROUP BY game_mode
        ORDER BY plays DESC
    ");
    $gameModes->execute([$lessonId]);
    $modeStats = $gameModes->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>تحليل صعوبة الأسئلة</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button><span>👨‍🏫</span><span>لوحة الأستاذ</span></div>
  <ul class="navbar-nav"><li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li></ul>
</nav>
<aside class="sidebar">
  <div class="sidebar-section">الإدارة</div>
  <a href="/teacher/dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"  class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"   class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"   class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <div class="sidebar-section">التحليلات</div>
  <a href="/teacher/analytics.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات العامة</a>
  <a href="/teacher/question-analytics.php" class="sidebar-link active"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
<main class="main-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-chart-line"></i> تحليل صعوبة الأسئلة</h2>
    <a href="/teacher/analytics.php" class="btn btn-outline"><i class="fas fa-arrow-right"></i> العودة</a>
  </div>

  <?php if (!$lessonId): ?>
  <!-- اختيار الدرس -->
  <div class="card">
    <div class="card-header"><div class="card-title">اختر درساً لتحليل أسئلته</div></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>اسم الدرس</th><th>المقرر</th><th>عدد الأسئلة</th><th>الإجراء</th></tr>
        </thead>
        <tbody>
          <?php
          $lessons = $db->query("
            SELECT l.id, l.name, c.name as course_name, COUNT(q.id) as question_count
            FROM lessons l
            LEFT JOIN courses c ON c.id = l.course_id
            LEFT JOIN questions q ON q.lesson_id = l.id
            WHERE l.is_open = 1
            GROUP BY l.id
            ORDER BY l.sort_order, l.id
          ")->fetchAll();
          
          foreach ($lessons as $l):
          ?>
          <tr>
            <td><strong><?= clean($l['name']) ?></strong></td>
            <td><?= clean($l['course_name']) ?></td>
            <td><?= $l['question_count'] ?></td>
            <td>
              <a href="?lesson_id=<?= $l['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-chart-bar"></i> تحليل
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <?php else: ?>
  <!-- تحليل الدرس المحدد -->
  
  <div class="alert alert-info">
    <strong>الدرس:</strong> <?= clean($lesson['name']) ?>
  </div>
  
  <!-- إحصائيات عامة -->
  <div class="stats-grid" style="margin-bottom:2rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-users" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= $overview['total_students'] ?? 0 ?></div>
      <div class="stat-label">طالب شارك</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-gamepad" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= $overview['total_plays'] ?? 0 ?></div>
      <div class="stat-label">محاولة لعب</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-trophy" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= $overview['total_completions'] ?? 0 ?></div>
      <div class="stat-label">فوز</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#F3E5F5;"><i class="fas fa-star" style="color:#9C27B0;"></i></div>
      <div class="stat-value"><?= round($overview['avg_points'] ?? 0) ?></div>
      <div class="stat-label">متوسط النقاط</div>
    </div>
  </div>
  
  <!-- أنماط اللعبة المفضلة -->
  <?php if (!empty($modeStats)): ?>
  <div class="card" style="margin-bottom:2rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-gamepad"></i> أنماط اللعبة المفضلة</div></div>
    <div style="padding:1.5rem;">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <?php
        $modeIcons = [
          'mountain' => '⛰️ الجبل',
          'maze' => '🗝️ المتاهة',
          'island' => '🏝️ الجزيرة',
          'ship' => '⛵ الإبحار'
        ];
        foreach ($modeStats as $mode):
        ?>
        <div style="background:var(--bg);border-radius:12px;padding:1rem;text-align:center;">
          <div style="font-size:2rem;margin-bottom:0.5rem;"><?= $modeIcons[$mode['game_mode']] ?? $mode['game_mode'] ?></div>
          <div style="font-weight:700;font-size:1.5rem;color:var(--primary);"><?= $mode['plays'] ?></div>
          <div style="color:var(--muted);font-size:0.9rem;">مرة لعب</div>
          <div style="color:var(--text);font-size:0.85rem;margin-top:0.25rem;">
            (<?= $mode['completions'] ?> فوز)
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- تحليل صعوبة الأسئلة -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-question-circle"></i> تحليل صعوبة الأسئلة</div>
    </div>
    
    <?php if (empty($questions)): ?>
    <div class="alert alert-warning" style="margin:1rem;">
      <i class="fas fa-exclamation-triangle"></i> لا توجد بيانات كافية لتحليل الأسئلة
    </div>
    <?php else: ?>
    
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>السؤال</th>
            <th>المحاولات</th>
            <th>إجابات صحيحة</th>
            <th>إجابات خاطئة</th>
            <th>نسبة النجاح</th>
            <th>متوسط المحاولات</th>
            <th>التصنيف</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($questions as $q):
            $successRate = $q['success_rate'] ?? 0;
            $difficulty = 'سهل';
            $difficultyColor = 'var(--primary)';
            $difficultyIcon = '😊';
            
            if ($successRate < 30) {
              $difficulty = 'صعب جداً';
              $difficultyColor = '#D32F2F';
              $difficultyIcon = '😰';
            } elseif ($successRate < 50) {
              $difficulty = 'صعب';
              $difficultyColor = '#F57C00';
              $difficultyIcon = '😟';
            } elseif ($successRate < 70) {
              $difficulty = 'متوسط';
              $difficultyColor = '#FBC02D';
              $difficultyIcon = '🤔';
            } elseif ($successRate < 85) {
              $difficulty = 'سهل';
              $difficultyColor = '#689F38';
              $difficultyIcon = '🙂';
            } else {
              $difficulty = 'سهل جداً';
              $difficultyColor = '#388E3C';
              $difficultyIcon = '😊';
            }
          ?>
          <tr>
            <td style="max-width:300px;"><?= clean(mb_substr($q['question_text'], 0, 80)) . (mb_strlen($q['question_text']) > 80 ? '...' : '') ?></td>
            <td><strong><?= $q['total_attempts'] ?? 0 ?></strong></td>
            <td style="color:var(--primary);"><strong><?= $q['correct_attempts'] ?? 0 ?></strong></td>
            <td style="color:var(--danger);"><strong><?= $q['wrong_attempts'] ?? 0 ?></strong></td>
            <td>
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="flex:1;background:#e0e0e0;border-radius:10px;height:10px;overflow:hidden;">
                  <div style="width:<?= $successRate ?>%;height:100%;background:<?= $difficultyColor ?>;transition:width 0.3s;"></div>
                </div>
                <span style="font-weight:700;color:<?= $difficultyColor ?>;"><?= round($successRate) ?>%</span>
              </div>
            </td>
            <td><?= round($q['avg_attempts_per_student'] ?? 0, 1) ?></td>
            <td>
              <span style="color:<?= $difficultyColor ?>;font-weight:700;">
                <?= $difficultyIcon ?> <?= $difficulty ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <!-- التوصيات والنصائح -->
    <div style="padding:1.5rem;border-top:2px solid var(--border);">
      <h3 style="color:var(--primary);margin-bottom:1rem;">📋 توصيات ونصائح للمعلم</h3>
      
      <?php
      // تحليل الأسئلة الصعبة
      $difficultQuestions = array_filter($questions, function($q) {
        return ($q['success_rate'] ?? 0) < 50;
      });
      
      $easyQuestions = array_filter($questions, function($q) {
        return ($q['success_rate'] ?? 100) > 85;
      });
      ?>
      
      <?php if (!empty($difficultQuestions)): ?>
      <div class="alert alert-danger" style="margin-bottom:1rem;">
        <i class="fas fa-exclamation-circle"></i>
        <strong>أسئلة تحتاج إلى مراجعة (<?= count($difficultQuestions) ?> سؤال)</strong>
        <ul style="margin-top:0.5rem;margin-right:1.5rem;">
          <li>راجع صياغة الأسئلة الصعبة لتكون أكثر وضوحاً</li>
          <li>قدم شرحاً إضافياً للمفاهيم المرتبطة بهذه الأسئلة</li>
          <li>أضف أمثلة توضيحية في العرض التقديمي أو الفيديو</li>
          <li>استخدم أنشطة تفاعلية لتعزيز الفهم</li>
        </ul>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($easyQuestions)): ?>
      <div class="alert alert-info" style="margin-bottom:1rem;">
        <i class="fas fa-info-circle"></i>
        <strong>أسئلة سهلة (<?= count($easyQuestions) ?> سؤال)</strong>
        <ul style="margin-top:0.5rem;margin-right:1.5rem;">
          <li>يمكن استبدال بعض الأسئلة السهلة بأسئلة أكثر تحدياً</li>
          <li>الطلاب يتقنون هذه المفاهيم جيداً</li>
        </ul>
      </div>
      <?php endif; ?>
      
      <div class="alert alert-success">
        <i class="fas fa-lightbulb"></i>
        <strong>نصائح عامة لتحسين الأداء:</strong>
        <ul style="margin-top:0.5rem;margin-right:1.5rem;">
          <li><strong>التنوع:</strong> استخدم أنماط مختلفة من الأسئلة (اختيار من متعدد، صح/خطأ، ترتيب...)</li>
          <li><strong>التدرج:</strong> ابدأ بأسئلة سهلة واصعد تدريجياً للأسئلة الأصعب</li>
          <li><strong>التغذية الراجعة:</strong> تأكد من أن التغذية الراجعة للإجابات الخاطئة واضحة ومفيدة</li>
          <li><strong>المراجعة الدورية:</strong> راجع الأسئلة بناءً على أداء الطلاب كل شهر</li>
          <li><strong>التشجيع:</strong> ركز على المواضيع التي يواجه الطلاب صعوبة فيها في الحصص القادمة</li>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';
</script>
</body>
</html>
