<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'cashloan_db.php';

// Check if user is authorized to export data
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=payments_received_' . date('Y-m-d') . '.csv');

// Create output file pointer
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding
fwrite($output, "\xEF\xBB\xBF");

// Write CSV headers - using "ID" as second column to avoid SYLK issue
fputcsv($output, [
    'Record',      // First column cannot be "ID"
    'Payment ID',
    'Loan ID',
    'Customer Name',
    'Amount Received',
    'Amount Due',
    'Date Received',
    'Status',
    'Recorded By'
]);

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

try {
    // Verify database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Base query
    $sql = "SELECT 
            pr.id, pr.payment_id, pr.loan_id, 
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            pr.amount_received, pr.amount_due, 
            pr.received_date, pr.status,
            u.username AS recorded_by
            FROM payments_received pr
            JOIN loans l ON pr.loan_id = l.loan_id
            JOIN customers c ON l.customer_id = c.customer_id
            LEFT JOIN users u ON pr.recorded_by = u.user_id
            WHERE (c.first_name LIKE :search OR c.last_name LIKE :search)";

    $params = [
        'search' => "%$search%",
        'search2' => "%$search%"
    ];

    if (!empty($status_filter)) {
        $sql .= " AND pr.status = :status_filter";
        $params['status_filter'] = $status_filter;
    }

    $sql .= " ORDER BY pr.received_date DESC";

    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed");
    }
    
    // Write data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],          // Now as second column
            $row['payment_id'],
            $row['loan_id'],
            $row['customer_name'],
            number_format($row['amount_received'], 2),
            number_format($row['amount_due'], 2),
            date('Y-m-d H:i', strtotime($row['received_date'])),
            ucfirst(str_replace('_', ' ', $row['status'])),
            $row['recorded_by'] ?? 'System'
        ]);
    }
    
} catch (Exception $e) {
    // Reset output buffer
    ob_clean();
    
    // Write error message
    fputcsv($output, ['Error Message', $e->getMessage()]);
    
    // Log error
    error_log("Export Error: " . $e->getMessage());
}

fclose($output);
exit;
?>