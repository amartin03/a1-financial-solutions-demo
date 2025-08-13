<?php
class AuditLogger {
    private $conn;
    private $user_id;
    private $username;
    private $role;

    public function __construct($conn) {
        $this->conn = $conn;
        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set user information from session
        $this->user_id = $_SESSION['user_id'] ?? 0;
        $this->username = $_SESSION['username'] ?? 'system';
        $this->role = $_SESSION['role'] ?? 'guest';
    }

    public function log($action, $details = null) {
        try {
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt = $this->conn->prepare("
                INSERT INTO audit_log (
                    user_id, username, role, action, details, ip_address, user_agent
                ) VALUES (
                    :user_id, :username, :role, :action, :details, :ip_address, :user_agent
                )
            ");
            
            $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':role', $this->role);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log to error log if database logging fails
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
    }

    private function getClientIP() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return 'unknown';
    }
}
?>