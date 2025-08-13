<?php
session_start();

// Database connection
include 'cashloan_db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Avoid undefined array key warnings
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role_name = isset($_POST['role']) ? $_POST['role'] : '';

    try {
        // List of allowed roles (can be moved to config/database later)
        $allowed_roles = ['Administrator', 'Accountant', 'Assistant'];

            // Step 2: Fetch user with matching username and role_id
            $userStmt = $conn->prepare("
                SELECT u.*, rt.role_name 
                FROM users u
                JOIN role_task rt ON u.role_id = rt.id
                WHERE u.username = ?
            ");
            $userStmt->execute([$username]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Step 2: Verify password and check if role is allowed
            if (password_verify($password, $user['password']) && in_array($user['role_name'], $allowed_roles)) {
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name']; // from role_task
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role_id'] = $user['role_id'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid credentials or insufficient privileges.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - A1-Financial Solutions</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .main-container {
            display: flex;
            height: 100vh;
        }

        .welcome-section {
            flex: 1;
            padding: 40px;
            background: linear-gradient(135deg, #1e5799 0%, #207cca 51%, #2989d8 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .welcome-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .benefits-list, .requirements-list {
            margin: 20px 0;
            padding-left: 20px;
        }

        .benefits-list li, .requirements-list li {
            margin-bottom: 10px;
            position: relative;
            list-style-type: none;
            padding-left: 30px;
        }

        .benefits-list li::before {
            content: "✓";
            color: #4CAF50;
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .contact-info {
            margin-top: 30px;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .social-media {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .social-media img {
            width: 30px;
            height: 30px;
            transition: transform 0.3s;
        }

        .social-media img:hover {
            transform: scale(1.2);
        }

        .login-section {
            width: 400px;
            background-color: #f0f2f5;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .logo img {
            max-width: 150px;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .error {
            color: red;
            margin-bottom: 15px;
            text-align: center;
        }

        .button {
            width: 100%;
            padding: 10px;
            background-color: #45a049;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .button:hover {
            background-color: #28a745;
        }

        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: red;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.querySelector(`#${inputId} + .toggle-password`);
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
            }
        }
    </script>
</head>
<body>
    <div class="main-container">
        <!-- LEFT: Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome to A1-FSA!</h2>
                <p>Need Quick Cash? Yes, We Can Help up to N$10,000 with 30% interest rate.</p>
                <h3>Why choose us?</h3>
                <ul class="benefits-list">
                    <li>Same day approval</li>
                    <li>Loan payout within 30 minutes</li>
                    <li>Easy application process</li>
                </ul>

                <h3>Requirements:</h3>
                <ul class="requirements-list">
                    <li>3 months bank statement</li>
                    <li>Latest payslip</li>
                    <li>ID Copy</li>
                </ul>

                <div class="contact-info">
                    Apply Now!<br>
                    Call: +264815811073
                </div>

            </div>
        </div>

        <!-- RIGHT: Login Section -->
        <div class="login-section">
            <div class="logo">
                <img src="images/1.jpg" alt="A1-Financial Solutions Logo">
            </div>
            <h1>A1-FINANCIAL SOLUTIONS APP</h1>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
                </div>

                <!-- Hidden Role Field -->
                <!--input type="hidden" name="role" value="Administrator"-->

                <button type="submit" class="button">Login</button>
            </form>

            <a href="resetpassword.php" class="forgot-password">Forgot Your Password?</a>
        </div>
    </div>
</body>
</html>
