<?php
require_once 'auth.php'; require_student();
require_once 'db.php';
require_once 'result_document.php';

$id = (string)$_SESSION['student_id'];
$year = (string)($_GET['year'] ?? '');
$semester = (string)($_GET['semester'] ?? '');
$student = null;
$rows = [];
$message = '';

if (!valid_student_id($id) || !valid_year_semester($year, $semester)) {
    $message = 'Invalid result request.';
} else {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM students WHERE student_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$student) {
        $message = 'Student not found.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM results WHERE student_id = ? AND year = ? AND semester = ? AND publish_status='published' AND approval_status='approved' ORDER BY subject_code ASC, id ASC");
        mysqli_stmt_bind_param($stmt, 'sss', $id, $year, $semester);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        if (!$rows) {
            $message = 'No results found for the selected semester.';
        }
    }
}

$rows = effective_result_rows($rows);
$summary = calculate_gpa_summary($rows);
$settingUniversity=app_setting($conn,'university_name','South Eastern University of Sri Lanka');
$settingRegistrar=app_setting($conn,'registrar_name','MACM Rameez');
$settingRegistrarTitle=app_setting($conn,'registrar_title','Deputy Registrar/Examination');
$settingFaculty=app_setting($conn,'faculty_name','Faculty of Applied Sciences');
$status = semester_result_status($rows);
$verifyPayload=$rows?semester_verification_payload($conn,$student,$year,$semester,$rows):'';
$verificationCode=$rows?ensure_verification_record($conn,'semester_result',$id,$year,$semester,$verifyPayload):'';
$printTableFont=min(8,128/max(16,count($rows)));
$printRowPadding=1;
if(count($rows)>=12 && max(array_map(fn($r)=>strlen((string)$r['subject']),$rows)?:[0])>70){$printTableFont=min($printTableFont,6.8);$printRowPadding=0.5;}
$verificationUrl=$verificationCode?result_verification_url($conn,$verificationCode):'';
$qrMatrix=$verificationUrl!==''?result_qr_matrix($verificationUrl):[];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Result Sheet</title><link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="modern-ui.css?v=20261006-modern2">
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f1f1f;
        }

        body {
            background: linear-gradient(180deg,#f7fafc 0%,#eef3f7 100%);
            padding: 28px 14px 40px;
        }

        .result-sheet {
            width: min(900px, 100%);
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 22px 55px rgba(15,39,66,.12);
            padding: 34px 42px 38px;
        }

        .sheet-header {
            display: grid;
            grid-template-columns: 115px 1fr 115px;
            align-items: center;
            gap: 18px;
            padding-bottom: 22px;
            border-bottom: 2px solid #3275ec;
        }

        .sheet-logo {
            width: 105px !important;
            max-width: 105px !important;
            height: auto !important;
            display: block;
            margin: 0 auto;
        }

        .heading {
            text-align: center;
        }

        .heading h1 {
            margin: 0 0 6px;
            font-size: 24px;
            line-height: 1.25;
            color: #172b4d;
        }

        .heading h2 {
            margin: 0;
            font-size: 17px;
            line-height: 1.35;
            font-weight: 600;
            color: #333;
        }

        .sheet-spacer { width: 105px; }

        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 36px;
            padding: 24px 4px 12px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 145px 1fr;
            gap: 10px;
            line-height: 1.45;
        }

        .detail-label { font-weight: 700; }

        .result-table-wrap {
            overflow-x: auto;
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        th, td {
            border: 1px solid #cfd6dc;
            padding: 10px 12px;
            font-size: 15px;
        }

        th {
            background: #f0f5f8;
            color: #33475b;
            font-weight: 700;
            text-align: center;
        }

        td:nth-child(1),
        td:nth-child(3),
        td:nth-child(4) {
            text-align: center;
        }

        td:nth-child(2) { text-align: left; }

        tbody tr:nth-child(even) { background: #fafbfc; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 22px;
        }

        .summary-card {
            border: 1px solid #d5dce2;
            border-radius: 14px;
            background: linear-gradient(180deg,#fbfcfd,#f6f9fb);
            padding: 14px 10px;
            text-align: center;
        }

        .summary-card .label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: #30465a;
        }

        .summary-card .value {
            font-size: 17px;
            font-weight: 600;
        }

        .sheet-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 30px;
            margin-top: 44px;
            padding-top: 20px;
            border-top: 1px solid #d7dce0;
            font-size: 14px;
            line-height: 1.45;
        }

        .signature { text-align: left; }

        .actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 22px auto 0;
        }

        .actions form { margin: 0; padding: 0; background: none; }

        .btn,
        .actions button {
            display: inline-block;
            min-width: 150px;
            border: 0;
            border-radius: 7px;
            padding: 11px 18px;
            font-size: 15px;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            color: #fff;
            background: #3275ec;
        }

        .btn.back { background: #647487; }

        .message {
            width: min(650px, 100%);
            margin: 40px auto;
            padding: 16px;
            border-radius: 8px;
            background: #ffebee;
            color: #b71c1c;
            text-align: center;
        }

        @media (max-width: 700px) {
            body { padding: 12px 8px 28px; }
            .result-sheet { padding: 24px 16px 28px; }
            .sheet-header {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .sheet-logo { width: 90px !important; max-width: 90px !important; }
            .sheet-spacer { display: none; }
            .heading h1 { font-size: 20px; }
            .heading h2 { font-size: 15px; }
            .student-details { grid-template-columns: 1fr; gap: 8px; }
            .detail-row { grid-template-columns: 125px 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
            .sheet-footer { flex-direction: column; align-items: flex-start; }
        }

        .sheet-header{border-bottom-color:#3275ec}.detail-label{font-size:11px;color:#738096;text-transform:uppercase;letter-spacing:.4px}.detail-row{font-size:13px}.heading h2{font-size:14px;color:#738096;font-weight:500}.summary-card{background:#f8faff;border-color:#e4e9f1;border-radius:10px}.summary-card .label{font-size:11px;color:#738096}.summary-card .value{color:#172b4d}.actions .back{background:white;color:#738096;border:1px solid #dfe5ef}.sheet-footer{font-size:12px}.result-sheet th{background:#f5f7fb;color:#738096;text-transform:uppercase;font-size:10px;letter-spacing:.4px}.result-sheet td{font-size:12px}.result-sheet table th,.result-sheet table td{border-color:#e4e9f1}.result-sheet td:nth-child(n+3){text-align:center}
        /* Compact physical A4 layout; also used while measuring before print. */
        .result-sheet.print-compact {width:180mm;max-width:none;margin:0 auto;padding:0;box-shadow:none;border-radius:0;background:white;color:#172b4d;}
        .print-compact .sheet-header {display:flex;align-items:center;gap:5mm;padding-bottom:4mm;}
        .print-compact .sheet-logo {width:19mm!important;max-width:19mm!important;margin:0;flex:none;}
        .print-compact .heading {flex:1;}
        .print-compact .heading h1 {font-size:15pt;line-height:1.2;margin:0 0 2mm;}
        .print-compact .heading h2 {font-size:10pt;line-height:1.25;}
        .print-compact .sheet-spacer {display:block;width:19mm;flex:none;}
        .print-compact .student-details {display:grid;grid-template-columns:1fr 1fr;gap:2mm 6mm;padding:4mm 0 2mm;}
        .print-compact .detail-row {grid-template-columns:27mm 1fr;gap:2mm;font-size:9pt;line-height:1.25;}
        .print-compact .detail-label {font-size:8pt;letter-spacing:0;}
        .print-compact .result-table-wrap {margin-top:3mm;overflow:visible;}
        .print-compact table {min-width:0;width:100%;table-layout:fixed;}
        .print-compact table th,.print-compact table td {padding:<?=number_format($printRowPadding,2,'.','')?>mm 1mm;font-size:<?= number_format($printTableFont,2,'.','') ?>pt;line-height:1.2;overflow-wrap:anywhere;}
        .print-compact table th:nth-child(1) {width:9mm!important;white-space:nowrap;}
        .print-compact table th:nth-child(2) {width:auto;}
        .print-compact table th:nth-child(3) {width:27mm!important;}
        .print-compact table th:nth-child(4),.print-compact table th:nth-child(5),.print-compact table th:nth-child(6),.print-compact table th:nth-child(7) {width:15mm!important;}
        .print-compact .summary-grid {display:flex;gap:3mm;margin-top:4mm;}
        .print-compact .summary-card {flex:1;padding:2.5mm 1mm;border-radius:2mm;}
        .print-compact .summary-card .label {font-size:8pt;margin-bottom:1mm;}
        .print-compact .summary-card .value {font-size:10pt;}
        .print-compact .sheet-verification {margin-top:3mm!important;padding:2mm 3mm!important;border-radius:2mm!important;font-size:8pt;line-height:1.2;}
        .print-compact .sheet-footer {flex-direction:row;align-items:flex-end;gap:5mm;margin-top:6mm;padding-top:3mm;font-size:9pt;line-height:1.3;}
        @media print {
            @page {size:A4 portrait;margin:15mm;}
            html,body {background:white!important;margin:0!important;padding:0!important;min-height:0!important;height:auto!important;color-scheme:light;}
            .result-sheet {box-shadow:none;border-radius:0;break-inside:avoid;page-break-inside:avoid;}
            .actions,.toast-stack,.ui-modal-backdrop {display:none!important;}
            .result-sheet a {color:inherit;text-decoration:none;}
            .result-sheet tr,.summary-grid,.sheet-footer {break-inside:avoid;}
        }

        .document-faculty{font-size:12px;margin:0 0 5px;color:#738096}.document-period{display:flex;justify-content:space-between;gap:15px;padding:12px 14px;margin-top:18px;background:#f5f8ff;border:1px solid #d8e1f3;font-size:12px}.document-section-title{font-size:12px;margin:15px 0 8px;color:#172b4d}.summary-grid{grid-template-columns:1.1fr 1.1fr 2fr .8fr .8fr;gap:8px}.summary-card{min-width:0}.summary-card .label{font-size:9px}.summary-card .value{font-size:14px}.needs-review td{font-weight:700}.review-legend{font-size:10px;margin-top:8px}.sheet-verification{display:flex;justify-content:space-between;align-items:center;gap:18px;border:1px dashed #9fb0bf;padding:12px 14px;margin-top:12px;font-size:11px;background:#f8fbfc;border-radius:10px}.verification-qr{width:95px;flex:none;text-align:center;display:block;text-decoration:none}.verification-qr svg{display:block;width:100%;height:auto}.verification-qr small{display:block;font-size:9px}.signature{text-align:right}.signature-space{height:30px;border-bottom:1px solid #b4beca;min-width:190px;margin-bottom:6px}.official-stamp{width:120px;height:55px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:5px;border:1px solid #c8d0dc;font-size:9px;color:#738096;margin-top:10px}
        .print-compact .document-faculty{font-size:8pt;margin-bottom:1mm}.print-compact .heading h2{color:#3275ec;font-weight:700}.print-compact .document-period{padding:2.5mm 3mm;margin-top:3mm;font-size:9pt}.print-compact .document-section-title{font-size:9pt;margin:3mm 0 1mm}.print-compact .student-details{padding:2mm 0;gap:1.5mm 6mm}.print-compact .result-table-wrap{margin-top:1mm}.print-compact .summary-grid{gap:1.5mm;margin-top:3mm}.print-compact .summary-card:nth-child(3){flex:2}.print-compact .summary-card .label{font-size:6.5pt}.print-compact .summary-card .value{font-size:9pt}.print-compact .review-legend{font-size:7pt;margin-top:2mm}.print-compact .sheet-verification{margin-top:2mm!important;min-height:22mm;padding:1mm 2mm!important}.print-compact .verification-qr{width:22mm}.print-compact .verification-qr small{font-size:6pt}.print-compact .sheet-footer{margin-top:3mm;padding-top:2mm}.print-compact .signature-space{height:8mm;min-width:55mm;margin-bottom:1mm}.print-compact .official-stamp{width:35mm;height:14mm;padding-bottom:1mm;margin-top:2mm;font-size:7pt}
        @media(max-width:700px){.summary-grid{grid-template-columns:repeat(2,1fr)}.summary-card:nth-child(3){grid-column:1/-1}.document-period{flex-wrap:wrap}.sheet-verification{align-items:flex-start}.signature{text-align:left}.print-compact .signature{text-align:right}}
        .student-name-row{grid-column:1/-1;}
        .print-compact .student-details{display:flex;flex-wrap:wrap;gap:1.5mm 6mm}.print-compact .detail-row{width:calc(50% - 3mm)}.print-compact .student-name-row{width:100%}
    </style>
</head>
<body>
<?php if ($message): ?>
    <div class="message"><?= e($message) ?></div>
<?php else:
    $batch = (int)$student['batch'];
    $start = 2000 + $batch;
    $end = $start + 1;
    $section = $student['section'] === 'PS'
        ? 'Physical Science'
        : ($student['section'] === 'BS' ? 'Biological Science' : $student['section']);
?>
    <main class="result-sheet">
        <header class="sheet-header">
            <img class="sheet-logo" src="logo.png" alt="South Eastern University Logo">
            <div class="heading">
                <h1><?= e($settingUniversity) ?></h1>
                <p class="document-faculty"><?=e($settingFaculty)?></p><h2>SEMESTER RESULT SHEET</h2>
            </div>
            <div class="sheet-spacer" aria-hidden="true"></div>
        </header>

        <div class="document-period"><strong>Academic Period: <?=e($start)?>/<?=e($end)?></strong><span>Year: <?=e($year)?></span><span>Semester: <?=e($semester)?></span></div>
        <h3 class="document-section-title">STUDENT INFORMATION</h3>
        <section class="student-details">
            <div class="detail-row student-name-row"><span class="detail-label">Name</span><span>: <?= e($student['name']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Student ID</span><span>: <?= e($id) ?></span></div>
            <div class="detail-row"><span class="detail-label">Index Number</span><span>: <?= e($student['index_number']) ?></span></div>
            <div class="detail-row"><span class="detail-label">Course of Study</span><span>: <?= e($section) ?></span></div>
            <div class="detail-row"><span class="detail-label">Section</span><span>: <?= e($student['section']) ?></span></div>
        </section>

        <h3 class="document-section-title">SEMESTER RESULTS</h3>
        <div class="result-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">No.</th>
                        <th>Subject</th>
                        <th style="width:170px;">Subject Code</th>
                        <th>Credit</th><th>Marks</th><th style="width:90px;">Grade</th><th>GPA</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <tr class="<?=result_needs_review($row)?'needs-review':''?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= e($row['subject']) ?><?=((int)($row['attempt_no']??1)>1?' [R'.(int)$row['attempt_no'].']':'')?></td>
                        <td><?= e($row['subject_code']) ?></td>
                        <td><?=e($row['credit'])?></td><td><?=number_format((float)$row['marks'],2)?></td><td><?= e($row['grade']) ?><?=result_needs_review($row)?' *':''?></td><td><?=result_gpa_display($row)?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="summary-grid">
            <div class="summary-card">
                <span class="label">Result</span>
                <span class="value"><?= e($status) ?></span>
            </div>
            <div class="summary-card">
                <span class="label">Semester GPA</span>
                <span class="value"><?= gpa_display($summary) ?></span>
            </div>
            <div class="summary-card">
                <span class="label">Class (indicative)</span>
                <span class="value"><?= e($summary['class']) ?></span>
            </div>
            <div class="summary-card"><span class="label">Total Credits</span><span class="value"><?=e($summary['credits'])?></span></div>
            <div class="summary-card"><span class="label">Subjects</span><span class="value"><?=count($rows)?></span></div>
        </section>
        <p class="review-legend">* Below minimum passing GPA. R = repeat attempt; Non-GPA subjects are excluded from GPA.</p>
        <?php if($verificationCode): ?><div class="sheet-verification"><div><strong>Result Sheet Verification Code:</strong> <?=e($verificationCode)?><br><small>Generated: <?=e(date('Y-m-d H:i'))?></small><br><a href="verify_result.php?code=<?=urlencode($verificationCode)?>" target="_blank" rel="noopener">Verify online</a></div><?php if($qrMatrix):?><a class="verification-qr" href="<?=e($verificationUrl)?>" target="_blank" rel="noopener"><?=result_qr_svg($qrMatrix)?><small>Scan to verify</small></a><?php endif;?></div><?php endif; ?>

        <footer class="sheet-footer">
            <div>Date: <?= date('F j, Y') ?><div class="official-stamp">Official Stamp</div></div>
            <div class="signature"><div class="signature-space" aria-label="Registrar signature space"></div>
                <strong><?= e($settingRegistrar) ?>,</strong><br>
                <?= e($settingRegistrarTitle) ?>,<br>
                For Registrar
            </div>
        </footer>
    </main>
<?php endif; ?>

<div class="actions">
    <a class="btn back" href="student_result.php?year=<?= urlencode($year) ?>&semester=<?= urlencode($semester) ?>">Back</a>
    <?php if ($rows): ?><button type="button" onclick="window.print()">Print Result Sheet</button>
        <form method="post" action="download_result.php" target="_blank">
            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?= e($id) ?>">
            <input type="hidden" name="year" value="<?= e($year) ?>">
            <input type="hidden" name="semester" value="<?= e($semester) ?>">
            <button type="submit">Download Result Sheet</button>
        </form>
    <?php endif; ?>
</div>
<script>
(function () {
    var sheet = document.querySelector('.result-sheet');
    if (!sheet) return;
    function fitPrintSheet() {
        sheet.classList.add('print-compact');
        sheet.style.zoom = '1';
        // Measure physical mm using the browser, not assumed screen resolution.
        var ruler = document.createElement('div');
        ruler.style.cssText = 'position:absolute;visibility:hidden;height:100mm;width:100mm;pointer-events:none;';
        document.body.appendChild(ruler);
        var pxPerMm = ruler.getBoundingClientRect().height / 100;
        ruler.remove();
        var height = Math.max(sheet.scrollHeight, sheet.getBoundingClientRect().height);
        var width = Math.max(sheet.scrollWidth, sheet.getBoundingClientRect().width);
        // A4 minus 15 mm page margins, with 2 mm spare height for rounding.
        var scale = Math.min(1, 265 * pxPerMm / height, 180 * pxPerMm / width);
        sheet.style.zoom = String(scale);
    }
    function restoreScreenSheet() {
        sheet.classList.remove('print-compact');
        sheet.style.removeProperty('zoom');
    }
    window.addEventListener('beforeprint', fitPrintSheet);
    window.addEventListener('afterprint', restoreScreenSheet);
})();
</script>
<script src="ui-enhancements.js?v=20260927-ui4" defer></script></body>
</html>
