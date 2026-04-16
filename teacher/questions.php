<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher  = requireTeacher();
$db       = getDB();
$lessonId = (int)($_GET['lesson_id'] ?? 0);

if (!$lessonId) { header('Location: /teacher/lessons.php'); exit; }

$lesson = $db->prepare("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
$lesson->execute([$lessonId]);
$lesson = $lesson->fetch();
if (!$lesson) { header('Location: /teacher/lessons.php'); exit; }

$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? '';

// Add question
if ($action === 'add') {
    $qtext   = trim($_POST['question_text'] ?? '');
    $optA    = trim($_POST['option_a'] ?? '');
    $optB    = trim($_POST['option_b'] ?? '');
    $optC    = trim($_POST['option_c'] ?? '');
    $optD    = trim($_POST['option_d'] ?? '');
    $correct = $_POST['correct_option'] ?? 'a';
    $fbOk    = trim($_POST['feedback_correct'] ?? '');
    $fbWrong = trim($_POST['feedback_wrong'] ?? '');
    if ($qtext && $optA && $optB && $optC && $optD) {
        $db->prepare("INSERT INTO questions (lesson_id,question_text,option_a,option_b,option_c,option_d,correct_option,feedback_correct,feedback_wrong) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$lessonId, $qtext, $optA, $optB, $optC, $optD, $correct, $fbOk, $fbWrong]);
        $msg = 'تمت إضافة السؤال.';
    }
}
if ($action === 'delete' && isset($_GET['qid'])) {
    $db->prepare("DELETE FROM questions WHERE id=? AND lesson_id=?")->execute([(int)$_GET['qid'], $lessonId]);
    $msg = 'تم حذف السؤال.'; $msgType = 'danger';
}

$questions = $db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY sort_order, id");
$questions->execute([$lessonId]);
$questions = $questions->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>أسئلة الدرس – <?= clean($lesson['name']) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button><span>👨‍🏫</span><span>لوحة الأستاذ</span></div>
  <ul class="navbar-nav"><li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li></ul>
</nav>
<aside class="sidebar">
  <a href="/teacher/dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"  class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"   class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"   class="sidebar-link active"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
</aside>
<main class="main-content">
  <div style="margin-bottom:.75rem;font-size:.88rem;color:var(--muted);">
    <a href="/teacher/lessons.php">الدروس</a> / <strong><?= clean($lesson['name']) ?></strong>
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-question-circle"></i> أسئلة: <?= clean($lesson['name']) ?></h2>
    <div style="display:flex;gap:.75rem;">
      <a href="/teacher/question_analysis.php?lesson_id=<?= $lessonId ?>" class="btn btn-info btn-sm"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
      <a href="/teacher/ai_generate.php?lesson_id=<?= $lessonId ?>&generate=questions" class="btn btn-accent btn-sm"><i class="fas fa-robot"></i> توليد بالذكاء</a>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> سؤال جديد</button>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <div style="margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;">
    <span class="badge badge-primary"><?= count($questions) ?> سؤال</span>
    <?php if (count($questions) < 7): ?>
    <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> يُنصح بـ 7 أسئلة على الأقل للعبة</span>
    <?php endif; ?>
  </div>

  <?php if (empty($questions)): ?>
  <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد أسئلة بعد. أضف أسئلة يدوياً أو استخدم الذكاء الاصطناعي.</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <?php foreach ($questions as $i => $q): ?>
    <div class="card" style="padding:1.25rem;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
        <div style="flex:1;">
          <div style="font-weight:700;margin-bottom:.75rem;font-size:1rem;"><span style="background:var(--dark);color:#fff;border-radius:50%;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;margin-left:.5rem;"><?= $i+1 ?></span> <?= clean($q['question_text']) ?></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem;">
            <?php foreach (['a'=>'أ','b'=>'ب','c'=>'ج','d'=>'د'] as $k => $label): ?>
            <div style="padding:.5rem .75rem;border-radius:6px;border:2px solid <?= $q['correct_option'] === $k ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $q['correct_option'] === $k ? '#E8F5E9' : '#fff' ?>;font-size:.9rem;">
              <strong><?= $label ?></strong>) <?= clean($q['option_'.$k]) ?>
              <?= $q['correct_option'] === $k ? ' ✅' : '' ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($q['feedback_correct'] || $q['feedback_wrong']): ?>
          <div style="font-size:.82rem;color:var(--muted);border-top:1px solid var(--border);padding-top:.5rem;">
            <?php if ($q['feedback_correct']): ?><div>✅ <?= clean($q['feedback_correct']) ?></div><?php endif; ?>
            <?php if ($q['feedback_wrong']): ?><div>❌ <?= clean($q['feedback_wrong']) ?></div><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <a href="?lesson_id=<?= $lessonId ?>&action=delete&qid=<?= $q['id'] ?>" class="btn btn-danger btn-sm" data-confirm="حذف هذا السؤال؟"><i class="fas fa-trash"></i></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<!-- Add Question Modal -->
<div id="addModal" style="display:none;" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">إضافة سؤال جديد</span>
      <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">نص السؤال *</label><textarea name="question_text" class="form-control" rows="2" required></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group"><label class="form-label">أ) الخيار الأول *</label><input type="text" name="option_a" class="form-control" required></div>
        <div class="form-group"><label class="form-label">ب) الخيار الثاني *</label><input type="text" name="option_b" class="form-control" required></div>
        <div class="form-group"><label class="form-label">ج) الخيار الثالث *</label><input type="text" name="option_c" class="form-control" required></div>
        <div class="form-group"><label class="form-label">د) الخيار الرابع *</label><input type="text" name="option_d" class="form-control" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">الإجابة الصحيحة *</label>
        <select name="correct_option" class="form-control">
          <option value="a">أ</option><option value="b">ب</option><option value="c">ج</option><option value="d">د</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">تغذية راجعة عند الإجابة الصحيحة</label><input type="text" name="feedback_correct" class="form-control" placeholder="رسالة تشجيعية…"></div>
      <div class="form-group"><label class="form-label">تغذية راجعة عند الإجابة الخاطئة</label><input type="text" name="feedback_wrong" class="form-control" placeholder="شرح الإجابة الصحيحة…"></div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> حفظ السؤال</button>
    </form>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>
