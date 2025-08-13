<?php 
// ✅ Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Include PDO connection ($conn)
require_once 'cashloan_db.php'; // defines $conn

// Default values
$username = 'Guest';
$role = 'Guest';

// ✅ If user is logged in, fetch username and role
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    try {
        // ✅ Use $conn and correct column name: user_id
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $username = $user['username'];
            $role = $user['role'];
        }
    } catch (PDOException $e) {
        $username = 'Error';
        $role = 'Error';
        // In production, log the error: error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Header</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background: linear-gradient(135deg, #1e5799 0%, #2989d8 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .username {
            font-size: 16px;
            font-weight: 600;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 2px;
        }

        .logout-button {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .logout-button:hover {
            background-color: #cc0000;
            transform: translateY(-2px);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-image {
            height: 50px;
            width: auto;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1;
        }

        .tagline {
            font-size: 12px;
            font-style: italic;
            opacity: 0.9;
            margin-top: 3px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 10px 15px;
                flex-direction: column;
                gap: 15px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
                order: 2;
            }
            
            .logo-container {
                order: 1;
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }
            
            .company-name {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <!-- A1-FINANCIAL SOLUTION CC Logo with Tagline -->
            <div class="logo-container">
                <img src="images/1.jpg" alt="A1-Financial Solutions Logo" class="logo-image">
                <div class="logo-text">
                    <div class="company-name">A1-FINANCIAL SOLUTION CC</div>
                    <div class="tagline">Your trusted financial partner</div>
                </div>
            </div>

            <!-- User Info and Logout Button -->
            <div class="user-info">
                <div class="user-details">
                    <div class="username"><?= htmlspecialchars($username) ?></div>
                    <div class="user-role"><?= htmlspecialchars($role) ?></div>
                </div>
                <button class="logout-button" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </header>
</body>
</html>