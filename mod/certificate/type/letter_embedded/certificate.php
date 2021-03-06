<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from view.php
}

$pdf = new PDF($certificate->orientation, 'pt', 'Letter', true, 'UTF-8', false);

$pdf->SetTitle($certificate->name);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false, 0);

//Define variables
//Landscape
if ($certificate->orientation == 'L') {
    $x = 28;
    $y = 125;
    $sealx = 590;
    $sealy = 425;
    $sigx = 130;
    $sigy = 440;
    $custx = 130;
    $custy = 440;
    $wmarkx = 190;
    $wmarky = 90;
    $wmarkw = 420;
    $wmarkh = 420;
    $brdrx = 0;
    $brdry = 0;
    $brdrw = 792;
    $brdrh = 612;
    $codey = 505;
} else {
//Portrait
    $x = 28;
    $y = 170;
    $sealx = 440;
    $sealy = 590;
    $sigx = 105;
    $sigy = 580;
    $custx = 105;
    $custy = 580;
    $wmarkx = 78;
    $wmarky = 130;
    $wmarkw = 420;
    $wmarkh = 420;
    $brdrx = 10;
    $brdry = 10;
    $brdrw = 594;
    $brdrh = 771;
    $codey = 660;
}

// Add images and lines
certificate_print_image($pdf, $certificate, CERT_IMAGE_BORDER, $brdrx, $brdry, $brdrw, $brdrh);
certificate_draw_frame_letter($pdf, $certificate);
// Set alpha to semi-transparency
$pdf->SetAlpha(0.1);
certificate_print_image($pdf, $certificate, CERT_IMAGE_WATERMARK, $wmarkx, $wmarky, $wmarkw, $wmarkh);
$pdf->SetAlpha(1);
certificate_print_image($pdf, $certificate, CERT_IMAGE_SEAL, $sealx, $sealy, '', '');
certificate_print_image($pdf, $certificate, CERT_IMAGE_SIGNATURE, $sigx, $sigy, '', '');

// Add text
if ($certificate->orientation == 'L') {
$pdf->SetTextColor(70, 29, 124);
certificate_print_text($pdf, $x, $y, 'C', 'freesans', '', 35, get_string('title', 'certificate'));
$pdf->SetTextColor(0, 0, 0);
certificate_print_text($pdf, $x, $y + 55, 'C', 'freeserif', '', 20, get_string('certify', 'certificate'));
certificate_print_text($pdf, $x, $y + 105, 'C', 'freeserif', '', 30, fullname($USER));
certificate_print_text($pdf, $x, $y + 155, 'C', 'freeserif', '', 20, get_string('statement', 'certificate'));
certificate_print_text($pdf, $x, $y + 205, 'C', 'freeserif', '', 20, $course->fullname);
certificate_print_text($pdf, $x, $y + 255, 'C', 'freeserif', '', 14, certificate_get_date($certificate, $certrecord, $course));
certificate_print_text($pdf, $x, $y + 283, 'C', 'freeserif', '', 10, certificate_get_grade($certificate, $course));
certificate_print_text($pdf, $x, $y + 311, 'C', 'freeserif', '', 10, certificate_get_outcome($certificate, $course));
certificate_print_text($pdf, $x, $y + 339, 'C', 'freeserif', '', 10, certificate_get_credit_hours($certificate));
certificate_print_text($pdf, $x, $codey, 'C', 'freeserif', '', 10, certificate_get_code($certificate, $certrecord));
} else {
$pdf->SetTextColor(70, 29, 124);
certificate_print_text($pdf, $x, $y, 'C', 'freesans', '', 25, get_string('title', 'certificate'));
$pdf->SetTextColor(0, 0, 0);
certificate_print_text($pdf, $x, $y + 55, 'C', 'freeserif', '', 20, get_string('certify', 'certificate'));
certificate_print_text($pdf, $x, $y + 105, 'C', 'freeserif', '', 30, fullname($USER));
certificate_print_text($pdf, $x, $y + 155, 'C', 'freeserif', '', 20, get_string('statement', 'certificate'));
certificate_print_text($pdf, $x, $y + 205, 'C', 'freeserif', '', 20, $course->fullname);
certificate_print_text($pdf, $x, $y + 255, 'C', 'freeserif', '', 14, certificate_get_date($certificate, $certrecord, $course));
certificate_print_text($pdf, $x, $y + 283, 'C', 'freeserif', '', 10, certificate_get_grade($certificate, $course));
certificate_print_text($pdf, $x, $y + 311, 'C', 'freeserif', '', 10, certificate_get_outcome($certificate, $course));
certificate_print_text($pdf, $x, $y + 339, 'C', 'freeserif', '', 10, certificate_get_credit_hours($certificate));
certificate_print_text($pdf, $x, $codey, 'C', 'freeserif', '', 10, certificate_get_code($certificate, $certrecord));
}
$i = 0;
if ($certificate->printteacher) {
    $context = context_module::instance($cm->id);
    if ($teachers = get_users_by_capability($context, 'mod/certificate:printteacher', '', $sort = 'u.lastname ASC', '', '', '', '', false)) {
        foreach ($teachers as $teacher) {
            $i++;
            certificate_print_text($pdf, $sigx, $sigy + ($i * 12), 'L', 'freeserif', '', 12, fullname($teacher));
        }
    }
}

certificate_print_text($pdf, $custx, $custy, 'L', 'Helvetica', '', '12', $certificate->customtext);
?>
