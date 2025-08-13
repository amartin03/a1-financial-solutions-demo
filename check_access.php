<?php
session_start();
include 'cashloan_db.php'; // Must define $pdo

$currentPage = basename($_SERVER['PHP_SELF']);

// Bypass for login page
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

try {
    $stmt = $conn->prepare("SELECT allowed_pages FROM role_task WHERE role_name = ?");
    $stmt->execute([$role]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die("Access denied: Role not found.");
    }

    $allowedPages = array_map('trim', explode(',', $result['allowed_pages']));

    if ($result['allowed_pages'] !== '*' && !in_array($currentPage, $allowedPages)) {
        echo "<h2>⛔ Access Denied</h2><p>You do not have permission to access this page.</p>";
        exit;
    }
} catch (PDOException $e) {
    die("Access check failed: " . $e->getMessage());
}
?>
