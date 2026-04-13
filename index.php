<?php
// ============================================================
// index.php — Main Dashboard
// ============================================================
require_once 'auth.php';
require_once 'db.php';
requireLogin();

$user  = currentUser();
$theme = $user['theme'] ?? 'dark';
$token = csrf_token();

// Stats for top bar
$db    = getDB();
$uid   = (int)$user['id'];
$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(status='completed') as completed,
    SUM(status='pending') as pending,
    SUM(priority='high' AND status='pending') as urgent
    FROM tasks WHERE user_id=$uid");
$s = $stats->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskFlow — Dashboard</title>
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="topnav">
  <div class="nav-brand">
    <span class="logo-mark">✦</span>
    <span class="logo-text">TaskFlow</span>
  </div>

  <div class="nav-center">
    <div class="search-wrap">
      <span class="search-icon">⌕</span>
      <input type="text" id="searchInput" placeholder="Search tasks…" class="search-input" autocomplete="off">
      <kbd class="search-kbd">⌘K</kbd>
    </div>
  </div>

  <div class="nav-actions">
    <a href="analytics.php" class="nav-btn" title="Analytics">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    </a>
    <button class="nav-btn theme-toggle" id="themeToggle" title="Toggle theme">
      <span id="themeIcon"><?= $theme === 'dark' ? '☀' : '🌙' ?></span>
    </button>
    <div class="nav-avatar" onclick="toggleUserMenu()" title="<?= htmlspecialchars($user['username']) ?>">
      <div class="avatar" style="background:<?= htmlspecialchars($user['avatar_color']) ?>">
        <?= strtoupper(substr($user['username'], 0, 1)) ?>
      </div>
    </div>
    <div class="user-menu" id="userMenu">
      <div class="user-menu-header">
        <div class="avatar" style="background:<?= htmlspecialchars($user['avatar_color']) ?>">
          <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
        <div>
          <div class="user-menu-name"><?= htmlspecialchars($user['username']) ?></div>
          <div class="user-menu-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
      </div>
      <a href="analytics.php" class="user-menu-item">📊 Analytics</a>
      <a href="logout.php" class="user-menu-item user-menu-logout">⇠ Sign Out</a>
    </div>
  </div>
</nav>

<!-- MAIN LAYOUT -->
<main class="main-layout">

  <!-- STATS ROW -->
  <section class="stats-row" id="statsRow">
    <div class="stat-card stat-total">
      <div class="stat-num"><?= (int)$s['total'] ?></div>
      <div class="stat-label">Total Tasks</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-num"><?= (int)$s['pending'] ?></div>
      <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card stat-done">
      <div class="stat-num"><?= (int)$s['completed'] ?></div>
      <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card stat-urgent">
      <div class="stat-num"><?= (int)$s['urgent'] ?></div>
      <div class="stat-label">Urgent</div>
    </div>
  </section>

  <!-- CONTENT GRID -->
  <div class="content-grid">

    <!-- LEFT: ADD TASK + FILTERS + LIST -->
    <div class="tasks-panel">

      <!-- ADD TASK -->
      <div class="add-task-card" id="addTaskCard">
        <div class="add-task-header" onclick="toggleAddForm()">
          <span class="add-task-title">✦ New Task</span>
          <span class="add-task-toggle" id="addToggle">＋</span>
        </div>
        <div class="add-task-body" id="addTaskBody">
          <input type="text" id="taskTitle" class="form-input" placeholder="What needs to be done?" maxlength="255">
          <textarea id="taskDesc" class="form-input form-textarea" placeholder="Description (optional)" rows="2"></textarea>
          <div class="add-task-row">
            <select id="taskPriority" class="form-input form-select">
              <option value="low">🟢 Low</option>
              <option value="medium" selected>🟡 Medium</option>
              <option value="high">🔴 High</option>
            </select>
            <input type="date" id="taskDue" class="form-input">
            <button class="btn btn-primary" onclick="addTask()">Add Task</button>
          </div>
        </div>
      </div>

      <!-- FILTER + SORT BAR -->
      <div class="filter-bar">
        <div class="filter-tabs">
          <button class="filter-tab active" data-filter="all" onclick="setFilter(this,'all')">All</button>
          <button class="filter-tab" data-filter="pending" onclick="setFilter(this,'pending')">Pending</button>
          <button class="filter-tab" data-filter="completed" onclick="setFilter(this,'completed')">Done</button>
        </div>
        <div class="sort-wrap">
          <select class="form-input form-select sort-select" id="sortSelect" onchange="loadTasks()">
            <option value="recent">Sort: Recent</option>
            <option value="priority">Sort: Priority</option>
            <option value="due">Sort: Due Date</option>
          </select>
        </div>
      </div>

      <!-- TASK LIST -->
      <div class="task-list" id="taskList">
        <div class="loading-tasks">
          <div class="spinner"></div>
        </div>
      </div>

    </div>

    <!-- RIGHT: POMODORO WIDGET -->
    <div class="side-panel">
      <div class="pomodoro-card" id="pomodoroCard">
        <div class="pomo-header">
          <span class="pomo-title">⏱ Pomodoro</span>
          <div class="pomo-modes">
            <button class="pomo-mode-btn active" data-min="25" onclick="setPomoMode(this,25)">Focus</button>
            <button class="pomo-mode-btn" data-min="5" onclick="setPomoMode(this,5)">Short</button>
            <button class="pomo-mode-btn" data-min="15" onclick="setPomoMode(this,15)">Long</button>
          </div>
        </div>
        <div class="pomo-ring-wrap">
          <svg class="pomo-ring" viewBox="0 0 120 120">
            <circle class="pomo-track" cx="60" cy="60" r="52"/>
            <circle class="pomo-progress" id="pomoProgress" cx="60" cy="60" r="52"
                    stroke-dasharray="326.73" stroke-dashoffset="0"/>
          </svg>
          <div class="pomo-time-display" id="pomoDisplay">25:00</div>
        </div>
        <div class="pomo-controls">
          <button class="pomo-btn" onclick="pomodoroAction('start')" id="pomoStart">▶ Start</button>
          <button class="pomo-btn pomo-btn-outline" onclick="pomodoroAction('reset')">↺</button>
        </div>
        <div class="pomo-session-count">
          Sessions today: <strong id="pomoSessions">0</strong>
        </div>
      </div>

      <!-- QUICK STATS MINI -->
      <div class="mini-progress-card">
        <div class="mini-progress-label">
          <span>Today's Progress</span>
          <span id="progressPct">0%</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" id="progressBar" style="width:0%"></div>
        </div>
        <p class="mini-progress-sub" id="progressSub">Keep going! 🚀</p>
      </div>

    </div>
  </div>
</main>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- CONFETTI CANVAS -->
<canvas id="confettiCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999"></canvas>

<input type="hidden" id="csrfToken" value="<?= $token ?>">

<script src="script.js"></script>
</body>
</html>