<?php
// Start session at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start(); // Start output buffering to prevent header errors

include 'cashloan_db.php';
include 'header.php';

// Validate and fetch customer details
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Customer not found.");
}

// Update borrower personal info
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_borrower'])) {
    $sql = "UPDATE customers SET 
        first_name = :first_name,
        last_name = :last_name,
        id_number = :id_number,
        marital_status = :marital_status,
        postal_address = :postal_address,
        residential_address = :residential_address,
        email = :email,
        phone = :phone,
        occupation = :occupation,
        employee_code = :employee_code,
        employer_name = :employer_name,
        employer_tel_no = :employer_tel_no,
        employer_address = :employer_address,
        reference_name = :reference_name,
        reference_tel_no = :reference_tel_no
        WHERE customer_id = :customer_id";

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
        ':customer_id' => $customer_id
    ]);

    $_SESSION['success'] = "Borrower information updated successfully!";
    header("Location: borrower_details.php?id=$customer_id");
    ob_end_flush(); // Send output buffer and turn off buffering
    exit();
}

// Update banking info
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_banking'])) {
    $sql = "UPDATE customers SET 
        bank_name = :bank_name,
        branch = :branch,
        bank_account_no = :bank_account_no,
        type_of_account = :type_of_account
        WHERE customer_id = :customer_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':bank_name' => $_POST['bank_name'],
        ':branch' => $_POST['branch'],
        ':bank_account_no' => $_POST['bank_account_no'],
        ':type_of_account' => $_POST['type_of_account'],
        ':customer_id' => $customer_id
    ]);

    $_SESSION['success'] = "Banking information updated successfully!";
    header("Location: borrower_details.php?id=$customer_id");
    ob_end_flush(); // Send output buffer and turn off buffering
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Borrower | AI-Financial Solution CC</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .page-title {
            margin: 0;
        }
        /* Form Section Styles */
        .form-section {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .section-title {
            margin: 0;
        }
        /* Form Styles */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 10px;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group.full-width {
            width: 100%;
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
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-danger { background-color: #e74c3c; color: white; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-success { background-color: #2ecc71; color: white; }
        .btn-success:hover { background-color: #27ae60; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-warning:hover { background-color: #d35400; }
        /* Disabled State */
        .disabled input,
        .disabled select,
        .disabled .btn {
            background-color: #f9f9f9;
            color: #777;
            cursor: not-allowed;
        }
        /* Form Footer */
        .form-footer {
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        /* Confirmation Popup */
        .confirmation-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1001;
        }
        .confirmation-popup p {
            margin: 0 0 20px;
        } 
        .confirmation-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
    <script>
        let currentFormId = null;

        function enableEditing(section) {
            const sectionElement = document.getElementById(section);
            const inputs = sectionElement.querySelectorAll("input, select");
            const buttons = sectionElement.querySelectorAll("button");
            
            inputs.forEach(input => input.disabled = false);
            buttons.forEach(button => button.disabled = false);
            sectionElement.classList.remove("disabled");
        }

        function confirmUpdate(event, formId) {
            event.preventDefault();
            currentFormId = formId;
            document.getElementById("confirmationPopup").style.display = "block";
        }

        function submitForm() {
            if (currentFormId) {
                document.getElementById(currentFormId).submit();
            }
        }

        function closeConfirmation() {
            document.getElementById("confirmationPopup").style.display = "none";
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Borrower Information Section -->
        <div class="form-section">
            <div class="section-header">
                <h2 class="section-title">Borrower Information</h2>
            </div>
            <form method="post" id="borrowerForm" onsubmit="confirmUpdate(event, 'borrowerForm')">
                <input type="hidden" name="update_borrower" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ID Number:</label>
                        <input type="text" name="id_number" value="<?php echo htmlspecialchars($customer['id_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Marital Status:</label>
                        <select name="marital_status" required>
                            <option value="single" <?php echo $customer['marital_status'] == 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo $customer['marital_status'] == 'married' ? 'selected' : ''; ?>>Married</option>
                            <option value="widow" <?php echo $customer['marital_status'] == 'widow' ? 'selected' : ''; ?>>Widow</option>
                            <option value="divorced" <?php echo $customer['marital_status'] == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">    
                    <div class="form-group">
                        <label>Postal Address:</label> 
                        <input type="text" name="postal_address" value="<?php echo htmlspecialchars($customer['postal_address']); ?>" required>
                    </div>
                    <div class="form-group">   
                        <label>Residential Address:</label> 
                        <input type="text" name="residential_address" value="<?php echo htmlspecialchars($customer['residential_address']); ?>" required>
                    </div>
                </div> 
                <div class="form-row">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone:</label> 
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Occupation:</label> 
                        <input type="text" name="occupation" value="<?php echo htmlspecialchars($customer['occupation']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Employee Code:</label> 
                        <input type="text" name="employee_code" value="<?php echo htmlspecialchars($customer['employee_code']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Employer Name:</label>
                        <input type="text" name="employer_name" value="<?php echo htmlspecialchars($customer['employer_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Employer Tel No:</label>
                        <input type="tel" name="employer_tel_no" value="<?php echo htmlspecialchars($customer['employer_tel_no']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Employer Address:</label>
                        <input type="text" name="employer_address" value="<?php echo htmlspecialchars($customer['employer_address']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Reference Name:</label> 
                        <input type="text" name="reference_name" value="<?php echo htmlspecialchars($customer['reference_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Reference Tel No:</label>
                        <input type="tel" name="reference_tel_no" value="<?php echo htmlspecialchars($customer['reference_tel_no']); ?>" required>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-success">Update</button>
                    <a href="customers.php?id=<?php echo $customer_id; ?>" class="btn btn-danger">Cancel</a>
                </div>
            </form>
        </div>
        
        <!-- Banking Information Section -->
        <div id="bankingInfo" class="form-section enable">
            <div class="section-header">
                <h2 class="section-title">Banking Information</h2>
                <button type="button" class="btn btn-primary" onclick="enableEditing('bankingInfo')">Edit</button>
            </div>
            <form method="post" id="bankingForm" onsubmit="confirmUpdate(event, 'bankingForm')">
                <input type="hidden" name="update_banking" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Bank Name:</label>
                        <select name="bank_name" required disabled>
                            <option value="First National Bank" <?php echo strtolower($customer['bank_name']) == 'first national bank' ? 'selected' : ''; ?>>First National Bank</option>
                            <option value="Bank Windhoek" <?php echo strtolower($customer['bank_name']) == 'bank windhoek' ? 'selected' : ''; ?>>Bank Windhoek</option>
                            <option value="Standard Bank" <?php echo strtolower($customer['bank_name']) == 'standard bank' ? 'selected' : ''; ?>>Standard Bank</option>
                            <option value="Nedbank" <?php echo strtolower($customer['bank_name']) == 'nedbank' ? 'selected' : ''; ?>>Nedbank</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch:</label>
                        <input type="text" name="branch" value="<?php echo htmlspecialchars($customer['branch']); ?>" required disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bank Account No:</label>
                        <input type="text" name="bank_account_no" value="<?php echo htmlspecialchars($customer['bank_account_no']); ?>" required disabled>
                    </div>
                    <div class="form-group">
                        <label>Type of Account:</label>
                        <select name="type_of_account" required disabled>
                            <option value="cheque" <?php echo $customer['type_of_account'] == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="savings" <?php echo $customer['type_of_account'] == 'savings' ? 'selected' : ''; ?>>Savings</option>
                        </select>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-success" disabled>Update</button>
                    <a href="customers.php?id=<?php echo $customer_id; ?>" class="btn btn-danger">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Popup -->
    <div id="confirmationPopup" class="confirmation-popup">
        <p>Are you sure you want to update this information?</p>
        <div class="confirmation-buttons">
            <button class="btn btn-success" onclick="submitForm()">OK</button>
            <button class="btn btn-danger" onclick="closeConfirmation()">Cancel</button>
        </div>
    </div>
    </div>
        <footer>
            <p>&copy; 2025 Powered by A1-Financial Solutions</p>
        </footer>
</body>
</html>
<?php
ob_end_flush(); // Send output buffer and turn off buffering
?>