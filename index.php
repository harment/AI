<?php
require_once __DIR__ . '/includes/functions.php';
startSession();

// Redirect if already logged in
if (!empty($_SESSION['student_id'])) {
    header('Location: /student/dashboard.php'); exit;
}
if (!empty($_SESSION['teacher_id'])) {
    header('Location: /teacher/dashboard.php'); exit;
}

$error = '';
$page  = $_GET['page'] ?? 'login';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $student  = loginStudent($email, $password);
        if ($student) {
            $_SESSION['student_id']   = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            logActivity($student['id'], null, 'login');
            header('Location: /student/dashboard.php'); exit;
        }
        $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
    }

    if ($action === 'register') {
        $name          = trim($_POST['name'] ?? '');
        $university_id = trim($_POST['university_id'] ?? '');
        $level         = $_POST['level'] ?? 'beginner';
        $study_year    = trim($_POST['study_year'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';

        if (!$name || !$university_id || !$email || !$password) {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة.';
        } elseif ($password !== $confirm) {
            $error = 'كلمتا المرور غير متطابقتين.';
        } elseif (strlen($password) < 6) {
            $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
        } else {
            try {
                $db   = getDB();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO students (name, university_id, level, study_year, email, password_hash) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name, $university_id, $level, $study_year, $email, $hash]);
                $id = $db->lastInsertId();
                $_SESSION['student_id']   = $id;
                $_SESSION['student_name'] = $name;
                logActivity($id, null, 'register');
                header('Location: /student/dashboard.php'); exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'البريد الإلكتروني أو الرقم الجامعي مسجل مسبقاً.';
                } else {
                    $error = 'حدث خطأ أثناء التسجيل. يرجى المحاولة مرة أخرى.';
                }
            }
        }
        if ($error) $page = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>المساعد الذّكاليّ في تعليم العربية</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#1A237E">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="الذّكاليّ">
  <link rel="icon" href="/assets/icons/icon-192.png">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div style="width:80px;height:80px;background:linear-gradient(135deg,#1A237E,#4CAF50);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto .75rem;">🌿</div>
      <h1>المساعد الذّكاليّ</h1>
      <p>في تعليم العربية لغير الناطقين بها</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= clean($error) ?></div>
    <?php endif; ?>

    <div class="tab-group">
      <button class="tab <?= $page !== 'register' ? 'active' : '' ?>" data-target="loginForm" data-container="#tabContent">
        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
      </button>
      <button class="tab <?= $page === 'register' ? 'active' : '' ?>" data-target="registerForm" data-container="#tabContent">
        <i class="fas fa-user-plus"></i> حساب جديد
      </button>
    </div>

    <div id="tabContent">
      <!-- ---- Login Form ---- -->
      <div id="loginForm" data-tab-content <?= $page === 'register' ? 'style="display:none"' : '' ?>>
        <form method="POST" action="/?page=login">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
          </div>
          <div class="form-group">
            <label class="form-label"><i class="fas fa-lock"></i> كلمة المرور</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fas fa-sign-in-alt"></i> دخول
          </button>
        </form>
        <div style="text-align:center;margin-top:1.25rem;">
          <a href="/teacher/login.php" style="color:var(--muted);font-size:.88rem;">
            <i class="fas fa-chalkboard-teacher"></i> دخول الأستاذ
          </a>
        </div>
      </div>

      <!-- ---- Register Form ---- -->
      <div id="registerForm" data-tab-content <?= $page !== 'register' ? 'style="display:none"' : '' ?>>
        <form method="POST" action="/?page=register">
          <input type="hidden" name="action" value="register">
          <div class="form-group">
            <label class="form-label"><i class="fas fa-user"></i> الاسم الكامل *</label>
            <input type="text" name="name" class="form-control" placeholder="أدخل اسمك الكامل" required maxlength="150">
          </div>
          <div class="form-group">
            <label class="form-label"><i class="fas fa-id-card"></i> الرقم الجامعي *</label>
            <input type="text" name="university_id" class="form-control" placeholder="مثال: 2024001" required maxlength="50">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label class="form-label"><i class="fas fa-layer-group"></i> المستوى</label>
              <select name="level" class="form-control">
                <option value="beginner">مبتدئ</option>
                <option value="intermediate">متوسط</option>
                <option value="advanced">متقدم</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label"><i class="fas fa-calendar"></i> السنة الدراسية</label>
              <select name="study_year" class="form-control">
                <option value="الأولى">الأولى</option>
                <option value="الثانية">الثانية</option>
                <option value="الثالثة">الثالثة</option>
                <option value="الرابعة">الرابعة</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني *</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" required maxlength="200">
          </div>
          <div class="form-group">
            <label class="form-label"><i class="fas fa-lock"></i> كلمة المرور *</label>
            <input type="password" name="password" class="form-control" placeholder="6 أحرف على الأقل" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label"><i class="fas fa-lock"></i> تأكيد كلمة المرور *</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور" required minlength="6">
          </div>
          <button type="submit" class="btn btn-accent btn-block btn-lg">
            <i class="fas fa-user-plus"></i> إنشاء الحساب
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
// Service Worker for PWA
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}
</script>
</body>
</html>
