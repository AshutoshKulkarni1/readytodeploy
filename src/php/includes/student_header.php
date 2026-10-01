<?php
/**
 * Student Dashboard Header & Sidebar Include
 * LMSportel Modern Template Shell
 *
 * Shared layout for all student pages:
 * dashboard, my-tests, results, analytics, profile, etc.
 */

if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

$pageToNavMap = [
    'dashboard'       => 'dashboard',
    'my-tests'        => 'my-tests',
    'results'         => 'results',
    'result-details'  => 'results',
    'analytics'       => 'analytics',
    'test-analysis'   => 'analytics',
    'average-score'   => 'analytics',
    'evaluated'       => 'analytics',
    'profile'         => 'profile',
];
$currentNav = $pageToNavMap[$currentPage] ?? $currentPage;

// Ensure student details are present
$studentName = trim($student['name'] ?? ($firstName ?? 'Student'));
$nameParts = preg_split('/\s+/', $studentName);
$firstName = $firstName ?? ($nameParts[0] ?? 'Student');
$studentInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
if (empty($studentInitials)) $studentInitials = 'ST';

$collegeName = trim($student['college_name'] ?? 'LMSportel');
$collegeWords = preg_split('/\s+/', $collegeName);
$collegeInitials = '';
foreach ($collegeWords as $w) {
    if (!empty($w)) $collegeInitials .= strtoupper($w[0]);
}
$collegeInitials = substr($collegeInitials, 0, 2) ?: 'NC';

// Get pending tests count for badge if not set
if (!isset($pendingCount)) {
    if (isset($pendingTests)) {
        $pendingCount = $pendingTests;
    } elseif (isset($student['id']) || isset($_SESSION['student_id'])) {
        $sId = $student['id'] ?? $_SESSION['student_id'];
        $testsForCount = getStudentTests($sId);
        $pendingCount = count(array_filter($testsForCount, fn($t) => $t['submission_status'] === 'in_progress' || ($t['status'] === 'active' && !$t['submission_status'])));
    } else {
        $pendingCount = 0;
    }
}
?>

<!-- Public Sans Font & Theme Flash Prevention -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>
  (function () {
    var t = 'light';
    try {
      t = localStorage.getItem('lms-theme') ||
          (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    } catch (e) {}
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>

<style>
/* ---------- Critical LMSportel Icon & Layout Styles ---------- */
:root {
  --bg: #F2F4F8;
  --surface: #FFFFFF;
  --surface-2: #F6F8FB;
  --ink: #152033;
  --ink-2: #4A576B;
  --ink-3: #6B778B;
  --line: #E2E7EF;
  --line-strong: #C9D2DF;

  --brand: #2447B5;
  --brand-hover: #1C3A9B;
  --on-brand: #FFFFFF;
  --brand-soft: #E7ECFA;
  --banner: #1B3A9C;

  --ok: #137049;       --ok-soft: #E0F2E9;
  --warn: #955300;     --warn-soft: #FCEED4;   --warn-dot: #E09A1A;
  --info: #1D6DB0;     --info-soft: #E0EEF9;
  --review: #6748B5;   --review-soft: #ECE6F8;

  --r-lg: 14px;
  --r-md: 10px;
  --r-sm: 8px;
  --topbar-h: 60px;
  --sidebar-w: 260px;
  color-scheme: light;
}

[data-theme="dark"] {
  --bg: #0F1522;
  --surface: #161E2E;
  --surface-2: #1B2536;
  --ink: #E8EDF6;
  --ink-2: #AAB5C7;
  --ink-3: #8593A8;
  --line: #263248;
  --line-strong: #37465F;

  --brand: #7D9AF2;
  --brand-hover: #9AB1F6;
  --on-brand: #0B1226;
  --brand-soft: #1F2C52;
  --banner: #182B6E;

  --ok: #5FD0A0;       --ok-soft: #12302A;
  --warn: #F0B04A;     --warn-soft: #3A2C12;   --warn-dot: #E8A73A;
  --info: #6DB4EE;     --info-soft: #12293D;
  --review: #B6A2F0;   --review-soft: #251F47;
  color-scheme: dark;
}

body {
  font-family: "Public Sans", system-ui, -apple-system, sans-serif !important;
  background-color: var(--bg) !important;
  color: var(--ink) !important;
  margin: 0 !important;
}

/* CRITICAL: SVG icon sizing & stroke enforcement so they NEVER render as giant black shapes */
.ic, svg.ic {
  width: 18px !important;
  height: 18px !important;
  max-width: 18px !important;
  max-height: 18px !important;
  min-width: 18px !important;
  min-height: 18px !important;
  fill: none !important;
  stroke: currentColor !important;
  stroke-width: 1.75 !important;
  stroke-linecap: round !important;
  stroke-linejoin: round !important;
  flex: none !important;
  display: inline-block !important;
  vertical-align: middle !important;
}
.ic *, svg.ic * {
  fill: none !important;
  stroke: currentColor !important;
  stroke-width: 1.75 !important;
  stroke-linecap: round !important;
  stroke-linejoin: round !important;
}

/* Shell Layout */
.app, .dashboard-layout {
  display: grid !important;
  grid-template-columns: var(--sidebar-w) minmax(0, 1fr) !important;
  min-height: 100vh !important;
  background: var(--bg) !important;
}

.sidebar, .dashboard-sidebar {
  position: sticky !important;
  top: 0 !important;
  height: 100vh !important;
  display: flex !important;
  flex-direction: column !important;
  background: var(--surface) !important;
  border-right: 1px solid var(--line) !important;
  padding: 16px 14px !important;
  z-index: 40 !important;
  width: var(--sidebar-w) !important;
  min-width: var(--sidebar-w) !important;
  max-width: var(--sidebar-w) !important;
  box-sizing: border-box !important;
}

.college { display: flex; align-items: center; gap: 10px; padding: 4px 6px 16px; border-bottom: 1px solid var(--line); margin-bottom: 12px; }
.college__logo {
  position: relative; width: 40px; height: 40px; border-radius: 10px; overflow: hidden; flex: none;
  background: var(--brand-soft); color: var(--brand);
  display: grid; place-items: center; font-weight: 700; font-size: 15px;
}
.college__logo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.college__name { font-weight: 600; font-size: 13px; line-height: 1.3; color: var(--ink); word-break: break-word; }
.college__sub { font-size: 11.5px; color: var(--ink-3); margin-top: 2px; }

.nav, .sidebar-nav { display: flex !important; flex-direction: column !important; gap: 4px !important; }
.nav a, .sidebar-nav a {
  display: flex !important; align-items: center !important; gap: 12px !important;
  height: 40px !important; padding: 0 12px !important; border-radius: var(--r-sm, 8px) !important;
  color: var(--ink-2) !important; font-weight: 500 !important; text-decoration: none !important;
  font-size: 13.5px !important; transition: background .15s, color .15s !important;
  white-space: nowrap !important;
}
.nav a:hover, .sidebar-nav a:hover { background: var(--surface-2) !important; color: var(--ink) !important; }
.nav a[aria-current="page"], .nav a.active, .sidebar-nav a.active {
  background: var(--brand-soft) !important; color: var(--brand) !important; font-weight: 600 !important;
}
.nav .count {
  margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
  background: var(--brand); color: var(--on-brand); font-size: 11.5px; font-weight: 600;
  display: grid; place-items: center;
}

.sidebar__foot {
  margin-top: auto !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 4px !important;
  border-top: 1px solid var(--line) !important;
  padding-top: 12px !important;
}
.switchrow {
  display: flex !important; align-items: center !important; gap: 12px !important;
  height: 40px !important; padding: 0 12px !important; color: var(--ink-2) !important;
  font-weight: 500 !important; font-size: 13.5px !important;
}
.switch {
  margin-left: auto; width: 36px; height: 20px; border-radius: 999px;
  background: var(--line-strong); position: relative; transition: background .2s; border: none; cursor: pointer; padding: 0;
}
.switch::after {
  content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
  background: #fff; transition: transform .2s;
}
.switch[aria-checked="true"] { background: var(--brand); }
.switch[aria-checked="true"]::after { transform: translateX(16px); background: var(--on-brand); }
.signout {
  display: flex !important; align-items: center !important; gap: 12px !important;
  height: 40px !important; padding: 0 12px !important; border-radius: var(--r-sm, 8px) !important;
  color: var(--ink-2) !important; font-weight: 500 !important; text-decoration: none !important; font-size: 13.5px !important;
}
.signout:hover { background: var(--surface-2) !important; color: var(--ink) !important; }
.me {
  display: flex !important; align-items: center !important; gap: 10px !important;
  padding: 10px 8px 0 !important; min-width: 0 !important; overflow: hidden !important;
}
.avatar {
  width: 34px !important; height: 34px !important; border-radius: 50% !important; flex: none !important;
  background: var(--brand-soft) !important; color: var(--brand) !important;
  display: grid !important; place-items: center !important; font-weight: 600 !important; font-size: 13px !important;
}
.me__name { font-weight: 600 !important; font-size: 13.5px !important; line-height: 1.25 !important; color: var(--ink) !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
.me__sub { font-size: 12px !important; color: var(--ink-3) !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }

.scrim, .sidebar-overlay { position: fixed; inset: 0; background: rgba(8, 14, 28, .5); opacity: 0; visibility: hidden; transition: opacity .25s, visibility 0s .25s; z-index: 35; }
.scrim.show, .sidebar-overlay.active { opacity: 1; visibility: visible; transition-delay: 0s; }

/* Topbar */
.topbar, .dashboard-topnav {
  position: sticky !important; top: 0 !important; z-index: 30 !important; height: var(--topbar-h) !important;
  display: flex !important; align-items: center !important; gap: 12px !important; padding: 0 28px !important;
  background: var(--surface) !important; border-bottom: 1px solid var(--line) !important; box-sizing: border-box !important;
}
.brandmark { display: flex !important; align-items: center !important; gap: 10px !important; font-weight: 700 !important; font-size: 16px !important; letter-spacing: -.01em !important; color: var(--ink) !important; text-decoration: none !important; }
.brandmark svg { width: 28px !important; height: 28px !important; flex: none !important; }
.topbar__right { margin-left: auto !important; display: flex !important; align-items: center !important; gap: 10px !important; }
.iconbtn {
  width: 36px !important; height: 36px !important; border-radius: var(--r-sm, 8px) !important;
  display: grid !important; place-items: center !important; color: var(--ink-2) !important;
  background: none !important; border: none !important; cursor: pointer !important; padding: 0 !important;
  overflow: hidden !important;
}
.iconbtn:hover { background: var(--surface-2) !important; color: var(--ink) !important; }
.themebtn svg.ic { width: 18px !important; height: 18px !important; }
.themebtn .i-sun { display: none !important; }
.themebtn .i-moon { display: block !important; }
[data-theme="dark"] .themebtn .i-sun { display: block !important; }
[data-theme="dark"] .themebtn .i-moon { display: none !important; }
.badge-me { display: flex !important; align-items: center !important; gap: 8px !important; padding: 3px 12px 3px 3px !important; border-radius: 999px !important; border: 1px solid var(--line) !important; font-weight: 600 !important; font-size: 13px !important; color: var(--ink) !important; text-decoration: none !important; }
.badge-me .avatar { width: 28px !important; height: 28px !important; font-size: 12px !important; }
.menubtn { display: none !important; }

/* Page Layout */
.page, .dashboard-content {
  padding: 24px 28px 96px !important;
  display: grid !important;
  grid-template-columns: minmax(0, 1fr) 312px !important;
  gap: 0 24px !important;
  align-items: start !important;
  max-width: 1480px !important;
  margin: 0 auto !important;
  box-sizing: border-box !important;
  background: var(--bg) !important;
}
.page--full, .page--full.dashboard-content {
  display: block !important;
  width: 100% !important;
  max-width: 1480px !important;
  margin: 0 auto !important;
}
.main, .dashboard-content-inner {
  display: flex !important;
  flex-direction: column !important;
  gap: 24px !important;
  min-width: 0 !important;
  width: 100% !important;
}

/* Floating Study Assistant */
.fab {
  position: fixed !important; right: 24px !important; bottom: 24px !important; z-index: 50 !important;
  width: 52px !important; height: 52px !important; border-radius: 50% !important;
  background: var(--brand) !important; color: var(--on-brand) !important;
  display: grid !important; place-items: center !important;
  box-shadow: 0 6px 18px rgba(20, 40, 100, .28) !important; border: none !important; cursor: pointer !important;
}
.fab:hover { background: var(--brand-hover) !important; }
.fab svg.ic { width: 24px !important; height: 24px !important; max-width: 24px !important; max-height: 24px !important; }
.fab .i-close { display: none !important; }
.fab[aria-expanded="true"] .i-close { display: block !important; }
.fab[aria-expanded="true"] .i-bot { display: none !important; }

.chat {
  position: fixed !important; right: 24px !important; bottom: 90px !important; z-index: 50 !important;
  width: 360px !important; max-width: calc(100vw - 32px) !important; height: min(540px, calc(100vh - 120px)) !important;
  display: flex !important; flex-direction: column !important;
  background: var(--surface) !important; border: 1px solid var(--line-strong) !important; border-radius: var(--r-lg) !important;
  box-shadow: 0 16px 40px rgba(10, 20, 40, .22) !important;
  opacity: 0 !important; transform: translateY(12px) !important; visibility: hidden !important;
  transition: opacity .2s ease, transform .2s ease, visibility 0s .2s !important;
}
.chat.open { opacity: 1 !important; transform: none !important; visibility: visible !important; transition-delay: 0s !important; }
.chat__head { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
.chat__head b { display: block; font-size: 14.5px; line-height: 1.2; color: var(--ink); }
.chat__head small { color: var(--ink-3); font-size: 12px; }
.chat__log { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.msg { max-width: 86%; padding: 9px 12px; border-radius: 12px; font-size: 13.5px; line-height: 1.45; }
.msg--bot { background: var(--surface-2); box-shadow: inset 0 0 0 1px var(--line); border-bottom-left-radius: 4px; color: var(--ink); }
.msg--me { align-self: flex-end; background: var(--brand); color: var(--on-brand); border-bottom-right-radius: 4px; }
.typing { display: inline-flex; gap: 4px; padding: 12px; }
.typing i { width: 6px; height: 6px; border-radius: 50%; background: var(--ink-3); animation: blink 1.1s infinite; }
.typing i:nth-child(2) { animation-delay: .15s; }
.typing i:nth-child(3) { animation-delay: .3s; }
@keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
.pills { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 16px 12px; }
.pill { padding: 5px 11px; border-radius: 999px; border: 1px solid var(--line-strong); font-size: 12.5px; color: var(--ink-2); background: var(--surface); cursor: pointer; }
.pill:hover { border-color: var(--brand); color: var(--brand); }
.chat__in { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--line); }
.chat__in input {
  flex: 1; height: 38px; padding: 0 12px; border-radius: var(--r-sm);
  border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink); font: inherit;
}
.chat__in input::placeholder { color: var(--ink-3); }
.chat__in input:focus-visible { outline: 2px solid var(--brand); outline-offset: 0; border-color: var(--brand); }
.chat__in .btn { width: 38px; padding: 0; height: 38px; }

/* Responsive adjustments */
@media (max-width: 900px) {
  .app, .dashboard-layout { grid-template-columns: minmax(0, 1fr) !important; }
  .sidebar, .dashboard-sidebar { position: fixed !important; left: 0 !important; top: 0 !important; width: 272px !important; transform: translateX(-100%) !important; transition: transform .25s ease !important; }
  .sidebar.open, .dashboard-sidebar.open { transform: none !important; }
  .menubtn { display: grid !important; }
  .topbar, .dashboard-topnav, .page, .dashboard-content { padding-left: 16px !important; padding-right: 16px !important; }
}
</style>

<!-- Lucide-style LMSportel SVG Icons (zero dimensions, invisible in layout) -->
<svg style="position:absolute; width:0; height:0; overflow:hidden; pointer-events:none;" aria-hidden="true">
  <defs>
    <symbol id="i-dash" viewBox="0 0 24 24"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></symbol>
    <symbol id="i-tests" viewBox="0 0 24 24"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></symbol>
    <symbol id="i-award" viewBox="0 0 24 24"><path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/></symbol>
    <symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-right" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="i-left" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></symbol>
    <symbol id="i-more" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></symbol>
    <symbol id="i-timer" viewBox="0 0 24 24"><path d="M10 2h4"/><path d="m12 14 3-3"/><circle cx="12" cy="14" r="8"/></symbol>
    <symbol id="i-bot" viewBox="0 0 24 24"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></symbol>
  </defs>
</svg>

<div class="app dashboard-layout" id="app">

  <!-- ===== Left Sidebar ===== -->
  <aside class="sidebar dashboard-sidebar" id="sidebar" aria-label="Main navigation">
    <div class="college">
      <div class="college__logo">
        <span><?= h($collegeInitials) ?></span>
        <?php if (!empty($student['college_logo'])): ?>
          <img src="<?= h($student['college_logo']) ?>" alt="" onerror="this.remove()">
        <?php else: ?>
          <img src="<?= ASSETS_URL ?>/img/college-logo.png" alt="" onerror="this.remove()">
        <?php endif; ?>
      </div>
      <div style="min-width: 0; overflow: hidden;">
        <div class="college__name"><?= h($collegeName) ?></div>
        <div class="college__sub"><?= h($student['course_name'] ?? 'Portal') ?></div>
      </div>
    </div>

    <nav class="nav sidebar-nav">
      <a href="dashboard.php" class="<?= $currentNav === 'dashboard' ? 'active' : '' ?>" <?= $currentNav === 'dashboard' ? 'aria-current="page"' : '' ?>><svg class="ic"><use href="#i-dash"/></svg><span>Dashboard</span></a>
      <a href="my-tests.php" class="<?= $currentNav === 'my-tests' ? 'active' : '' ?>" <?= $currentNav === 'my-tests' ? 'aria-current="page"' : '' ?>><svg class="ic"><use href="#i-tests"/></svg><span>My tests</span><?php if ($pendingCount > 0): ?><span class="count" id="navCount"><?= $pendingCount ?></span><?php endif; ?></a>
      <a href="results.php" class="<?= $currentNav === 'results' ? 'active' : '' ?>" <?= $currentNav === 'results' ? 'aria-current="page"' : '' ?>><svg class="ic"><use href="#i-award"/></svg><span>Results</span></a>
      <a href="analytics.php" class="<?= $currentNav === 'analytics' ? 'active' : '' ?>" <?= $currentNav === 'analytics' ? 'aria-current="page"' : '' ?>><svg class="ic"><use href="#i-chart"/></svg><span>Analytics</span></a>
      <a href="profile.php" class="<?= $currentNav === 'profile' ? 'active' : '' ?>" <?= $currentNav === 'profile' ? 'aria-current="page"' : '' ?>><svg class="ic"><use href="#i-user"/></svg><span>Profile</span></a>
    </nav>

    <div class="sidebar__foot">
      <div class="switchrow">
        <svg class="ic"><use href="#i-moon"/></svg>
        <span id="darkLabel">Dark mode</span>
        <button class="switch" id="darkSwitch" role="switch" aria-checked="false" aria-labelledby="darkLabel" type="button"></button>
      </div>
      <a class="signout" href="<?= BASE_URL ?>/logout.php"><svg class="ic"><use href="#i-logout"/></svg><span>Sign out</span></a>
      <div class="me">
        <div class="avatar"><?= h($studentInitials) ?></div>
        <div style="min-width: 0; overflow: hidden;">
          <div class="me__name"><?= h($studentName) ?></div>
          <div class="me__sub"><?= !empty($student['roll_number']) ? 'Roll no. ' . h($student['roll_number']) : 'Student' ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Mobile Scrim Overlay -->
  <div class="scrim sidebar-overlay" id="scrim" onclick="toggleSidebar()"></div>

  <!-- ===== Main Column ===== -->
  <div class="dashboard-main-col" style="min-width: 0; width: 100%; display: flex; flex-direction: column; min-height: 100vh;">
    
    <!-- Topbar -->
    <header class="topbar dashboard-topnav">
      <button class="iconbtn menubtn" id="menuBtn" aria-label="Open navigation" aria-controls="sidebar" type="button"><svg class="ic"><use href="#i-menu"/></svg></button>
      <a class="brandmark" href="dashboard.php">
        <svg viewBox="0 0 28 28" aria-hidden="true"><rect width="28" height="28" rx="8" fill="var(--brand)"/><path d="M9.5 7.5v12h8" fill="none" stroke="var(--on-brand)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="18.5" cy="9.5" r="2" fill="var(--on-brand)"/></svg>
        LMSportel
      </a>
      <div class="topbar__right">
        <button class="iconbtn themebtn" id="themeBtn" aria-label="Switch color theme" type="button">
          <svg class="ic i-moon"><use href="#i-moon"/></svg><svg class="ic i-sun"><use href="#i-sun"/></svg>
        </button>
        <a class="badge-me" href="profile.php" aria-label="Your profile"><div class="avatar"><?= h($studentInitials) ?></div><span><?= h($firstName) ?></span></a>
      </div>
    </header>

    <!-- Page Content Container -->
    <main class="page <?= ($currentPage === 'dashboard') ? '' : 'page--full' ?> dashboard-content" style="flex: 1;">
      <div class="main dashboard-content-inner" style="width: 100%; min-width: 0;">