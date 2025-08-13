<?php
session_start();
include 'cashloan_db.php'; // Ensure this file returns a PDO connection as $pdo

// Check if user is authorized to export data
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get filters from query parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$method_filter = isset($_GET['method_filter']) ? $_GET['method_filter'] : '';

// Base query for export (get all records matching filters)
$sql = "SELECT 
        p.payment_id, p.amount, p.payment_date, p.payment_method, 
        p.transaction_reference, p.status, p.notes,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
        l.loan_id,
        u.username AS recorded_by_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.customer_id
        JOIN loans l ON p.loan_id = l.loan_id
        LEFT JOIN users u ON p.recorded_by = u.user_id
        WHERE (c.first_name LIKE :search OR c.last_name LIKE :search OR p.transaction_reference LIKE :search)";

// Add filters if specified
$params = [
    'search' => "%$search%",
    'search2' => "%$search%",
    'search3' => "%$search%"
];

if (!empty($status_filter)) {
    $sql .= " AND p.status = :status_filter";
    $params['status_filter'] = $status_filter;
}

if (!empty($method_filter)) {
    $sql .= " AND p.payment_method = :method_filter";
    $params['method_filter'] = $method_filter;
}

// Always sort by most recent payments first
$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

try {
    $stmt = $conn->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $key => &$value) {
        $stmt->bindParam(':' . $key, $value);
    }
    
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=payments_export_' . date('Y-m-d') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, [
        'Payment ID',
        'Customer Name',
        'Loan ID',
        'Amount',
        'Payment Date',
        'Payment Method',
        'Transaction Reference',
        'Status',
        'Recorded By',
        'Notes'
    ]);

    // Add data rows
    foreach ($payments as $payment) {
        fputcsv($output, [
            $payment['payment_id'],
            $payment['customer_name'],
            $payment['loan_id'],
            number_format($payment['amount'], 2),
            date('Y-m-d H:i', strtotime($payment['payment_date'])),
            ucfirst(str_replace('_', ' ', $payment['payment_method'])),
            $payment['transaction_reference'],
            ucfirst($payment['status']),
            $payment['recorded_by_name'] ?? 'System',
            $payment['notes']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: payments.php");
    exit;
}
?>