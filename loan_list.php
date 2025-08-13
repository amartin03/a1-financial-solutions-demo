<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

include 'cashloan_db.php';
include 'header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_loan'])) {
            // Calculate capital (Loan Amount + Fees)
            $fees = $_POST['loan_amount'] * ($_POST['interest_rate'] / 100);
            $capital = $_POST['loan_amount'] + $fees;
            
            // Add new loan
            $stmt = $conn->prepare("INSERT INTO loans (
                customer_id, loan_amount, interest_rate, fees, capital, 
                disbursement_date, due_date, status, purpose, collateral_description
            ) VALUES (
                :customer_id, :loan_amount, :interest_rate, :fees, :capital,
                :disbursement_date, :due_date, :status, :purpose, :collateral
            )");
            
            $due_date = date('Y-m-d', strtotime($_POST['disbursement_date'] . ' + ' . $_POST['period'] . ' days'));
            
            $stmt->execute([
                ':customer_id' => $_POST['customer_id'],
                ':loan_amount' => $_POST['loan_amount'],
                ':interest_rate' => $_POST['interest_rate'],
                ':fees' => $fees,
                ':capital' => $capital,
                ':disbursement_date' => $_POST['disbursement_date'],
                ':due_date' => $due_date,
                ':status' => 'pending',
                ':purpose' => $_POST['purpose'],
                ':collateral' => $_POST['collateral']
            ]);
            
            $_SESSION['success'] = "Loan added successfully!";
            
        } elseif (isset($_POST['update_loan'])) {
            // Update loan status
            $stmt = $conn->prepare("UPDATE loans SET status = :status WHERE loan_id = :loan_id");
            $stmt->execute([
                ':status' => $_POST['status'],
                ':loan_id' => $_POST['loan_id']
            ]);
            
            $_SESSION['success'] = "Loan updated successfully!";
        } elseif (isset($_POST['approve_loan'])) {
            // Approve loan
            $stmt = $conn->prepare("UPDATE loans SET 
                status = 'approved',
                approved_by = :user_id,
                approval_date = NOW()
                WHERE loan_id = :loan_id");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':loan_id' => $_POST['loan_id']
            ]);
            
            $_SESSION['success'] = "Loan approved successfully!";
            header("Location: loan_list.php?loan_id=" . $_POST['loan_id']);
            exit();
        }
        
        header("Location: loan_list.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

// Get customer data if passed from borrower_details.php
$customer_id = $_GET['customer_id'] ?? '';
$customer_name = $_GET['customer_name'] ?? '';

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Fetch total number of loans
$total_stmt = $conn->query("SELECT COUNT(*) FROM loans");
$total_loans = $total_stmt->fetchColumn();
$total_pages = ceil($total_loans / $per_page);

// Fetch loans with customer names (paginated)
$stmt = $conn->prepare("SELECT l.*, c.first_name, c.last_name 
                       FROM loans l
                       JOIN customers c ON l.customer_id = c.customer_id
                       ORDER BY l.due_date DESC
                       LIMIT :offset, :per_page");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers for dropdown
$customers = $conn->query("SELECT customer_id, first_name, last_name FROM customers")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans Management | AI-Financial Solution CC</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 150vh;
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
        /* Form Styles */
        .form-container {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group-full {
            flex: 0 0 100%;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        .button.blue {
            background-color: #207cca;
            color: white;
        }
        
        .btn-primary {
            background-color: #207cca;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #d35400;
        }
        
        .btn-danger {
            background-color: #F44336;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #f1c40f;
            color: #000;
        }
        .status-approved, .status-active {
            background-color: #4CAF50;
            color: #fff;
        }
        .status-rejected {
            background-color: #F44336;
            color: #fff;
        }
        .status-completed {
            background-color: #207CCA;
            color: #fff;
        }
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        /* Message Styles */
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .error {
            background-color: #ffdddd;
            color: #f44336;
            border-left: 4px solid #f44336;
        }
        .success {
            background-color: #ddffdd;
            color: #4CAF50;
            border-left: 4px solid #4CAF50;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        /* Additional modal style for status change */
        .status-modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .loans-table {
                display: block;
                overflow-x: auto;
            }
            .action-buttons {
                flex-direction: column;
            }
            .modal-content {
                width: 90%;
                margin: 30% auto;
            }
        }
        .readonly-field {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Loan Management</h1>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Loans Table -->
        <h2>Loan Portfolio</h2>
        <div style="margin-top: 10px; text-align: center;">
            <button onclick="window.location.href='payments.php'" class="button green">
                <i class="fas fa-money-bill-wave"></i> Payments
            </button>
            <button onclick="window.location.href='dashboard.php'" class="button blue">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
        </div>
        <table class="loans-table">
            <thead>
                <tr>
                    <th>Loan ID</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Interest</th>
                    <th>Fees</th>
                    <th>Capital</th>
                    <th>Disbursed</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td><?= $loan['loan_id'] ?></td>
                        <td><?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?></td>
                        <td>N$<?= number_format($loan['loan_amount'], 2) ?></td>
                        <td><?= $loan['interest_rate'] ?>%</td>
                        <td>N$<?= number_format($loan['fees'], 2) ?></td>
                        <td>N$<?= number_format($loan['capital'], 2) ?></td>
                        <td><?= date('M d, Y', strtotime($loan['disbursement_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($loan['status']) ?>">
                                <?= ucfirst($loan['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($loan['status'] == 'pending'): ?>
                                    <button onclick="showApproveModal(<?= $loan['loan_id'] ?>)" class="btn btn-success">Approve</button>
                                <?php endif; ?>
                                
                                <select onchange="showStatusModal(this, <?= $loan['loan_id'] ?>)" class="btn btn-warning">
                                    <option value="">Change Status</option>
                                    <option value="pending" <?= $loan['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $loan['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $loan['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    <option value="active" <?= $loan['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="completed" <?= $loan['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="default" <?= $loan['status'] == 'default' ? 'selected' : '' ?>>Default</option>
                                    <option value="interest_paid" <?= $loan['status'] == 'interest_paid' ? 'selected' : '' ?>>Interest Paid</option>
                                    <option value="full_paid" <?= $loan['status'] == 'full_paid' ? 'selected' : '' ?>>Full Paid</option>
                                    <option value="partial" <?= $loan['status'] == 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                                </select>
                                
                                <?php if ($loan['status'] == 'approved'): ?>
                                    <a href="payments.php?loan_id=<?= $loan['loan_id'] ?>&customer_id=<?= $loan['customer_id'] ?>&amount=<?= $loan['loan_amount'] ?>&customer=<?= urlencode($loan['first_name'] . ' ' . $loan['last_name']) ?>&status=<?= $loan['status'] ?>" class="btn btn-primary">Pay</a>
                                    <?php else: ?>
                                    <button class="btn btn-primary btn-disabled" disabled>Pay</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        
    </div>
        
    <!-- Approve Loan Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Approval</h3>
            <p>Are you sure you want to approve this loan?</p>
            <form id="approveForm" method="POST">
                <input type="hidden" name="loan_id" id="modalLoanId">
                <input type="hidden" name="approve_loan" value="1">
                <div class="modal-buttons">
                    <button type="button" onclick="hideModal()" class="btn btn-danger">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
    <!-- New Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="status-modal-content">
            <h3>Confirm Status Change</h3>
            <p id="statusChangeText">Are you sure you want to change the status?</p>
            <form id="statusForm" method="POST">
                <input type="hidden" name="loan_id" id="statusLoanId">
                <input type="hidden" name="status" id="statusValue">
                <input type="hidden" name="update_loan" value="1">
                <div class="modal-buttons">
                    <button type="button" onclick="hideStatusModal()" class="btn btn-danger">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm Change</button>
                </div>
            </form>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
    
    <script>
        // Calculate fees and capital when loan amount or interest rate changes
        function calculateFeesAndCapital() {
            const loanAmount = parseFloat(document.getElementById('loan_amount').value) || 0;
            const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;
            
            const fees = loanAmount * (interestRate / 100);
            const capital = loanAmount + fees;
            
            document.getElementById('fees').value = fees.toFixed(2);
            document.getElementById('fees_display').textContent = 'N$' + fees.toFixed(2);
            document.getElementById('capital').value = capital.toFixed(2);
            document.getElementById('capital_display').textContent = 'N$' + capital.toFixed(2);
        }
        
        document.getElementById('loan_amount').addEventListener('input', calculateFeesAndCapital);
        document.getElementById('interest_rate').addEventListener('input', calculateFeesAndCapital);
        
        // Calculate due date based on disbursement date and period
        document.getElementById('disbursement_date').addEventListener('change', function() {
            const period = document.getElementById('period').value;
            if (period && this.value) {
                const disbursementDate = new Date(this.value);
                disbursementDate.setDate(disbursementDate.getDate() + parseInt(period));
                document.getElementById('due_date').value = disbursementDate.toISOString().split('T')[0];
            }
        });
        
        document.getElementById('period').addEventListener('change', function() {
            const disbursementDate = document.getElementById('disbursement_date').value;
            if (disbursementDate && this.value) {
                const date = new Date(disbursementDate);
                date.setDate(date.getDate() + parseInt(this.value));
                document.getElementById('due_date').value = date.toISOString().split('T')[0];
            }
        });
        
        // Modal functions
        function showApproveModal(loanId) {
            document.getElementById('modalLoanId').value = loanId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function hideModal() {
            document.getElementById('approveModal').style.display = 'none';
        }
        
        // Modal functions for status change
        function showStatusModal(select, loanId) {
            if (select.value) {
                const statusText = select.options[select.selectedIndex].text;
                document.getElementById('statusChangeText').textContent = 
                    `Are you sure you want to change the status to "${statusText}"?`;
                document.getElementById('statusLoanId').value = loanId;
                document.getElementById('statusValue').value = select.value;
                document.getElementById('statusModal').style.display = 'block';
                
                // Reset the select to current value
                select.value = '';
            }
        }

        // Update status function
        function updateStatus(select, loanId) {
            if (select.value) {
                if (confirm('Are you sure you want to change the status?')) {
                    window.location.href = 'loan_list.php?update_loan=1&loan_id=' + loanId + '&status=' + select.value;
                } else {
                    select.value = '';
                }
            }
        }
        function hideStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const approveModal = document.getElementById('approveModal');
            const statusModal = document.getElementById('statusModal');
            
            if (event.target == approveModal) {
                hideModal();
            }
            if (event.target == statusModal) {
                hideStatusModal();
            }
        }
        
        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateFeesAndCapital();
            
            // Set default disbursement date to today if not set
            if (!document.getElementById('disbursement_date').value) {
                document.getElementById('disbursement_date').valueAsDate = new Date();
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>