<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();

$msg = ''; $msgType = 'success';
$courseFilter = (int)($_GET['course_id'] ?? 0);

// Handle toggle open/close
if (isset($_GET['toggle'])) {
    $lid = (int)$_GET['toggle'];
    $db->prepare("UPDATE lessons SET is_open = 1 - is_open WHERE id = ?")->execute([$lid]);
    $msg = 'تم تغيير حالة الدرس.';
}

// Handle add
if (($_POST['action'] ?? '') === 'add') {
    $cid   = (int)($_POST['course_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $gtype = $_POST['game_type'] ?? 'mountain';
    $open  = isset($_POST['is_open']) ? 1 : 0;
    if ($cid && $name) {
        // Handle file uploads
        $pdfUrl = $podcastUrl = $videoUrl = null;
        if (!empty($_FILES['pdf']['name'])) {
            $pdfUrl = uploadFile($_FILES['pdf'], 'pdfs', ['application/pdf','pdf']);
        }
        if (!empty($_FILES['podcast']['name'])) {
            $podcastUrl = uploadFile($_FILES['podcast'], 'podcasts', ['audio/mpeg','audio/mp3','mp3','mpeg']);
        }
        if (!empty($_FILES['video']['name'])) {
            $videoUrl = uploadFile($_FILES['video'], 'videos', ['video/mp4','mp4']);
        }
        // Video URL from text
        if (empty($videoUrl) && !empty($_POST['video_url'])) {
            $videoUrl = trim($_POST['video_url']);
        }
        $db->prepare("INSERT INTO lessons (course_id, name, description, pdf_url, podcast_url, video_url, is_open, game_type) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$cid, $name, $desc, $pdfUrl, $podcastUrl, $videoUrl, $open, $gtype]);
        $lessonId = $db->lastInsertId();
        $msg = 'تم إضافة الدرس.';
        // Redirect to AI generation if requested
        if (!empty($_POST['ai_generate'])) {
            header("Location: /teacher/ai_generate.php?lesson_id=$lessonId");
            exit;
        }
    }
}

// Handle delete
if (($_GET['action'] ?? '') === 'delete') {
    $lid = (int)($_GET['id'] ?? 0);
    if ($lid) { $db->prepare("DELETE FROM lessons WHERE id=?")->execute([$lid]); $msg = 'تم حذف الدرس.'; $msgType = 'danger'; }
}

// Fetch courses for dropdown
$courses = $db->query("SELECT id, name FROM courses WHERE status='active' ORDER BY id")->fetchAll();

// Fetch lessons with filter
$where = $courseFilter ? "WHERE l.course_id = $courseFilter" : '';
$lessons = $db->query("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id $where ORDER BY l.course_id, l.sort_order, l.id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>إدارة الدروس</title>
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
  <a href="/teacher/analytics.php"        class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
  <a href="/teacher/question_analysis.php" class="sidebar-link"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> إضافة درس</button>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <!-- Filter by course -->
  <form method="GET" style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <select name="course_id" class="form-control" style="width:auto;">
      <option value="">كل المقررات</option>
      <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $courseFilter === $c['id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> تصفية</button>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>اسم الدرس</th><th>المقرر</th><th>PDF</th><th>بودكاست</th><th>فيديو</th><th>لعبة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
      <tbody>
        <?php if (empty($lessons)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);">لا توجد دروس.</td></tr>
        <?php endif; ?>
        <?php foreach ($lessons as $i => $l): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= clean($l['name']) ?></strong><?php if ($l['description']): ?><br><small style="color:var(--muted);"><?= clean(mb_substr($l['description'],0,50)) ?>…</small><?php endif; ?></td>
          <td><?= clean($l['course_name']) ?></td>
          <td><?= $l['pdf_url'] ? '<i class="fas fa-check" style="color:var(--primary)"></i>' : '<i class="fas fa-times" style="color:var(--muted)"></i>' ?></td>
          <td><?= $l['podcast_url'] ? '<i class="fas fa-check" style="color:var(--primary)"></i>' : '<i class="fas fa-times" style="color:var(--muted)"></i>' ?></td>
          <td><?= $l['video_url'] ? '<i class="fas fa-check" style="color:var(--primary)"></i>' : '<i class="fas fa-times" style="color:var(--muted)"></i>' ?></td>
          <td><?= ['mountain'=>'⛰️','maze'=>'🌀','ship'=>'⛵'][$l['game_type']] ?></td>
          <td>
            <a href="?toggle=<?= $l['id'] ?><?= $courseFilter ? "&course_id=$courseFilter" : '' ?>" class="btn <?= $l['is_open'] ? 'btn-danger' : 'btn-primary' ?> btn-sm">
              <?= $l['is_open'] ? '🔒 إغلاق' : '🔓 فتح' ?>
            </a>
          </td>
          <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <a href="/teacher/ai_generate.php?lesson_id=<?= $l['id'] ?>" class="btn btn-accent btn-sm" title="توليد محتوى بالذكاء الاصطناعي"><i class="fas fa-robot"></i></a>
            <a href="/teacher/questions.php?lesson_id=<?= $l['id'] ?>" class="btn btn-info btn-sm" title="إدارة الأسئلة"><i class="fas fa-question-circle"></i></a>
            <a href="/teacher/question_analysis.php?lesson_id=<?= $l['id'] ?>" class="btn btn-primary btn-sm" title="تحليل الأسئلة"><i class="fas fa-chart-line"></i></a>
            <a href="?action=delete&id=<?= $l['id'] ?><?= $courseFilter ? "&course_id=$courseFilter" : '' ?>" class="btn btn-danger btn-sm" data-confirm="حذف الدرس وجميع أسئلته؟" title="حذف"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Add Lesson Modal -->
<div id="addModal" style="display:none;" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">إضافة درس جديد</span>
      <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">المقرر *</label>
        <select name="course_id" class="form-control" required>
          <option value="">اختر المقرر…</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $courseFilter === $c['id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">اسم الدرس *</label><input type="text" name="name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">نوع اللعبة</label>
          <select name="game_type" class="form-control">
            <option value="mountain">⛰️ مغامرة الجبل</option>
            <option value="maze">🌀 المتاهة</option>
            <option value="ship">⛵ مغامرة البحر</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">الحالة</label>
          <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;cursor:pointer;">
            <input type="checkbox" name="is_open" value="1"> مفتوح للطلاب
          </label>
        </div>
      </div>
      <div class="form-group"><label class="form-label"><i class="fas fa-file-pdf" style="color:#E53935;"></i> رفع ملف PDF</label><input type="file" name="pdf" class="form-control" accept=".pdf"></div>
      <div class="form-group"><label class="form-label"><i class="fas fa-podcast" style="color:var(--info);"></i> رفع ملف بودكاست (mp3)</label><input type="file" name="podcast" class="form-control" accept="audio/*"></div>
      <div class="form-group"><label class="form-label"><i class="fas fa-video" style="color:var(--accent);"></i> رفع فيديو أو رابط YouTube</label>
        <input type="file" name="video" class="form-control" accept="video/*" style="margin-bottom:.5rem;">
        <input type="url" name="video_url" class="form-control" placeholder="أو أدخل رابط يوتيوب…">
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary flex-1"><i class="fas fa-save"></i> حفظ الدرس</button>
        <button type="submit" name="ai_generate" value="1" class="btn btn-accent flex-1"><i class="fas fa-robot"></i> حفظ + توليد بالذكاء</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>
