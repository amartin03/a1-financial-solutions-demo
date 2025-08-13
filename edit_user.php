<?php
require_once 'cashloan_db.php';
include 'header.php';

$successMessage = "";
$errorMessage = "";
$user = null;

if (isset($_GET['user_id'])) {
    $userId = (int) $_GET['user_id'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errorMessage = "User not found.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Error fetching user: " . $e->getMessage();
    }
} else {
    $errorMessage = "User ID is missing.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $userId = (int) $_POST['user_id'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    try {
        // Fetch role_id based on role name
        $roleStmt = $conn->prepare("SELECT id FROM role_task WHERE role_name = ?");
        $roleStmt->execute([$role]);
        $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$roleData) {
            $errorMessage = "Invalid role selected.";
        } else {
            $roleId = $roleData['id'];

            // Update user with role_id
            $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, role_id = ? WHERE user_id = ?");
            $updateStmt->execute([$firstName, $lastName, $email, $role, $roleId, $userId]);

            if ($updateStmt->rowCount() > 0) {
                $successMessage = "User details updated successfully!";
            } else {
                $errorMessage = "No changes were made or update failed.";
            }

            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $errorMessage = "Error updating user: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .button {
            padding: 8px 15px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            border-radius: 5px;
        }
        .button.green { background-color: green; color: white; }
        .button.green:hover { background: #45a049; }
        .button.red { background-color: red; color: white; }
        .button.red:hover { background: darkred; }
        .success { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit User</h1>

        <?php if (!empty($successMessage)): ?>
            <div class="success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMessage)): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="POST" action="">
                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="Administrator" <?= $user['role'] === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                        <option value="Accountant" <?= $user['role'] === 'Accountant' ? 'selected' : '' ?>>Accountant</option>
                        <option value="Assistant" <?= $user['role'] === 'Assistant' ? 'selected' : '' ?>>Assistant</option>
                    </select>
                </div>
                <button type="submit" name="edit_user" class="button green">Save Changes</button>
                <button type="button" onclick="window.location.href='manage_users.php'" class="button red">Cancel</button>
            </form>
        <?php else: ?>
            <p>No user data available.</p>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>
