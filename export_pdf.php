<?php
require_once 'cashloan_db.php';
require('E:\xampp\htdocs\A1-Financial Solutions/fpdf.php');

$loanIds = json_decode($_POST['loan_ids'] ?? '[]', true);

if (empty($loanIds)) {
    die("No loan IDs selected.");
}

$placeholders = implode(',', array_fill(0, count($loanIds), '?'));
$sql = "SELECT l.*, c.first_name, c.last_name, c.customer_id, pr.amount_received, pr.amount_due, pr.received_date
        FROM loans l 
        JOIN customers c ON l.customer_id = c.customer_id 
        LEFT JOIN payments_received pr ON l.loan_id = pr.loan_id
        WHERE l.loan_id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$stmt->execute($loanIds);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize totals
$totalLoaned = 0;
$totalFees = 0;
$totalCapital = 0;
$totalAmount_Received = 0;
$totalBalance = 0;

// Use landscape orientation
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Loan Report', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 10);

// Define column widths
$widths = [
    'customer_id' => 30,
    'name'        => 50,
    'loaned'      => 30,
    'fees'        => 30,
    'capital'     => 30,
    'amount_received'    => 40,
    'balance'     => 30,
    'status'     => 30
];

// Header
$pdf->Cell($widths['customer_id'],     10, 'Customer ID',     1);
$pdf->Cell($widths['name'],            10, 'Name',            1);
$pdf->Cell($widths['loaned'],          10, 'Loaned',          1);
$pdf->Cell($widths['fees'],            10, 'Fees',            1);
$pdf->Cell($widths['capital'],         10, 'Capital',         1);
$pdf->Cell($widths['amount_received'], 10, 'Amount_Received', 1);
$pdf->Cell($widths['balance'],         10, 'Balance',         1);
$pdf->Cell($widths['status'],          10, 'Status',          1);
$pdf->Ln();

// Data rows
$pdf->SetFont('Arial', '', 10);
foreach ($loans as $loan) {
    $pdf->Cell($widths['customer_id'],    10, $loan['customer_id'], 1);
    $pdf->Cell($widths['name'],           10, $loan['first_name'] . ' ' . $loan['last_name'], 1);
    $pdf->Cell($widths['loaned'],         10, number_format($loan['loan_amount'], 2), 1);
    $pdf->Cell($widths['fees'],           10, number_format($loan['fees'], 2), 1);
    $pdf->Cell($widths['capital'],        10, number_format($loan['capital'], 2), 1);
    $pdf->Cell($widths['amount_received'],10, number_format($loan['amount_received'], 2), 1);
    $pdf->Cell($widths['balance'],        10, number_format($loan['amount_due'], 2), 1);
    $pdf->Cell($widths['status'],         10, ucfirst($loan['status']), 1);

    $pdf->Ln();

    // Totals
    $totalLoaned          += $loan['loan_amount'];
    $totalFees            += $loan['fees'];
    $totalCapital         += $loan['capital'];
    $totalAmount_Received += $loan['amount_received'];
    $totalBalance         += $loan['amount_due'];
}

// Totals Row
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($widths['customer_id'] + $widths['name'], 10, 'TOTALS', 1);
$pdf->Cell($widths['loaned'],   10, number_format($totalLoaned, 2), 1);
$pdf->Cell($widths['fees'],     10, number_format($totalFees, 2), 1);
$pdf->Cell($widths['capital'],  10, number_format($totalCapital, 2), 1);
$pdf->Cell($widths['amount_received'], 10, number_format($totalAmount_Received, 2), 1);
$pdf->Cell($widths['balance'],  10, number_format($totalBalance, 2), 1);



$pdf->Output();
?>
