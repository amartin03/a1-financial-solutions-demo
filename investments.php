<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

include 'header.php';
include 'cashloan_db.php';
include 'audit_logger.php';

$auditLogger = new AuditLogger($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_investment'])) {
        try {
            $amount_invested = (float)$_POST['amount_invested'];
            $notes = $_POST['notes'] ?? null;
            $recorded_by = $_SESSION['user_id'] ?? null;

            $stmt = $conn->prepare("INSERT INTO investments (
                amount_invested, recorded_by, notes
            ) VALUES (
                :amount_invested, :recorded_by, :notes
            )");

            $stmt->bindParam(':amount_invested', $amount_invested);
            $stmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
            $stmt->bindParam(':notes', $notes);

            if ($stmt->execute()) {
                $auditLogger->log("INVESTMENT_ADDED", "New investment: $amount_invested");
                $_SESSION['success'] = "Investment recorded successfully!";
                header("Location: investments.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: investments.php");
            exit;
        }
    }
    
    if (isset($_POST['update_investment'])) {
        try {
            $investment_id = (int)$_POST['investment_id'];
            $amount_invested = (float)$_POST['amount_invested'];
            $notes = $_POST['notes'] ?? null;

            $stmt = $conn->prepare("UPDATE investments SET 
                amount_invested = :amount_invested,
                notes = :notes
                WHERE investment_id = :investment_id");

            $stmt->bindParam(':amount_invested', $amount_invested);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':investment_id', $investment_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $auditLogger->log("INVESTMENT_UPDATED", "Updated investment ID: $investment_id");
                $_SESSION['success'] = "Investment updated successfully!";
                header("Location: investments.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: investments.php");
            exit;
        }
    }
    
    if (isset($_POST['delete_investment'])) {
        try {
            $investment_id = (int)$_POST['investment_id'];

            $stmt = $conn->prepare("DELETE FROM investments WHERE investment_id = :investment_id");
            $stmt->bindParam(':investment_id', $investment_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $auditLogger->log("INVESTMENT_DELETED", "Deleted investment ID: $investment_id");
                $_SESSION['success'] = "Investment deleted successfully!";
                header("Location: investments.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: investments.php");
            exit;
        }
    }
}

// Fetch all investments
$stmt = $conn->query("SELECT * FROM investments ORDER BY investment_date DESC");
$investments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total invested
$stmtTotal = $conn->query("SELECT SUM(amount_invested) AS total_invested FROM investments");
$totalInvested = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total_invested'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investments</title>
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
            background-color: #f44336;
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
        
        .summary-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .summary-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .summary-card p {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
            color: #207cca;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 50%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
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
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Investments Management</h1>
        
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
        
        <div class="summary-card">
            <h3>Total Amount Invested</h3>
            <p><?= htmlspecialchars(number_format($totalInvested, 2)) ?></p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <button onclick="openAddInvestmentModal()" class="button green">
                <i class="fas fa-plus"></i> Add New Investment
            </button>
            <button onclick="window.location.href='mytasks.php'" class="button blue">
                <i class="fas fa-tasks"></i> My Tasks
            </button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($investments as $investment): ?>
                <tr>
                    <td><?= htmlspecialchars($investment['investment_id']) ?></td>
                    <td><?= htmlspecialchars(number_format($investment['amount_invested'], 2)) ?></td>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($investment['investment_date']))) ?></td>
                    <td><?= htmlspecialchars($investment['notes'] ?? '-') ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="button blue" title="Edit" onclick="openEditModal(
                                <?= $investment['investment_id'] ?>,
                                '<?= htmlspecialchars($investment['amount_invested']) ?>',
                                '<?= htmlspecialchars($investment['notes'] ?? '') ?>'
                            )">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="button red" title="Delete" onclick="confirmDelete(<?= $investment['investment_id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Add Investment Modal -->
        <div id="addInvestmentModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('addInvestmentModal')">&times;</span>
                <h2>Add New Investment</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="amount_invested">Amount Invested</label>
                        <input type="number" step="0.01" id="amount_invested" name="amount_invested" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Optional notes about the investment"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('addInvestmentModal')" class="button red">Cancel</button>
                        <button type="submit" name="add_investment" class="button green">Save Investment</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Edit Investment Modal -->
        <div id="editInvestmentModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('editInvestmentModal')">&times;</span>
                <h2>Edit Investment</h2>
                <form method="POST">
                    <input type="hidden" id="edit_investment_id" name="investment_id">
                    
                    <div class="form-group">
                        <label for="edit_amount_invested">Amount Invested</label>
                        <input type="number" step="0.01" id="edit_amount_invested" name="amount_invested" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notes</label>
                        <textarea id="edit_notes" name="notes" rows="3" placeholder="Optional notes about the investment"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('editInvestmentModal')" class="button red">Cancel</button>
                        <button type="submit" name="update_investment" class="button green">Update Investment</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteConfirmationModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('deleteConfirmationModal')">&times;</span>
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete this investment?</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="investment_id" id="delete_investment_id">
                    <input type="hidden" name="delete_investment" value="1">
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('deleteConfirmationModal')" class="button gray">Cancel</button>
                        <button type="submit" class="button red">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>

    <script>
        function openAddInvestmentModal() {
            document.getElementById('addInvestmentModal').style.display = 'block';
        }
        
        function openEditModal(investmentId, amount, notes) {
            document.getElementById('edit_investment_id').value = investmentId;
            document.getElementById('edit_amount_invested').value = amount;
            document.getElementById('edit_notes').value = notes;
            document.getElementById('editInvestmentModal').style.display = 'block';
        }
        
        function confirmDelete(investmentId) {
            document.getElementById('delete_investment_id').value = investmentId;
            document.getElementById('deleteConfirmationModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>