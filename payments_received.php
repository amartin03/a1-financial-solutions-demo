
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

include 'cashloan_db.php';
include 'header.php';

// Handle delete action
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = (int)$_GET['delete_id'];
        $stmt = $conn->prepare("DELETE FROM payments_received WHERE id = :id");
        $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Payment record deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete payment record.";
        }
        
        header("Location: payments_received.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: payments_received.php");
        exit;
    }
}

// Handle incoming payment data from payments.php
if (isset($_GET['source_payment_id']) && isset($_GET['loan_id'])) {
    try {
        // Get data from payments table
        $stmt = $conn->prepare("SELECT p.*, l.loan_amount, c.first_name, c.last_name 
                               FROM payments p
                               JOIN loans l ON p.loan_id = l.loan_id
                               JOIN customers c ON l.customer_id = c.customer_id
                               WHERE p.payment_id = :payment_id");
        $stmt->bindParam(':payment_id', $_GET['source_payment_id'], PDO::PARAM_INT);
        $stmt->execute();
        $source_payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($source_payment) {
            // Calculate amount due (capital is loan_amount + fees)
            $fees = $source_payment['loan_amount'] * 0.3;
            $capital = $source_payment['loan_amount'] + $fees;
            
            // Get total paid so far for this loan from payments_received
            $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount_received), 0) AS total_paid 
                                       FROM payments_received 
                                       WHERE loan_id = :loan_id");
            $paidStmt->bindParam(':loan_id', $source_payment['loan_id'], PDO::PARAM_INT);
            $paidStmt->execute();
            $paid = $paidStmt->fetch(PDO::FETCH_ASSOC);
            $total_paid = $paid['total_paid'];
            
            // Calculate amount due
            $amount_due = $capital - $total_paid;

            // Prepare data for the form
            $payment_to_edit = [
                'payment_id' => $source_payment['payment_id'],
                'loan_id' => $source_payment['loan_id'],
                'loan_amount' => $source_payment['loan_amount'],
                'amount_received' => $source_payment['amount'],
                'amount_due' => $amount_due,
                'fees' => $fees,
                'capital' => $capital,
                'customer_name' => $source_payment['first_name'] . ' ' . $source_payment['last_name'],
                'status' => $amount_due <= 0 ? 'completed' : 'partial'
            ];
            
            // Store in session to persist through redirect
            $_SESSION['payment_to_edit'] = $payment_to_edit;
            
            // Redirect to clear URL parameters
            header("Location: payments_received.php");
            exit;
        } else {
            $_SESSION['error'] = "Payment record not found";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

// Get pre-populated data from session if available
$payment_to_edit = $_SESSION['payment_to_edit'] ?? null;
unset($_SESSION['payment_to_edit']); // Clear after use

// Handle payment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    try {
        $payment_id = (int)$_POST['payment_id'];
        $loan_id = (int)$_POST['loan_id'];
        $amount_received = (float)$_POST['amount_received'];
        $amount_due = (float)$_POST['amount_due'];
        $status = $_POST['status'];
        $recorded_by = $_SESSION['user_id'] ?? null;

        // Convert 'full_paid' status to 'completed'
        if ($status === 'full_paid') {
            $status = 'completed';
        }

        // Insert into payments_received table
        $stmt = $conn->prepare("INSERT INTO payments_received 
                               (payment_id, loan_id, amount_received, amount_due, status, recorded_by, received_date)
                               VALUES 
                               (:payment_id, :loan_id, :amount_received, :amount_due, :status, :recorded_by, NOW())");
        
        $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $stmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->bindParam(':amount_received', $amount_received);
        $stmt->bindParam(':amount_due', $amount_due);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Update loan status if fully paid
            if ($status === 'completed') {
                // Update loans table
                $updateLoanStmt = $conn->prepare("UPDATE loans SET status = 'completed' WHERE loan_id = :loan_id");
                $updateLoanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
                $updateLoanStmt->execute();
                
                // Update payments table
                $updatePaymentStmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE loan_id = :loan_id");
                $updatePaymentStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
                $updatePaymentStmt->execute();
            } elseif ($status === 'partial') {
                // Update loans table for partial payment
                $updateLoanStmt = $conn->prepare("UPDATE loans SET status = 'active' WHERE loan_id = :loan_id");
                $updateLoanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
                $updateLoanStmt->execute();
                
                // Update payments table for partial payment
                $updatePaymentStmt = $conn->prepare("UPDATE payments SET status = 'partial' WHERE payment_id = :payment_id");
                $updatePaymentStmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
                $updatePaymentStmt->execute();
            }
            
            $_SESSION['success'] = "Payment received recorded successfully!";
        } else {
            $_SESSION['error'] = "Failed to record payment received.";
        }
        
        header("Location: payments_received.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: payments_received.php");
        exit;
    }
}

// Handle search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Base query for payments_received list
$sql = "SELECT 
        pr.id, pr.payment_id, pr.loan_id, pr.amount_received, pr.amount_due,
        pr.received_date, pr.status,
        c.first_name, c.last_name,
        l.loan_amount, l.interest_rate
        FROM payments_received pr
        JOIN loans l ON pr.loan_id = l.loan_id
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE (c.first_name LIKE :search OR c.last_name LIKE :search OR pr.payment_id = :exact_payment_id)";

// Add filters if specified
$params = [
    'search' => "%$search%",
    'exact_payment_id' => is_numeric($search) ? $search : 0
];

if (!empty($status_filter)) {
    $sql .= " AND pr.status = :status_filter";
    $params['status_filter'] = $status_filter;
}

// Add ORDER BY and LIMIT
$sql .= " ORDER BY pr.received_date DESC, pr.id DESC LIMIT :limit OFFSET :offset";
$params['limit'] = $limit;
$params['offset'] = $offset;

try {
    $stmt = $conn->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $key => &$value) {
        if ($key === 'limit' || $key === 'offset') {
            $stmt->bindParam(':' . $key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindParam(':' . $key, $value);
        }
    }
    
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total records
    $countSql = "SELECT COUNT(*) AS total FROM payments_received pr
                 JOIN loans l ON pr.loan_id = l.loan_id
                 JOIN customers c ON l.customer_id = c.customer_id
                 WHERE (c.first_name LIKE :search OR c.last_name LIKE :search OR pr.payment_id = :exact_payment_id)";

    $countParams = [
        'search' => "%$search%",
        'exact_payment_id' => is_numeric($search) ? $search : 0
    ];

    if (!empty($status_filter)) {
        $countSql .= " AND pr.status = :status_filter";
        $countParams['status_filter'] = $status_filter;
    }

    $countStmt = $conn->prepare($countSql);
    
    foreach ($countParams as $key => &$value) {
        $countStmt->bindParam(':' . $key, $value);
    }
    
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $payments = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments Received</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 170vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .page-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 0px;
        }
        .page-title {
            margin: 0;
        }
        
        .button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        
        .button.blue {
            background-color: #3498db;
            color: white;
        }
        
        .button.orange {
            background-color: #f39c12;
            color: white;
        }
        
        .button.red {
            background-color: #e74c3c;
            color: white;
        }
        
        .button.gray {
            background-color: #95a5a6;
            color: white;
        }
        
        .button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .search-filters > div {
            flex: 1;
            min-width: 200px;
        }
        
        .search-filters label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .search-filters input, 
        .search-filters select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-completed {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .status-full_paid {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-interest_paid {
            color: #f39c12;
            font-weight: bold;
        }
        
        .status-default {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .status-partial {
            color: #3498db;
            font-weight: bold;
        }
        
        /* Form styles */
        .payment-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Confirmation dialog styles */
        .confirmation-dialog {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .confirmation-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 400px;
            text-align: center;
        }
        
        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .edit-form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-filters > div {
                min-width: 100%;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Payments Received</h1>
        </div>
        
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right; background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right; background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <h2>Update Payments Received from Customer</h2>
        <div style="margin-bottom:20px; text-align: center;" class="navigation-buttons"> 
            <button onclick="window.location.href='loan_list.php'" class="button orange">
                <i class="fas fa-list"></i> Loan List
            </button>
            <button onclick="window.location.href='payments.php'" class="button green">
                <i class="fas fa-money-bill-wave"></i> Payments
            </button>
            <button onclick="window.location.href='dashboard.php'" class="button blue">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
        </div>

        <!-- Update Payment Received Form - Always visible -->
        <div class="edit-form-container">
            <form method="POST" action="payments_received.php">
                <?php if (isset($payment_to_edit) && $payment_to_edit): ?>
                    <input type="hidden" name="payment_id" value="<?= htmlspecialchars($payment_to_edit['payment_id']) ?>">
                    <input type="hidden" name="loan_id" value="<?= htmlspecialchars($payment_to_edit['loan_id']) ?>">
                    
                    <div class="form-group">
                        <label>Customer:</label>
                        <input type="text" value="<?= htmlspecialchars($payment_to_edit['customer_name']) ?>" readonly>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Loan Amount:</label>
                            <input type="text" value="<?= number_format($payment_to_edit['loan_amount'], 2) ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Fees (30%):</label>
                            <input type="text" value="<?= number_format($payment_to_edit['fees'], 2) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Capital (Loan Amount + Fees):</label>
                            <input type="text" value="<?= number_format($payment_to_edit['capital'], 2) ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount_received">Amount Received:</label>
                            <input type="number" step="0.01" id="amount_received" name="amount_received" 
                                value="<?= isset($payment_to_edit['amount_received']) && $payment_to_edit['amount_received'] != 0 ? htmlspecialchars($payment_to_edit['amount_received']) : '0.00' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount_due">Amount Due:</label>
                            <input type="number" step="0.01" id="amount_due" name="amount_due" 
                                value="<?= isset($payment_to_edit['amount_due']) ? htmlspecialchars($payment_to_edit['amount_due']) : number_format($payment_to_edit['capital'], 2) ?>" required readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" required>
                                <option value="completed" <?= $payment_to_edit['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="partial" <?= $payment_to_edit['status'] === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                                <option value="interest_paid" <?= $payment_to_edit['status'] === 'interest_paid' ? 'selected' : '' ?>>Interest Paid</option>
                                <option value="default" <?= $payment_to_edit['status'] === 'default' ? 'selected' : '' ?>>Default</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_payment" class="button green">
                            <i class="fas fa-save"></i>Update Payment
                        </button>
                        <button type="reset" class="button red">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                <?php else: ?>
                    <p>No payment data available to edit. Please use the "Update Payment" button in the Payments page.</p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Confirmation Dialog -->
        <div id="confirmationDialog" class="confirmation-dialog">
            <div class="confirmation-content">
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete this payment record? This action cannot be undone.</p>
                <div class="confirmation-buttons">
                    <button id="confirmYes" class="button red">Yes, Delete</button>
                    <button id="confirmNo" class="button blue">No, Cancel</button>
                </div>
            </div>
        </div>
        
        <h2>Payments Received List</h2>
        <div class="search-filters">
            <div>
                <label for="search">Search</label>
                <input type="text" id="search" name="search" placeholder="Search by customer name or payment ID" value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div>
                <label for="status_filter">Status</label>
                <select id="status_filter" name="status_filter">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                    <option value="interest_paid" <?= $status_filter === 'interest_paid' ? 'selected' : '' ?>>Interest Paid</option>
                    <option value="default" <?= $status_filter === 'default' ? 'selected' : '' ?>>Default</option>
                </select>
            </div>
            
            <div>
                <button type="button" onclick="applyFilters()" class="button blue">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button onclick="window.location.href='export_payments_received.php?search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>'" 
                        class="button green">
                    <i class="fas fa-file-excel"></i> Export
                </button>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Loan ID</th>
                    <th>Payment ID</th>
                    <th>Amount Received</th>
                    <th>Amount Due</th>
                    <th>Date Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['id']) ?></td>
                    <td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
                    <td><?= htmlspecialchars($payment['loan_id']) ?></td>
                    <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                    <td><?= htmlspecialchars(number_format($payment['amount_received'], 2)) ?></td>
                    <td><?= htmlspecialchars(number_format($payment['amount_due'], 2)) ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($payment['received_date']))) ?></td>
                    <td class="status-<?= htmlspecialchars($payment['status']) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['status']))) ?>
                    </td>
                    <td>
                        <button class="button red" title="Delete" onclick="showConfirmation(<?= $payment['id'] ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= buildPaginationUrl($page - 1) ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= buildPaginationUrl($i) ?>" <?= ($i == $page) ? 'class="current"' : '' ?>>
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="<?= buildPaginationUrl($page + 1) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
       
    <script>
        let paymentIdToDelete = null;
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status_filter').value;
            
            let url = 'payments_received.php?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (status) url += 'status_filter=' + encodeURIComponent(status);
            
            window.location.href = url;
        }
        
        function showConfirmation(paymentId) {
            paymentIdToDelete = paymentId;
            document.getElementById('confirmationDialog').style.display = 'block';
        }
        
        function closeConfirmation() {
            document.getElementById('confirmationDialog').style.display = 'none';
            paymentIdToDelete = null;
        }
        
        // Setup confirmation dialog buttons
        document.getElementById('confirmYes').addEventListener('click', function() {
            if (paymentIdToDelete) {
                window.location.href = 'payments_received.php?delete_id=' + paymentIdToDelete;
            }
            closeConfirmation();
        });
        
        document.getElementById('confirmNo').addEventListener('click', function() {
            closeConfirmation();
        });
        
        // Close confirmation when clicking outside of it
        window.onclick = function(event) {
            const confirmation = document.getElementById('confirmationDialog');
            if (event.target === confirmation) {
                closeConfirmation();
            }
        }

         document.addEventListener('DOMContentLoaded', function() {
            const amountReceivedInput = document.getElementById('amount_received');
            const amountDueInput = document.getElementById('amount_due');
            const capitalInput = document.querySelector('input[value="<?= isset($payment_to_edit['capital']) ? number_format($payment_to_edit['capital'], 2) : '0.00' ?>"]');
            const statusSelect = document.getElementById('status');
            
            // Calculate amount due when amount received changes
            if (amountReceivedInput && amountDueInput && capitalInput) {
                amountReceivedInput.addEventListener('input', function() {
                    const capital = parseFloat(capitalInput.value.replace(/,/g, ''));
                    const amountReceived = parseFloat(this.value) || 0;
                    const amountDue = capital - amountReceived;
                    
                    amountDueInput.value = amountDue.toFixed(2);
                    
                    // Update status based on amount due
                    if (amountDue <= 0) {
                        statusSelect.value = 'completed';
                    } else if (amountReceived > 0) {
                        statusSelect.value = 'partial';
                    }
                });
            }
            
            // Enhanced Clear button functionality
            const clearButton = document.querySelector('button[type="reset"]');
            if (clearButton) {
                clearButton.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default form reset
                    
                    // Reset all form fields
                    if (amountReceivedInput) {
                        amountReceivedInput.value = '0.00';
                    }
                    if (amountDueInput && capitalInput) {
                        const capital = parseFloat(capitalInput.value.replace(/,/g, ''));
                        amountDueInput.value = capital.toFixed(2);
                    }
                    if (statusSelect) {
                        statusSelect.value = 'partial';
                    }
                    
                    // Trigger the input event to recalculate
                    if (amountReceivedInput) {
                        amountReceivedInput.dispatchEvent(new Event('input'));
                    }
                });
            }
        });
    </script>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>

<?php
// Helper function to build pagination URLs
function buildPaginationUrl($page) {
    global $search, $status_filter;
    
    $url = 'payments_received.php?page=' . $page;
    if (!empty($search)) $url .= '&search=' . urlencode($search);
    if (!empty($status_filter)) $url .= '&status_filter=' . urlencode($status_filter);
    
    return $url;
}

ob_end_flush();
?>