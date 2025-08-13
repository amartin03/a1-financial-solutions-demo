<?php
require_once 'cashloan_db.php';

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

// Set headers for CSV output
$filename = 'Loan_Report_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Customer ID', 'Name', 'Loaned', 'Fees', 'Capital', 'Amount Received', 'Balance', 'Status']);

// Totals
$totalLoaned = 0;
$totalFees = 0;
$totalCapital = 0;
$totalAmount_Received = 0;
$totalBalance = 0;

foreach ($loans as $loan) {
    $name = $loan['first_name'] . ' ' . $loan['last_name'];
    $loaned = (float) $loan['loan_amount'];
    $fees = (float) $loan['fees'];
    $capital = (float) $loan['capital'];
    $amount_received = (float) $loan['amount_received'];
    $balance = (float) $loan['amount_due'];
    $status = ucfirst($loan['status']);

    $totalLoaned += $loaned;
    $totalFees += $fees;
    $totalCapital += $capital;
    $totalAmount_Received += $amount_received;
    $totalBalance += $balance;

    fputcsv($output, [
        $loan['customer_id'],
        $name,
        number_format($loaned, 2),
        number_format($fees, 2),
        number_format($capital, 2),
        number_format($amount_received, 2),
        number_format($balance, 2),
        $status
    ]);
}

// Add totals row
fputcsv($output, [
    'TOTALS',
    '',
    number_format($totalLoaned, 2),
    number_format($totalFees, 2),
    number_format($totalCapital, 2),
    number_format($totalAmount_Received, 2),
    number_format($totalBalance, 2),
    '' // Empty status column
]);

fclose($output);
exit;
?>
