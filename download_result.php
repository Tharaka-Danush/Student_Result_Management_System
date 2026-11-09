<?php
require_once 'auth.php';
require_student();
require 'fpdf/fpdf.php';
require 'db.php';
require_once 'result_document.php';

function normalizeText($text) {
    $text = str_replace(['−','–','—','‒','‑'], '-', (string)$text);
    return iconv('UTF-8','Windows-1252//TRANSLIT',$text);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    die('Invalid access method.');
}

$id = (string)$_SESSION['student_id'];
$year = (string)($_POST['year'] ?? '');
$semester = (string)($_POST['semester'] ?? '');

if (!valid_student_id($id) || !valid_year_semester($year, $semester)) {
    die('Invalid request.');
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM students WHERE student_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$student) die('Student not found.');

$stmt = mysqli_prepare($conn, "SELECT * FROM results WHERE student_id=? AND year=? AND semester=? AND publish_status='published' AND approval_status='approved' ORDER BY subject_code ASC, id ASC");
mysqli_stmt_bind_param($stmt, 'sss', $id, $year, $semester);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
if (!$rows) die('No published results found for this semester.');

$rows = effective_result_rows($rows);
$summary = calculate_gpa_summary($rows);
$status = semester_result_status($rows);
$verifyPayload=semester_verification_payload($conn,$student??[],$year,$semester,$rows);
$verificationCode = ensure_verification_record($conn, 'semester_result', $id, $year, $semester, $verifyPayload);
$verificationUrl=result_verification_url($conn,$verificationCode);
$qrMatrix=$verificationUrl!==''?result_qr_matrix($verificationUrl):[];

$university = app_setting($conn, 'university_name', 'South Eastern University of Sri Lanka');
$faculty = app_setting($conn, 'faculty_name', 'Faculty of Applied Sciences');
$registrar = app_setting($conn, 'registrar_name', 'MACM Rameez');
$registrarTitle = app_setting($conn, 'registrar_title', 'Deputy Registrar/Examination');

$section = $student['section'] === 'PS' ? 'Physical Science' : ($student['section'] === 'BS' ? 'Biological Science' : $student['section']);
$batch = (int)$student['batch'];
$academicStart = 2000 + $batch;
$academicEnd = $academicStart + 1;

class SemesterResultPDF extends FPDF {
    public $university = '';
    public $faculty = '';
    public $verificationCode = '';

    function LineCount($w,$text) {
        $cw=$this->CurrentFont['cw'];$max=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',(string)$text);$n=strlen($s);$sep=-1;$i=0;$j=0;$length=0;$lines=1;
        while($i<$n){$c=$s[$i];if($c==="\n"){$i++;$sep=-1;$j=$i;$length=0;$lines++;continue;}if($c===' ')$sep=$i;$length+=$cw[$c]??0;if($length>$max){if($sep===-1){if($i===$j)$i++;}else $i=$sep+1;$sep=-1;$j=$i;$length=0;$lines++;}else $i++;}
        return $lines;
    }
    function ResultRow($values,$widths,$height,$fill,$lineHeight,$padding) {
        foreach($values as $i=>$value){$x=$this->GetX();$y=$this->GetY();$this->Rect($x,$y,$widths[$i],$height,$fill?'DF':'D');$this->SetXY($x,$y+$padding);$this->MultiCell($widths[$i],$lineHeight,$value,0,$i===1?'L':'C');$this->SetXY($x+$widths[$i],$y);}
        $this->Ln($height);
    }
    function Header() {
        if (file_exists('logo.png')) {
            $this->Image('logo.png', 16, 11, 20);
        }
        $this->SetXY(44, 12);
        $this->SetTextColor(15, 39, 66);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 7, normalizeText($this->university), 0, 1, 'C');
        $this->SetX(44);
        $this->SetFont('Arial', '', 10.5);
        $this->SetTextColor(80, 94, 108);
        $this->Cell(150, 6, normalizeText($this->faculty), 0, 1, 'C');
        $this->SetX(44);
        $this->SetFont('Arial', 'B', 12.5);
        $this->SetTextColor(50, 117, 236);
        $this->Cell(150, 7, 'SEMESTER RESULT SHEET', 0, 1, 'C');
        $this->SetDrawColor(50, 117, 236);
        $this->SetLineWidth(0.7);
        $this->Line(15, 35, 195, 35);
        $this->Ln(7);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(220, 226, 231);
        $this->SetLineWidth(0.25);
        $this->Line(15, 282, 195, 282);
        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(105, 116, 128);
        $this->Cell(90, 5, normalizeText('Official semester result sheet - '.$this->verificationCode), 0, 0, 'L');
        $this->Cell(90, 5, 'Page '.$this->PageNo(), 0, 0, 'R');
    }
}

$pdf = new SemesterResultPDF('P', 'mm', 'A4');
$pdf->university = $university;
$pdf->faculty = $faculty;
$pdf->verificationCode = $verificationCode;
$pdf->SetMargins(15, 12, 15);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// Semester banner
$pdf->SetFillColor(245, 248, 255);
$pdf->SetDrawColor(216, 225, 243);
$pdf->SetLineWidth(0.3);
$pdf->Rect(15, 40, 180, 14, 'DF');
$pdf->SetXY(19, 44);
$pdf->SetTextColor(15, 39, 66);
$pdf->SetFont('Arial', 'B', 11.5);
$pdf->Cell(82, 5, normalizeText('Academic Period: '.$academicStart.'/'.$academicEnd), 0, 0, 'L');
$pdf->Cell(45, 5, normalizeText('Year: '.$year), 0, 0, 'C');
$pdf->Cell(45, 5, normalizeText('Semester: '.$semester), 0, 1, 'R');

// Student information
$pdf->SetY(59);
$pdf->SetTextColor(15, 39, 66);
$pdf->SetFont('Arial', 'B', 10.5);
$pdf->Cell(0, 6, 'STUDENT INFORMATION', 0, 1, 'L');
$pdf->SetDrawColor(225, 231, 236);
$pdf->SetFillColor(250, 252, 253);
$pdf->Rect(15, 66, 180, 24, 'DF');

$pdf->SetXY(19,68);
$pdf->SetFont('Arial','B',9);$pdf->Cell(30,6,'Name');
$pdf->SetFont('Arial','',9.5);$pdf->Cell(142,6,normalizeText($student['name']),0,1);
$details=[['Student ID',$id,'Index No.',$student['index_number']],['Course',$section,'Section',$student['section']]];
foreach($details as $detail){$pdf->SetX(19);for($i=0;$i<4;$i++){$pdf->SetFont('Arial',$i%2?'':'B',9);$pdf->Cell($i%2?56:30,6,normalizeText($detail[$i]),0,$i===3?1:0);}}

// Results table
$pdf->SetY(94);
$pdf->SetFont('Arial', 'B', 10.5);
$pdf->SetTextColor(15, 39, 66);
$pdf->Cell(0, 7, 'SEMESTER RESULTS', 0, 1, 'L');

$widths = [10, 63, 32, 16, 19, 20, 20];
$headers = ['No.', 'Subject', 'Code', 'Credit', 'Marks', 'Grade', 'GPA'];
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(236, 243, 247);
$pdf->SetTextColor(52, 70, 88);
$pdf->SetDrawColor(205, 216, 224);
foreach ($headers as $i => $h) {
    $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// Measure every cell before drawing so every semester fits the reserved table area.
// Keep summary, verification and signature together below the table on the same A4 page.
$tableRows=[];
foreach ($rows as $i => $row) {
    $tableRows[]=[(string)($i+1),normalizeText($row['subject'].((int)($row['attempt_no']??1)>1?' [R'.(int)$row['attempt_no'].']':'')),normalizeText($row['subject_code']),(string)$row['credit'],number_format((float)$row['marks'],2),normalizeText($row['grade']).(result_needs_review($row)?' *':''),result_gpa_display($row)];
}
$availableHeight=196-$pdf->GetY();
$tableFont=8.5;
do {
    $pdf->SetFont('Arial','',$tableFont);
    $lineHeight=$tableFont*0.352778*1.25;
    $padding=$tableFont*0.10;
    $rowHeights=[];
    foreach($tableRows as $rowIndex=>$values) {
        $pdf->SetFont('Arial',result_needs_review($rows[$rowIndex])?'B':'',$tableFont);
        $lines=1;
        foreach($values as $j=>$value) $lines=max($lines,$pdf->LineCount($widths[$j],$value));
        $rowHeights[]=max($tableFont*0.80,$lines*$lineHeight+2*$padding);
    }
    if(array_sum($rowHeights)<=$availableHeight) break;
    $tableFont*=0.95;
} while(true);
$pdf->SetLineWidth(0.15);
foreach ($tableRows as $i => $values) {
    $fill=($i%2===1);
    $pdf->SetFillColor($fill?249:255,$fill?251:255,$fill?252:255);
    $pdf->SetTextColor(32,48,64);
    $pdf->SetFont('Arial',result_needs_review($rows[$i])?'B':'',$tableFont);
    $pdf->ResultRow($values,$widths,$rowHeights[$i],$fill,$lineHeight,$padding);
}

// Summary section
$pdf->Ln(4);
$y = $pdf->GetY();
$pdf->SetFillColor(245, 248, 255);
$pdf->SetDrawColor(216, 225, 243);
$pdf->Rect(15, $y, 180, 19, 'DF');
$cardWidths=[32,34,64,25,25];
$labels=['Result','Semester GPA','Class (indicative)','Total Credits','Subjects'];
$values=[$status,gpa_display($summary),$summary['class'],(string)$summary['credits'],(string)count($rows)];
$x=15;
for($i=0;$i<count($labels);$i++) {
    $cardW=$cardWidths[$i];
    if($i>0)$pdf->Line($x,$y+3,$x,$y+16);
    $pdf->SetXY($x,$y+2);$pdf->SetFont('Arial','B',7.2);$pdf->SetTextColor(92,108,119);
    $pdf->Cell($cardW,5,$labels[$i],0,1,'C');$pdf->SetX($x);
    $pdf->SetFont('Arial','B',$i===2?9:11);$pdf->SetTextColor(15,39,66);
    $pdf->Cell($cardW,8,normalizeText($values[$i]),0,0,'C');$x+=$cardW;
}
$verifyY=$y+23;
$pdf->SetXY(15,$verifyY);$pdf->SetFont('Arial','',7.5);$pdf->SetTextColor(79,92,105);
$pdf->Cell(150,4,'* Below passing GPA. R = repeat attempt; Non-GPA excluded from GPA.',0,1);
$pdf->SetX(15);$pdf->Cell(150,5,'Verification Code: '.$verificationCode,0,1);
$pdf->SetX(15);$pdf->Cell(150,4,'Generated: '.date('Y-m-d H:i'),0,1);
$pdf->SetX(15);$pdf->Cell(150,4,$qrMatrix?'Scan QR to verify this record.':'Verify this code through the academic portal.',0,1);
if($qrMatrix) {
    $qrSize=22;$modules=count($qrMatrix)+8;$cell=$qrSize/$modules;$qx=173;$qy=$verifyY;
    $pdf->SetFillColor(255,255,255);$pdf->Rect($qx,$qy,$qrSize,$qrSize,'F');$pdf->SetFillColor(0,0,0);
    foreach($qrMatrix as $ry=>$qrRow)foreach($qrRow as $rx=>$dark)if($dark)$pdf->Rect($qx+($rx+4)*$cell,$qy+($ry+4)*$cell,$cell+0.01,$cell+0.01,'F');
    $pdf->Link($qx,$qy,$qrSize,$qrSize,$verificationUrl);
}
$signY=$verifyY+25;
$pdf->SetXY(15,$signY);$pdf->SetFont('Arial','',8);$pdf->Cell(85,4,'Date: '.date('F j, Y'),0,1);
$pdf->SetDrawColor(180,190,202);$pdf->Rect(15,$signY+5,35,16);
$pdf->SetXY(15,$signY+15);$pdf->SetFont('Arial','',6.5);$pdf->Cell(35,4,'Official Stamp',0,0,'C');
$pdf->Line(120,$signY+10,190,$signY+10);
$pdf->SetXY(105,$signY+11);$pdf->SetFont('Arial','B',9);$pdf->Cell(90,4,normalizeText($registrar),0,1,'R');
$pdf->SetX(105);$pdf->SetFont('Arial','',8);$pdf->Cell(90,4,normalizeText($registrarTitle),0,1,'R');
$pdf->SetX(105);$pdf->Cell(90,4,'For Registrar',0,1,'R');

$filename = 'Semester_Result_'.$student['index_number'].'_Y'.$year.'_S'.$semester.'.pdf';
$pdf->Output('I', $filename);
exit;
