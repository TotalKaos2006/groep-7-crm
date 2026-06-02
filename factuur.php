<?php
// factuur.php — Genereert een factuur-PDF voor een project
// Geen Composer nodig — werkt met een handmatig gedownloade TCPDF map.
//
// Installatie:
//   1. Download https://github.com/tecnickcom/tcpdf/archive/refs/heads/main.zip
//   2. Pak uit en hernoem de map naar "tcpdf"
//   3. Zet de map in dezelfde map als dit bestand (naast factuur.php)
//
// Gebruik: factuur.php?project_id=1

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/auth_check.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// ── TCPDF laden ───────────────────────────────────────────────────────────
$tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_path)) {
    die('
        <style>body{font-family:sans-serif;padding:30px;background:#f5f5f5;}
        .box{background:#fff;border-left:4px solid #e74c3c;padding:20px;border-radius:6px;max-width:600px;}
        code{background:#eee;padding:2px 6px;border-radius:3px;font-size:13px;}
        </style>
        <div class="box">
        <h2>⚠️ TCPDF niet gevonden</h2>
        <p>Volg deze stappen:</p>
        <ol>
            <li>Download: <a href="https://github.com/tecnickcom/tcpdf/archive/refs/heads/main.zip" target="_blank">tcpdf-main.zip</a></li>
            <li>Pak het ZIP-bestand uit</li>
            <li>Hernoem de map van <code>tcpdf-main</code> naar <code>tcpdf</code></li>
            <li>Zet de map hier: <code>' . htmlspecialchars(__DIR__) . '/tcpdf/</code></li>
            <li>Ververs deze pagina</li>
        </ol>
        </div>
    ');
}
require_once $tcpdf_path;

// ── Project ID ophalen ────────────────────────────────────────────────────
$project_id = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
if (!$project_id) {
    die('Geen geldig project_id opgegeven.');
}

// ── 1. Projectgegevens + klantgegevens ophalen ────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        p.project_id,
        p.projectnaam,
        p.status,
        p.omschrijving,
        p.uurloon,
        k.naam            AS klant_voornaam,
        k.achternaam      AS klant_achternaam,
        k.email           AS klant_email,
        k.telefoonnummer  AS klant_telefoon
    FROM projecten p
    JOIN klanten k ON p.klant_id = k.klant_id
    WHERE p.project_id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die('Project niet gevonden.');
}

// ── 2. Gewerkte uren ophalen ──────────────────────────────────────────────
$stmt2 = $pdo->prepare("
    SELECT
        g.uren,
        g.omschrijving,
        CONCAT(m.naam, ' ', m.achternaam) AS medewerker_naam
    FROM gewerkte_uren g
    JOIN medewerkers m ON g.medewerker_id = m.medewerker_id
    WHERE g.project_id = ?
    ORDER BY m.naam, g.uren_id
");
$stmt2->execute([$project_id]);
$uren_regels = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ── 3. Totalen berekenen ──────────────────────────────────────────────────
$totaal_uren    = array_sum(array_column($uren_regels, 'uren'));
$uurloon        = (float) $project['uurloon'];
$subtotaal      = $totaal_uren * $uurloon;
$btw_percentage = 21;
$btw_bedrag     = $subtotaal * ($btw_percentage / 100);
$totaal_incl    = $subtotaal + $btw_bedrag;

$factuurnummer  = 'F-' . str_pad($project_id, 5, '0', STR_PAD_LEFT);
$factuurdatum   = date('d-m-Y');

// ── 4. PDF aanmaken ───────────────────────────────────────────────────────
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Klokker');
$pdf->SetAuthor('Klokker');
$pdf->SetTitle('Factuur ' . $factuurnummer);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(20, 20, 20);
$pdf->AddPage();

// Kleuren
$blauw      = [26, 26, 46];
$lichtgrijs = [245, 245, 250];
$grijs      = [100, 100, 120];

// ── HEADER ────────────────────────────────────────────────────────────────
$pdf->SetFillColor(...$blauw);
$pdf->Rect(0, 0, 210, 35, 'F');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 22);
$pdf->SetXY(20, 8);
$pdf->Cell(100, 10, 'KLOKKER', 0, 0, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(130, 8);
$pdf->Cell(60, 6, 'Factuur', 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetXY(130, 14);
$pdf->Cell(60, 6, $factuurnummer, 0, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(130, 21);
$pdf->Cell(60, 5, 'Datum: ' . $factuurdatum, 0, 1, 'R');

// ── KLANT GEGEVENS ────────────────────────────────────────────────────────
$pdf->SetTextColor(...$grijs);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY(20, 45);
$pdf->Cell(80, 5, 'FACTUUR VOOR', 0, 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetX(20);
$pdf->Cell(80, 6, $project['klant_voornaam'] . ' ' . $project['klant_achternaam'], 0, 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetX(20);
$pdf->Cell(80, 5, $project['klant_email'], 0, 1);
$pdf->SetX(20);
$pdf->Cell(80, 5, $project['klant_telefoon'], 0, 1);

// ── PROJECT INFO (rechts) ─────────────────────────────────────────────────
$pdf->SetFillColor(...$lichtgrijs);
$pdf->RoundedRect(120, 43, 70, 32, 3, '1111', 'F');

$pdf->SetTextColor(...$grijs);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY(125, 46);
$pdf->Cell(60, 5, 'PROJECT', 0, 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY(125, 51);
$pdf->Cell(60, 6, $project['projectnaam'], 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(125, 58);
$pdf->SetTextColor(...$grijs);
$pdf->Cell(25, 5, 'Status:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(35, 5, ucfirst($project['status']), 0, 1);

$pdf->SetXY(125, 63);
$pdf->SetTextColor(...$grijs);
$pdf->Cell(25, 5, 'Uurloon:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(35, 5, '€ ' . number_format($uurloon, 2, ',', '.'), 0, 1);

$pdf->SetXY(125, 68);
$pdf->SetTextColor(...$grijs);
$pdf->Cell(25, 5, 'Totaal uren:', 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(35, 5, number_format($totaal_uren, 2, ',', '.') . ' u', 0, 1);

// ── OMSCHRIJVING ──────────────────────────────────────────────────────────
if (!empty($project['omschrijving'])) {
    $pdf->SetXY(20, 82);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(...$grijs);
    $pdf->MultiCell(95, 5, $project['omschrijving'], 0, 'L');
}

// ── TABEL HEADER ──────────────────────────────────────────────────────────
$pdf->SetY(96);
$pdf->SetFillColor(...$blauw);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Omschrijving', 1, 0, 'L', true);
$pdf->Cell(40, 8, 'Medewerker',   1, 0, 'L', true);
$pdf->Cell(20, 8, 'Uren',         1, 0, 'C', true);
$pdf->Cell(20, 8, 'Tarief',       1, 0, 'C', true);
$pdf->Cell(20, 8, 'Bedrag',       1, 1, 'R', true);

// ── TABEL RIJEN ───────────────────────────────────────────────────────────
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

foreach ($uren_regels as $i => $regel) {
    $bedrag = (float) $regel['uren'] * $uurloon;
    $fill   = ($i % 2 === 0);
    if ($fill) $pdf->SetFillColor(...$lichtgrijs);

    $pdf->SetX(20);
    $pdf->Cell(70, 7, mb_substr($regel['omschrijving'] ?? '-', 0, 45), 'LR', 0, 'L', $fill);
    $pdf->Cell(40, 7, $regel['medewerker_naam'],                         'LR', 0, 'L', $fill);
    $pdf->Cell(20, 7, number_format((float)$regel['uren'], 2, ',', '.'), 'LR', 0, 'C', $fill);
    $pdf->Cell(20, 7, '€ ' . number_format($uurloon, 2, ',', '.'),       'LR', 0, 'C', $fill);
    $pdf->Cell(20, 7, '€ ' . number_format($bedrag,  2, ',', '.'),       'LR', 1, 'R', $fill);
}

// Afsluitende lijn onder tabel
$pdf->SetX(20);
$pdf->Cell(170, 0, '', 'T', 1);
$pdf->Ln(4);

// ── TOTALEN ───────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 10);
$pdf->SetX(20);
$pdf->Cell(130, 7, '');
$pdf->Cell(20, 7, 'Subtotaal:');
$pdf->Cell(20, 7, '€ ' . number_format($subtotaal, 2, ',', '.'), 0, 1, 'R');

$pdf->SetX(20);
$pdf->Cell(130, 7, '');
$pdf->Cell(20, 7, 'BTW ' . $btw_percentage . '%:');
$pdf->Cell(20, 7, '€ ' . number_format($btw_bedrag, 2, ',', '.'), 0, 1, 'R');

// Scheidingslijn
$pdf->SetX(130);
$pdf->Cell(60, 0, '', 'T', 1);
$pdf->Ln(1);

// Totaal blok
$pdf->SetFillColor(...$blauw);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetX(20);
$pdf->Cell(130, 10, '');
$pdf->Cell(40, 10, 'TOTAAL INCL. BTW', 0, 0, 'L', true);
$pdf->Cell(20, 10, '€ ' . number_format($totaal_incl, 2, ',', '.'), 0, 1, 'R', true);

// ── BETALINGSINFO ─────────────────────────────────────────────────────────
$pdf->Ln(8);
$pdf->SetTextColor(...$grijs);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetX(20);
$pdf->Cell(170, 5, 'BETALINGSINFORMATIE', 0, 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(20);
$pdf->MultiCell(170, 5,
    "Gelieve het bovenstaande bedrag binnen 30 dagen over te maken onder vermelding van factuurnummer {$factuurnummer}.\n" .
    "IBAN: NL24RABO0330887866  |  KvK: 14086144  |  BTW: NL323351785B11",
    0, 'L'
);

// ── FOOTER ────────────────────────────────────────────────────────────────
$pdf->SetY(-18);
$pdf->SetFillColor(...$blauw);
$pdf->Rect(0, $pdf->GetY(), 210, 20, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetX(20);
$pdf->Cell(170, 10, 'Klokker  |  info@klokker.nl  |  www.klokker.nl', 0, 0, 'C');

// ── PDF uitvoeren ─────────────────────────────────────────────────────────
$bestandsnaam = 'Factuur_' . $factuurnummer . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['projectnaam']) . '.pdf';
$pdf->Output($bestandsnaam, 'D'); // 'D' = direct downloaden