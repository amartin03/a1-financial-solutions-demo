<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    ob_start();
}

include 'header.php';
include 'cashloan_db.php';
include 'audit_logger.php';

$auditLogger = new AuditLogger($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        try {
            $calendar_year = $_POST['calendar_year'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $returns = $_POST['returns']; // Array of selected returns
            
            $quarters = [
                'quarter_1' => isset($_POST['quarter_1']) ? 1 : 0,
                'quarter_2' => isset($_POST['quarter_2']) ? 1 : 0,
                'quarter_3' => isset($_POST['quarter_3']) ? 1 : 0,
                'quarter_4' => isset($_POST['quarter_4']) ? 1 : 0
            ];
            
            // Insert a task for each selected return type
            foreach ($returns as $return_type) {
                $stmt = $conn->prepare("INSERT INTO mytasks (
                    returns, calendar_year, start_date, end_date, 
                    quarter_1, quarter_2, quarter_3, quarter_4
                ) VALUES (
                    :returns, :calendar_year, :start_date, :end_date, 
                    :quarter_1, :quarter_2, :quarter_3, :quarter_4
                )");
                
                $stmt->bindParam(':returns', $return_type);
                $stmt->bindParam(':calendar_year', $calendar_year);
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
                $stmt->bindParam(':quarter_1', $quarters['quarter_1'], PDO::PARAM_INT);
                $stmt->bindParam(':quarter_2', $quarters['quarter_2'], PDO::PARAM_INT);
                $stmt->bindParam(':quarter_3', $quarters['quarter_3'], PDO::PARAM_INT);
                $stmt->bindParam(':quarter_4', $quarters['quarter_4'], PDO::PARAM_INT);
                
                $stmt->execute();
            }
            
            $auditLogger->log("TASK_ADDED", "New tasks created for year: $calendar_year");
            $_SESSION['success'] = "Tasks created successfully!";
            header("Location: mytasks.php");
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: mytasks.php");
            exit;
        }
    }
    
    if (isset($_POST['update_task'])) {
        try {
            $task_id = $_POST['task_id'];
            $status = $_POST['status'];
            
            $submission_date = $status === 'submitted' ? date('Y-m-d') : null;
            
            $stmt = $conn->prepare("UPDATE mytasks SET 
                status = :status,
                submission_date = :submission_date
                WHERE id = :id");
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':submission_date', $submission_date);
            $stmt->bindParam(':id', $task_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $auditLogger->log("TASK_UPDATED", "Task ID: $task_id updated");
                $_SESSION['success'] = "Task updated successfully!";
                header("Location: mytasks.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: mytasks.php");
            exit;
        }
    }
}

// Fetch all tasks
$stmt = $conn->prepare("SELECT * FROM mytasks ORDER BY calendar_year DESC, start_date DESC");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Tasks</title>
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
        
        .button.green {
            background-color: #2ecc71;
            color: white;
        }
        
        .button.blue {
            background-color: #207cca;
            color: white;
        }
        
        .button.orange {
            background-color: #f39c12;
            color: white;
        }
        
        .button.red {
            background-color: #f44336;
            color: white;
        }
        
        .button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        
        .status-submitted {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .quarter-checkboxes, .returns-checkboxes {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }
        
        .quarter-checkbox, .returns-checkboxes {
            display: flex;
            align-items: center;
        }
        
        .quarter-checkbox input, .returns-checkboxes input {
            margin-right: 5px;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 50%;
            max-width: 600px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .button-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>My Tasks</h1>
        
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right; background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right; background:none; border:none; cursor:pointer;">×</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="button-container">
            <button onclick="openAddTaskModal()" class="button green">
                <i class="fas fa-plus"></i> Add New Task
            </button>
            <button onclick="window.location.href='investments.php'" class="button blue">
                <i class="fas fa-plus"></i> Investment
            </button>
            <button onclick="window.location.href='dashboard.php'" class="button blue">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Return Type</th>
                    <th>Year</th>
                    <th>Quarters</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Submission Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $task['returns']))) ?></td>
                    <td><?= htmlspecialchars($task['calendar_year']) ?></td>
                    <td>
                        <?php
                        $quarters = [];
                        if ($task['quarter_1']) $quarters[] = 'Q1';
                        if ($task['quarter_2']) $quarters[] = 'Q2';
                        if ($task['quarter_3']) $quarters[] = 'Q3';
                        if ($task['quarter_4']) $quarters[] = 'Q4';
                        echo implode(', ', $quarters);
                        ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(date('M d, Y', strtotime($task['start_date']))) ?> - 
                        <?= htmlspecialchars(date('M d, Y', strtotime($task['end_date']))) ?>
                    </td>
                    <td class="status-<?= htmlspecialchars($task['status']) ?>">
                        <?= htmlspecialchars(ucfirst($task['status'])) ?>
                    </td>
                    <td><?= $task['submission_date'] ? htmlspecialchars(date('M d, Y', strtotime($task['submission_date']))) : '-' ?></td>
                    <td>
                        <button class="button orange" title="Update Task" onclick="openUpdateModal(<?= $task['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Add Task Modal -->
        <div id="addTaskModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('addTaskModal')">&times;</span>
                <h2>Add New Task</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="calendar_year">Calendar Year</label>
                            <select id="calendar_year" name="calendar_year" required>
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year - 1; $year <= $current_year + 1; $year++) {
                                    echo "<option value=\"$year\"" . ($year == $current_year ? ' selected' : '') . ">$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Returns</label>
                            <div class="returns-checkboxes">
                                <label class="returns-checkbox">
                                    <input type="checkbox" name="returns[]" value="mlr_1_return"> MLR-1 Return (Certificate of Compliance)
                                </label>
                                <label class="returns-checkbox">
                                    <input type="checkbox" name="returns[]" value="mlr_2_return"> MLR-2 Return (Quarterly return)
                                </label>
                                <label class="returns-checkbox">
                                    <input type="checkbox" name="returns[]" value="levy_returns"> Levy Returns
                                </label>
                                <label class="returns-checkbox">
                                    <input type="checkbox" name="returns[]" value="audited_financials"> Audited Financials
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quarters</label>
                            <div class="quarter-checkboxes">
                                <label class="quarter-checkbox">
                                    <input type="checkbox" name="quarter_1" value="1"> Q1 (Jan-Mar)
                                </label>
                                <label class="quarter-checkbox">
                                    <input type="checkbox" name="quarter_2" value="1"> Q2 (Apr-Jun)
                                </label>
                                <label class="quarter-checkbox">
                                    <input type="checkbox" name="quarter_3" value="1"> Q3 (Jul-Sep)
                                </label>
                                <label class="quarter-checkbox">
                                    <input type="checkbox" name="quarter_4" value="1"> Q4 (Oct-Dec)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('addTaskModal')" class="button red">Cancel</button>
                        <button type="submit" name="add_task" class="button green">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Update Task Modal -->
        <div id="updateTaskModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('updateTaskModal')">&times;</span>
                <h2>Update Task</h2>
                <form method="POST">
                    <input type="hidden" id="modal_task_id" name="task_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="submitted">Submitted</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('updateTaskModal')" class="button red">Cancel</button>
                        <button type="submit" name="update_task" class="button green">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>

    <script>
        // Set default dates for new task
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').value = today;
            document.getElementById('end_date').value = today;
        });
        
        function openAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'block';
        }
        
        function openUpdateModal(taskId) {
            document.getElementById('modal_task_id').value = taskId;
            document.getElementById('updateTaskModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>