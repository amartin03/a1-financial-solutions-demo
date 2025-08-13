<?php 
include 'cashloan_db.php';
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role_name = $_POST['role'];

    $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,14}$/';

    if (!preg_match($password_pattern, $password)) {
        $error = "Password must be 8-14 characters long and include at least one lowercase letter, one uppercase letter, one number, and one special character.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists.";
            } else {
                // Fetch role_id from role_task table
                $roleStmt = $conn->prepare("SELECT id FROM role_task WHERE role_name = :role_name");
                $roleStmt->execute(['role_name' => $role_name]);
                $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);

                if ($roleRow) {
                    $role_id = $roleRow['id'];
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert into users table
                    $insertStmt = $conn->prepare("INSERT INTO users 
                        (first_name, last_name, username, email, password, role, role_id) 
                        VALUES (:first_name, :last_name, :username, :email, :password, :role, :role_id)");
                    
                    $insertStmt->execute([
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashed_password,
                        'role' => $role_name,
                        'role_id' => $role_id
                    ]);

                    $success = "User registered successfully!";
                } else {
                    $error = "Invalid role selected.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 40px 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input {
            width: 100%;
            padding-right: 40px;
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
    <script>
        function generateUsername() {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            if (firstName && lastName) {
                const username = firstName.charAt(0).toLowerCase() + lastName.toLowerCase();
                document.getElementById('username').value = username;
            }
        }

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1>User Registration</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="users.php">
        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required oninput="generateUsername()">
        </div>
        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" required oninput="generateUsername()">
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required readonly>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <input type="password" id="password" name="password" minlength="8" maxlength="14" required
                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,14}$"
                       title="Password must be 8-14 characters long and include at least one lowercase letter, one uppercase letter, one number, and one special character.">
                <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="14" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
            </div>
        </div>

        <div class="role-group">
            <label>Role:</label>
            <label><input type="radio" name="role" value="Administrator" required> Administrator</label>
            <label><input type="radio" name="role" value="Accountant"> Accountant</label>
            <label><input type="radio" name="role" value="Assistant"> Assistant</label>
        </div><br>

        <button type="submit" class="button green">
            <i class="fas fa-save"></i>Register
        </button>
        <button type="button" class="button red" onclick="window.location.href='manage_users.php'">Cancel</button>
    </form>
</div>

<footer>
    <p>&copy; 2025 Powered by A1-Financial Solutions</p>
</footer>
</body>
</html>
