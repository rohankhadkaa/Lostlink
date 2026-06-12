<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "lost_found_db");

if(isset($_GET['id'], $_GET['action'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if($action == "approve") {
        $conn->query("UPDATE verifications SET status='approved' WHERE id=$id");
    } elseif($action == "reject") {
        $conn->query("UPDATE verifications SET status='rejected' WHERE id=$id");
    }
}

header("Location: admin_dashboard.php");
exit();
?>