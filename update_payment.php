<?php
session_start();
include 'cashloan_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$required = ['payment_id', 'loan_id', 'amount_received', 'amount_due', 'status'];
foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$payment_id = (int)$_POST['payment_id'];
$loan_id = (int)$_POST['loan_id'];
$amount_received = (float)$_POST['amount_received'];
$amount_due = (float)$_POST['amount_due'];
$status = $_POST['status'];
$recorded_by = $_SESSION['user_id'] ?? null;

try {
    // Update payments_received table
    $stmt = $conn->prepare("UPDATE payments_received 
                           SET amount_received = :amount_received,
                               amount_due = :amount_due,
                               status = :status,
                               recorded_by = :recorded_by
                           WHERE id = :payment_id");
    
    $stmt->bindParam(':amount_received', $amount_received);
    $stmt->bindParam(':amount_due', $amount_due);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
    $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Update loan status if needed
        if ($status === 'completed' || $status === 'full_paid') {
            $loanStmt = $conn->prepare("UPDATE loans SET status = 'paid' WHERE loan_id = :loan_id");
            $loanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $loanStmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update payment']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>