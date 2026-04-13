<?php
// ============================================================
// analytics.php
// ============================================================
require_once 'auth.php';
require_once 'db.php';
requireLogin();

$user  = currentUser();
$theme = $user['theme'] ?? 'dark';
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskFlow — Analytics</title>
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="topnav">
  <div class="nav-brand">
    <a href="index.php" style="text-decoration:none;display:flex;align-items:center;gap:8px;color:inherit">
      <span class="logo-mark">✦</span>
      <span class="logo-text">TaskFlow</span>
    </a>
  </div>
  <div class="nav-center">
    <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;opacity:0.7">Analytics</span>
  </div>
  <div class="nav-actions">
    <a href="index.php" class="btn btn-outline-sm">← Dashboard</a>
    <a href="logout.php" class="nav-btn" title="Sign out">⇠</a>
  </div>
</nav>

<main class="analytics-main">
  <div class="analytics-hero">
    <h1 class="analytics-title">Your Productivity <span class="accent-text">Insights</span></h1>
    <p class="analytics-sub">Visual breakdown of your task patterns and completion trends.</p>
  </div>

  <!-- KPI CARDS -->
  <div class="kpi-row" id="kpiRow">
    <div class="kpi-card">
      <div class="kpi-icon">📋</div>
      <div class="kpi-num" id="kpiTotal">—</div>
      <div class="kpi-label">Total Tasks</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon">✅</div>
      <div class="kpi-num" id="kpiCompleted">—</div>
      <div class="kpi-label">Completed</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon">⏳</div>
      <div class="kpi-num" id="kpiPending">—</div>
      <div class="kpi-label">Pending</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon">🎯</div>
      <div class="kpi-num" id="kpiRate">—</div>
      <div class="kpi-label">Completion Rate</div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="charts-grid">
    <div class="chart-card">
      <div class="chart-card-header">
        <h3 class="chart-title">Status Overview</h3>
        <span class="chart-badge">Donut</span>
      </div>
      <div class="chart-wrap">
        <canvas id="statusChart"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-card-header">
        <h3 class="chart-title">Priority Breakdown</h3>
        <span class="chart-badge">Bar</span>
      </div>
      <div class="chart-wrap">
        <canvas id="priorityChart"></canvas>
      </div>
    </div>

    <div class="chart-card chart-card-wide">
      <div class="chart-card-header">
        <h3 class="chart-title">Tasks Added — Last 7 Days</h3>
        <span class="chart-badge">Line</span>
      </div>
      <div class="chart-wrap chart-wrap-wide">
        <canvas id="weeklyChart"></canvas>
      </div>
    </div>
  </div>

</main>

<input type="hidden" id="csrfToken" value="<?= $token ?>">

<script>
// ---- Fetch analytics data ----
async function loadAnalytics() {
  const token = document.getElementById('csrfToken').value;
  const res   = await fetch('actions.php?action=analytics');
  const data  = await res.json();

  document.getElementById('kpiTotal').textContent     = data.total || 0;
  document.getElementById('kpiCompleted').textContent = data.completed || 0;
  document.getElementById('kpiPending').textContent   = data.pending || 0;
  const rate = data.total > 0 ? Math.round((data.completed / data.total) * 100) : 0;
  document.getElementById('kpiRate').textContent = rate + '%';

  const isDark  = document.documentElement.getAttribute('data-theme') !== 'light';
  const textCol = isDark ? 'rgba(255,255,255,0.75)' : 'rgba(30,20,60,0.75)';
  const gridCol = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';

  Chart.defaults.color = textCol;
  Chart.defaults.font.family = "'DM Sans', sans-serif";

  // --- Donut: Status ---
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: ['Completed', 'Pending'],
      datasets: [{
        data: [data.completed, data.pending],
        backgroundColor: ['#a78bfa', '#ffb899'],
        borderColor: isDark ? '#1a1030' : '#f5f0ff',
        borderWidth: 3,
        hoverOffset: 8,
      }]
    },
    options: {
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } },
        tooltip: { callbacks: { label: c => ` ${c.label}: ${c.raw} tasks` } }
      }
    }
  });

  // --- Bar: Priority ---
  const priMap = { low: 0, medium: 0, high: 0 };
  (data.by_priority || []).forEach(p => { priMap[p.priority] = parseInt(p.cnt); });
  new Chart(document.getElementById('priorityChart'), {
    type: 'bar',
    data: {
      labels: ['Low', 'Medium', 'High'],
      datasets: [{
        label: 'Tasks',
        data: [priMap.low, priMap.medium, priMap.high],
        backgroundColor: ['#34d399', '#fbbf24', '#fb7185'],
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: gridCol }, ticks: { stepSize: 1 } },
        x: { grid: { display: false } }
      }
    }
  });

  // --- Line: Weekly ---
  const days = [];
  const counts = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().split('T')[0];
    const found = (data.weekly || []).find(w => w.day === key);
    days.push(d.toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric' }));
    counts.push(found ? parseInt(found.cnt) : 0);
  }

  new Chart(document.getElementById('weeklyChart'), {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Tasks Added',
        data: counts,
        borderColor: '#a78bfa',
        backgroundColor: isDark ? 'rgba(167,139,250,0.15)' : 'rgba(167,139,250,0.1)',
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#a78bfa',
        pointRadius: 5,
        pointHoverRadius: 8,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: gridCol }, ticks: { stepSize: 1 } },
        x: { grid: { display: false } }
      }
    }
  });
}

// Animate KPI counters
function animateNum(el, target) {
  let val = 0;
  const step = Math.ceil(target / 30);
  const iv = setInterval(() => {
    val = Math.min(val + step, target);
    el.textContent = el.id === 'kpiRate' ? val + '%' : val;
    if (val >= target) clearInterval(iv);
  }, 30);
}

loadAnalytics().then(() => {
  setTimeout(() => {
    document.querySelectorAll('.kpi-num').forEach(el => {
      const txt = el.textContent;
      const num = parseInt(txt.replace('%',''));
      if (!isNaN(num)) {
        el.textContent = '0' + (txt.includes('%') ? '%' : '');
        animateNum(el, num);
      }
    });
  }, 100);
});
</script>
</body>
</html>