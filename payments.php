<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

include 'cashloan_db.php';
include 'header.php';
include 'audit_logger.php';
$auditLogger = new AuditLogger($conn);

// Get parameters from loan_list.php if coming from approval
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$customer_name = isset($_GET['customer']) ? $_GET['customer'] : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_payment'])) {
        try {
            $loan_id = (int)$_POST['loan_id'];
            $customer_id = (int)$_POST['customer_id'];
            $amount = (float)$_POST['amount'];
            $payment_date = $_POST['payment_date'];
            $payment_method = $_POST['payment_method'];
            $payment_status = 'active'; // Always active
            $transaction_reference = !empty($_POST['transaction_reference']) ? $_POST['transaction_reference'] : null;
            $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;
            $recorded_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            // Validate payment amount against loan balance
            $loanSql = "SELECT l.loan_id, l.loan_amount, l.status,
                       c.first_name, c.last_name
                       FROM loans l
                       JOIN customers c ON l.customer_id = c.customer_id
                       WHERE l.loan_id = :loan_id";
            
            $loanStmt = $conn->prepare($loanSql);
            $loanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $loanStmt->execute();
            $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                $auditLogger->log("PAYMENT_FAILED", "Loan not found for ID: $loan_id");
                $_SESSION['error'] = "Error: Loan not found.";
                header("Location: payments.php");
                exit;
            }

            // Calculate total paid so far for this loan
            $paidSql = "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE loan_id = :loan_id";
            $paidStmt = $conn->prepare($paidSql);
            $paidStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $paidStmt->execute();
            $paid = $paidStmt->fetch(PDO::FETCH_ASSOC);
            $total_paid = $paid['total_paid'];

            // Calculate new outstanding balance
            $newOutstanding = $loan['loan_amount'] - ($total_paid + $amount);

            // Insert payment record with all schema fields
            if (empty($transaction_reference)) {
                $firstInitial = substr($loan['first_name'], 0, 1);
                $lastName = $loan['last_name'];
                $transaction_reference = strtolower($firstInitial . $lastName . '_loan_' . date('Ymd'));
            }

            $stmt = $conn->prepare("INSERT INTO payments (
                loan_id, customer_id, amount, payment_date, payment_method, 
                transaction_reference, status, recorded_by, notes, amount_due
            ) VALUES (
                :loan_id, :customer_id, :amount, :payment_date, :payment_method, 
                :transaction_reference, :status, :recorded_by, :notes, :amount_due
            )");
            
            // Calculate amount due (assuming capital is loan_amount + fees)
            $fees = $loan['loan_amount'] * 0.3;
            $capital = $loan['loan_amount'] + $fees;
            $amount_due = $capital - ($total_paid + $amount);
            
            $stmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':payment_date', $payment_date);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':transaction_reference', $transaction_reference);
            $stmt->bindParam(':status', $payment_status); // always "active"
            $stmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':amount_due', $amount_due);

        if ($stmt->execute()) {
                $payment_id = $conn->lastInsertId();
                $auditLogger->log("PAYMENT_CREATED", json_encode([
                    'payment_id' => $payment_id,
                    'loan_id' => $loan_id,
                    'amount' => $amount,
                    'method' => $payment_method,
                    'reference' => $transaction_reference
                ]));

                // Update loan status to 'active' when payment is recorded
                $updateLoanStmt = $conn->prepare("UPDATE loans SET status= :status WHERE loan_id=:loan_id");
                $updateLoanStmt->bindParam(':status', $payment_status);
                $updateLoanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
                $updateLoanStmt->execute();
                
                // Verify the update was successful
                $rowsAffected = $updateLoanStmt->rowCount();
                if ($rowsAffected === 0) {
                    throw new Exception("Failed to update loan status to active");
                }
                
                $auditLogger->log("LOAN_STATUS_UPDATED", "Loan ID: $loan_id status updated to active");

                // Update loan status to 'paid' if full amount is paid
                if ($newOutstanding <= 0) {
                    $updateLoan = $conn->prepare("UPDATE loans SET status='paid' WHERE loan_id=:loan_id");
                    $updateLoan->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
                    $updateLoan->execute();
                    
                    $rowsAffected = $updateLoan->rowCount();
                    if ($rowsAffected === 0) {
                        throw new Exception("Failed to update loan status to paid");
                    }
                    
                    $auditLogger->log("LOAN_PAID", "Loan ID: $loan_id fully paid");
                }

                $_SESSION['success'] = "Payment recorded successfully! Loan status updated.";
                header("Location: payments.php");
                exit;
            } else {
                $auditLogger->log("PAYMENT_FAILED", "Database error recording payment for loan ID: $loan_id");
                $_SESSION['error'] = "Error: Unable to record payment.";
                header("Location: payments.php");
                exit;
            }
        } catch (PDOException $e) {
            $auditLogger->log("PAYMENT_ERROR", "Database error: " . $e->getMessage());
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: payments.php");
            exit;
        } catch (Exception $e) {
            $auditLogger->log("LOAN_UPDATE_FAILED", "Error updating loan status: " . $e->getMessage());
            $_SESSION['error'] = "Payment recorded but failed to update loan status: " . $e->getMessage();
            header("Location: payments.php");
            exit;
        }
    } 
} 
// Fetch payments with complete details (sorted in descending order)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$method_filter = isset($_GET['method_filter']) ? $_GET['method_filter'] : '';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Base query with descending order
$sql = "SELECT 
        p.payment_id, p.amount, p.payment_date, p.payment_method, 
        p.transaction_reference, p.status, p.notes,
        c.customer_id, c.first_name, c.last_name,
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

// Add ORDER BY and LIMIT once at the end
$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT :limit OFFSET :offset";
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
    $countSql = "SELECT COUNT(*) AS total FROM payments p
                 JOIN customers c ON p.customer_id = c.customer_id
                 JOIN loans l ON p.loan_id = l.loan_id
                 WHERE (c.first_name LIKE :search OR c.last_name LIKE :search OR p.transaction_reference LIKE :search)";

    $countParams = [
        'search' => "%$search%",
        'search2' => "%$search%",
        'search3' => "%$search%"
    ];

    if (!empty($status_filter)) {
        $countSql .= " AND p.status = :status_filter";
        $countParams['status_filter'] = $status_filter;
    }

    if (!empty($method_filter)) {
        $countSql .= " AND p.payment_method = :method_filter";
        $countParams['method_filter'] = $method_filter;
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
    <title>Payments Management</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 200vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        h1, h2 {
            margin-top: 0;
        }
        
        .button {
            padding: 8px 15px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        
        .button.blue {
            background-color: #207cca;
            color: white;
        }

        .button.red {
            background-color: #F44336;
            color: white;
        }
        
        .button.orange {
            background-color: #f39c12;
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
            margin-bottom: 10px;
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
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .payment-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .payment-form .form-group {
            margin-bottom: 15px;
        }
        
        .payment-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .payment-form input, 
        .payment-form select, 
        .payment-form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .payment-method-options {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }
        
        .payment-method-option {
            display: flex;
            align-items: center;
        }
        
        .payment-method-option input {
            margin-right: 5px;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .status-completed {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        
        .status-failed {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .hint {
            font-size: 0.8em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .search-filters {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .payment-method-options {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payments Management</h1>

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
        
        <h2>Payment to Customers</h2>
        <div style="margin-top: 20px; text-align: center;">
            <button onclick="window.location.href='loan_list.php'" class="button orange">
                <i class="fas fa-list"></i> Loan List
            </button>
            <button onclick="window.location.href='payments_received.php'" class="button gray">
                <i class="fas fa-dollar-sign"></i> Payments Received
            </button>
            <button onclick="window.location.href='dashboard.php'" class="button blue">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
        </div>
        <div class="payment-form">
            <form method="POST">
                <input type="hidden" name="loan_id" id="loan_id" value="<?= htmlspecialchars($loan_id) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Customer ID</label>
                        <input type="text" id="customer_id" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" step="0.01" id="amount" name="amount" value="<?= htmlspecialchars($amount) ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" id="payment_date" name="payment_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_reference">Transaction Reference</label>
                        <input type="text" id="transaction_reference" name="transaction_reference" 
                               value="<?= htmlspecialchars($customer_name) ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="payment-method-options">
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="cash" required> Cash
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="bank_transfer"> Bank Transfer
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="debit_order"> Debit Order
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="ewailet"> Ewailet
                        </label>
                         <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="pay2cell"> Pay2Cell
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Optional notes about the payment"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="submit_payment" class="button green">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                    <button type="reset" class="button red">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <h2>Payments List</h2>
        <div class="search-filters">
            <div>
                <label for="search">Search</label>
                <input type="text" id="search" name="search" placeholder="Search by name or reference" value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div>
                <label for="status_filter">Status</label>
                <select id="status_filter" name="status_filter">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="full_paid" <?= $status_filter === 'full_paid' ? 'selected' : '' ?>>Full Paid</option>
                    <option value="interest_paid" <?= $status_filter === 'interest_paid' ? 'selected' : '' ?>>Interest Paid</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="default" <?= $status_filter === 'default' ? 'selected' : '' ?>>Default</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                </select>
            </div>
            
            <div>
                <label for="method_filter">Payment Method</label>
                <select id="method_filter" name="method_filter">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $method_filter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank_transfer" <?= $method_filter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="debit_order" <?= $method_filter === 'debit_order' ? 'selected' : '' ?>>Debit Order</option>
                    <option value="ewailet" <?= $method_filter === 'ewailet' ? 'selected' : '' ?>>Ewailet</option>
                </select>
            </div>
            
            <div style="align-self: flex-end; margin-top: 20px;">
                <button type="button" onclick="applyFilters()" class="button blue">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button onclick="window.location.href='export_payments.php'" class="button green">
                    <i class="fas fa-file-excel"></i> Export
                </button>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Customer</th>
                    <th>Loan ID</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Recorded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                    <td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
                    <td><?= htmlspecialchars($payment['loan_id']) ?></td>
                    <td><?= htmlspecialchars(number_format($payment['amount'], 2)) ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($payment['payment_date']))) ?></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                    <td><?= htmlspecialchars($payment['transaction_reference']) ?></td>
                    <td class="status-<?= htmlspecialchars($payment['status']) ?>">
                        <?= htmlspecialchars(ucfirst($payment['status'])) ?>
                    </td>
                    <td><?= htmlspecialchars($payment['recorded_by_name'] ?? 'System') ?></td>
                    <td>
                        <button class="button orange" title="Update Payment" 
                                onclick="window.location.href='payments_received.php?source_payment_id=<?= $payment['payment_id'] ?>&loan_id=<?= $payment['loan_id'] ?>'">
                            <i class="fas fa-edit"></i>
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
        // Set default payment date to today and disable future/back dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('payment_date').value = today;
            
            // Auto-fill customer name as transaction reference if coming from loan list
            const customerName = "<?= addslashes($customer_name) ?>";
            if (customerName) {
                document.getElementById('transaction_reference').value = customerName;
            }
        });
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status_filter').value;
            const method = document.getElementById('method_filter').value;
            
            let url = 'payments.php?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (status) url += 'status_filter=' + encodeURIComponent(status) + '&';
            if (method) url += 'method_filter=' + encodeURIComponent(method);
            
            window.location.href = url;
        }
        
        function viewPayment(paymentId) {
            window.location.href = 'payment_details.php?id=' + paymentId;
        }
    </script>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>

<?php
// Helper function to build pagination URLs
function buildPaginationUrl($page) {
    global $search, $status_filter, $method_filter;
    
    $url = 'payments.php?page=' . $page;
    if (!empty($search)) $url .= '&search=' . urlencode($search);
    if (!empty($status_filter)) $url .= '&status_filter=' . urlencode($status_filter);
    if (!empty($method_filter)) $url .= '&method_filter=' . urlencode($method_filter);
    
    return $url;
}

ob_end_flush();
?>