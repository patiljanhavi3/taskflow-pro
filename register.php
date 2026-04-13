<?php
// ============================================================
// register.php
// ============================================================
require_once 'auth.php';
require_once 'db.php';
requireGuest();

$error   = '';
$success = '';

$avatarColors = ['#a78bfa','#f97316','#34d399','#60a5fa','#fb7185','#fbbf24'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3–30 chars (letters, numbers, underscore).';
        } else {
            $db = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $check->execute([$email, $username]);
            if ($check->fetch()) {
                $error = 'Email or username already taken.';
            } else {
                $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $color = $avatarColors[array_rand($avatarColors)];
                $stmt  = $db->prepare("INSERT INTO users (username, email, password_hash, avatar_color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash, $color]);
                $success = 'Account created! Redirecting…';
                header('Refresh: 2; url=login.php');
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
<title>TaskFlow — Create Account</title>
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
    <h1 class="auth-title">Create account</h1>
    <p class="auth-sub">Free forever. No credit card.</p>

    <?php if ($error): ?>
    <div class="alert alert-error"><span class="alert-icon">⚠</span> <?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><span class="alert-icon">✓</span> <?= sanitize($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= $token ?>">

      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-input"
               placeholder="cooluser" value="<?= sanitize($_POST['username'] ?? '') ?>"
               autocomplete="username" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input"
               placeholder="you@example.com" value="<?= sanitize($_POST['email'] ?? '') ?>"
               autocomplete="email" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password <span class="form-hint">(min 8 chars)</span></label>
        <div class="input-wrapper">
          <input type="password" name="password" id="pw1" class="form-input"
                 placeholder="••••••••" autocomplete="new-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('pw1','icon1')" aria-label="Toggle">
            <span id="icon1">👁</span>
          </button>
        </div>
        <div class="pw-strength" id="pwStrength"></div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <div class="input-wrapper">
          <input type="password" name="confirm" id="pw2" class="form-input"
                 placeholder="••••••••" autocomplete="new-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('pw2','icon2')" aria-label="Toggle">
            <span id="icon2">👁</span>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full">
        <span>Create Account</span>
        <span class="btn-arrow">→</span>
      </button>
    </form>

    <p class="auth-footer">
      Already have an account? <a href="login.php" class="auth-link">Sign in</a>
    </p>
  </div>
</div>

<script>
function togglePw(id, iconId) {
  const inp = document.getElementById(id);
  const icon = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; icon.textContent = '🙈'; }
  else { inp.type = 'password'; icon.textContent = '👁'; }
}

document.getElementById('pw1').addEventListener('input', function() {
  const val = this.value;
  const bar = document.getElementById('pwStrength');
  let strength = 0;
  if (val.length >= 8) strength++;
  if (/[A-Z]/.test(val)) strength++;
  if (/[0-9]/.test(val)) strength++;
  if (/[^a-zA-Z0-9]/.test(val)) strength++;
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  const classes = ['', 'pw-weak', 'pw-fair', 'pw-good', 'pw-strong'];
  bar.textContent = val.length > 0 ? labels[strength] : '';
  bar.className = 'pw-strength ' + (val.length > 0 ? classes[strength] : '');
});
</script>
</body>
</html>