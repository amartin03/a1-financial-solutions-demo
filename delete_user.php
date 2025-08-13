<?php
// Start session
session_start();

// Restrict access to administrators only
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: login.php");
    exit;
}

// Database connection using PDO
require_once 'cashloan_db.php'; // Assumes $conn is a PDO instance

// Validate and sanitize user ID
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id > 0) {
    try {
        // Begin transaction
        $conn->beginTransaction();

        // Delete the user
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->execute([$user_id]);

        // Log the deletion activity
        $activity = "User with ID $user_id deleted by {$_SESSION['username']} on " . date('Y-m-d H:i:s');
        $logStmt = $conn->prepare("INSERT INTO activity_log (activity) VALUES (?)");
        $logStmt->execute([$activity]);

        // Commit transaction
        $conn->commit();

        header("Location: manage_users.php?success=User deleted successfully.");
        exit;
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();
        header("Location: manage_users.php?error=Error deleting user.");
        exit;
    }
} else {
    header("Location: manage_users.php?error=Invalid user ID.");
    exit;
}
?>
