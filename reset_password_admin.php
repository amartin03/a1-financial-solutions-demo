<?php  
// Database connection using PDO
require_once 'cashloan_db.php'; // Assumes $conn is a valid PDO instance

$successMessage = "";
$errorMessage = "";

if (!isset($_GET['user_id'])) {
    $errorMessage = "User ID is missing.";
} else {
    $userId = (int) $_GET['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            $errorMessage = "Passwords do not match.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            try {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);

                if ($stmt->rowCount() > 0) {
                    $successMessage = "Password reset successfully!";
                } else {
                    $errorMessage = "No changes made or user not found.";
                }
            } catch (PDOException $e) {
                $errorMessage = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
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
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input[type="password"],
        .input-wrapper input[type="text"] {
            width: 90%;
            padding: 10px 40px 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
        }
        .button {
            padding: 10px 20px;
            background-color: green;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .button.green { background-color: green; color: white; }
        .button.green:hover { background: #45a049; }
        .button.red { background-color: red; color: white; }
        .button.red:hover { background: darkred; 
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
    <script>
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const toggleIcon = input.nextElementSibling;
            if (input.type === "password") {
                input.type = "text";
                toggleIcon.textContent = "🙈";
            } else {
                input.type = "password";
                toggleIcon.textContent = "👁️";
            }
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>Reset Password</h1>

        <?php if ($successMessage): ?>
            <div class="success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-wrapper">
                    <input type="password" id="new_password" name="new_password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('new_password')">👁️</span>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
                </div>
            </div>
            <button type="submit" name="reset_password" class="button">Reset Password</button>
            <button class="button red" onclick="window.location.href='manage_users.php'">Cancel</a>
        </form>
    </div>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>
