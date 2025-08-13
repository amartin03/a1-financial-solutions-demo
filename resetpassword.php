<?php
session_start();

// Include PDO connection
include 'cashloan_db.php'; // Assumes $pdo is your PDO object

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Error: Username does not exist in our system.";
        } else {
            // Validate password complexity
            $password_errors = [];

            if (strlen($new_password) < 8 || strlen($new_password) > 14) {
                $password_errors[] = "Password must be 8–14 characters long.";
            }
            if (!preg_match('/[A-Z]/', $new_password)) {
                $password_errors[] = "Password must contain at least one uppercase letter.";
            }
            if (!preg_match('/[a-z]/', $new_password)) {
                $password_errors[] = "Password must contain at least one lowercase letter.";
            }
            if (!preg_match('/[0-9]/', $new_password)) {
                $password_errors[] = "Password must contain at least one number.";
            }
            if (!preg_match('/[\W]/', $new_password)) {
                $password_errors[] = "Password must contain at least one special character.";
            }
            if ($new_password !== $confirm_password) {
                $password_errors[] = "Passwords do not match.";
            }

            if (!empty($password_errors)) {
                $error = "Password requirements not met:<br>" . implode("<br>", $password_errors);
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                if ($updateStmt->execute([$hashed_password, $username])) {
                    $success = "Password reset successfully! Redirecting to login...";
                    header("Refresh: 2; url=login.php");
                } else {
                    $error = "Failed to update password.";
                }
            }
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
  <title>Reset Password - A1 Financial Solutions</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      min-height: 100vh;
      margin: 0;
      background-color: #f0f2f5;
    }
    .company-name {
        font-weight: bold;
        font-size: 1.3rem; /* optional: adjust size if needed */
         /* optional: center the text */
    }
    .tagline {
        text-align: center;
    }

    .container {
      max-width: 500px;
      width: 100%;
      margin: 40px auto;
      padding: 30px;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    h1 {
      text-align: center;
      margin-bottom: 20px;
    }

    .username-note {
      display: block;
      text-align: center;
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
    }

    .input-wrapper {
      position: relative;
    }

    input[type="text"],
    input[type="password"] {
      width: 90%;
      padding: 10px 40px 10px 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }

    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 1.2rem;
      color: #666;
    }

    .button {
      width: 100%;
      padding: 10px;
      background-color: #2989d8;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
      margin-top: 10px;
    }

    .button:hover {
      background-color: #1e6bb8;
    }

    .back-button {
      display: block;
      margin: 20px auto 0;
      text-align: center;
      background-color: #2989d8;
      padding: 10px 20px;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      width: fit-content;
    }

    .back-button:hover {
      background-color: #1e6bb8;
    }

    .error, .success {
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 6px;
      font-size: 0.9rem;
    }

    .error {
      background-color: #ffe6e6;
      color: #c00;
    }

    .success {
      background-color: #e0ffe0;
      color: #2d862d;
    }

    .password-requirements {
      margin: 15px 0;
      padding: 10px;
      background-color: #f8f9fa;
      border-radius: 6px;
      border-left: 4px solid #3498db;
    }

    .password-requirements h4 {
      margin-top: 0;
      color: #2c3e50;
    }

    .password-requirements ul {
      margin-bottom: 0;
      padding-left: 20px;
    }

    .password-requirements li {
      margin-bottom: 5px;
      color: #666;
      font-size: 0.85rem;
    }

    .password-requirements li.valid {
      color: #27ae60;
    }

    .password-requirements li.invalid {
      color: #e74c3c;
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

    function validatePassword() {
      const password = document.getElementById('new_password').value;
      const confirm = document.getElementById('confirm_password').value;

      document.getElementById('length-req').className = password.length >= 8 && password.length <= 14 ? 'valid' : 'invalid';
      document.getElementById('upper-req').className = /[A-Z]/.test(password) ? 'valid' : 'invalid';
      document.getElementById('lower-req').className = /[a-z]/.test(password) ? 'valid' : 'invalid';
      document.getElementById('number-req').className = /[0-9]/.test(password) ? 'valid' : 'invalid';
      document.getElementById('special-req').className = /[\W]/.test(password) ? 'valid' : 'invalid';
      document.getElementById('match-req').className = password === confirm && password !== '' ? 'valid' : 'invalid';
    }

    document.addEventListener('DOMContentLoaded', function () {
      document.getElementById('new_password').addEventListener('input', validatePassword);
      document.getElementById('confirm_password').addEventListener('input', validatePassword);
    });
  </script>
</head>
<body>
    <div class="header" style="margin-top: 20px;">
        <div class="company-name">A1-FINANCIAL SOLUTIONS</div>
        <div class="tagline">Your Trusted Financial Partner</div>
    </div>
    
    <div class="container">
        <h1>Reset Password</h1>
        <span class="username-note">The username is generated automatically at registration as the first letter of the first name combined with the last name (e.g., jdoe for John Doe).</span>

        <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="resetpassword.php">
        <div class="form-group">
            <label for="username">Username</label>
            <div class="input-wrapper">
            <input type="text" id="username" name="username" required>
            </div>
        </div>

        <div class="password-requirements">
            <h4>Password Requirements:</h4>
            <ul>
            <li id="length-req">8-14 characters long</li>
            <li id="upper-req">At least one uppercase letter (A-Z)</li>
            <li id="lower-req">At least one lowercase letter (a-z)</li>
            <li id="number-req">At least one number (0-9)</li>
            <li id="special-req">At least one special character (!@#$%^&*)</li>
            <li id="match-req">Passwords must match</li>
            </ul>
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <div class="input-wrapper">
            <input type="password" id="new_password" name="new_password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility('new_password')">👁️</span>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrapper">
            <input type="password" id="confirm_password" name="confirm_password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
            </div>
        </div>

        <button type="submit" class="button">Reset Password</button>
        </form>

        <a href="login.php" class="back-button">Back to Login</a>
    </div>
    
    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions. All rights reserved.</p>
    </footer>
</body>
</html>