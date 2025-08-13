<?php
session_start();
include 'cashloan_db.php';

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;

if (!$payment_id || !$loan_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Payment ID and Loan ID are required']);
    exit;
}

try {
    // Fetch loan details to calculate fees and capital
    $loanStmt = $conn->prepare("
        SELECT l.loan_id, l.loan_amount, l.interest_rate,
               c.first_name, c.last_name
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.loan_id = :loan_id
    ");
    $loanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $loanStmt->execute();
    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        http_response_code(404);
        echo json_encode(['error' => 'Loan not found']);
        exit;
    }

    // Calculate fees (30% of loan amount) and capital (loan amount + fees)
    $loanAmount = (float)$loan['loan_amount'];
    $fees = $loanAmount * 0.3;
    $capital = $loanAmount + $fees;

    // Fetch payment details from payments table
    $paymentStmt = $conn->prepare("
        SELECT p.amount, p.status, p.amount_due
        FROM payments p
        WHERE p.payment_id = :payment_id AND p.loan_id = :loan_id
    ");
    $paymentStmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $paymentStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $paymentStmt->execute();
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    // Check if we're handling an update request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
        $amount_received = (float)$_POST['amount_received'];
        $status = $_POST['status'];
        $recorded_by = $_SESSION['user_id'] ?? null;
        
        // Check if payment received record exists
        $checkStmt = $conn->prepare("SELECT id FROM payments_received WHERE payment_id = :payment_id");
        $checkStmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // Update existing record
            $updateStmt = $conn->prepare("
                UPDATE payments_received 
                SET amount_received = :amount_received,
                    status = :status,
                    recorded_by = :recorded_by,
                    updated_at = NOW()
                WHERE payment_id = :payment_id
            ");
        } else {
            // Insert new record
            $updateStmt = $conn->prepare("
                INSERT INTO payments_received 
                (payment_id, loan_id, amount_received, amount_due, status, recorded_by, created_at)
                VALUES 
                (:payment_id, :loan_id, :amount_received, :amount_due, :status, :recorded_by, NOW())
            ");
            $updateStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $updateStmt->bindParam(':amount_due', $capital, PDO::PARAM_STR);
        }
        
        $updateStmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $updateStmt->bindParam(':amount_received', $amount_received);
        $updateStmt->bindParam(':status', $status);
        $updateStmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            // Update loan status if payment is completed or fully paid
            if ($status === 'completed' || $status === 'full_paid') {
                $loanStatus = 'paid';
            } elseif ($status === 'default') {
                $loanStatus = 'defaulted';
            } else {
                $loanStatus = 'active';
            }
            
            $loanUpdateStmt = $conn->prepare("UPDATE loans SET status = :status WHERE loan_id = :loan_id");
            $loanUpdateStmt->bindParam(':status', $loanStatus);
            $loanUpdateStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $loanUpdateStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Payment received updated successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update payment received record']);
            exit;
        }
    }

    // Fetch payment received details if exists
    $paymentReceivedStmt = $conn->prepare("
        SELECT amount_received, status 
        FROM payments_received 
        WHERE payment_id = :payment_id
    ");
    $paymentReceivedStmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $paymentReceivedStmt->execute();
    $paymentReceived = $paymentReceivedStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate total paid for this loan
    $totalPaidStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount_received), 0) AS total_paid 
        FROM payments_received 
        WHERE loan_id = :loan_id AND status IN ('completed', 'full_paid')
    ");
    $totalPaidStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $totalPaidStmt->execute();
    $totalPaid = $totalPaidStmt->fetch(PDO::FETCH_ASSOC)['total_paid'];

    // Calculate amount due (capital - total paid)
    $amountDue = max(0, $capital - $totalPaid);

    // Determine the status with better fallback logic
    $status = 'active'; // Default status
    if (!empty($paymentReceived['status'])) {
        $status = $paymentReceived['status'];
    } elseif (!empty($payment['status'])) {
        $status = $payment['status'];
    }

    $response = [
        'success' => true,
        'data' => [
            'loan_id' => $loan_id,
            'payment_id' => $payment_id,
            'customer_name' => $loan['first_name'] . ' ' . $loan['last_name'],
            'loan_amount' => $loanAmount,
            'fees' => $fees,
            'capital' => $capital,
            'payment_amount' => $payment['amount'],
            'amount_received' => $paymentReceived['amount_received'] ?? 0,
            'status' => $status,
            'amount_due' => $amountDue,
            'total_paid' => $totalPaid,
            'interest_rate' => $loan['interest_rate']
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}