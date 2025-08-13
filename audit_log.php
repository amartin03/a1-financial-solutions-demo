<?php
include 'header.php';
include 'cashloan_db.php';
include 'audit_logger.php';

$auditLogger = new AuditLogger($conn);

// Pagination setup
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get audit logs
$sql = "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total logs
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM audit_log");
$countStmt->execute();
$totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalLogs / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Log</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .button {
            padding: 8px 15px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .button.blue {
            background-color: #207cca;
            color: white;
        }
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        .audit-log {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .audit-log th, .audit-log td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .audit-log th {
            background-color: #207cca;
            color: white;
        }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        .pagination a {
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
        }
        .pagination a.current {
            background-color: #3498db;
            color: white;
        }
        .json-details {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .button-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Audit Log</h1>
        <div class="button-container">
            <button onclick="window.location.href='manage_users.php'" class="button blue">
                <i class="fas fa-users"></i> Manage Users
            </button>
            <button onclick="window.location.href='export_auditlog.php'" class="button green">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
        
        <table class="audit-log">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['role']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td class="json-details" title="<?= htmlspecialchars($log['details']) ?>">
                        <?= htmlspecialchars($log['details']) ?>
                    </td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="audit_log.php?page=<?= $page - 1 ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="audit_log.php?page=<?= $i ?>" <?= ($i == $page) ? 'class="current"' : '' ?>>
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="audit_log.php?page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>