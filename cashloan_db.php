<?php
$db_sname = "localhost";
$db_uname = "root";
$db_password = "";
$db_name = "cashloan_db";

try {
    $dsn = "mysql:host=$db_sname;dbname=$db_name;charset=utf8mb4";
    $conn = new PDO($dsn, $db_uname, $db_password);

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "You are connected to 'cashloan_db'!"; // ❌ Remove or comment this line

} catch (PDOException $e) {
    echo "Could not connect to '$db_name'! Error: " . $e->getMessage();
}
?>
