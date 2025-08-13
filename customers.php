<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

require_once 'cashloan_db.php';
include 'header.php';

$success = false;

// Handle customer creation
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE id_number = ? OR email = ? OR employee_code = ?");
        $stmt->execute([$_POST['id_number'], $_POST['email'], $_POST['employee_code']]);

        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Customer with the same ID number, email, or employee code already exists.';
        } else {
            $sql = "INSERT INTO customers (
                first_name, last_name, id_number, marital_status,
                postal_address, residential_address, email, phone, occupation,
                employee_code, employer_name, employer_tel_no, employer_address,
                reference_name, reference_tel_no, bank_name, branch,
                bank_account_no, type_of_account
            ) VALUES (
                :first_name, :last_name, :id_number, :marital_status,
                :postal_address, :residential_address, :email, :phone, :occupation,
                :employee_code, :employer_name, :employer_tel_no, :employer_address,
                :reference_name, :reference_tel_no, :bank_name, :branch,
                :bank_account_no, :type_of_account
            )";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':first_name' => $_POST['first_name'],
                ':last_name' => $_POST['last_name'],
                ':id_number' => $_POST['id_number'],
                ':marital_status' => $_POST['marital_status'],
                ':postal_address' => $_POST['postal_address'],
                ':residential_address' => $_POST['residential_address'],
                ':email' => $_POST['email'],
                ':phone' => $_POST['phone'],
                ':occupation' => $_POST['occupation'],
                ':employee_code' => $_POST['employee_code'],
                ':employer_name' => $_POST['employer_name'],
                ':employer_tel_no' => $_POST['employer_tel_no'],
                ':employer_address' => $_POST['employer_address'],
                ':reference_name' => $_POST['reference_name'],
                ':reference_tel_no' => $_POST['reference_tel_no'],
                ':bank_name' => $_POST['bank_name'],
                ':branch' => $_POST['branch'],
                ':bank_account_no' => $_POST['bank_account_no'],
                ':type_of_account' => $_POST['type_of_account']
            ]);

            $_SESSION['success'] = 'Customer added successfully!';
            header("Location: customers.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalPages = ceil($total / $limit);
$stmt = $conn->query("SELECT * FROM customers ORDER BY customer_id DESC LIMIT $offset, $limit");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers Management | AI-Financial Solution CC</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
             min-height: 240vh;
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
            margin-bottom: 15px;
        }
        .grid-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 10px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        label.required::after {
            content: '*';
            color: #F44336;
            margin-left: 4px;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        /* Button Styles */
        .button.blue {
            background-color: #207cca;
            color: white;
        }
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        .btn-primary { background-color: #207cca; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-danger { background-color: #e74c3c; color: white; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-success { background-color: #2ecc71; color: white; }
        .btn-success:hover { background-color: #27ae60; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-warning:hover { background-color: #d35400; }
        /* Table Styles */
        .table-container {
            margin-top: 10px;
        }
        .action-link {
            color: #3498db;
            text-decoration: none;
            margin: 0 5px;
        }
        .action-link:hover {
            text-decoration: underline;
        }
        /* Search and Pagination */
        .search-export {
            display: flex;
            align-items: center; /* Vertically align items */
            gap: 10px; /* Space between items */
            margin-bottom: 20px;
        }
        #search {
            padding: 8px 10px;
            width: 100%; /* Take remaining space */
            border: 1px solid #ddd;
            border-radius: 4px;
            height: 28px; /* Match button height */
            box-sizing: border-box;
            margin-bottom: 0px;
        }
        .button.blue, .btn-primary {
            height: 28px; /* Match search input height */
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap; /* Prevent button text wrapping */
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
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .grid-form {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .search-export {
                flex-direction: column;
                gap: 10px;
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
            <h1 class="page-title">Customer Management</h1>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2>Add New Customer</h2>
            <form class="grid-form" method="post" onsubmit="return validateForm()">
                <div class="form-group">
                    <label class="required">First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label class="required">Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label class="required">ID Number</label>
                    <input type="text" name="id_number" maxlength="11" required>
                </div>
                <div class="form-group">
                    <label class="required">Marital Status</label>
                    <select name="marital_status" required>
                        <option value="">Select</option>
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="widow">Widow</option>
                        <option value="divorced">Divorced</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label class="required">Postal Address</label>
                    <textarea name="postal_address" required></textarea>
                </div>
                <div class="form-group full-width">
                    <label class="required">Residential Address</label>
                    <textarea name="residential_address" required></textarea>
                </div>
                <div class="form-group">
                    <label class="required">Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label class="required">Phone</label>
                    <input type="text" name="phone" value="+264" pattern="\+264[0-9]{7,9}" maxlength="13" required>
                </div>
                <div class="form-group">
                    <label class="required">Occupation</label>
                    <input type="text" name="occupation" required>
                </div>
                <div class="form-group">
                    <label class="required">Employee Code</label>
                    <input type="text" name="employee_code" required>
                </div>
                <div class="form-group">
                    <label class="required">Employer Name</label>
                    <input type="text" name="employer_name" required>
                </div>
                <div class="form-group">
                    <label class="required">Employer Tel No</label>
                    <input type="text" name="employer_tel_no" value="+264" pattern="\+264[0-9]{7,9}" maxlength="13" required>
                </div>
                <div class="form-group full-width">
                    <label class="required">Employer Address</label>
                    <textarea name="employer_address" required></textarea>
                </div>
                <div class="form-group">
                    <label class="required">Reference Name</label>
                    <input type="text" name="reference_name" required>
                </div>
                <div class="form-group">
                    <label class="required">Reference Tel No</label>
                    <input type="text" name="reference_tel_no" value="+264" pattern="\+264[0-9]{7,9}" maxlength="13" required>
                </div>
                <div class="form-group">
                    <label class="required">Bank Name</label>
                    <select name="bank_name" required>
                        <option value="">Select</option>
                        <option value="First National Bank">First National Bank</option>
                        <option value="Bank Windhoek">Bank Windhoek</option>
                        <option value="Standard Bank">Standard Bank</option>
                        <option value="Nedbank">Nedbank</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Branch</label>
                    <input type="text" name="branch" required>
                </div>
                <div class="form-group">
                    <label class="required">Bank Account Number</label>
                    <input type="text" name="bank_account_no" pattern="[0-9]{6,20}" required>
                </div>
                <div class="form-group">
                    <label class="required">Type of Account</label>
                    <select name="type_of_account" required>
                        <option value="">Select</option>
                        <option value="cheque">Cheque</option>
                        <option value="savings">Savings</option>
                    </select>
                </div>
                <div class="form-group full-width" style="margin-top: 10px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-add"></i> Add Customer
                    </button>
                    <button type="reset" class="btn btn-danger">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <h2>Borrower's List</h2>
            <div class="search-export">
                <input type="text" id="search" onkeyup="filterCustomers()" placeholder="Search by Name, ID, or Employee Code">
                <button onclick="window.location.href='dashboard.php'" class="button blue">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </button>
                <button class="btn btn-primary" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
            </div>

            <table id="customerTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First</th>
                        <th>Last</th>
                        <th>ID Number</th>
                        <th>Marital</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Emp Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $row): ?>
                        <tr>
                            <td><?= $row['customer_id'] ?></td>
                            <td><?= htmlspecialchars($row['first_name']) ?></td>
                            <td><?= htmlspecialchars($row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['id_number']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($row['marital_status'])) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= htmlspecialchars($row['employee_code']) ?></td>
                            <td>
                                <a href="borrower_details.php?id=<?= $row['customer_id'] ?>" class="action-link">View</a>
                                <a href="edit_customer.php?id=<?= $row['customer_id'] ?>" class="action-link">Edit</a>
                                <a href="delete_customer.php?id=<?= $row['customer_id'] ?>" class="action-link">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i === $page ? 'style="font-weight:bold"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
        <footer
            <p>&copy; 2025 Powered by A1-Financial Solutions</p>
        </footer>
    <script>
        function filterCustomers() {
            const input = document.getElementById("search").value.toLowerCase();
            const rows = document.querySelectorAll("#customerTable tbody tr");
            rows.forEach(row => {
                const rowText = row.innerText.toLowerCase();
                row.style.display = rowText.includes(input) ? "" : "none";
            });
        }

        function exportToExcel() {
            const table = document.getElementById("customerTable");
            const tableHTML = table.outerHTML.replace(/ /g, '%20');
            const filename = 'customers.xls';
            const downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = 'data:application/vnd.ms-excel,' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        function validateForm() {
            // All field-level validation is handled via HTML5 attributes
            const idNumber = document.getElementById('id_number').value;
            const phone = document.getElementById('phone').value;
            const employerTelNo = document.getElementById('employer_tel_no').value;
            const referenceTelNo = document.getElementById('reference_tel_no').value;

            if (nationalId.length !== 11 || isNaN(nationalId)) {
                alert('National ID must be exactly 11 digits.');
                return false;
            }

            if (phone.length !== 13 || isNaN(phone)) {
                alert('Cellphone must be exactly 13 digits.');
                return false;
            }
            if (employerTelNo.length !== 13 || isNaN(employerTelNo)) {
                alert('Employer Tel/Cellphone number must be exactly 13 digits.');
                return false;
            }
            if (referenceTelNo.length !== 13 || isNaN(referenceTelNo)) {
                alert('Reference Tel/Cellphone number must be exactly 13 digits.');
                return false;
            }
            return true; 
        }

        // Autofill +264 in phone fields if empty
        document.addEventListener("DOMContentLoaded", function () {
            const phoneFields = [
                document.querySelector('input[name="phone"]'),
                document.querySelector('input[name="employer_tel_no"]'),
                document.querySelector('input[name="reference_tel_no"]')
            ];

            phoneFields.forEach(field => {
                if (field && field.value.trim() === '') {
                    field.value = '+264';
                    field.addEventListener('focus', () => {
                        if (field.value.trim() === '') {
                            field.value = '+264';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Send output buffer and turn off buffering
?>