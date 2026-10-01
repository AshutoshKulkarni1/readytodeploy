<?php
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Get student info with college details
$stmt = $pdo->prepare("
    SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo 
    FROM students s 
    JOIN batches b ON b.id = s.batch_id 
    JOIN courses c ON c.id = b.course_id 
    JOIN colleges cl ON cl.id = c.college_id 
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    redirect(BASE_URL . '/login.php');
}

// Get tests for this student's batch
$tests = getStudentTests($studentId);

// Name and Initials
$studentName = trim($student['name'] ?? 'Student');
$nameParts = preg_split('/\s+/', $studentName);
$firstName = $nameParts[0] ?? 'Student';
$studentInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
if (empty($studentInitials)) $studentInitials = 'ST';

$collegeName = trim($student['college_name'] ?? 'LMSportel');
$collegeWords = preg_split('/\s+/', $collegeName);
$collegeInitials = '';
foreach ($collegeWords as $w) {
    if (!empty($w)) $collegeInitials .= strtoupper($w[0]);
}
$collegeInitials = substr($collegeInitials, 0, 2) ?: 'NC';

// Date helpers
$today = new DateTime();
$dayName = $today->format('l');
$formattedDate = $today->format('F j, Y');

// Build frontend tests array
$frontendTests = [];
foreach ($tests as $t) {
    $now = time();
    $startTs = !empty($t['start_time']) ? strtotime($t['start_time']) : null;
    $endTs = !empty($t['end_time']) ? strtotime($t['end_time']) : null;

    $st = 'closed';
    if ($t['status'] === 'active') {
        if ($endTs && $now > $endTs) {
            $st = 'closed';
        } elseif ($startTs && $now < $startTs) {
            $st = 'upcoming';
        } else {
            $st = 'active';
        }
    } elseif ($t['status'] === 'upcoming' || $t['status'] === 'scheduled') {
        $st = 'upcoming';
    } else {
        $st = 'closed';
    }

    $mine = 'new';
    if ($t['submission_status'] === 'evaluated') {
        $mine = 'done';
    } elseif ($t['submission_status'] === 'submitted' || ($t['evaluation_status'] ?? '') === 'pending_manual_review') {
        $mine = 'review';
    } elseif ($t['submission_status'] === 'in_progress') {
        $mine = 'progress';
    } else {
        $mine = 'new';
    }

    $takenMinutes = 0;
    if (!empty($t['started_at']) && !empty($t['submitted_at'])) {
        $diff = max(0, strtotime($t['submitted_at']) - strtotime($t['started_at']));
        $takenMinutes = max(1, round($diff / 60));
    }

    $desc = !empty($t['description']) 
        ? $t['description'] 
        : ('Assessment for ' . ($t['course_name'] ?? 'your batch') . '. One attempt permitted. Your answers are saved automatically.');

    $frontendTests[] = [
        'id'      => (int)$t['id'],
        'title'   => $t['title'],
        'subject' => $t['course_name'] ?? 'General',
        'mins'    => (int)($t['duration_minutes'] ?? 60),
        'q'       => (int)($t['total_questions'] ?? 0),
        'marks'   => (float)($t['total_marks'] ?? 0),
        'status'  => $st,
        'mine'    => $mine,
        'score'   => $t['total_marks_obtained'] !== null ? (float)$t['total_marks_obtained'] : null,
        'taken'   => $takenMinutes,
        'start'   => !empty($t['start_time']) ? date('c', strtotime($t['start_time'])) : null,
        'end'     => !empty($t['end_time']) ? date('c', strtotime($t['end_time'])) : null,
        'desc'    => $desc
    ];
}

// Build recent scores
$scores = [];
$evaluatedTests = array_values(array_filter($tests, fn($t) => $t['submission_status'] === 'evaluated'));
usort($evaluatedTests, fn($a, $b) => strtotime($a['submitted_at'] ?? $a['end_time'] ?? 0) <=> strtotime($b['submitted_at'] ?? $b['end_time'] ?? 0));
$lastEvaluated = array_slice($evaluatedTests, -6);

foreach ($lastEvaluated as $et) {
    $obtained = (float)($et['total_marks_obtained'] ?? 0);
    $total = (float)($et['total_marks'] ?? 1);
    $pct = $total > 0 ? round(($obtained / $total) * 100) : 0;

    $avgPct = 65;
    try {
        $stmtAvg = $pdo->prepare("SELECT AVG(total_marks_obtained) as avg_marks FROM student_test_attempts WHERE test_id = ? AND status = 'evaluated'");
        $stmtAvg->execute([$et['id']]);
        $res = $stmtAvg->fetch();
        if ($res && $res['avg_marks'] !== null && $total > 0) {
            $avgPct = round(((float)$res['avg_marks'] / $total) * 100);
        }
    } catch (\Exception $e) {}

    $words = explode(' ', $et['title']);
    $label = count($words) > 2 ? ($words[0] . ' ' . $words[1]) : $et['title'];
    if (strlen($label) > 12) $label = substr($label, 0, 10) . '..';

    $scores[] = [
        'l'   => $label,
        'you' => $pct,
        'avg' => $avgPct
    ];
}

if (empty($scores)) {
    $scores = [
        ['l' => 'Unit 1', 'you' => 70, 'avg' => 64],
        ['l' => 'Quiz 1', 'you' => 78, 'avg' => 70],
        ['l' => 'Midterm', 'you' => 82, 'avg' => 72],
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LMSportel | Student dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<script>
  /* Set theme before first paint to avoid a flash */
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
/* ---------- Tokens ---------- */
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
  --sidebar-w: 248px;
  color-scheme: light;
}

:root[data-theme="dark"] {
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

/* ---------- Base ---------- */
*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
  margin: 0;
  font: 400 14px/1.5 "Public Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  color: var(--ink);
  background: var(--bg);
  font-variant-numeric: tabular-nums;
}
h1, h2, h3, p, dl, dd, ul { margin: 0; }
ul { padding: 0; list-style: none; }
button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; }
a { color: inherit; text-decoration: none; }
[hidden] { display: none !important; }
:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; border-radius: 6px; }
.ic { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; flex: none; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

/* ---------- Shell ---------- */
.app { display: grid; grid-template-columns: var(--sidebar-w) minmax(0, 1fr); min-height: 100vh; }

.sidebar {
  position: sticky; top: 0; height: 100vh;
  display: flex; flex-direction: column;
  background: var(--surface); border-right: 1px solid var(--line);
  padding: 16px 12px;
  z-index: 40;
}
.college { display: flex; align-items: center; gap: 10px; padding: 4px 8px 16px; }
.college__logo {
  position: relative; width: 40px; height: 40px; border-radius: 10px; overflow: hidden; flex: none;
  background: var(--brand-soft); color: var(--brand);
  display: grid; place-items: center; font-weight: 700; font-size: 15px;
}
.college__logo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.college__name { font-weight: 600; font-size: 13.5px; line-height: 1.3; }
.college__sub { font-size: 12px; color: var(--ink-3); }

.nav { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; }
.nav a {
  display: flex; align-items: center; gap: 12px;
  height: 40px; padding: 0 12px; border-radius: var(--r-sm);
  color: var(--ink-2); font-weight: 500;
}
.nav a:hover { background: var(--surface-2); color: var(--ink); }
.nav a[aria-current="page"] { background: var(--brand-soft); color: var(--brand); font-weight: 600; }
.nav .count {
  margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
  background: var(--brand); color: var(--on-brand); font-size: 11.5px; font-weight: 600;
  display: grid; place-items: center;
}

.sidebar__foot { margin-top: auto; display: flex; flex-direction: column; gap: 4px; border-top: 1px solid var(--line); padding-top: 12px; }
.switchrow { display: flex; align-items: center; gap: 12px; height: 40px; padding: 0 12px; color: var(--ink-2); font-weight: 500; }
.switch {
  margin-left: auto; width: 36px; height: 20px; border-radius: 999px;
  background: var(--line-strong); position: relative; transition: background .2s;
}
.switch::after {
  content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
  background: #fff; transition: transform .2s;
}
.switch[aria-checked="true"] { background: var(--brand); }
.switch[aria-checked="true"]::after { transform: translateX(16px); background: var(--on-brand); }
.signout { display: flex; align-items: center; gap: 12px; height: 40px; padding: 0 12px; border-radius: var(--r-sm); color: var(--ink-2); font-weight: 500; }
.signout:hover { background: var(--surface-2); color: var(--ink); }
.me { display: flex; align-items: center; gap: 10px; padding: 10px 8px 0; }
.avatar {
  width: 34px; height: 34px; border-radius: 50%; flex: none;
  background: var(--brand-soft); color: var(--brand);
  display: grid; place-items: center; font-weight: 600; font-size: 13px;
}
.me__name { font-weight: 600; font-size: 13.5px; line-height: 1.25; }
.me__sub { font-size: 12px; color: var(--ink-3); }

.scrim { position: fixed; inset: 0; background: rgba(8, 14, 28, .5); opacity: 0; visibility: hidden; transition: opacity .25s, visibility 0s .25s; z-index: 35; }
.scrim.show { opacity: 1; visibility: visible; transition-delay: 0s; }

.topbar {
  position: sticky; top: 0; z-index: 30; height: var(--topbar-h);
  display: flex; align-items: center; gap: 12px; padding: 0 28px;
  background: var(--surface); border-bottom: 1px solid var(--line);
}
.brandmark { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 16px; letter-spacing: -.01em; }
.brandmark svg { width: 28px; height: 28px; }
.topbar__right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.iconbtn { width: 36px; height: 36px; border-radius: var(--r-sm); display: grid; place-items: center; color: var(--ink-2); }
.iconbtn:hover { background: var(--surface-2); color: var(--ink); }
.themebtn .i-sun { display: none; }
:root[data-theme="dark"] .themebtn .i-sun { display: block; }
:root[data-theme="dark"] .themebtn .i-moon { display: none; }
.badge-me { display: flex; align-items: center; gap: 8px; padding: 3px 12px 3px 3px; border-radius: 999px; border: 1px solid var(--line); font-weight: 600; font-size: 13px; }
.badge-me .avatar { width: 28px; height: 28px; font-size: 12px; }
.menubtn { display: none; }

/* ---------- Page layout ---------- */
.page { padding: 24px 28px 96px; display: grid; grid-template-columns: minmax(0, 1fr) 312px; gap: 0 24px; align-items: start; max-width: 1480px; }
.page__top { grid-column: 1 / -1; }
.main { display: flex; flex-direction: column; gap: 28px; min-width: 0; }
.rail { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

/* ---------- Entry banner ---------- */
.intro-wrap { display: grid; grid-template-rows: 1fr; margin-bottom: 24px; transition: grid-template-rows .35s ease, margin .35s ease, opacity .25s ease; }
.intro-wrap.is-closed { grid-template-rows: 0fr; margin-bottom: 0; opacity: 0; }
.intro-clip { overflow: hidden; min-height: 0; border-radius: var(--r-lg); }
.intro {
  position: relative; display: flex; align-items: center; justify-content: space-between; gap: 24px;
  padding: 20px 28px; min-height: 112px;
  background-color: var(--banner);
  background-image: radial-gradient(rgba(255, 255, 255, .09) 1px, transparent 1.2px);
  background-size: 16px 16px;
  color: #fff;
}
.intro__brand { display: flex; align-items: center; gap: 12px; font-size: 28px; font-weight: 700; letter-spacing: -.02em; line-height: 1.1; }
.intro__brand svg { width: 34px; height: 34px; }
.intro__text { margin-top: 8px; color: rgba(255, 255, 255, .8); max-width: 46ch; }
.intro__close { position: absolute; top: 10px; right: 10px; width: 30px; height: 30px; border-radius: 8px; color: rgba(255, 255, 255, .75); display: grid; place-items: center; }
.intro__close:hover { background: rgba(255, 255, 255, .14); color: #fff; }
.intro__close .ic { width: 16px; height: 16px; }
.intro:has(.intro__close:focus-visible) { outline: none; }
.intro .iconbtn:focus-visible, .intro__close:focus-visible { outline-color: #fff; }

.gfx { width: 220px; height: auto; flex: none; margin-right: 24px; }
.gfx * { fill: none; stroke: rgba(255, 255, 255, .92); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.gfx .gfx-sheet, .gfx .gfx-panel { stroke: rgba(255, 255, 255, .32); fill: rgba(255, 255, 255, .06); stroke-width: 1.5; }
.gfx .box { stroke: rgba(255, 255, 255, .55); stroke-width: 1.5; }
.gfx .axis { stroke: rgba(255, 255, 255, .32); stroke-width: 1.5; }
.gfx .tick, .gfx .ln, .gfx .trend { stroke-dasharray: 1; stroke-dashoffset: 1; animation: draw .5s ease-out forwards; animation-delay: var(--d, 0s); }
.gfx .ln { animation-delay: calc(var(--d, 0s) + .18s); stroke: rgba(255, 255, 255, .6); }
.gfx .trend { animation-duration: 1s; animation-delay: 1.7s; }
.gfx .end { fill: #fff; stroke: none; opacity: 0; animation: pop .3s ease-out 2.6s forwards; }
.gfx .ring { stroke: rgba(255, 255, 255, .7); stroke-width: 1.5; opacity: 0; transform-box: fill-box; transform-origin: center; animation: ring 2.8s ease-out 3s infinite; }
@keyframes draw { to { stroke-dashoffset: 0; } }
@keyframes pop { to { opacity: 1; } }
@keyframes ring { 0% { transform: scale(1); opacity: .6; } 70%, 100% { transform: scale(3.2); opacity: 0; } }

/* ---------- Welcome ---------- */
.welcome { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.welcome h1 { font-size: 26px; line-height: 1.2; letter-spacing: -.015em; font-weight: 700; }
.welcome p { color: var(--ink-2); margin-top: 4px; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.chip { padding: 3px 10px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); font-size: 12.5px; color: var(--ink-2); }
.today { text-align: right; }
.today b { display: block; font-size: 15px; font-weight: 600; }
.today span { font-size: 13px; color: var(--ink-3); }

/* ---------- Panels & headings ---------- */
.panel { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); }
.sechead { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.sechead h2 { font-size: 18px; font-weight: 600; letter-spacing: -.01em; }
.sechead p { font-size: 13px; color: var(--ink-3); margin-top: 2px; }
.panel__title { font-size: 15px; font-weight: 600; }
.panel__sub { font-size: 12.5px; color: var(--ink-3); }

/* ---------- Buttons & badges ---------- */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  height: 34px; padding: 0 14px; border-radius: var(--r-sm);
  font-size: 13px; font-weight: 600; white-space: nowrap;
  border: 1px solid transparent; transition: background .15s, border-color .15s, color .15s;
}
.btn--primary { background: var(--brand); color: var(--on-brand); }
.btn--primary:hover { background: var(--brand-hover); }
.btn--outline { border-color: var(--line-strong); background: var(--surface); }
.btn--outline:hover { background: var(--surface-2); }
.btn--ghost { color: var(--ink-3); }
.btn[disabled] { cursor: not-allowed; color: var(--ink-3); background: transparent; border-color: transparent; font-weight: 500; }
.link { color: var(--brand); font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 4px; }
.link:hover { text-decoration: underline; text-underline-offset: 3px; }

.badge { display: inline-flex; align-items: center; gap: 6px; height: 22px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge--success { background: var(--ok-soft); color: var(--ok); }
.badge--warn { background: var(--warn-soft); color: var(--warn); }
.badge--info { background: var(--info-soft); color: var(--info); }
.badge--review { background: var(--review-soft); color: var(--review); }
.badge--muted { background: var(--surface-2); color: var(--ink-2); box-shadow: inset 0 0 0 1px var(--line); }
.live { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: live 2s ease-in-out infinite; }
@keyframes live { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }

/* ---------- KPI cards ---------- */
.kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; align-items: start; }
.kpi { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); }
.kpi__head { width: 100%; text-align: left; padding: 16px; display: block; border-radius: var(--r-md); }
.kpi__top { display: flex; align-items: center; justify-content: space-between; }
.tile { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; }
.tile--brand { background: var(--brand-soft); color: var(--brand); }
.tile--ok { background: var(--ok-soft); color: var(--ok); }
.tile--warn { background: var(--warn-soft); color: var(--warn); }
.kpi .chev { color: var(--ink-3); transition: transform .25s; }
.kpi.open .chev { transform: rotate(180deg); }
.kpi__num { display: block; margin-top: 14px; font-size: 32px; line-height: 1; font-weight: 700; letter-spacing: -.02em; }
.kpi__label { display: block; margin-top: 6px; font-size: 13.5px; color: var(--ink-2); }
.kpi__more { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .28s ease; }
.kpi.open .kpi__more { grid-template-rows: 1fr; }
.kpi__more > div { overflow: hidden; min-height: 0; }
.kpi__inner { padding: 4px 16px 16px; border-top: 1px solid var(--line); margin: 0 16px; padding: 12px 0 16px; }
.kv { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; font-size: 13px; }
.kv dt { color: var(--ink-3); }
.kv dd { font-weight: 600; text-align: right; }
.kpi__inner .link { margin-top: 8px; }
.kpi__title { font-weight: 600; font-size: 13.5px; line-height: 1.35; margin-bottom: 6px; }

/* ---------- Segmented view switch ---------- */
.seg { display: inline-flex; padding: 3px; border-radius: 9px; background: var(--surface-2); box-shadow: inset 0 0 0 1px var(--line); }
.seg button { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 12px; border-radius: 7px; font-size: 13px; font-weight: 500; color: var(--ink-2); }
.seg button .ic { width: 15px; height: 15px; }
.seg button[aria-pressed="true"] { background: var(--surface); color: var(--ink); font-weight: 600; box-shadow: 0 0 0 1px var(--line-strong); }

/* ---------- Test table ---------- */
.ttable { width: 100%; border-collapse: collapse; }
.ttable th { text-align: left; font-size: 12px; font-weight: 600; color: var(--ink-3); padding: 12px 16px; border-bottom: 1px solid var(--line); white-space: nowrap; }
.ttable td { padding: 12px 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.trow { cursor: pointer; }
.trow:hover td, .trow.open td { background: var(--surface-2); }
.trow.open td { border-bottom-color: transparent; }
.rowtoggle { display: flex; align-items: center; gap: 10px; text-align: left; }
.rowtoggle .chev { color: var(--ink-3); transition: transform .25s; width: 16px; height: 16px; }
.trow.open .chev { transform: rotate(90deg); }
.rowtoggle strong { display: block; font-weight: 600; font-size: 14px; }
.rowtoggle small { display: block; font-size: 12.5px; color: var(--ink-3); }
.actcell { width: 1%; }
.act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }

.detail td { padding: 0; border-bottom: 0; background: var(--surface-2); }
.detail.open td { border-bottom: 1px solid var(--line); }
.detail:last-child td, .detail:last-child.open td { border-bottom: 0; }
.drawer { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .28s ease; }
.open > .drawer, .detail.open .drawer, .tcard.open .drawer { grid-template-rows: 1fr; }
.drawer > div { overflow: hidden; min-height: 0; }
.detail__inner { padding: 4px 16px 18px 42px; display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 16px 32px; align-items: start; }
.detail__desc { color: var(--ink-2); max-width: 60ch; }
.detail__inner .link { margin-top: 10px; }

/* overflow menu */
.menu { position: relative; }
.menu__list {
  position: absolute; right: 0; top: calc(100% + 4px); z-index: 25; min-width: 176px; padding: 4px;
  background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--r-md);
  box-shadow: 0 8px 24px rgba(10, 20, 40, .14);
}
.menu__list button, .menu__list a { display: block; width: 100%; text-align: left; padding: 8px 10px; border-radius: 6px; font-size: 13px; color: var(--ink); }
.menu__list button:hover, .menu__list a:hover { background: var(--surface-2); }

/* card view */
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; align-items: start; }
.tcard { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); padding: 16px; }
.tcard__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.tcard__badges { display: flex; flex-wrap: wrap; gap: 6px; }
.tcard h3 { font-size: 15px; font-weight: 600; line-height: 1.35; margin-top: 12px; }
.tcard__sub { font-size: 12.5px; color: var(--ink-3); }
.tcard .meta { display: flex; gap: 20px; margin-top: 12px; }
.tcard .meta dt { font-size: 12px; color: var(--ink-3); }
.tcard .meta dd { font-weight: 600; font-size: 13.5px; }
.tcard .drawer > div > div { padding-top: 12px; font-size: 13px; color: var(--ink-2); }
.tcard .drawer p + p { margin-top: 6px; color: var(--ink-3); }
.tcard__foot { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); }
.tcard .chev { transition: transform .25s; width: 15px; height: 15px; }
.tcard.open .chev { transform: rotate(180deg); }

/* ---------- Stats row ---------- */
.stats { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 14px; align-items: stretch; }
.stat { padding: 18px; }
.stat__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
.legend { display: flex; gap: 14px; font-size: 12px; color: var(--ink-2); align-items: center; flex-wrap: wrap; }
.legend i { display: inline-block; vertical-align: -1px; margin-right: 6px; }
.sw-bar { width: 10px; height: 10px; border-radius: 3px; background: var(--brand); }
.sw-avg { width: 12px; height: 0; border-top: 2px solid var(--ink); }

.chart { position: relative; height: 216px; padding: 8px 0 40px 30px; }
.chart__plot { position: relative; height: 100%; }
.gl { position: absolute; left: 0; right: 0; height: 0; border-top: 1px solid var(--line); }
.gl span { position: absolute; left: -30px; top: -8px; width: 24px; text-align: right; font-size: 11px; color: var(--ink-3); }
.cols { position: absolute; inset: 0; display: flex; gap: 10px; }
.col { position: relative; flex: 1; min-width: 0; }
.bar {
  position: absolute; left: 20%; right: 20%; bottom: 0; height: 0;
  background: var(--brand); border-radius: 6px 6px 0 0;
  transition: height .8s cubic-bezier(.2, .7, .2, 1); transition-delay: calc(var(--i) * 60ms);
}
.avg {
  position: absolute; left: 8%; right: 8%; bottom: var(--avg); height: 0;
  border-top: 2px solid var(--ink); box-shadow: 0 -1px 0 var(--surface), 0 1px 0 var(--surface);
  opacity: 0; transition: opacity .4s ease .8s;
}
.xl { position: absolute; top: calc(100% + 8px); left: -4px; right: -4px; text-align: center; line-height: 1.25; }
.xl b { display: block; font-size: 13px; font-weight: 600; }
.xl span { display: block; font-size: 11.5px; color: var(--ink-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.is-ready .bar { height: var(--you); }
.is-ready .avg { opacity: 1; }

.donutbox { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.donut { position: relative; width: 132px; height: 132px; flex: none; }
.donut svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.donut circle { fill: none; stroke-width: 13; transition: stroke-dasharray .9s cubic-bezier(.2, .7, .2, 1); }
.donut circle.track { stroke: var(--surface-2); }
.donut__c { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.donut__c b { font-size: 24px; font-weight: 700; line-height: 1; letter-spacing: -.02em; }
.donut__c span { font-size: 12px; color: var(--ink-3); margin-top: 4px; }
.dlegend { flex: 1; min-width: 140px; display: flex; flex-direction: column; gap: 8px; }
.dlegend li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink-2); }
.dlegend i { width: 10px; height: 10px; border-radius: 3px; flex: none; }
.dlegend b { margin-left: auto; color: var(--ink); font-weight: 600; }

/* ---------- Rail: calendar & upcoming ---------- */
.cal { padding: 16px 16px 14px; }
.cal__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.cal__grid { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; }
.cal__dow { font-size: 11.5px; color: var(--ink-3); padding: 6px 0; font-weight: 500; }
.day { position: relative; height: 38px; display: grid; place-items: center; font-size: 13px; color: var(--ink); }
.day .n { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; }
.day.today .n { background: var(--brand); color: var(--on-brand); font-weight: 700; }
.day.dim { color: var(--ink-3); }
.dots { position: absolute; bottom: 0; left: 0; right: 0; display: flex; justify-content: center; gap: 2px; }
.dots i { width: 4px; height: 4px; border-radius: 50%; }
.d-active { background: var(--warn-dot); }
.d-upcoming { background: var(--info); }
.d-closed { background: var(--line-strong); }
.cal .legend { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); font-size: 12px; gap: 14px; }
.cal .legend i { width: 7px; height: 7px; border-radius: 50%; margin-right: 6px; vertical-align: 0; }

.soon { padding: 16px; }
.soon ul { margin-top: 10px; }
.soon li { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-top: 1px solid var(--line); }
.soon li:first-child { border-top: 0; }
.soon .dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex: none; }
.soon b { display: block; font-size: 13.5px; font-weight: 600; line-height: 1.35; }
.soon small { color: var(--ink-3); font-size: 12.5px; }
.soon .rel { margin-left: auto; font-size: 12px; font-weight: 600; color: var(--ink-2); white-space: nowrap; padding-top: 2px; }

/* ---------- Study assistant ---------- */
.fab {
  position: fixed; right: 24px; bottom: 24px; z-index: 50;
  width: 52px; height: 52px; border-radius: 50%;
  background: var(--brand); color: var(--on-brand);
  display: grid; place-items: center;
  box-shadow: 0 6px 18px rgba(20, 40, 100, .28);
}
.fab:hover { background: var(--brand-hover); }
.fab .ic { width: 24px; height: 24px; }
.fab .i-close { display: none; }
.fab[aria-expanded="true"] .i-close { display: block; }
.fab[aria-expanded="true"] .i-bot { display: none; }

.chat {
  position: fixed; right: 24px; bottom: 90px; z-index: 50;
  width: 360px; max-width: calc(100vw - 32px); height: min(540px, calc(100vh - 120px));
  display: flex; flex-direction: column;
  background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--r-lg);
  box-shadow: 0 16px 40px rgba(10, 20, 40, .22);
  opacity: 0; transform: translateY(12px); visibility: hidden;
  transition: opacity .2s ease, transform .2s ease, visibility 0s .2s;
}
.chat.open { opacity: 1; transform: none; visibility: visible; transition-delay: 0s; }
.chat__head { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
.chat__head b { display: block; font-size: 14.5px; line-height: 1.2; }
.chat__head small { color: var(--ink-3); font-size: 12px; }
.chat__log { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.msg { max-width: 86%; padding: 9px 12px; border-radius: 12px; font-size: 13.5px; line-height: 1.45; }
.msg--bot { background: var(--surface-2); box-shadow: inset 0 0 0 1px var(--line); border-bottom-left-radius: 4px; }
.msg--me { align-self: flex-end; background: var(--brand); color: var(--on-brand); border-bottom-right-radius: 4px; }
.typing { display: inline-flex; gap: 4px; padding: 12px; }
.typing i { width: 6px; height: 6px; border-radius: 50%; background: var(--ink-3); animation: blink 1.1s infinite; }
.typing i:nth-child(2) { animation-delay: .15s; }
.typing i:nth-child(3) { animation-delay: .3s; }
@keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
.pills { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 16px 12px; }
.pill { padding: 5px 11px; border-radius: 999px; border: 1px solid var(--line-strong); font-size: 12.5px; color: var(--ink-2); background: var(--surface); }
.pill:hover { border-color: var(--brand); color: var(--brand); }
.chat__in { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--line); }
.chat__in input {
  flex: 1; height: 38px; padding: 0 12px; border-radius: var(--r-sm);
  border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink); font: inherit;
}
.chat__in input::placeholder { color: var(--ink-3); }
.chat__in input:focus-visible { outline: 2px solid var(--brand); outline-offset: 0; border-color: var(--brand); }
.chat__in .btn { width: 38px; padding: 0; height: 38px; }

/* ---------- Responsive ---------- */
@media (max-width: 1200px) {
  .page { grid-template-columns: minmax(0, 1fr); }
  .rail { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 28px; align-items: start; }
}
@media (max-width: 900px) {
  .app { grid-template-columns: minmax(0, 1fr); }
  .sidebar { position: fixed; left: 0; top: 0; width: 272px; transform: translateX(-100%); transition: transform .25s ease; }
  .sidebar.open { transform: none; }
  .menubtn { display: grid; margin-left: -8px; }
  .topbar, .page { padding-left: 16px; padding-right: 16px; }
  .intro { padding: 20px 18px; }
  .gfx { width: 170px; margin-right: 28px; }
}
@media (max-width: 760px) {
  .kpis { grid-template-columns: minmax(0, 1fr); }
  .stats { grid-template-columns: minmax(0, 1fr); }
  .rail { grid-template-columns: minmax(0, 1fr); }
  .gfx { display: none; }
  .intro__brand { font-size: 24px; }
  .badge-me span { display: none; }
  .badge-me { padding: 3px; }
  .today { text-align: left; }

  /* table rows become cards */
  .panel--table { background: transparent; border: 0; }
  .ttable thead { display: none; }
  .ttable, .ttable tbody, .ttable tr, .ttable td { display: block; }
  .trow { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); padding: 12px 14px; margin-bottom: 10px; }
  .trow td, .trow:hover td, .trow.open td { padding: 4px 0; border: 0; background: transparent; }
  .ttable td[data-label]::before { content: attr(data-label); color: var(--ink-3); font-size: 12px; margin-right: 12px; }
  .ttable td[data-label="Assessment"]::before, .ttable td[data-label="Action"]::before { display: none; }
  .ttable td[data-label]:not([data-label="Assessment"]):not([data-label="Action"]) { display: flex; justify-content: space-between; align-items: center; }
  .actcell { width: auto; padding-top: 10px !important; }
  .detail { margin: -10px 0 10px; }
  .detail td { background: var(--surface-2); border-radius: 0 0 var(--r-md) var(--r-md); }
  .detail__inner { padding: 8px 14px 14px; grid-template-columns: minmax(0, 1fr); }
  .chat { right: 16px; bottom: 84px; }
  .fab { right: 16px; bottom: 16px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition-duration: .01ms !important; transition-delay: 0s !important; }
  .gfx .tick, .gfx .ln, .gfx .trend { animation: none; stroke-dashoffset: 0; }
  .gfx .end { animation: none; opacity: 1; }
  .gfx .ring, .live, .typing i { animation: none; }
}
</style>
</head>

<body>

<!-- Icon sprite (Lucide-style, stroke icons) -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
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

<div class="app">

  <!-- ===== Sidebar ===== -->
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="college">
      <div class="college__logo">
        <span><?= h($collegeInitials) ?></span>
        <?php if (!empty($student['college_logo'])): ?>
          <img src="<?= h($student['college_logo']) ?>" alt="" onerror="this.remove()">
        <?php else: ?>
          <img src="<?= ASSETS_URL ?>/img/college-logo.png" alt="" onerror="this.remove()">
        <?php endif; ?>
      </div>
      <div>
        <div class="college__name"><?= h($collegeName) ?></div>
        <div class="college__sub"><?= h($student['course_name'] ?? 'Portal') ?></div>
      </div>
    </div>

    <nav class="nav">
      <a href="dashboard.php" aria-current="page"><svg class="ic"><use href="#i-dash"/></svg>Dashboard</a>
      <a href="my-tests.php"><svg class="ic"><use href="#i-tests"/></svg>My tests<span class="count" id="navCount">0</span></a>
      <a href="results.php"><svg class="ic"><use href="#i-award"/></svg>Results</a>
      <a href="analytics.php"><svg class="ic"><use href="#i-chart"/></svg>Analytics</a>
      <a href="profile.php"><svg class="ic"><use href="#i-user"/></svg>Profile</a>
    </nav>

    <div class="sidebar__foot">
      <div class="switchrow">
        <svg class="ic"><use href="#i-moon"/></svg>
        <span id="darkLabel">Dark mode</span>
        <button class="switch" id="darkSwitch" role="switch" aria-checked="false" aria-labelledby="darkLabel" type="button"></button>
      </div>
      <a class="signout" href="<?= BASE_URL ?>/logout.php"><svg class="ic"><use href="#i-logout"/></svg>Sign out</a>
      <div class="me">
        <div class="avatar"><?= h($studentInitials) ?></div>
        <div>
          <div class="me__name"><?= h($studentName) ?></div>
          <div class="me__sub"><?= !empty($student['roll_number']) ? 'Roll no. ' . h($student['roll_number']) : 'Student' ?></div>
        </div>
      </div>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>

  <!-- ===== Main column ===== -->
  <div>
    <header class="topbar">
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

    <main class="page">

      <!-- Entry banner: appears when the student lands in the portal; dismissible -->
      <div class="page__top">
        <div class="intro-wrap" id="introWrap">
          <div class="intro-clip">
            <section class="intro" aria-label="Welcome to LMSportel">
              <div>
                <div class="intro__brand">
                  <svg viewBox="0 0 28 28" aria-hidden="true"><rect width="28" height="28" rx="8" fill="#fff"/><path d="M9.5 7.5v12h8" fill="none" stroke="#1B3A9C" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="18.5" cy="9.5" r="2" fill="#1B3A9C"/></svg>
                  LMSportel
                </div>
                <p class="intro__text">Your assessments, results and study progress for <?= h($collegeName) ?>, in one place.</p>
              </div>

              <svg class="gfx" viewBox="0 0 240 112" aria-hidden="true">
                <rect class="gfx-sheet" x="14" y="8" width="104" height="96" rx="8"/>
                <g style="--d:.5s"><rect class="box" x="28" y="26" width="12" height="12" rx="3"/><path class="tick" pathLength="1" d="M31 32l3 3 5-6"/><path class="ln" pathLength="1" d="M50 32h52"/></g>
                <g style="--d:.9s"><rect class="box" x="28" y="50" width="12" height="12" rx="3"/><path class="tick" pathLength="1" d="M31 56l3 3 5-6"/><path class="ln" pathLength="1" d="M50 56h40"/></g>
                <g style="--d:1.3s"><rect class="box" x="28" y="74" width="12" height="12" rx="3"/><path class="tick" pathLength="1" d="M31 80l3 3 5-6"/><path class="ln" pathLength="1" d="M50 80h46"/></g>
                <rect class="gfx-panel" x="132" y="24" width="94" height="68" rx="8"/>
                <path class="axis" d="M144 38v44h72"/>
                <path class="trend" pathLength="1" d="M146 74l16-10 14 6 18-18 20-12"/>
                <circle class="end" cx="214" cy="40" r="3.5"/>
                <circle class="ring" cx="214" cy="40" r="3.5"/>
              </svg>

              <button class="intro__close" id="introClose" aria-label="Dismiss welcome banner" type="button"><svg class="ic"><use href="#i-x"/></svg></button>
            </section>
          </div>
        </div>
      </div>

      <div class="main">

        <!-- Welcome -->
        <section class="welcome" aria-labelledby="hello">
          <div>
            <h1 id="hello">Welcome back, <?= h($firstName) ?></h1>
            <p id="openLine">You have tests waiting for you.</p>
            <div class="chips">
              <span class="chip"><?= h($student['course_name'] ?? 'General') ?></span>
              <span class="chip">Batch <?= h($student['batch_name'] ?? 'Current') ?></span>
              <?php if (!empty($student['section'])): ?>
                <span class="chip">Section <?= h($student['section']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="today"><b id="dow"><?= h($dayName) ?></b><span id="dmy"><?= h($formattedDate) ?></span></div>
        </section>

        <!-- KPI cards -->
        <section class="kpis" aria-label="Test summary">
          <article class="kpi" id="kpiTotal">
            <button class="kpi__head" aria-expanded="false" aria-controls="kpiTotalMore" type="button">
              <span class="kpi__top"><span class="tile tile--brand"><svg class="ic"><use href="#i-file"/></svg></span><svg class="ic chev"><use href="#i-down"/></svg></span>
              <span class="kpi__num" data-count id="nTotal">0</span>
              <span class="kpi__label">Assigned tests</span>
            </button>
            <div class="kpi__more" id="kpiTotalMore"><div><div class="kpi__inner">
              <dl id="totalList"></dl>
              <a class="link" href="analytics.php">View full analysis</a>
            </div></div></div>
          </article>

          <article class="kpi" id="kpiDone">
            <button class="kpi__head" aria-expanded="false" aria-controls="kpiDoneMore" type="button">
              <span class="kpi__top"><span class="tile tile--ok"><svg class="ic"><use href="#i-check"/></svg></span><svg class="ic chev"><use href="#i-down"/></svg></span>
              <span class="kpi__num" data-count id="nDone">0</span>
              <span class="kpi__label">Completed and evaluated</span>
            </button>
            <div class="kpi__more" id="kpiDoneMore"><div><div class="kpi__inner">
              <div class="kpi__title" id="latestTitle"></div>
              <dl id="latestList"></dl>
              <a class="link" href="test-analysis.php">View analysis</a>
            </div></div></div>
          </article>

          <article class="kpi" id="kpiPend">
            <button class="kpi__head" aria-expanded="false" aria-controls="kpiPendMore" type="button">
              <span class="kpi__top"><span class="tile tile--warn"><svg class="ic"><use href="#i-timer"/></svg></span><svg class="ic chev"><use href="#i-down"/></svg></span>
              <span class="kpi__num" data-count id="nPend">0</span>
              <span class="kpi__label">Pending or active</span>
            </button>
            <div class="kpi__more" id="kpiPendMore"><div><div class="kpi__inner">
              <dl id="pendList"></dl>
              <a class="link" href="#tests" id="toTests">Go to your tests</a>
            </div></div></div>
          </article>
        </section>

        <!-- Test list -->
        <section id="tests" aria-labelledby="testsHead">
          <div class="sechead">
            <div>
              <h2 id="testsHead">Your tests</h2>
              <p>Select a row for timings and details.</p>
            </div>
            <div class="seg" role="group" aria-label="Switch test view">
              <button id="segTable" aria-pressed="true" type="button"><svg class="ic"><use href="#i-list"/></svg>Table</button>
              <button id="segCards" aria-pressed="false" type="button"><svg class="ic"><use href="#i-grid"/></svg>Cards</button>
            </div>
          </div>

          <div id="tableView" class="panel panel--table">
            <table class="ttable">
              <thead>
                <tr><th>Assessment</th><th>Duration</th><th>Status</th><th>Your status</th><th><span class="sr-only">Action</span></th></tr>
              </thead>
              <tbody id="tableBody"></tbody>
            </table>
          </div>
          <div id="cardView" class="cards" hidden></div>
        </section>

        <!-- Stats -->
        <section class="stats" aria-label="Performance overview">
          <div class="panel stat">
            <div class="stat__head">
              <div>
                <h2 class="panel__title">Recent scores</h2>
                <p class="panel__sub">Last evaluated tests, in percent</p>
              </div>
              <div class="legend"><span><i class="sw-bar"></i>You</span><span><i class="sw-avg"></i>Class average</span></div>
            </div>
            <div class="chart" id="chart" role="img"></div>
          </div>

          <div class="panel stat">
            <div class="stat__head">
              <div>
                <h2 class="panel__title">Test progress</h2>
                <p class="panel__sub">Across all assigned tests</p>
              </div>
            </div>
            <div class="donutbox">
              <div class="donut">
                <svg viewBox="0 0 132 132" id="donut" aria-hidden="true"><circle class="track" cx="66" cy="66" r="52"/></svg>
                <div class="donut__c"><b id="donutNum">0 of 0</b><span>submitted</span></div>
              </div>
              <ul class="dlegend" id="donutLegend"></ul>
            </div>
          </div>
        </section>
      </div>

      <!-- Right rail -->
      <aside class="rail" aria-label="Schedule">
        <section class="panel cal" aria-labelledby="calTitle">
          <div class="cal__head">
            <h2 class="panel__title" id="calTitle"></h2>
            <div>
              <button class="iconbtn" id="calPrev" aria-label="Previous month" type="button"><svg class="ic"><use href="#i-left"/></svg></button>
              <button class="iconbtn" id="calNext" aria-label="Next month" type="button"><svg class="ic"><use href="#i-right"/></svg></button>
            </div>
          </div>
          <div class="cal__grid" id="calGrid"></div>
          <div class="legend">
            <span><i class="d-active"></i>Closes</span>
            <span><i class="d-upcoming"></i>Opens</span>
            <span><i class="d-closed"></i>Past</span>
          </div>
        </section>

        <section class="panel soon" aria-labelledby="soonHead">
          <h2 class="panel__title" id="soonHead">Coming up</h2>
          <ul id="soonList"></ul>
        </section>
      </aside>

    </main>
  </div>
</div>

<!-- Floating study assistant -->
<button class="fab" id="chatFab" aria-expanded="false" aria-controls="chat" aria-label="Open study assistant" type="button">
  <svg class="ic i-bot"><use href="#i-bot"/></svg><svg class="ic i-close"><use href="#i-x"/></svg>
</button>
<section class="chat" id="chat" aria-label="Study assistant">
  <div class="chat__head">
    <span class="avatar"><svg class="ic"><use href="#i-bot"/></svg></span>
    <div><b>Study assistant</b><small>Answers use your test data</small></div>
  </div>
  <div class="chat__log" id="chatLog" role="log" aria-live="polite">
    <div class="msg msg--bot">Hi <?= h($firstName) ?>. Ask me about your results, or pick one of the shortcuts below.</div>
  </div>
  <div class="pills" id="pills">
    <button class="pill" type="button">My weaknesses</button>
    <button class="pill" type="button">What is my PCI?</button>
    <button class="pill" type="button">What should I study?</button>
    <button class="pill" type="button">Generate my report</button>
  </div>
  <div class="chat__in">
    <input id="chatInput" type="text" placeholder="Ask a question" aria-label="Message the study assistant" autocomplete="off">
    <button class="btn btn--primary" id="chatSend" aria-label="Send message" type="button"><svg class="ic"><use href="#i-send"/></svg></button>
  </div>
</section>

<script>
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var now = new Date();
  var fmt = function (d) { 
    if (!d) return '—';
    try {
      return d.toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', hour12: true }); 
    } catch(e) { return String(d); }
  };
  var fmtDay = function (d) { 
    if (!d) return '—';
    try {
      return d.toLocaleString('en-IN', { day: 'numeric', month: 'short' }); 
    } catch(e) { return String(d); }
  };
  var esc = function (s) { return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var icon = function (id, cls) { return '<svg class="ic ' + (cls || '') + '"><use href="#i-' + id + '"/></svg>'; };

  /* ------------------------------------------------------------------
     Server-provided data from PHP MySQL
     status: active | upcoming | closed
     mine:   new | progress | review | done
  ------------------------------------------------------------------ */
  var TESTS = <?= json_encode($frontendTests) ?>;
  var SCORES = <?= json_encode($scores) ?>;

  // Convert start/end date strings to Date objects
  TESTS.forEach(function (t) {
    if (t.start) {
      t.start = new Date(t.start);
    } else {
      t.start = new Date(now.getTime() - 86400000);
    }
    if (t.end) {
      t.end = new Date(t.end);
    } else {
      t.end = new Date(now.getTime() + 86400000);
    }
  });

  var STATUS = { active: ['Active', 'success', true], upcoming: ['Upcoming', 'info'], closed: ['Closed', 'muted'] };
  var MINE = { 'new': ['Not started', 'muted'], progress: ['In progress', 'warn'], review: ['Under evaluation', 'review'], done: ['Evaluated', 'success'] };

  var badge = function (b) { 
    b = b || ['Unknown', 'muted'];
    return '<span class="badge badge--' + b[1] + '">' + (b[2] ? '<i class="live"></i>' : '') + b[0] + '</span>'; 
  };
  var pct = function (t) { return t.marks > 0 ? Math.round(t.score / t.marks * 100) : 0; };

  function action(t) {
    if (t.mine === 'progress') return { label: 'Resume', cls: 'btn--primary', href: 'test.php?test_id=' + t.id };
    if (t.mine === 'new' && t.status === 'active') return { label: 'Start test', cls: 'btn--primary', href: 'test.php?test_id=' + t.id };
    if (t.mine === 'new') return { label: 'Opens ' + fmtDay(t.start), cls: 'btn--ghost', off: true };
    if (t.mine === 'review') return { label: 'Under evaluation', cls: 'btn--ghost', off: true };
    return { label: 'View result', cls: 'btn--outline', href: 'result-details.php?type=evaluated' };
  }
  var actionBtn = function (t) {
    var a = action(t);
    if (a.href && !a.off) {
      return '<a href="' + a.href + '" class="btn ' + a.cls + '">' + a.label + '</a>';
    }
    return '<button class="btn ' + a.cls + '"' + (a.off ? ' disabled' : '') + ' type="button">' + a.label + '</button>';
  };

  function menu(t) {
    return '<div class="menu"><button class="iconbtn" aria-haspopup="menu" aria-expanded="false" aria-label="More actions for ' + esc(t.title) + '" type="button">' + icon('more') + '</button>' +
      '<div class="menu__list" role="menu" hidden>' +
      '<button role="menuitem" data-act="details" type="button">View details</button>' +
      '<a role="menuitem" href="test.php?test_id=' + t.id + '">Instructions</a>' +
      (t.mine === 'done' ? '<a role="menuitem" href="test-analysis.php">View analysis</a>' : '') +
      '</div></div>';
  }
  function detailBody(t) {
    var rows = [['Opens', fmt(t.start)], ['Closes', fmt(t.end)], ['Questions', t.q], ['Total marks', t.marks]];
    if (t.mine === 'done') rows.push(['Your score', t.score + ' of ' + t.marks + ' (' + pct(t) + '%)']);
    return '<p class="detail__desc">' + esc(t.desc) + '</p>' +
      '<dl>' + rows.map(function (r) { return '<div class="kv"><dt>' + r[0] + '</dt><dd>' + r[1] + '</dd></div>'; }).join('') + '</dl>';
  }

  /* ---------- Table view ---------- */
  function rowHTML(t) {
    return '<tr class="trow" data-id="' + t.id + '">' +
      '<td data-label="Assessment"><button class="rowtoggle" aria-expanded="false" aria-controls="d-' + t.id + '" type="button">' + icon('right', 'chev') + '<span><strong>' + esc(t.title) + '</strong><small>' + esc(t.subject) + '</small></span></button></td>' +
      '<td data-label="Duration">' + t.mins + ' min</td>' +
      '<td data-label="Status">' + badge(STATUS[t.status] || STATUS.closed) + '</td>' +
      '<td data-label="Your status">' + badge(MINE[t.mine] || MINE.new) + '</td>' +
      '<td data-label="Action" class="actcell"><div class="act">' + actionBtn(t) + menu(t) + '</div></td></tr>' +
      '<tr class="detail" id="d-' + t.id + '"><td colspan="5"><div class="drawer"><div><div class="detail__inner">' + detailBody(t) + '</div></div></div></td></tr>';
  }
  var tbody = $('#tableBody');
  if (tbody) {
    tbody.innerHTML = TESTS.length ? TESTS.map(rowHTML).join('') : '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--ink-3)">No tests assigned to your batch yet.</td></tr>';
  }

  function toggleRow(row, force) {
    var open = typeof force === 'boolean' ? force : !row.classList.contains('open');
    row.classList.toggle('open', open);
    if (row.nextElementSibling) row.nextElementSibling.classList.toggle('open', open);
    var btn = row.querySelector('.rowtoggle');
    if (btn) btn.setAttribute('aria-expanded', String(open));
  }
  if (tbody) {
    tbody.addEventListener('click', function (e) {
      var row = e.target.closest('.trow');
      if (!row || e.target.closest('.act')) return;
      toggleRow(row);
    });
  }

  /* ---------- Card view ---------- */
  function cardHTML(t) {
    return '<article class="tcard" data-id="' + t.id + '">' +
      '<div class="tcard__top"><div class="tcard__badges">' + badge(STATUS[t.status] || STATUS.closed) + badge(MINE[t.mine] || MINE.new) + '</div>' + menu(t) + '</div>' +
      '<h3>' + esc(t.title) + '</h3><div class="tcard__sub">' + esc(t.subject) + '</div>' +
      '<dl class="meta"><div><dt>Duration</dt><dd>' + t.mins + ' min</dd></div><div><dt>Questions</dt><dd>' + t.q + '</dd></div><div><dt>Marks</dt><dd>' + t.marks + '</dd></div></dl>' +
      '<div class="drawer"><div><div><p>' + esc(t.desc) + '</p><p>Opens ' + fmt(t.start) + '<br>Closes ' + fmt(t.end) + '</p></div></div></div>' +
      '<div class="tcard__foot"><button class="link js-card-toggle" aria-expanded="false" type="button">Details ' + icon('down', 'chev') + '</button>' + actionBtn(t) + '</div></article>';
  }
  var cView = $('#cardView');
  if (cView) {
    cView.innerHTML = TESTS.length ? TESTS.map(cardHTML).join('') : '<p style="text-align:center;padding:32px;color:var(--ink-3)">No tests assigned to your batch yet.</p>';
  }

  function toggleCard(card) {
    var open = !card.classList.contains('open');
    card.classList.toggle('open', open);
    var btn = card.querySelector('.js-card-toggle');
    if (btn) btn.setAttribute('aria-expanded', String(open));
  }
  if (cView) {
    cView.addEventListener('click', function (e) {
      var b = e.target.closest('.js-card-toggle');
      if (b) toggleCard(b.closest('.tcard'));
    });
  }

  /* Shared: overflow menu and "View details" item */
  function closeMenus() {
    $$('.menu__list').forEach(function (l) { 
      l.hidden = true; 
      if (l.previousElementSibling) l.previousElementSibling.setAttribute('aria-expanded', 'false'); 
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.menu > .iconbtn');
    if (btn) {
      var list = btn.nextElementSibling, willOpen = list.hidden;
      closeMenus();
      list.hidden = !willOpen;
      btn.setAttribute('aria-expanded', String(willOpen));
      return;
    }
    var item = e.target.closest('[data-act="details"]');
    if (item) {
      var row = item.closest('.trow'), card = item.closest('.tcard');
      if (row) toggleRow(row, true);
      if (card && !card.classList.contains('open')) toggleCard(card);
    }
    if (!e.target.closest('.menu') || item) closeMenus();
  });

  /* View switch */
  function setView(v) {
    var t = v === 'table';
    var tv = $('#tableView'), cv = $('#cardView'), st = $('#segTable'), sc = $('#segCards');
    if (tv) tv.hidden = !t;
    if (cv) cv.hidden = t;
    if (st) st.setAttribute('aria-pressed', String(t));
    if (sc) sc.setAttribute('aria-pressed', String(!t));
  }
  if ($('#segTable')) $('#segTable').addEventListener('click', function () { setView('table'); });
  if ($('#segCards')) $('#segCards').addEventListener('click', function () { setView('cards'); });

  /* ---------- Numbers derived from data ---------- */
  var n = { done: 0, review: 0, progress: 0, fresh: 0 };
  TESTS.forEach(function (t) { 
    if (t.mine === 'done') n.done++; 
    else if (t.mine === 'review') n.review++; 
    else if (t.mine === 'progress') n.progress++; 
    else n.fresh++; 
  });
  var total = TESTS.length, pending = n.progress + n.fresh, submitted = n.done + n.review;
  var openNow = TESTS.filter(function (t) { return t.status === 'active'; }).length;

  var nTotEl = $('#nTotal'), nDoneEl = $('#nDone'), nPendEl = $('#nPend'), navCountEl = $('#navCount');
  if (nTotEl) nTotEl.dataset.count = total;
  if (nDoneEl) nDoneEl.dataset.count = n.done;
  if (nPendEl) nPendEl.dataset.count = pending;
  if (navCountEl) navCountEl.textContent = pending;

  var kv = function (rows) { return rows.map(function (r) { return '<div class="kv"><dt>' + r[0] + '</dt><dd>' + r[1] + '</dd></div>'; }).join(''); };
  var totList = $('#totalList');
  if (totList) {
    totList.innerHTML = kv([
      ['Evaluated', n.done], 
      ['Under evaluation', n.review], 
      ['In progress', n.progress], 
      ['Not started', n.fresh], 
      ['Completion rate', (total > 0 ? Math.round(submitted / total * 100) : 0) + '%']
    ]);
  }

  var latest = TESTS.filter(function (t) { return t.mine === 'done'; }).sort(function (a, b) { return b.end - a.end; })[0];
  var latTitle = $('#latestTitle'), latList = $('#latestList');
  if (latest && latTitle && latList) {
    latTitle.textContent = latest.title;
    latList.innerHTML = kv([
      ['Date', fmtDay(latest.end)], 
      ['Score', latest.score + ' of ' + latest.marks], 
      ['Percentage', pct(latest) + '%'], 
      ['Time taken', (latest.taken || '—') + ' min']
    ]);
  } else if (latTitle && latList) {
    latTitle.textContent = 'No evaluated tests yet';
    latList.innerHTML = kv([['Status', 'Complete an assessment to see results here']]);
  }

  var closing = TESTS.filter(function (t) { return t.status === 'active' && t.mine !== 'done'; }).sort(function (a, b) { return a.end - b.end; })[0];
  var pendList = $('#pendList');
  if (pendList) {
    pendList.innerHTML = kv([
      ['In progress', n.progress], 
      ['Not started', n.fresh]
    ].concat(closing ? [['Closes next', fmt(closing.end)]] : []));
  }

  var openLineEl = $('#openLine');
  if (openLineEl) {
    openLineEl.textContent = openNow + (openNow === 1 ? ' test is' : ' tests are') + ' open right now' + (n.progress ? ', and ' + n.progress + ' is in progress.' : '.');
  }

  $$('.kpi__head').forEach(function (h) {
    h.addEventListener('click', function () {
      var k = h.closest('.kpi'), open = !k.classList.contains('open');
      k.classList.toggle('open', open); h.setAttribute('aria-expanded', String(open));
    });
  });
  if ($('#toTests')) {
    $('#toTests').addEventListener('click', function (e) { 
      e.preventDefault(); 
      var el = $('#tests');
      if (el) el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' }); 
    });
  }

  /* ---------- Scores chart ---------- */
  (function () {
    var el = $('#chart');
    if (!el) return;
    var grid = [100, 75, 50, 25, 0].map(function (v) { return '<div class="gl" style="bottom:' + v + '%"><span>' + v + '</span></div>'; }).join('');
    var cols = SCORES.map(function (s, i) {
      return '<div class="col" style="--i:' + i + '"><div class="bar" style="--you:' + s.you + '%"></div><div class="avg" style="--avg:' + s.avg + '%"></div>' +
        '<div class="xl"><b>' + s.you + '</b><span>' + esc(s.l) + '</span></div></div>';
    }).join('');
    el.innerHTML = '<div class="chart__plot">' + grid + '<div class="cols">' + cols + '</div></div>';
    el.setAttribute('aria-label', 'Bar chart of your last scores compared with the class average: ' +
      SCORES.map(function (s) { return s.l + ' ' + s.you + ' percent against ' + s.avg; }).join(', '));
  })();

  /* ---------- Donut ---------- */
  var donutSegs = [
    { label: 'Evaluated', n: n.done, c: 'var(--ok)' },
    { label: 'Under evaluation', n: n.review, c: 'var(--review)' },
    { label: 'In progress', n: n.progress, c: 'var(--warn-dot)' },
    { label: 'Not started', n: n.fresh, c: 'var(--line-strong)' }
  ];
  var CIRC = 2 * Math.PI * 52, GAP = 3;
  (function () {
    var svg = $('#donut');
    if (!svg) return;
    var acc = 0, html = '';
    donutSegs.forEach(function (s, i) {
      if (!s.n || total === 0) return;
      s.len = Math.max(s.n / total * CIRC - GAP, 1);
      html += '<circle data-i="' + i + '" cx="66" cy="66" r="52" style="stroke:' + s.c + ';stroke-dasharray:0 ' + CIRC + ';stroke-dashoffset:' + (-acc) + '"/>';
      acc += s.n / total * CIRC;
    });
    svg.insertAdjacentHTML('beforeend', html);
    if ($('#donutNum')) $('#donutNum').textContent = submitted + ' of ' + total;
    if ($('#donutLegend')) $('#donutLegend').innerHTML = donutSegs.map(function (s) { return '<li><i style="background:' + s.c + '"></i>' + s.label + '<b>' + s.n + '</b></li>'; }).join('');
  })();

  /* ---------- Calendar and upcoming ---------- */
  var view = new Date(now.getFullYear(), now.getMonth(), 1);
  function renderCal() {
    var calTitle = $('#calTitle'), calGrid = $('#calGrid');
    if (!calTitle || !calGrid) return;
    var y = view.getFullYear(), m = view.getMonth();
    calTitle.textContent = view.toLocaleString('en-IN', { month: 'long', year: 'numeric' });
    var offset = (new Date(y, m, 1).getDay() + 6) % 7;      /* Monday first */
    var days = new Date(y, m + 1, 0).getDate();
    var ev = {};
    TESTS.forEach(function (t) {
      var d = t.status === 'upcoming' ? t.start : t.end;
      if (d && d.getFullYear() === y && d.getMonth() === m) {
        (ev[d.getDate()] = ev[d.getDate()] || []).push(t);
      }
    });
    var html = ['M', 'T', 'W', 'T', 'F', 'S', 'S'].map(function (d) { return '<div class="cal__dow" aria-hidden="true">' + d + '</div>'; }).join('');
    for (var i = 0; i < offset; i++) html += '<div class="day"></div>';
    for (var d = 1; d <= days; d++) {
      var isToday = d === now.getDate() && m === now.getMonth() && y === now.getFullYear();
      var list = ev[d] || [];
      var label = d + ' ' + view.toLocaleString('en-IN', { month: 'long' }) + (isToday ? ', today' : '') +
        list.map(function (t) { return '. ' + t.title + (t.status === 'upcoming' ? ' opens' : t.status === 'active' ? ' closes' : ' closed'); }).join('');
      html += '<div class="day' + (isToday ? ' today' : '') + '" role="text" aria-label="' + esc(label) + '"><span class="n">' + d + '</span>' +
        (list.length ? '<span class="dots">' + list.slice(0, 3).map(function (t) { return '<i class="d-' + t.status + '"></i>'; }).join('') + '</span>' : '') + '</div>';
    }
    calGrid.innerHTML = html;
  }
  if ($('#calPrev')) $('#calPrev').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); renderCal(); });
  if ($('#calNext')) $('#calNext').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); renderCal(); });
  renderCal();

  function rel(d) {
    if (!d) return '';
    var mins = Math.round((d - now) / 60000);
    if (mins < 0) return 'ended';
    if (mins < 60) return 'in ' + Math.max(mins, 1) + ' min';
    if (mins < 1440) return 'in ' + Math.round(mins / 60) + ' h';
    var days = Math.round(mins / 1440);
    return days === 1 ? 'tomorrow' : 'in ' + days + ' days';
  }
  var soon = TESTS.filter(function (t) { return t.status !== 'closed'; }).sort(function (a, b) {
    return (a.status === 'active' ? a.end : a.start) - (b.status === 'active' ? b.end : b.start);
  });
  var soonEl = $('#soonList');
  if (soonEl) {
    soonEl.innerHTML = soon.length ? soon.slice(0, 4).map(function (t) {
      var active = t.status === 'active', when = active ? t.end : t.start;
      return '<li><span class="dot d-' + t.status + '"></span><div><b>' + esc(t.title) + '</b><small>' + (active ? 'Closes ' : 'Opens ') + fmt(when) + '</small></div><span class="rel">' + rel(when) + '</span></li>';
    }).join('') : '<li><small>No tests are scheduled. New assignments will show up here.</small></li>';
  }

  /* ---------- Theme ---------- */
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    var ds = $('#darkSwitch');
    if (ds) ds.setAttribute('aria-checked', String(t === 'dark'));
    try { localStorage.setItem('lms-theme', t); } catch (e) {}
  }
  var cur = function () { return document.documentElement.getAttribute('data-theme'); };
  if ($('#darkSwitch')) {
    $('#darkSwitch').setAttribute('aria-checked', String(cur() === 'dark'));
    $('#darkSwitch').addEventListener('click', function () { setTheme(cur() === 'dark' ? 'light' : 'dark'); });
  }
  if ($('#themeBtn')) {
    $('#themeBtn').addEventListener('click', function () { setTheme(cur() === 'dark' ? 'light' : 'dark'); });
  }

  /* ---------- Mobile drawer ---------- */
  function drawer(open) {
    var sb = $('#sidebar'), sc = $('#scrim');
    if (sb) sb.classList.toggle('open', open);
    if (sc) sc.classList.toggle('show', open);
    document.body.style.overflow = open ? 'hidden' : '';
    if ($('#menuBtn')) $('#menuBtn').setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  }
  if ($('#menuBtn')) $('#menuBtn').addEventListener('click', function () { drawer(!$('#sidebar').classList.contains('open')); });
  if ($('#scrim')) $('#scrim').addEventListener('click', function () { drawer(false); });

  /* ---------- Entry banner ---------- */
  if ($('#introClose')) {
    $('#introClose').addEventListener('click', function () {
      var w = $('#introWrap');
      if (w) {
        w.classList.add('is-closed');
        setTimeout(function () { w.hidden = true; }, reduce ? 0 : 400);
      }
    });
  }

  /* ---------- Study assistant with backend API connection ---------- */
  var chat = $('#chat'), fab = $('#chatFab'), log = $('#chatLog'), input = $('#chatInput');
  var REPLIES = {
    'My weaknesses': 'Based on your evaluated tests, focus on revising topics with lower score accuracy. Start with practice tests in your course.',
    'What is my PCI?': 'Your Performance Competency Index is computed from your completed assessments. Check the Analytics tab for detailed breakdowns.',
    'What should I study?': 'Review the upcoming assessments in the list above and prepare for the topics scheduled to close earliest.',
    'Generate my report': 'You can download and review comprehensive performance reports under the Results tab.'
  };

  function addMsg(text, who) {
    if (!log) return;
    var d = document.createElement('div'); d.className = 'msg msg--' + who; d.textContent = text;
    log.appendChild(d); log.scrollTop = log.scrollHeight; return d;
  }

  async function ask(q) {
    if (!q || !q.trim()) return;
    q = q.trim();
    addMsg(q, 'me');
    var t = document.createElement('div'); t.className = 'msg msg--bot typing'; t.innerHTML = '<i></i><i></i><i></i>';
    log.appendChild(t); log.scrollTop = log.scrollHeight;

    try {
      var res = await fetch('<?= BASE_URL ?>/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: q })
      });
      var data = await res.json();
      t.remove();
      var botReply = '';
      if (data && data.message) {
        botReply = data.message;
      } else if (data && data.python_response && data.python_response.data && data.python_response.data.message) {
        botReply = data.python_response.data.message;
      } else if (data && data.error) {
        botReply = data.error;
      } else {
        botReply = REPLIES[q] || 'I can help with your test performance, review topics, or study priorities. Try one of the shortcuts above.';
      }
      botReply = botReply.replace(/<think>[\s\S]*?<\/think>/gi, '').trim();
      addMsg(botReply, 'bot');
    } catch (err) {
      t.remove();
      addMsg(REPLIES[q] || 'I can help with your weak topics, your PCI, a study plan or a report. Try one of the shortcuts above.', 'bot');
    }
  }

  function chatOpen(open) {
    if (!chat || !fab) return;
    chat.classList.toggle('open', open);
    fab.setAttribute('aria-expanded', String(open));
    fab.setAttribute('aria-label', open ? 'Close study assistant' : 'Open study assistant');
    if (open && input) setTimeout(function () { input.focus(); }, 220);
  }
  if (fab) fab.addEventListener('click', function () { chatOpen(!chat.classList.contains('open')); });
  if ($('#pills')) $('#pills').addEventListener('click', function (e) { var p = e.target.closest('.pill'); if (p) ask(p.textContent); });
  if ($('#chatSend') && input) $('#chatSend').addEventListener('click', function () { var v = input.value; input.value = ''; ask(v); });
  if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var v = input.value; input.value = ''; ask(v); } });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeMenus(); drawer(false);
    if (chat && chat.classList.contains('open')) { chatOpen(false); if (fab) fab.focus(); }
  });

  /* ---------- Load sequence: one short, quiet moment ---------- */
  function countUp(el) {
    var to = +el.dataset.count || 0;
    if (reduce || !to) { el.textContent = to; return; }
    var t0 = null, dur = 700;
    function step(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min((ts - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(to * e);
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  function start() {
    document.body.classList.add('is-ready');
    $$('[data-count]').forEach(countUp);
    $$('#donut circle[data-i]').forEach(function (c) {
      var s = donutSegs[+c.dataset.i];
      if (s && s.len) c.style.strokeDasharray = s.len + ' ' + (CIRC - s.len);
    });
  }
  setTimeout(start, reduce ? 0 : 300);
})();
</script>
</body>
</html>
