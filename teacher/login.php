<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (!empty($_SESSION['teacher_id'])) {
    header('Location: /teacher/dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $teacher  = loginTeacher($email, $password);
    if ($teacher) {
        $_SESSION['teacher_id']   = $teacher['id'];
        $_SESSION['teacher_name'] = $teacher['name'];
        header('Location: /teacher/dashboard.php'); exit;
    }
    $error = 'البريد أو كلمة المرور غير صحيحة.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>دخول الأستاذ – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="login-page" style="background:linear-gradient(135deg,#1A237E 0%,#283593 100%);">
  <div class="login-card">
    <div class="login-logo">
      <div style="width:80px;height:80px;background:linear-gradient(135deg,#1A237E,#3F51B5);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto .75rem;">👨‍🏫</div>
      <h1>لوحة الأستاذ</h1>
      <p>المساعد الذّكاليّ في تعليم العربية</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= clean($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
        <input type="email" name="email" class="form-control" placeholder="admin@dhakali.edu" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fas fa-lock"></i> كلمة المرور</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" style="background:var(--dark);">
        <i class="fas fa-sign-in-alt"></i> دخول لوحة الإدارة
      </button>
    </form>
    <div style="text-align:center;margin-top:1.25rem;">
      <a href="/" style="color:var(--muted);font-size:.88rem;"><i class="fas fa-user-graduate"></i> دخول الطالب</a>
    </div>
    <div class="alert alert-info" style="margin-top:1rem;font-size:.82rem;">
      <i class="fas fa-info-circle"></i>
      <strong>بيانات الدخول التجريبية:</strong><br>
      البريد: admin@dhakali.edu<br>
      كلمة المرور: password
    </div>
  </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
