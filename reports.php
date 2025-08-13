<?php
session_start();
require_once 'cashloan_db.php';
include 'header.php';

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Adjust this to match your actual date column in the loans table
$dateColumn = 'disbursement_date';

$countQuery = "SELECT COUNT(*) FROM loans l
               JOIN customers c ON l.customer_id = c.customer_id
               WHERE 1";

$query = "SELECT l.*, c.first_name, c.last_name, c.customer_id, pr.amount_received, 0,
                   pr.amount_due, 0, pr.received_date
          FROM loans l 
          JOIN customers c ON l.customer_id = c.customer_id
          LEFT JOIN payments_received pr ON l.loan_id = pr.loan_id
          WHERE 1";

$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND DATE(l.$dateColumn) BETWEEN ? AND ?";
    $countQuery .= " AND DATE(l.$dateColumn) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

if (!empty($search)) {
    $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.customer_id LIKE ?)";
    $countQuery .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.customer_id LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

if (!empty($statusFilter)) {
    $query .= " AND l.status = ?";
    $countQuery .= " AND l.status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $conn->prepare($countQuery);
$totalStmt->execute($params);
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

$query .= " ORDER BY l.$dateColumn DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary totals
$totalLoaned = 0;
$totalFees = 0;
$totalCapital = 0;
$totalAmount_Received = 0;
$totalBalance = 0;
foreach ($loans as $loan) {
    $totalLoaned += $loan['loan_amount'];
    $totalFees += $loan['fees'];
    $totalCapital += $loan['capital'];
    $totalAmount_Received += $loan['amount_received'];
    $totalBalance += $loan['amount_due'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Reports</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
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
            padding-bottom: 10px;
        }
        .page-title {
            margin: 0;
        }
        .filter-form {
            background-color: #f8f9fa;
            padding: 8px 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #ddd; /* Add border to the form */
            box-sizing: border-box;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Optional subtle shadow */
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select {
            display: block;
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.15s ease-in-out, box-shadow inset 0 1px 2px rgba(0,0,0,0.1);
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        /* Date input specific styling */
        input[type="date"] {
            appearance: none;
            background-color: white;
            padding: 8px 5px;
        }
        input[type="text"] {
            appearance: none;
            background-color: white;
            padding: 8px 5px;
        }
        .button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .button.blue {
            background-color: #207cca;
            color: white;
        }
        .button:hover {
            opacity: 0.9;
        }
        /* Fix table container */
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .export-buttons {
            margin: 20px 0;
            display: inline;
            gap: 10px;
            margin-bottom: 15px;
        }
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        .button.red {
            background-color: #e74c3c;
            color: white;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #777;
            font-style: italic;
        }
        .no-data.error {
            color: #e74c3c;
        }
        input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .status-pending {
            background-color: #f39c12;
        }
        .status-approved {
            background-color: #3498db;
        }
        .status-active {
            background-color: #2ecc71;
        }
        .status-rejected {
            background-color: #e74c3c;
        }
        .status-completed {
            background-color: #9b59b6;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-group {
                min-width: 100%;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    </script>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Loan Consolidation</h1>
        </div>
    <h2>Loan Reports</h2>

    <form method="GET" action="reports.php">
        <div class="form-row">
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="form-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" placeholder="Search by name or ID" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="button green"><i class="fas fa-filter"></i> Filter</button>
                <button type="button" onclick="window.location.href='dashboard.php'" class="button blue">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </button>
            </div>
        </div>
    </form>

    <br>
    <div style="margin-bottom:20px;" class="export-buttons">
        <!-- Export PDF -->
        <form id="exportPdfForm" method="POST" action="export_pdf.php" style="display:inline;">
            <input type="hidden" name="loan_ids" id="pdfLoanIds">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <button type="submit" class="button blue">Export to PDF</button>
        </form>

        <!-- Export Excel -->
        <form id="exportExcelForm" method="POST" action="export_excel.php" style="display:inline;">
            <input type="hidden" name="loan_ids" id="excelLoanIds">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <button type="submit" class="button blue">Export to Excel</button>
        </form>
    </div>

    <div id="loanSelectionForm">
        <table border="1" cellpadding="10" cellspacing="0">
            <thead>
                <tr>
                    <th width="40px">Select</th>
                    <th>Loan ID</th>
                    <th>Customer ID</th>
                    <th>Customer Name</th>
                    <th class="text-right">Loan Amount</th>
                    <th class="text-right">Fees</th>
                    <th class="text-right">Capital</th>
                    <th>Disbursement Date</th>
                    <th class="text-right">Amount Received</th>
                    <th class="text-right">Balance</th>
                    <th>Received Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($loans) > 0): ?>
                <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="loan-checkbox" value="<?= $loan['loan_id'] ?>">
                        </td>
                        <td><?= htmlspecialchars($loan['loan_id']) ?></td>
                        <td><?= htmlspecialchars($loan['customer_id']) ?></td>
                        <td><?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?></td>
                        <td class="text-right"><?= number_format($loan['loan_amount'], 2) ?></td>
                        <td class="text-right"><?= number_format($loan['fees'], 2) ?></td>
                        <td class="text-right"><?= number_format($loan['capital'], 2) ?></td>
                        <td><?= !empty($loan['disbursement_date']) ? date('d/m/Y', strtotime($loan['disbursement_date'])) : 'N/A' ?></td>
                        <td class="text-right"><?= number_format($loan['amount_received'], 2) ?></td>
                        <td class="text-right"><?= number_format($loan['amount_due'], 2) ?></td>
                        <td><?= !empty($loan['received_date']) ? date('d/m/Y', strtotime($loan['received_date'])) : 'N/A' ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($loan['status']) ?>">
                                <?= ucfirst(htmlspecialchars($loan['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button green" onclick="toggleLoanSelection('<?= $loan['loan_id'] ?>')">
                                <i class="fas fa-plus"></i> <span class="btn-text">Add</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold;">
                    <td colspan="4">TOTAL</td>
                    <td><?= number_format($totalLoaned, 2) ?></td>
                    <td><?= number_format($totalFees, 2) ?></td>
                    <td><?= number_format($totalCapital, 2) ?></td>
                    <td></td>
                    <td><?= number_format($totalAmount_Received, 2) ?></td>
                    <td><?= number_format($totalBalance, 2) ?></td>
                    <td colspan="3"></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="13">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>">&laquo; First</a>
            <a href="?page=<?= $page - 1 ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>">&lsaquo; Prev</a>
        <?php endif; ?>

        <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>">Next &rsaquo;</a>
            <a href="?page=<?= $totalPages ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>">Last &raquo;</a>
        <?php endif; ?>
    </div>
</div>
    <script>
        let selectedLoans = [];

        function toggleLoanSelection(loanId) {
            const checkbox = document.querySelector(`.loan-checkbox[value="${loanId}"]`);
            const button = document.querySelector(`button[onclick="toggleLoanSelection('${loanId}')"]`);
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                if (!selectedLoans.includes(loanId)) {
                    selectedLoans.push(loanId);
                }
                button.innerHTML = '<i class="fas fa-minus"></i> <span class="btn-text">Remove</span>';
                button.classList.add('red');
                button.classList.remove('green');
            } else {
                selectedLoans = selectedLoans.filter(i => i !== loanId);
                button.innerHTML = '<i class="fas fa-plus"></i> <span class="btn-text">Add</span>';
                button.classList.add('green');
                button.classList.remove('red');
            }
            
            updateExportLinks();
        }

        document.querySelectorAll('.loan-checkbox').forEach(cb => {
            cb.addEventListener('change', function () {
                const id = this.value;
                const button = document.querySelector(`button[onclick="toggleLoanSelection('${id}')"]`);
                
                if (this.checked) {
                    if (!selectedLoans.includes(id)) selectedLoans.push(id);
                    button.innerHTML = '<i class="fas fa-minus"></i> <span class="btn-text">Remove</span>';
                    button.classList.add('red');
                    button.classList.remove('green');
                } else {
                    selectedLoans = selectedLoans.filter(i => i !== id);
                    button.innerHTML = '<i class="fas fa-plus"></i> <span class="btn-text">Add</span>';
                    button.classList.add('green');
                    button.classList.remove('red');
                }
                updateExportLinks();
            });
        });

        function updateExportLinks() {
            const query = selectedLoans.length > 0 ? '&loan_ids=' + selectedLoans.join(',') : '';
            document.getElementById('exportPdfBtn').href = `export_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>${query}`;
            document.getElementById('exportExcelBtn').href = `export_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= $search ?>&status=<?= $statusFilter ?>${query}`;
        }
        
        document.getElementById('exportPdfForm').addEventListener('submit', function(e) {
            let ids = Array.from(document.querySelectorAll('.loan-checkbox:checked'))
                        .map(cb => cb.value);
            if (ids.length === 0) {
                alert("Please select at least one loan to export.");
                e.preventDefault();
                return;
            }
            document.getElementById('pdfLoanIds').value = JSON.stringify(ids);
        });

        document.getElementById('exportExcelForm').addEventListener('submit', function(e) {
            let ids = Array.from(document.querySelectorAll('.loan-checkbox:checked'))
                        .map(cb => cb.value);
            if (ids.length === 0) {
                alert("Please select at least one loan to export.");
                e.preventDefault();
                return;
            }
            document.getElementById('excelLoanIds').value = JSON.stringify(ids);
        });
    </script>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>