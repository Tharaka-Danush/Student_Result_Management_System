<?php
require_once 'auth.php'; require_student();
require_once 'db.php';
$id = (string)$_SESSION['student_id'];
$year = is_string($_GET['year'] ?? '') ? ($_GET['year'] ?? '') : '';
$semester = is_string($_GET['semester'] ?? '') ? ($_GET['semester'] ?? '') : '';
$explicitTerm = array_key_exists('year', $_GET) || array_key_exists('semester', $_GET);
$s = mysqli_prepare($conn, 'SELECT * FROM students WHERE student_id=? LIMIT 1');
mysqli_stmt_bind_param($s, 's', $id); mysqli_stmt_execute($s);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
$allRows = []; $rows = []; $message = '';
$ar = mysqli_prepare($conn, "SELECT * FROM results WHERE student_id=? AND publish_status='published' AND approval_status='approved' ORDER BY year,semester,subject_code");
mysqli_stmt_bind_param($ar, 's', $id); mysqli_stmt_execute($ar);
$agr = mysqli_stmt_get_result($ar);
while ($ax = mysqli_fetch_assoc($agr)) $allRows[] = $ax;
$history = array_reverse(result_history_by_term($allRows), true);
if ($explicitTerm && !valid_year_semester($year, $semester)) {
    $message = 'Please select a valid academic year and semester.';
} elseif (valid_year_semester($year, $semester)) {
    foreach ($allRows as $row) if ((string)$row['year'] === $year && (string)$row['semester'] === $semester) $rows[] = $row;
} else {
    $message = 'Select an academic year and semester, then click View Results.';
}
$chosenIds=array_column(effective_result_rows($rows),'id');
$summary = calculate_gpa_summary($rows);
$cgpaSummary = calculate_cgpa_summary($allRows);
$studentName = $student['name'] ?? 'Student';
$totalSubjects = count(effective_result_rows($rows));
$publishedDates = array_filter(array_column($rows, 'published_at'));
$publishedAt = $publishedDates ? max($publishedDates) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
// A selected semester belongs to this view. Reloading the clean URL starts fresh.
if (window.history.replaceState) {
    window.history.replaceState(null, '', window.location.pathname + window.location.hash);
}
window.addEventListener('pageshow', function () {
    var form = document.getElementById('academic-term-form');
    if (form) form.reset();
});
</script>
<title>Your Results</title><link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="modern-ui.css?v=20261008-student1">


<style>
/* Keep the viewport as the sticky header's scroll container. */
body.student-portal{overflow-x:clip!important;overflow-y:visible!important}
.student-portal .main-wrapper,.student-portal .result-workspace,.student-portal .result-shell{overflow:visible!important}

.student-portal .result-panel,.student-portal .table-scroll{overflow:visible!important}
.student-portal .table-scroll{max-height:none!important}
.student-portal .table-scroll thead{position:sticky!important;top:0;z-index:40;background:var(--surface-2)}
.student-portal .table-scroll thead th{position:static!important;background:var(--surface-2);box-shadow:0 1px 0 var(--line)}
@media(max-width:900px){
 .student-portal .table-scroll table{min-width:0!important;width:100%;table-layout:fixed}
 .student-portal .table-scroll th,.student-portal .table-scroll td{padding:10px 7px;white-space:normal;overflow-wrap:anywhere}
 .student-portal .table-scroll .code-pill{white-space:normal;overflow-wrap:anywhere;padding:3px}
 .student-portal .table-scroll .btn-link{white-space:normal;padding:5px;font-size:10px}
 .student-portal .table-scroll[aria-label="Published semester results"] th:nth-child(1){width:17%}
 .student-portal .table-scroll[aria-label="Published semester results"] th:nth-child(2){width:35%}
 .student-portal .table-scroll[aria-label="Published semester results"] th:nth-child(n+3){width:12%}
}
@media print{.student-portal .table-scroll thead{position:static!important}}
</style>
</head>
<body class="result-page student-portal">
<div class="main-wrapper">
<?php include 'public_header.php'; ?>
<main class="result-workspace">
  <nav class="student-account-bar" aria-label="Student account">
    <span><span class="account-dot"></span> Student Portal</span>
    <div><a href="student_change_pin.php">Change PIN</a><a href="student_result_issue.php">Report a Result Issue</a><a href="student_logout.php">Logout ↗</a></div>
  </nav>
  <div class="result-shell">
    <section class="result-hero">
      <div class="hero-left">
        <div class="eyebrow">Student Academic Record</div>
        <h1>Your Results</h1>
        <p class="hero-sub"><?= e($studentName) ?> · <?= e(app_setting($conn,'faculty_name','Faculty of Applied Sciences')) ?></p>
        <div class="student-identity-grid">
          <div><span>Student ID</span><strong><?= e($id) ?></strong></div>
          <div><span>Index Number</span><strong><?= e($student['index_number']) ?></strong></div>
          <div><span>Batch</span><strong><?= e($student['batch']) ?></strong></div>
          <div><span>Section</span><strong><?= e($student['section']) ?></strong></div>
        </div>
        <div class="term-chips">
          <?php if (valid_year_semester($year, $semester)): ?>
          <span class="term-chip">Year <?= e($year) ?></span>
          <span class="term-chip">Semester <?= e($semester) ?></span>
          <?php endif; ?>
          <?php if ($rows): ?><span class="term-chip"><?=count(effective_result_rows($rows))?> Subjects</span><?php endif; ?>
        </div>
      </div>
      <?php if ($student): ?>
      <div class="hero-overall"><div class="student-overall-card">
        <span class="overall-label">Overall GPA</span>
        <div class="overall-number"><?= $allRows ? gpa_display($cgpaSummary) : '—' ?></div>
        <div class="overall-class"><?= $allRows ? e($cgpaSummary['class']) : 'Awaiting published results' ?></div>
        <small>Unofficial GPA · Provisional class · <?=e('Repeat policy: '.str_replace('_',' ',app_setting($conn,'repeat_selection_policy','latest')))?></small>
      </div></div>
      <?php endif; ?>
    </section>

    <section class="term-selector">
      <div class="selector-title"><div><h2>Academic Period</h2><span>Select an academic year and semester to view your published results.</span></div></div>
      <form method="get" class="term-form" id="academic-term-form" autocomplete="off">
        <div class="term-field">
          <label for="year">Academic Year</label>
          <select name="year" id="year" required>
            <option value="">Select Year</option>
            <?php for($i=1;$i<=4;$i++): ?>
              <option value="<?= $i ?>" <?= $year===(string)$i?'selected':'' ?>>Year <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="term-field">
          <label for="semester">Semester</label>
          <select name="semester" id="semester" required>
            <option value="">Select Semester</option>
            <option value="1" <?= $semester==='1'?'selected':'' ?>>Semester 1</option>
            <option value="2" <?= $semester==='2'?'selected':'' ?>>Semester 2</option>
          </select>
        </div>
        <button type="submit" class="term-submit">View Results</button>
      </form>
    </section>

    <?php if (!$message && $rows): ?>
    <section class="summary-row">
      <div class="summary-card-modern">
        <div><span class="label">Semester GPA</span><strong class="value"><?= gpa_display($summary) ?></strong></div>
        <div class="summary-icon">GPA</div>
      </div>
      <div class="summary-card-modern"><div><span class="label">Total Credits</span><strong class="value"><?= e($summary['credits']) ?></strong></div><div class="summary-icon">CR</div></div>
      <div class="summary-card-modern"><div><span class="label">Subjects</span><strong class="value"><?= $totalSubjects ?></strong></div><div class="summary-icon">#</div></div>
      <div class="summary-card-modern"><div><span class="label">Academic Period</span><strong class="value term-value">Year <?= e($year) ?> / Sem <?= e($semester) ?></strong></div><div class="summary-icon">Y/S</div></div>
    </section>
    <?php endif; ?>

    <section class="result-panel" id="semester-results" tabindex="-1" aria-labelledby="semester-results-title">
      <div class="panel-head">
        <div>
          <h2 id="semester-results-title">Semester Results</h2>
          <small><?= valid_year_semester($year,$semester) ? 'Year '.e($year).' · Semester '.e($semester) : 'Choose an academic period above' ?></small>
        </div>
        <?php if ($rows): ?><div class="publication-meta"><span class="panel-status">Published</span><small><?= $publishedAt ? 'Last published: '.e(date('d M Y, H:i',strtotime($publishedAt))) : 'Publication date not recorded' ?></small></div><?php endif; ?>
      </div>

      <?php if ($message): ?>
        <div class="empty-state">
          <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v13H4z"/><path d="M8 9h8M8 13h5"/></svg></div>
          <strong><?= e($message) ?></strong>
          <span>Your published semester results will appear here.</span>
        </div>
      <?php elseif (!$rows): ?>
        <div class="empty-state">
          <div class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v13H4z"/><path d="M8 9h8M8 13h5"/></svg></div>
          <strong>No published results found</strong>
          <span>Results for this semester have not been published yet. Please check again later.</span>
        </div>
      <?php else: ?>
      <div class="table-scroll" tabindex="0" role="region" aria-label="Published semester results">
        <table class="results-table" data-student-frozen-header>
          <thead><tr><th scope="col">Subject Code</th><th scope="col">Subject Name</th><th scope="col">Credits</th><th scope="col">Marks</th><th scope="col">Grade</th><th scope="col">GPA</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><span class="code-pill"><?= e($row['subject_code']) ?></span></td>
              <td><span class="subject-name"><?= e($row['subject']) ?><small style="display:block"><?=e(result_attempt_label($row))?> · <?=in_array($row['id'],$chosenIds,true)?'Selected attempt':'Other attempt'?><?=((int)$row['includes_gpa']?'':' · Non-GPA')?></small></span></td>
              <td><?=e($row['credit'])?></td>
              <td><?= $row['marks'] === null ? '—' : e(number_format((float)$row['marks'],2)) ?></td>
              <td><span class="grade-badge"><?= e($row['grade']) ?></span></td>
              <td><span class="gpa-value"><?= result_gpa_display($row) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th colspan="2" scope="row">Semester Summary</th><td><?= e($summary['credits']) ?></td><td colspan="2">Semester GPA</td><td><strong><?= gpa_display($summary) ?></strong></td></tr></tfoot>
        </table>
      </div>
      <?php endif; ?>
      <?php if ($rows): ?><div class="semester-panel-actions"><p>See a mark that needs checking? Submit an issue for registrar review.</p><div><a class="btn-link btn-secondary" href="student_result_issue.php?year=<?= e($year) ?>&amp;semester=<?= e($semester) ?>">Report an Issue</a><a class="btn-link" href="result_sheet.php?year=<?= e($year) ?>&amp;semester=<?= e($semester) ?>">View / Print Semester Sheet ↗</a></div></div><?php endif; ?>
    </section>

    <?php if($history): ?>
    <section class="result-panel">
      <div class="panel-head"><div><h2>Academic Result History</h2><small>Published academic record · newest semester first</small></div><span class="panel-status">Published Results</span></div>
      <div class="table-scroll"><table class="results-table" data-student-frozen-header><thead><tr><th>Academic Term</th><th>Semester GPA</th><th>Credits</th><th>Subjects</th><th>Results</th></tr></thead><tbody>
      <?php foreach($history as $h): ?><tr><td><span class="subject-name">Year <?=e($h['year'])?> · Semester <?=e($h['semester'])?></span></td><td><span class="gpa-value"><?=gpa_display($h['summary'])?></span></td><td><?=e($h['summary']['credits'])?></td><td><?=count(effective_result_rows($h['rows']))?></td><td><a class="btn-link small-btn" href="student_result.php?year=<?=urlencode($h['year'])?>&amp;semester=<?=urlencode($h['semester'])?>#semester-results">View Results</a></td></tr><?php endforeach;?>
      </tbody></table></div>
    </section>
    <?php endif; ?>
    <div class="result-actions">
      <div class="action-note">All published attempts are shown. Each subject counts once; Non-GPA subjects are excluded from GPA. Class is provisional.</div>
      <div class="action-buttons">
        <a href="student_logout.php" class="btn-link btn-secondary">Logout</a>
      </div>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>

</div>
<!-- Your Results header revision: 20261010-verified -->
<script>
/* Inline fallback for browsers that do not keep the native table header at the viewport. */
(function () {
 const tables = document.querySelectorAll('[data-student-frozen-header]');
 if (!tables.length) return;
 const style = document.createElement('style');
 style.textContent = '.student-table-header{position:fixed;z-index:40;overflow:hidden;pointer-events:none;box-shadow:0 3px 8px #172b4d18}.student-table-header[hidden]{display:none!important}.student-table-header table{border-collapse:collapse;table-layout:fixed;margin:0;min-width:0!important}@media print{.student-table-header{display:none!important}}';
 document.head.appendChild(style);
 const items = [];
 tables.forEach(table => {
  const wrap = table.closest('.table-scroll');
  const head = table.tHead;
  if (!wrap || !head || !head.rows.length) return;
  const sourceCells = Array.from(head.querySelectorAll('th'));
  sourceCells.forEach(cell => cell.style.position = 'static');
  const floating = document.createElement('div');
  floating.className = 'student-table-header';
  floating.setAttribute('aria-hidden', 'true');
  floating.hidden = true;
  const copy = document.createElement('table');
  const columns = document.createElement('colgroup');
  sourceCells.forEach(() => columns.appendChild(document.createElement('col')));
  copy.appendChild(columns);
  copy.appendChild(head.cloneNode(true));
  floating.appendChild(copy);
  document.body.appendChild(floating);
  items.push({table,wrap,head,sourceCells,floating,copy,columns,copyCells:Array.from(copy.querySelectorAll('th'))});
 });
 let pending = false;
 function update() {
  pending = false;
  items.forEach(item => {
   const {table,wrap,head,sourceCells,floating,copy,columns,copyCells} = item;
   const heading = head.getBoundingClientRect();
   const bounds = table.getBoundingClientRect();
   const viewport = wrap.getBoundingClientRect();
   floating.hidden = heading.top >= 0 || bounds.bottom <= 0 || !wrap.getClientRects().length;
   if (floating.hidden) return;
   sourceCells.forEach((cell,i) => {
    columns.children[i].style.width = cell.getBoundingClientRect().width + 'px';
    const computed = getComputedStyle(cell);
    ['fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','textTransform','textAlign','verticalAlign','paddingTop','paddingRight','paddingBottom','paddingLeft','color','borderBottom','whiteSpace','boxSizing'].forEach(key => copyCells[i].style[key] = computed[key]);
    copyCells[i].style.position = 'static';
    copyCells[i].style.backgroundColor = computed.backgroundColor;
   });
   copy.style.width = bounds.width + 'px';
   copy.style.transform = 'translateX(' + (-wrap.scrollLeft) + 'px)';
   floating.style.left = (viewport.left + wrap.clientLeft) + 'px';
   floating.style.width = wrap.clientWidth + 'px';
   floating.style.top = Math.min(0,bounds.bottom - heading.height) + 'px';
  });
 }
 function schedule() {
  if (!pending) { pending = true; requestAnimationFrame(update); }
 }
 // Capture scrolling on the page and any enclosing scroll surface.
 window.addEventListener('scroll', schedule, {capture:true,passive:true});
 window.addEventListener('resize', schedule);
 window.addEventListener('pageshow', schedule);
 window.addEventListener('afterprint', schedule);
 if (window.ResizeObserver) {
  const observer = new ResizeObserver(schedule);
  items.forEach(item => observer.observe(item.table));
 }
 if (document.fonts) document.fonts.ready.then(schedule);
 update();
})();

</script>
</body>
</html>
