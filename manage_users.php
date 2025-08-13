<?php
// Database connection
require_once 'cashloan_db.php';
include 'header.php'; // Assumes $pdo is already defined as PDO instance

// Pagination
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

try {
    // Get total user count
    $stmtTotal = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int) $stmtTotal->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);

    // Get users for current page
    $stmt = $conn->prepare("SELECT * FROM users LIMIT :offset, :perPage");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Header Styles */
        .page-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .button {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        .button.gray { background-color: gray; color: white; }
        .button.green { background-color: #4caf50; color: white; }
        .button.orange { background-color: orange; color: white; }
        .button.blue { background-color: #207cca; color: white; }
        .button.red { background-color: #f44336; color: white; }
        .button.green:hover { background: #2ecc71; }
        .button.orange:hover { background: #e67e22; }
        .button.blue:hover { background: #3498db; }
        .button.red:hover { background: red; 
        }

        .action-icons {
            display: flex;
            gap: 8px;
        }


        .action-icons a {
            color: #7f8c8d;
            transition: color 0.3s;
        }

        .action-icons a:hover {
            color: #3498db;
        }

        /* Search and Filter Section */
        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #2ecc71;
        }

        .filter-options {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-options select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .users-table {
                display: block;
                overflow-x: auto;
            }
            
            .search-filter {
                flex-direction: column;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
        }
        .modal-content {
            background: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .modal-content p {
            margin-bottom: 20px;
        }
        .modal-actions {
            display: flex;
            justify-content: space-around;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-actions {
            text-align: right;
            margin-top: 20px;
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
        // Delete User
       function confirmDelete(userId) {
            document.getElementById("deleteUserId").value = userId;
            document.getElementById("deleteConfirmModal").style.display = "block";
        }

        function closeDeleteModal() {
            document.getElementById("deleteConfirmModal").style.display = "none";
        }

        // Filter Users
        function filterUsers() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const firstName = row.cells[0].textContent.toLowerCase();
                const lastName = row.cells[1].textContent.toLowerCase();
                const username = row.cells[2].textContent.toLowerCase();
                const role = row.cells[4].textContent;

                const matchesSearch = firstName.includes(searchInput) || lastName.includes(searchInput) || username.includes(searchInput);
                const matchesRole = roleFilter === "" || role === roleFilter;

                if (matchesSearch && matchesRole) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Navigation Buttons -->
        <div class="navigation-buttons">
            <button onclick="window.location.href='dashboard.php'" class="button blue">
                <i class="fas fa-tachometer-alt"></i>Dashboard</button>
            <button onclick="window.location.href='check_access.php'" class="button orange">Role-Based Access Control</button>
            <button onclick="window.location.href='roles&tasks.php'" class="button red">
                <i class="fas fa-tasks"></i>Roles & Tasks</button>
            <button onclick="window.location.href='users.php'" class="button green">
                <i class="fas fa-users"></i>User Registration</button>
            <button onclick="window.location.href='audit_log.php'" class="button gray">Audit Log</button>
        </div>

        <h1>Manage Users</h1>

        <!-- Search and Filter -->
        <div class="search-filter">
            <input type="text" id="searchInput" placeholder="Search by name, username, or role">
            <select id="roleFilter">
                <option value="">All Roles</option>
                <option value="Administrator">Administrator</option>
                <option value="Accountant">Accountant</option>
                <option value="Assistant">Assistant</option>
            </select>
            <button class="button blue" onclick="filterUsers()"><i class="fas fa-filter"></i>Search</button>
        </div>

        <!-- Users List Table -->
        <table>
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['first_name']) ?></td>
                        <td><?= htmlspecialchars($user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td>
                            <div class="action-buttons">
                                <!-- Edit Button -->
                                <button class="edit" onclick="location.href='edit_user.php?user_id=<?= $user['user_id'] ?>'">Edit</button>
                                <!-- Delete Button -->
                                <button class="delete" onclick="confirmDelete(<?= $user['user_id'] ?>)">Delete</button>
                                <!-- Reset Password Button -->
                                <button class="reset-password" onclick="location.href='reset_password_admin.php?user_id=<?= $user['user_id'] ?>'">Reset Password</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this user?</p>
            <form id="deleteForm" method="GET" action="delete_user.php">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-actions">
                    <button type="submit" class="button green">Yes</button>
                    <button type="button" class="button red" onclick="closeDeleteModal()">No</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>