<?php
// Start session at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start(); // Start output buffering to prevent header errors

include 'cashloan_db.php';
include 'header.php';

// Validate customer ID
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch customer details for confirmation message
$stmt = $conn->prepare("SELECT first_name, last_name FROM customers WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: customers.php");
    exit();
}

// Handle delete confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm_delete'])) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // First delete related documents
            $stmt = $conn->prepare("DELETE FROM documents WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            
            // Then delete the customer
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Customer deleted successfully!";
            header("Location: customers.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error deleting customer: " . $e->getMessage();
            header("Location: customers.php");
            exit();
        }
    } else {
        // User clicked "No", redirect back
        header("Location: customers.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delete Customer | AI-Financial Solution CC</title>
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
        
        .page-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .page-title {
            margin: 0;
        }
        
        /* Confirmation Dialog */
        .confirmation-dialog {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 30px auto;
            text-align: center;
        }
        
        .confirmation-message {
            font-size: 1.1rem;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
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
            min-width: 100px;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-secondary {
            background-color: #207cca;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Delete Customer</h1>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="confirmation-dialog">
            <div class="confirmation-message">
                Are you sure you want to permanently delete customer:<br>
                <strong><?php echo htmlspecialchars($customer['first_name']) . ' ' . htmlspecialchars($customer['last_name']); ?></strong>?
                <br><br>
                This action cannot be undone and will also delete all associated documents.
            </div>
                        
            <form method="post" class="confirmation-buttons">
                <button type="submit" name="confirm_delete" value="1" class="btn btn-danger">Yes, Delete</button>
                <a href="customers.php" class="btn btn-secondary">No, Cancel</a>
            </form>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 Powered by AI-Financial Solutions</p>
    </footer>
</body>
</html>
<?php
ob_end_flush(); // Send output buffer and turn off buffering
?>