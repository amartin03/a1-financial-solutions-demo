<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

include 'cashloan_db.php';
include 'header.php';

// Set the absolute path to your documents directory
$upload_dir = 'E:/xampp/htdocs/A1-Financial Solutions/documents/';

// Create directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Initialize success flag
$upload_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    
    // Define allowed document types and their corresponding file inputs
    $document_types = [
        'payslip' => ['application/pdf'],
        'id' => ['application/pdf', 'image/jpeg', 'image/png'],
        'application_form' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'bank_statement' => ['application/pdf'] // Added bank statement
    ];
    
    // Process each document type
    foreach ($document_types as $doc_type => $allowed_mimes) {
        if (!empty($_FILES[$doc_type]['name'])) {
            $original_name = $_FILES[$doc_type]['name'];
            
            // Sanitize the filename
            $file_name = preg_replace("/[^A-Za-z0-9 \.-]/", '', $original_name);
            $file_name = str_replace(' ', '_', $file_name);
            $file_name = $doc_type . '_' . $customer_id . '_' . $file_name;
            $file_path = $upload_dir . $file_name;

            // Check if file already exists - if so, append a timestamp
            if (file_exists($file_path)) {
                $file_name = time() . '_' . $file_name;
                $file_path = $upload_dir . $file_name;
            }

            // Get the actual file MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $_FILES[$doc_type]['tmp_name']);
            finfo_close($finfo);
            
            // Validate file type
            if (!in_array($file_type, $allowed_mimes)) {
                $_SESSION['error'] = "Error: Invalid file type for $doc_type. Allowed types: " . implode(', ', $allowed_mimes);
                header("Location: borrower_details.php?id=$customer_id");
                exit();
            }

            // Additional validation - check file extension
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_extensions = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            if (!array_key_exists($file_ext, $allowed_extensions) || $allowed_extensions[$file_ext] !== $file_type) {
                $_SESSION['error'] = "Error: File extension doesn't match file content for $doc_type.";
                header("Location: borrower_details.php?id=$customer_id");
                exit();
            }

            // Move uploaded file to the server
            if (move_uploaded_file($_FILES[$doc_type]['tmp_name'], $file_path)) {
                // Insert document record into the database using PDO
                try {
                    $stmt = $conn->prepare("INSERT INTO documents (customer_id, document_type, document_name, file_path) 
                                          VALUES (:customer_id, :doc_type, :original_name, :file_path)");
                    $stmt->execute([
                        ':customer_id' => $customer_id,
                        ':doc_type' => $doc_type,
                        ':original_name' => $original_name,
                        ':file_path' => $file_path
                    ]);
                    
                    $upload_success = true;
                } catch (PDOException $e) {
                    // Delete the file if database insert failed
                    unlink($file_path);
                    $_SESSION['error'] = "Error saving $doc_type record to database: " . $e->getMessage();
                    header("Location: borrower_details.php?id=$customer_id");
                    exit();
                }
            } else {
                // Get the specific upload error
                $error = $_FILES[$doc_type]['error'];
                $error_messages = [
                    0 => 'There is no error, the file uploaded with success',
                    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    3 => 'The uploaded file was only partially uploaded',
                    4 => 'No file was uploaded',
                    6 => 'Missing a temporary folder',
                    7 => 'Failed to write file to disk.',
                    8 => 'A PHP extension stopped the file upload.',
                ];
                
                $_SESSION['error'] = "Error uploading $doc_type: " . ($error_messages[$error] ?? "Unknown error");
                header("Location: borrower_details.php?id=$customer_id");
                exit();
            }
        }
    }
    
    // If we get here, all uploads were successful
    if ($upload_success) {
        $_SESSION['success'] = "Documents uploaded successfully!";
        header("Location: borrower_details.php?id=$customer_id");
        exit();
    }
}

// Get customer details using PDO
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
try {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = :customer_id");
    $stmt->execute([':customer_id' => $customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        die("Customer not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrower Details</title>
    <link rel="stylesheet" href="styles.css">
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
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .page-title {
            margin: 0;
        }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card h3 {
            margin-top: 0;
            color: #207cca;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .summary-item {
            margin: 8px 0;
        }
        
        .summary-item strong {
            display: inline-block;
            width: 150px;
        }
        
        /* Document Upload Styles */
        .document-section {
            margin: 30px 0;
        }
        
        .document-upload {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .document-upload h3 {
            margin-top: 0;
            color: #207cca;
        }
        
        .upload-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .upload-group {
            margin-bottom: 15px;
        }
        
        .upload-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        /* Uploaded Documents Styles */
        .documents-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .document-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .document-item a {
            color: #3498db;
            text-decoration: none;
            margin-right: 10px;
        }
        
        .document-item a:hover {
            text-decoration: underline;
        }
        
        /* Button Styles */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-danger { background-color: #F44336; color: white; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-success { background-color: #4CAF50; color: white; }
        .btn-success:hover { background-color: #27ae60; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-warning:hover { background-color: #d35400; }
        .btn-secondary { background-color: #34495e; color: white; }
        .btn-secondary:hover { background-color: #7f8c8d; }
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
            .summary-section {
                grid-template-columns: 1fr;
            }
            
            .upload-form {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Borrower Details</h1>
        </div>
        
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="summary-section">
            <div class="summary-card">
                <h3>Personal Information</h3>
                <div class="summary-item"><strong>Name:</strong> <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                <div class="summary-item"><strong>ID Number:</strong> <?php echo htmlspecialchars($customer['id_number']); ?></div>
                <div class="summary-item"><strong>Marital Status:</strong> <?php echo htmlspecialchars($customer['marital_status']); ?></div>
                <div class="summary-item"><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></div>
                <div class="summary-item"><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Address Information</h3>
                <div class="summary-item"><strong>Postal Address:</strong> <?php echo htmlspecialchars($customer['postal_address']); ?></div>
                <div class="summary-item"><strong>Residential Address:</strong> <?php echo htmlspecialchars($customer['residential_address']); ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Employment Information</h3>
                <div class="summary-item"><strong>Occupation:</strong> <?php echo htmlspecialchars($customer['occupation']); ?></div>
                <div class="summary-item"><strong>Employee Code:</strong> <?php echo htmlspecialchars($customer['employee_code']); ?></div>
                <div class="summary-item"><strong>Employer Name:</strong> <?php echo htmlspecialchars($customer['employer_name']); ?></div>
                <div class="summary-item"><strong>Employer Tel:</strong> <?php echo htmlspecialchars($customer['employer_tel_no']); ?></div>
                <div class="summary-item"><strong>Employer Address:</strong> <?php echo htmlspecialchars($customer['employer_address']); ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Bank & References</h3>
                <div class="summary-item"><strong>Bank Name:</strong> <?php echo htmlspecialchars($customer['bank_name']); ?></div>
                <div class="summary-item"><strong>Branch:</strong> <?php echo htmlspecialchars($customer['branch']); ?></div>
                <div class="summary-item"><strong>Account No:</strong> <?php echo htmlspecialchars($customer['bank_account_no']); ?></div>
                <div class="summary-item"><strong>Account Type:</strong> <?php echo htmlspecialchars($customer['type_of_account']); ?></div>
                <div class="summary-item"><strong>Reference Name:</strong> <?php echo htmlspecialchars($customer['reference_name']); ?></div>
                <div class="summary-item"><strong>Reference Tel:</strong> <?php echo htmlspecialchars($customer['reference_tel_no']); ?></div>
            </div>
        </div>

        <div class="document-section">
            <div class="document-upload">
                <h3>Upload Documents</h3>
                <form action="borrower_details.php" method="post" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    
                    <div class="upload-group">
                        <label for="payslip">Payslip (PDF only)</label>
                        <input type="file" name="payslip" id="payslip" accept="application/pdf">
                    </div>
                    
                    <div class="upload-group">
                        <label for="id">ID Document (PDF/JPEG/PNG)</label>
                        <input type="file" name="id" id="id" accept="application/pdf,image/jpeg,image/png">
                    </div>
                    
                    <div class="upload-group">
                        <label for="application_form">Application Form (PDF/DOC/DOCX)</label>
                        <input type="file" name="application_form" id="application_form" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    </div>
                    
                    <div class="upload-group">
                        <label for="bank_statement">Bank Statement (PDF only)</label>
                        <input type="file" name="bank_statement" id="bank_statement" accept="application/pdf">
                    </div>
                    
                    <div class="upload-group">
                        <button type="submit" class="btn btn-primary">Upload Documents</button>
                    </div>
                </form>
            </div>
            
            <h3>Uploaded Documents</h3>
            <div class="documents-list">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT * FROM documents WHERE customer_id = :customer_id");
                    $stmt->execute([':customer_id' => $customer_id]);
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($documents)) {
                        echo "<p>No documents uploaded yet.</p>";
                    } else {
                        foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))); ?></strong><br>
                                <?php echo htmlspecialchars($doc['document_name']); ?><br>
                                <a href="view_document.php?id=<?php echo $doc['id']; ?>" target="_blank">View</a>
                                <a href="download_document.php?id=<?php echo $doc['id']; ?>">Download</a>
                            </div>
                        <?php endforeach;
                    }
                } catch (PDOException $e) {
                    echo "<div class='message error'>Error loading documents: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>

        <div class="action-buttons">
            <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">Edit Borrower</a>
            <a href="delete_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-danger">Delete Borrower</a>
            <a href="loan.php?customer_id=<?= $customer['customer_id'] ?>&customer_name=<?= urlencode($customer['first_name'] . ' ' . $customer['last_name']) ?>" class="btn btn-primary">Create Loan</a>
            <a href="customers.php" class="btn btn-secondary">Customers List</a>
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