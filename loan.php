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
            header("Location: payments.php?loan_id=" . $_POST['loan_id']);
            exit();
        }
        
        header("Location: loan.php");
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
                       ORDER BY l.due_date ASC
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
    <style>
        body {
            min-height: 100vh;
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
            padding-bottom: 5px;
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
        
        .btn-primary {
            background-color: #207cca;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #4CAF50;
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
        .status-approved {
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
        
        <!-- Add Loan Form -->
        <div class="form-container">
            <h2>Create New Loan</h2>
            <form method="POST" id="loanForm">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                
                <div class="form-row">
                    <div class="form-group-full">
                        <label>Customer</label>
                        <div class="readonly-field"><?= htmlspecialchars($customer_name) ?></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="loan_amount">Loan Amount (N$)</label>
                        <input type="number" step="0.01" name="loan_amount" id="loan_amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="interest_rate">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="interest_rate" value="30.00" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="period">Term (days)</label>
                        <input type="number" name="period" id="period" value="30" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="disbursement_date">Disbursement Date</label>
                        <input type="date" name="disbursement_date" id="disbursement_date" required 
                               max="<?= date('Y-m-d') ?>" 
                               min="<?= date('Y-m-d', strtotime('-5 days')) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="purpose">Purpose</label>
                        <select name="purpose" id="purpose" required>
                            <option value="">Select Purpose</option>
                            <option value="Education">Education</option>
                            <option value="Debt Consolidation">Debt Consolidation</option>
                            <option value="Emergency Expenses">Emergency Expenses</option>
                            <option value="Fix Vehicle">Fix Vehicle</option>
                            <option value="Home Improvement">Home Improvement</option>
                            <option value="Large Purchases">Large Purchases</option>
                            <option value="Medical Bills">Medical Bills</option>
                            <option value="Pay Workers">Pay Workers</option>
                            <option value="Personal">Personal</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="collateral">Collateral Description</label>
                        <select name="collateral" id="collateral" required>
                            <option value="">Select Collateral</option>
                            <option value="ATM Card">ATM Card</option>
                            <option value="National ID">National ID</option>
                            <option value="House">House</option>
                            <option value="Life Cover Contract">Life Cover Contract</option>
                            <option value="Passport">Passport</option>
                            <option value="Purchases Order">Purchases Order</option>
                            <option value="Vehicle">Vehicle</option>
                            <option value="Others">Others</option>
                            <option value="None">None</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fees (Auto-calculated)</label>
                        <div class="readonly-field" id="fees_display">N$0.00</div>
                        <input type="hidden" name="fees" id="fees">
                    </div>
                    
                    <div class="form-group">
                        <label>Capital (Auto-calculated)</label>
                        <div class="readonly-field" id="capital_display">N$0.00</div>
                        <input type="hidden" name="capital" id="capital">
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="add_loan" class="btn btn-success">Create Loan</button>
                    <button type="button" onclick="resetAndRedirect()" class="btn btn-danger">Cancel</button>
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
        
        // Reset form and redirect
        function resetAndRedirect() {
             // Reset the form (assuming your form has ID 'myForm')
            const form = document.getElementById('myForm');
            if (form) form.reset();
            
            // Redirect to customers.php
            window.location.href = 'customers.php';
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