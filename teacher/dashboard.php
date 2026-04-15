<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalCourses  = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalLessons  = $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalGames    = $db->query("SELECT COUNT(*) FROM student_games WHERE completed = 1")->fetchColumn();

// Recent students
$recentStudents = $db->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Top students
$topStudents = $db->query("SELECT s.id, s.name, s.points, COUNT(DISTINCT ss.scholar_id) scholars FROM students s LEFT JOIN student_scholars ss ON ss.student_id = s.id GROUP BY s.id ORDER BY s.points DESC LIMIT 5")->fetchAll();

// Active lessons
$openLessons = $db->query("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.is_open = 1 ORDER BY l.id DESC LIMIT 5")->fetchAll();

// ========== تحليلات متقدمة ==========

// 1. الأسئلة الأكثر صعوبة (نسبة نجاح أقل من 50%)
$difficultQuestions = $db->query("
    SELECT 
        q.id,
        q.question_text,
        l.name as lesson_name,
        COUNT(qa.id) as total_attempts,
        SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_attempts,
        ROUND(SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(qa.id), 0), 2) as success_rate
    FROM questions q
    LEFT JOIN question_attempts qa ON qa.question_id = q.id
    LEFT JOIN lessons l ON l.id = q.lesson_id
    WHERE qa.id IS NOT NULL
    GROUP BY q.id
    HAVING success_rate < 50 OR success_rate IS NULL
    ORDER BY success_rate ASC, total_attempts DESC
    LIMIT 10
")->fetchAll();

// 2. الطلاب المتعثرون (فشلوا في أكثر من 3 ألعاب)
$strugglingStudents = $db->query("
    SELECT 
        s.id,
        s.name,
        s.university_id,
        s.points,
        COUNT(sg.id) as total_games,
        SUM(CASE WHEN sg.completed = 0 THEN 1 ELSE 0 END) as failed_games,
        SUM(CASE WHEN sg.completed = 1 THEN 1 ELSE 0 END) as completed_games,
        ROUND(SUM(CASE WHEN sg.completed = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(sg.id), 1) as failure_rate
    FROM students s
    LEFT JOIN student_games sg ON sg.student_id = s.id
    WHERE sg.id IS NOT NULL
    GROUP BY s.id
    HAVING failed_games >= 3 OR failure_rate > 50
    ORDER BY failed_games DESC, failure_rate DESC
    LIMIT 10
")->fetchAll();

// 3. الدروس الأكثر صعوبة (متوسط نقاط منخفض)
$difficultLessons = $db->query("
    SELECT 
        l.id,
        l.name,
        c.name as course_name,
        COUNT(DISTINCT sg.student_id) as students_count,
        COUNT(sg.id) as total_plays,
        SUM(sg.completed) as completions,
        ROUND(AVG(sg.points_earned), 1) as avg_points,
        ROUND(SUM(sg.completed) * 100.0 / COUNT(sg.id), 1) as completion_rate
    FROM lessons l
    LEFT JOIN courses c ON c.id = l.course_id
    LEFT JOIN student_games sg ON sg.lesson_id = l.id
    WHERE sg.id IS NOT NULL AND l.is_open = 1
    GROUP BY l.id
    ORDER BY completion_rate ASC, avg_points ASC
    LIMIT 5
")->fetchAll();

// 4. نقاط الضعف العامة - تحليل الموضوعات
$weaknessAnalysis = $db->query("
    SELECT 
        q.question_text,
        l.name as topic,
        COUNT(DISTINCT qa.student_id) as students_affected,
        COUNT(qa.id) as total_wrong_attempts
    FROM question_attempts qa
    JOIN questions q ON q.id = qa.question_id
    JOIN lessons l ON l.id = qa.lesson_id
    WHERE qa.is_correct = 0
    GROUP BY q.id
    ORDER BY students_affected DESC, total_wrong_attempts DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة الأستاذ – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>👨‍🏫</span><span>لوحة الأستاذ</span>
  </div>
  <ul class="navbar-nav">
    <li><span class="nav-link"><i class="fas fa-user"></i> <?= clean($teacher['name']) ?></span></li>
    <li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>
<aside class="sidebar">
  <div class="sidebar-section">الإدارة</div>
  <a href="/teacher/dashboard.php"  class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"   class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"    class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"    class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"   class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <div class="sidebar-section">التحليلات</div>
  <a href="/teacher/analytics.php"  class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات العامة</a>
  <a href="/teacher/question-analytics.php" class="sidebar-link"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
<main class="main-content">
  <h2 style="margin-bottom:1.5rem;">لوحة التحكم الرئيسية</h2>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-users" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-label">طالب مسجل</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-book" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= $totalCourses ?></div>
      <div class="stat-label">مقرر</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-layer-group" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= $totalLessons ?></div>
      <div class="stat-label">درس</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-gamepad" style="color:#C2185B;"></i></div>
      <div class="stat-value"><?= $totalGames ?></div>
      <div class="stat-label">مغامرة مكتملة</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
    <!-- Top students -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-medal"></i> أفضل الطلاب</div>
        <a href="/teacher/students.php" class="btn btn-outline btn-sm">الكل</a>
      </div>
      <ul class="leaderboard">
        <?php foreach ($topStudents as $i => $s): ?>
        <li>
          <span class="rank-num rank-<?= $i+1 ?>"><?= $i+1 ?></span>
          <span class="lb-name"><?= clean($s['name']) ?></span>
          <span class="lb-pts"><?= number_format($s['points']) ?> نقطة</span>
          <span class="badge badge-primary"><?= $s['scholars'] ?> علماء</span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Open lessons -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-unlock"></i> الدروس المفتوحة</div>
        <a href="/teacher/lessons.php" class="btn btn-outline btn-sm">الكل</a>
      </div>
      <?php if (empty($openLessons)): ?>
      <p style="color:var(--muted);font-size:.9rem;">لا توجد دروس مفتوحة.</p>
      <?php else: ?>
      <?php foreach ($openLessons as $l): ?>
      <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px solid var(--border);">
        <span style="font-size:1.2rem;">📗</span>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:.9rem;"><?= clean($l['name']) ?></div>
          <div style="font-size:.8rem;color:var(--muted);"><?= clean($l['course_name']) ?></div>
        </div>
        <a href="/teacher/lessons.php?toggle=<?= $l['id'] ?>&csrf=<?= $_SESSION['teacher_id'] ?>" class="btn btn-danger btn-sm">إغلاق</a>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent students -->
  <div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-user-plus"></i> أحدث الطلاب المسجلين</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الاسم</th><th>الرقم الجامعي</th><th>المستوى</th><th>السنة</th><th>النقاط</th><th>تاريخ التسجيل</th></tr></thead>
        <tbody>
          <?php foreach ($recentStudents as $s): ?>
          <tr>
            <td><?= clean($s['name']) ?></td>
            <td><?= clean($s['university_id']) ?></td>
            <td><?= clean($s['level']) ?></td>
            <td><?= clean($s['study_year']) ?></td>
            <td><strong style="color:var(--accent);"><?= $s['points'] ?></strong></td>
            <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ========== قسم التحليلات المتقدمة ========== -->
  <h3 style="margin-top:2.5rem;margin-bottom:1.5rem;color:var(--primary);">
    <i class="fas fa-chart-line"></i> تحليلات الأداء ونقاط الضعف
  </h3>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
    <!-- الأسئلة الأكثر صعوبة -->
    <div class="card">
      <div class="card-header" style="background:linear-gradient(135deg,#D32F2F,#F44336);color:white;">
        <div class="card-title"><i class="fas fa-exclamation-triangle"></i> الأسئلة الأكثر صعوبة</div>
        <a href="/teacher/question-analytics.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:none;">عرض الكل</a>
      </div>
      <?php if (empty($difficultQuestions)): ?>
      <div style="padding:1.5rem;text-align:center;color:var(--muted);">
        <i class="fas fa-check-circle" style="font-size:2rem;color:var(--primary);margin-bottom:0.5rem;"></i>
        <p style="margin:0;">لا توجد أسئلة صعبة حالياً. أداء ممتاز!</p>
      </div>
      <?php else: ?>
      <div style="max-height:400px;overflow-y:auto;">
        <table style="width:100%;font-size:0.9rem;">
          <thead style="position:sticky;top:0;background:white;border-bottom:2px solid var(--border);">
            <tr>
              <th style="padding:0.75rem;text-align:right;">السؤال</th>
              <th style="padding:0.75rem;text-align:center;width:80px;">النجاح</th>
              <th style="padding:0.75rem;text-align:center;width:100px;">المحاولات</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($difficultQuestions as $q): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:0.75rem;">
                <div style="font-weight:600;margin-bottom:0.25rem;"><?= clean(mb_substr($q['question_text'], 0, 60)) ?>...</div>
                <div style="font-size:0.8rem;color:var(--muted);">📚 <?= clean($q['lesson_name']) ?></div>
              </td>
              <td style="padding:0.75rem;text-align:center;">
                <span style="display:inline-block;padding:0.25rem 0.5rem;border-radius:12px;font-weight:700;font-size:0.85rem;background:<?= ($q['success_rate'] < 30) ? '#FFEBEE' : '#FFF3E0' ?>;color:<?= ($q['success_rate'] < 30) ? '#D32F2F' : '#F57C00' ?>;">
                  <?= round($q['success_rate'] ?? 0) ?>%
                </span>
              </td>
              <td style="padding:0.75rem;text-align:center;font-weight:600;color:var(--muted);">
                <?= $q['total_attempts'] ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- الطلاب المتعثرون -->
    <div class="card">
      <div class="card-header" style="background:linear-gradient(135deg,#F57C00,#FF9800);color:white;">
        <div class="card-title"><i class="fas fa-user-injured"></i> الطلاب المتعثرون</div>
        <a href="/teacher/students.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:none;">عرض الكل</a>
      </div>
      <?php if (empty($strugglingStudents)): ?>
      <div style="padding:1.5rem;text-align:center;color:var(--muted);">
        <i class="fas fa-smile" style="font-size:2rem;color:var(--primary);margin-bottom:0.5rem;"></i>
        <p style="margin:0;">جميع الطلاب يؤدون بشكل جيد!</p>
      </div>
      <?php else: ?>
      <div style="max-height:400px;overflow-y:auto;">
        <table style="width:100%;font-size:0.9rem;">
          <thead style="position:sticky;top:0;background:white;border-bottom:2px solid var(--border);">
            <tr>
              <th style="padding:0.75rem;text-align:right;">الطالب</th>
              <th style="padding:0.75rem;text-align:center;width:80px;">الفشل</th>
              <th style="padding:0.75rem;text-align:center;width:80px;">الألعاب</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($strugglingStudents as $st): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:0.75rem;">
                <a href="/teacher/analytics.php?student_id=<?= $st['id'] ?>" style="text-decoration:none;color:inherit;">
                  <div style="font-weight:600;margin-bottom:0.25rem;"><?= clean($st['name']) ?></div>
                  <div style="font-size:0.8rem;color:var(--muted);"><?= clean($st['university_id']) ?> • <?= $st['points'] ?> نقطة</div>
                </a>
              </td>
              <td style="padding:0.75rem;text-align:center;">
                <span style="display:inline-block;padding:0.25rem 0.5rem;border-radius:12px;font-weight:700;font-size:0.85rem;background:<?= ($st['failure_rate'] > 70) ? '#FFEBEE' : '#FFF3E0' ?>;color:<?= ($st['failure_rate'] > 70) ? '#D32F2F' : '#F57C00' ?>;">
                  <?= round($st['failure_rate']) ?>%
                </span>
              </td>
              <td style="padding:0.75rem;text-align:center;font-weight:600;color:var(--muted);">
                <?= $st['failed_games'] ?>/<?= $st['total_games'] ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- الدروس الأكثر صعوبة -->
  <?php if (!empty($difficultLessons)): ?>
  <div class="card" style="margin-top:1.5rem;">
    <div class="card-header" style="background:linear-gradient(135deg,#7B1FA2,#9C27B0);color:white;">
      <div class="card-title"><i class="fas fa-book-open"></i> الدروس التي تحتاج إلى تحسين</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>الدرس</th>
            <th>المقرر</th>
            <th>عدد الطلاب</th>
            <th>نسبة الإكمال</th>
            <th>متوسط النقاط</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($difficultLessons as $dl): ?>
          <tr>
            <td>
              <strong><?= clean($dl['name']) ?></strong>
            </td>
            <td style="color:var(--muted);font-size:0.9rem;"><?= clean($dl['course_name']) ?></td>
            <td><?= $dl['students_count'] ?> طالب</td>
            <td>
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="flex:1;background:#e0e0e0;border-radius:10px;height:8px;overflow:hidden;">
                  <div style="width:<?= $dl['completion_rate'] ?>%;height:100%;background:<?= ($dl['completion_rate'] < 30) ? '#D32F2F' : (($dl['completion_rate'] < 60) ? '#F57C00' : '#689F38') ?>;"></div>
                </div>
                <span style="font-weight:700;color:<?= ($dl['completion_rate'] < 30) ? '#D32F2F' : (($dl['completion_rate'] < 60) ? '#F57C00' : '#689F38') ?>;"><?= round($dl['completion_rate']) ?>%</span>
              </div>
            </td>
            <td style="font-weight:700;color:var(--accent);"><?= round($dl['avg_points']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- نقاط الضعف العامة -->
  <?php if (!empty($weaknessAnalysis)): ?>
  <div class="card" style="margin-top:1.5rem;">
    <div class="card-header" style="background:linear-gradient(135deg,#1976D2,#2196F3);color:white;">
      <div class="card-title"><i class="fas fa-bullseye"></i> نقاط الضعف العامة لدى الطلاب</div>
    </div>
    <div style="padding:1.5rem;">
      <p style="color:var(--text);margin-bottom:1rem;">
        <i class="fas fa-info-circle"></i> هذه الأسئلة/المواضيع تسببت بأكبر عدد من الأخطاء:
      </p>
      <div style="display:grid;gap:1rem;">
        <?php foreach ($weaknessAnalysis as $wa): ?>
        <div style="background:var(--bg);border-right:4px solid #2196F3;padding:1rem;border-radius:8px;">
          <div style="font-weight:700;margin-bottom:0.5rem;color:var(--primary);">
            <?= clean(mb_substr($wa['question_text'], 0, 100)) ?>...
          </div>
          <div style="display:flex;gap:1.5rem;font-size:0.85rem;color:var(--muted);">
            <div><i class="fas fa-book"></i> <?= clean($wa['topic']) ?></div>
            <div><i class="fas fa-users"></i> <?= $wa['students_affected'] ?> طالب أخطأوا</div>
            <div><i class="fas fa-times-circle"></i> <?= $wa['total_wrong_attempts'] ?> محاولة خاطئة</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- خطة التحسين المقترحة -->
  <div class="card" style="margin-top:1.5rem;border:2px solid #4CAF50;">
    <div class="card-header" style="background:linear-gradient(135deg,#388E3C,#4CAF50);color:white;">
      <div class="card-title"><i class="fas fa-lightbulb"></i> خطة التحسين المقترحة</div>
    </div>
    <div style="padding:1.5rem;">
      <h4 style="color:var(--primary);margin-bottom:1rem;">📋 توصيات عملية للمعلم:</h4>
      
      <?php if (!empty($difficultQuestions)): ?>
      <div class="alert alert-warning" style="margin-bottom:1rem;">
        <strong><i class="fas fa-exclamation-triangle"></i> الأسئلة الصعبة (<?= count($difficultQuestions) ?> سؤال)</strong>
        <ul style="margin:0.5rem 0 0 1.5rem;padding:0;">
          <li>راجع صياغة هذه الأسئلة لتكون أكثر وضوحاً</li>
          <li>أضف شرحاً تفصيلياً في التغذية الراجعة</li>
          <li>قدم أمثلة إضافية في العرض التقديمي أو الفيديو</li>
          <li>خصص وقتاً أطول لشرح هذه المفاهيم في الحصة</li>
        </ul>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($strugglingStudents)): ?>
      <div class="alert alert-danger" style="margin-bottom:1rem;">
        <strong><i class="fas fa-user-injured"></i> الطلاب المتعثرون (<?= count($strugglingStudents) ?> طالب)</strong>
        <ul style="margin:0.5rem 0 0 1.5rem;padding:0;">
          <li>تواصل مع هؤلاء الطلاب بشكل فردي</li>
          <li>قدم لهم جلسات دعم إضافية</li>
          <li>راجع معهم الأخطاء الشائعة التي وقعوا فيها</li>
          <li>شجعهم على المحاولة مرة أخرى بعد المراجعة</li>
        </ul>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($difficultLessons)): ?>
      <div class="alert alert-info" style="margin-bottom:1rem;">
        <strong><i class="fas fa-book-open"></i> الدروس الصعبة (<?= count($difficultLessons) ?> درس)</strong>
        <ul style="margin:0.5rem 0 0 1.5rem;padding:0;">
          <li>قم بتحديث محتوى الدروس الصعبة</li>
          <li>أضف المزيد من الأمثلة والتمارين التطبيقية</li>
          <li>فكر في تقسيم الدروس المعقدة إلى أجزاء أصغر</li>
          <li>استخدم وسائل توضيحية متنوعة (فيديو، رسوم، تفاعلية)</li>
        </ul>
      </div>
      <?php endif; ?>
      
      <div class="alert alert-success">
        <strong><i class="fas fa-check-circle"></i> نصائح عامة لتحسين الأداء:</strong>
        <ul style="margin:0.5rem 0 0 1.5rem;padding:0;">
          <li><strong>المتابعة المستمرة:</strong> راجع هذه التحليلات أسبوعياً لمتابعة التحسن</li>
          <li><strong>التغذية الراجعة:</strong> تأكد من أن كل إجابة خاطئة تحتوي على شرح واضح</li>
          <li><strong>التنوع:</strong> استخدم أساليب تدريس متنوعة لتناسب أنماط التعلم المختلفة</li>
          <li><strong>التشجيع:</strong> احتفل بالتقدم الصغير لتحفيز الطلاب</li>
          <li><strong>المرونة:</strong> كن مستعداً لتعديل خطة الدرس بناءً على أداء الطلاب</li>
        </ul>
      </div>
      
      <div style="margin-top:1.5rem;text-align:center;">
        <a href="/teacher/question-analytics.php" class="btn btn-primary">
          <i class="fas fa-chart-line"></i> عرض تحليل تفصيلي للأسئلة
        </a>
        <a href="/teacher/analytics.php" class="btn btn-outline">
          <i class="fas fa-chart-bar"></i> التحليلات العامة
        </a>
      </div>
    </div>
  </div>

</main>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>
