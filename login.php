<?php
// ============================================================
// login.php
// ============================================================
require_once 'auth.php';
require_once 'db.php';
requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid credentials. Please try again.';
            }
        }
    }
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskFlow — Sign In</title>
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-bg">
  <div class="auth-orb orb1"></div>
  <div class="auth-orb orb2"></div>
  <div class="auth-orb orb3"></div>
</div>

<div class="auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-mark">✦</div>
      <span class="logo-text">TaskFlow</span>
    </div>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to your workspace</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <span class="alert-icon">⚠</span> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= $token ?>">

      <div class="form-group">
        <label class="form-label">Email or Username</label>
        <input type="text" name="identifier" class="form-input"
               placeholder="you@example.com"
               value="<?= sanitize($_POST['identifier'] ?? '') ?>"
               autocomplete="username" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrapper">
          <input type="password" name="password" id="passwordInput" class="form-input"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw()" aria-label="Toggle password">
            <span id="pwIcon">👁</span>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full">
        <span>Sign In</span>
        <span class="btn-arrow">→</span>
      </button>
    </form>

    <p class="auth-footer">
      No account? <a href="register.php" class="auth-link">Create one free</a>
    </p>
  </div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('passwordInput');
  const icon = document.getElementById('pwIcon');
  if (inp.type === 'password') { inp.type = 'text'; icon.textContent = '🙈'; }
  else { inp.type = 'password'; icon.textContent = '👁'; }
}
</script>
</body>
</html>