<?php
session_start();

// Database connection using PDO
include 'cashloan_db.php'; // defines $conn = new PDO(...)

// ✅ Fetch all roles from role_task
$roleStmt = $conn->query("SELECT * FROM role_task");
$roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch all users
$userStmt = $conn->query("SELECT * FROM users");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for assigning roles
$successMessage = $errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $userId = (int)$_POST['user_id'];
    $roleId = (int)$_POST['role_id'];

    // ✅ Fetch role_name by role ID from role_task
    $roleStmt = $conn->prepare("SELECT role_name FROM role_task WHERE id = ?");
    $roleStmt->execute([$roleId]);
    $roleData = $roleStmt->fetch();

    if ($roleData && isset($roleData['role_name'])) {
        // ✅ Update user's role_id and role
        $updateStmt = $conn->prepare("UPDATE users SET role_id = ?, role = ? WHERE user_id = ?");
        $updated = $updateStmt->execute([$roleId, $roleData['role_name'], $userId]);

        if ($updated) {
            $successMessage = "Role assigned successfully!";
        } else {
            $errorMessage = "Failed to assign role or no changes made.";
        }
    } else {
        $errorMessage = "Invalid role selected.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles & Tasks</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            max-width: 1000px;
            height: 1750px;
            margin: 30px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        .role-section {
            margin-bottom: 35px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        .role-section h2 {
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .task-list {
            list-style-type: none;
            padding-left: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .task-list li {
            padding: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 14px;
        }
        .button {
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .button.primary {
            background-color: #28a745;
            color: white;
        }
        .button.primary:hover {
            background-color: #218838;
        }
        .button.secondary {
            background-color: #6c757d;
            color: white;
        }
        .button.secondary:hover {
            background-color: #5a6268;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #495057;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 16px;
        }
        .success {
            color: #28a745;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #d4edda;
            border-radius: 4px;
        }
        .error {
            color: #dc3545;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 4px;
        }
        .flex-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .role-highlight {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <h1>Roles & Permissions</h1>

    <!-- Display messages -->
    <?php if (!empty($successMessage)): ?>
        <div class="success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <!-- Assign Role Form -->
    <div class="role-section">
        <h2>Assign Role to User</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id">Select User</label>
                <select id="user_id" name="user_id" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']) ?>">
                            <?= htmlspecialchars($user['username']) ?>
                            (Current Role: <span class="role-highlight"><?= htmlspecialchars($user['role'] ?? 'None') ?></span>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="role_id">Select New Role</label>
                <select id="role_id" name="role_id" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_role" class="button primary">Assign Role</button>
        </form>
    </div>

    <!-- Display Roles and Permissions -->
    <?php foreach ($roles as $role): ?>
        <div class="role-section">
            <h2><?= htmlspecialchars($role['role_name']) ?></h2>
            <ul class="task-list">
                <?php
                $permissions = preg_split('/[\n,]+/', $role['allowed_pages']);
                foreach ($permissions as $permission):
                    if (!empty(trim($permission))): ?>
                        <li><?= htmlspecialchars(trim($permission)) ?></li>
                    <?php endif;
                endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <div class="flex-container">
        <button onclick="window.location.href='manage_users.php'" class="button secondary">Back to User Management</button>
        <button onclick="window.location.href='dashboard.php'" class="button secondary">Return to Dashboard</button>
    </div>
</div>

<footer>
    <p>&copy; 2025 Powered by A1-Financial Solutions</p>
</footer>
</body>
</html>