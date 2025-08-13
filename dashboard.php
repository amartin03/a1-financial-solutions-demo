<?php
// Database connection using PDO
require_once 'cashloan_db.php'; // Assumes $pdo is defined
require_once 'header.php';

try {
    // Total Loans
    $stmtLoans = $conn->query("SELECT COUNT(*) AS total_loans FROM loans");
    $totalLoans = $stmtLoans->fetch(PDO::FETCH_ASSOC)['total_loans'];

    // Total Payments Paid Out
    $stmtPayments = $conn->query("SELECT SUM(amount) AS total_payments FROM payments");
    $totalPayments = $stmtPayments->fetch(PDO::FETCH_ASSOC)['total_payments'] ?? 0;

    // Total Payments Received
    $stmtPaymentsReceived = $conn->query("SELECT SUM(amount_received) AS total_payments_received FROM payments_received");
    $totalPaymentsReceived = $stmtPaymentsReceived->fetch(PDO::FETCH_ASSOC)['total_payments_received'] ?? 0;

    // Loans by Status
    $stmtStatus = $conn->query("SELECT status, COUNT(*) AS count FROM loans GROUP BY status");
    $loansByStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    // Current month (YYYY-MM)
    $currentMonth = date('Y-m');

    // Total Loans This Month
    $stmtLoansMonth = $conn->prepare("SELECT COUNT(*) AS total_loans_this_month FROM loans WHERE DATE_FORMAT(disbursement_date, '%Y-%m') = ?");
    $stmtLoansMonth->execute([$currentMonth]);
    $totalLoansThisMonth = $stmtLoansMonth->fetch(PDO::FETCH_ASSOC)['total_loans_this_month'];

    // Total New Customers This Month
    $stmtNewCustomers = $conn->prepare("SELECT COUNT(*) AS total_new_customers FROM customers WHERE DATE_FORMAT(date_created, '%Y-%m') = ?");
    $stmtNewCustomers->execute([$currentMonth]);
    $totalNewCustomers = $stmtNewCustomers->fetch(PDO::FETCH_ASSOC)['total_new_customers'];

    // Get pending tasks
    $stmtPendingTasks = $conn->query("SELECT returns FROM mytasks WHERE status = 'pending'");
    $pendingTasks = $stmtPendingTasks->fetchAll(PDO::FETCH_COLUMN);

    // Get investment amount
    $stmtInvestment = $conn->query("SELECT SUM(amount_invested) AS total_investment FROM investments");
    $totalInvestment = $stmtInvestment->fetch(PDO::FETCH_ASSOC)['total_investment'] ?? 0;
    $amountRemaining = $totalInvestment - $totalPayments;

    // Calculate Profit and Loss
    // Get all completed loans fees (profit)
    $stmtProfit = $conn->query("
        SELECT SUM(l.fees) AS total_profit 
        FROM loans l
        JOIN payments_received pr ON l.loan_id = pr.loan_id
        WHERE pr.status = 'Completed'
    ");
    $totalProfit = $stmtProfit->fetch(PDO::FETCH_ASSOC)['total_profit'] ?? 0;

    // Get all amount due from non-completed loans (loss)
    $stmtLoss = $conn->query("
        SELECT SUM(pr.amount_due) AS total_loss 
        FROM payments_received pr
        WHERE pr.status != 'Completed' OR pr.status IS NULL
    ");
    $totalLoss = $stmtLoss->fetch(PDO::FETCH_ASSOC)['total_loss'] ?? 0;

    // Calculate net profit/loss
    $netResult = $totalProfit - $totalLoss;

    // Payments Due This Month (Active loans only)
    $stmtPaymentsDue = $conn->prepare("
        SELECT COUNT(*) AS payments_due 
        FROM loans 
        WHERE status = 'Active' 
        AND DATE_FORMAT(due_date, '%Y-%m') = ?
    ");
    $stmtPaymentsDue->execute([$currentMonth]);
    $paymentsDue = $stmtPaymentsDue->fetch(PDO::FETCH_ASSOC)['payments_due'] ?? 0;

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-wrapper {
            display: flex;
            width: 100%;
            max-width: 1900px;
            gap: 20px;
        }
        
        .left-space {
            flex: 1;
            min-width: 100px;
            max-width: 200px;
        }
        
        .content-area {
            display: flex;
            gap: 20px;
            flex: 3;
        }
        
        .main-container {
            flex: 1;
            min-width: 950px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .profit-loss-container {
            width: 250px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
            margin-top: 20px;
        }
        
        .right-space {
            flex: 1;
            min-width: 100px;
            max-width: 200px;
        }
        
        h1 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #3498db;
            cursor: pointer;
        }
        
        .card.payments-due {
            border-left-color: #f39c12; /* Orange border for payments due card */
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .card p {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .card .status-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .button-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .button i {
            margin-right: 8px;
        }
        
        .button.black { background-color: #34495e; color: white; }
        .button.green { background-color: #2ecc71; color: white; }
        .button.blue { background-color: #3498db; color: white; }
        .button.red { background-color: #e74c3c; color: white; }
        .button.orange { background-color: #f39c12; color: white; }
        .button.gray { background-color: #95a5a6; color: white; }
        
        .button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Task Alert Styles */
        .task-alert {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }

        .task-alert p {
            margin: 0;
            font-size: 16px;
            color: #856404;
            font-weight: 500;
            white-space: nowrap;
            animation: scrollText 15s linear infinite;
        }

        /* Profit/Loss Card Styles */
        .pl-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid;
        }
        
        .pl-card.profit {
            border-left-color: #2ecc71;
        }
        
        .pl-card.loss {
            border-left-color: #e74c3c;
        }
        
        .pl-card.net {
            border-left-color: #3498db;
        }
        
        .pl-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .pl-card p {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .profit-amount {
            color: #2ecc71;
        }
        
        .loss-amount {
            color: #e74c3c;
        }
        
        .net-amount {
            color: #3498db;
        }

        @keyframes scrollText {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        @media (max-width: 1200px) {
            .page-wrapper {
                flex-direction: column;
            }
            
            .left-space, .right-space {
                display: none;
            }
            
            .content-area {
                flex-direction: column;
            }
            
            .main-container, .profit-loss-container {
                width: 100%;
                min-width: auto;
            }
            
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .button-container {
                flex-direction: column;
            }
            
            .button {
                width: 100%;
                justify-content: center;
            }

            .task-alert p {
                white-space: normal;
                animation: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="left-space"></div>
        
        <div class="content-area">
            <div class="main-container">
                <h1>Dashboard Overview</h1>

                <!-- Pending Tasks Alert -->
                <?php if (!empty($pendingTasks)): ?>
                    <div class="task-alert">
                        <p>
                            <i class="fas fa-exclamation-circle"></i> 
                            <?php
                            $alertMessages = [];
                            foreach ($pendingTasks as $task) {
                                $taskName = ucwords(str_replace('_', ' ', $task));
                                $alertMessages[] = "Your $taskName is pending for submission to NAMFISA";
                            }
                            echo implode(' • ', $alertMessages);
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Cards -->
                <div class="dashboard">
                    <div class="card">
                        <h3>Total Loans</h3>
                        <p><?= htmlspecialchars($totalLoans) ?></p>
                    </div>
                    
                    <div class="card">
                        <h3>Total Payments Paid-Out</h3>
                        <p><?= htmlspecialchars(number_format($totalPayments, 2)) ?></p>
                    </div>

                    <div class="card">
                        <h3>Total Payments Received</h3>
                        <p><?= htmlspecialchars(number_format($totalPaymentsReceived, 2)) ?></p>
                    </div>
                    
                    <div class="card payments-due" onclick="window.location.href='payments.php'">
                        <h3>Payments Due This Month</h3>
                        <p><?= htmlspecialchars($paymentsDue) ?></p>
                    </div>
                    
                    <div class="card">
                        <h3>Investment Summary</h3>
                        <div class="status-item">
                            <span>Amount Invested:</span>
                            <span><?= htmlspecialchars(number_format($totalInvestment, 2)) ?></span>
                        </div>
                        <div class="status-item">
                            <span>Amount Remaining:</span>
                            <span><?= htmlspecialchars(number_format($amountRemaining, 2)) ?></span>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Loans by Status</h3>
                        <?php foreach ($loansByStatus as $status): ?>
                            <div class="status-item">
                                <span><?= htmlspecialchars(ucfirst($status['status'])) ?>:</span>
                                <span><?= htmlspecialchars($status['count']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="card">
                        <h3>Loans This Month</h3>
                        <p><?= htmlspecialchars($totalLoansThisMonth) ?></p>
                    </div>
                    
                    <div class="card">
                        <h3>New Customers This Month</h3>
                        <p><?= htmlspecialchars($totalNewCustomers) ?></p>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="button-container">
                    <button onclick="window.location.href='customers.php'" class="button black">
                        <i class="fas fa-users"></i> View Customers
                    </button>
                    <button onclick="window.location.href='loan_list.php'" class="button orange">
                        <i class="fas fa-list"></i> View Loans
                    </button>
                    <button onclick="window.location.href='payments.php'" class="button green">
                        <i class="fas fa-money-bill-wave"></i> View Payments
                    </button>
                    <button onclick="window.location.href='payments_received.php'" class="button gray">
                        <i class="fas fa-dollar-sign"></i> View Payments Received
                    </button>
                    <button onclick="window.location.href='mytasks.php'" class="button blue">
                        <i class="fas fa-tasks"></i> My Tasks
                    </button>
                    <button onclick="window.location.href='reports.php'" class="button blue">
                        <i class="fas fa-pen"></i> Reports
                    </button>
                    <?php if ($_SESSION['role'] === 'Administrator'): ?>
                        <button onclick="window.location.href='manage_users.php'" class="button red">
                            <i class="fas fa-user-cog"></i> View Users
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profit/Loss Container -->
            <div class="profit-loss-container">
                <h2>Forex</h2>
                <p style="color: #7f8c8d; margin-top: -10px; margin-bottom: 20px;">LEND WITH PASSION</p>
                
                <div class="pl-card profit">
                    <h3>Total Profit</h3>
                    <p class="profit-amount"><?= htmlspecialchars(number_format($totalProfit, 2)) ?></p>
                </div>
                
                <div class="pl-card loss">
                    <h3>Total Loss</h3>
                    <p class="loss-amount"><?= htmlspecialchars(number_format($totalLoss, 2)) ?></p>
                </div>
                
                <div class="pl-card net">
                    <h3>Net Result</h3>
                    <p class="net-amount"><?= htmlspecialchars(number_format($netResult, 2)) ?></p>
                </div>
            </div>
        </div>
        
        <div class="right-space"></div>
    </div>

    <footer style="width: 100%; text-align: center; margin-top: 20px;">
        <p>&copy; 2025 Powered by A1-Financial Solutions</p>
    </footer>
</body>
</html>