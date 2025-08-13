<?php
include 'cashloan_db.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="audit_log_'.date('Y-m-d').'.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Get all audit logs
$sql = "SELECT * FROM audit_log ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start Excel output
echo '<table border="1">';
echo '<tr>';
echo '<th>Timestamp</th>';
echo '<th>User</th>';
echo '<th>Role</th>';
echo '<th>Action</th>';
echo '<th>Details</th>';
echo '<th>IP Address</th>';
echo '<th>User Agent</th>';
echo '</tr>';

foreach ($logs as $log) {
    echo '<tr>';
    echo '<td>'.htmlspecialchars($log['created_at']).'</td>';
    echo '<td>'.htmlspecialchars($log['username']).'</td>';
    echo '<td>'.htmlspecialchars($log['role']).'</td>';
    echo '<td>'.htmlspecialchars($log['action']).'</td>';
    echo '<td>'.htmlspecialchars($log['details']).'</td>';
    echo '<td>'.htmlspecialchars($log['ip_address']).'</td>';
    echo '<td>'.htmlspecialchars($log['user_agent']).'</td>';
    echo '</tr>';
}

echo '</table>';
exit;
?>