<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "lost_found_db");

if (isset($_POST['ver_id'])) {
    $ver_id = $_POST['ver_id'];
    $user_answer1 = $_POST['user_answer1'];
    $user_answer2 = $_POST['user_answer2'];

    $stmt = $conn->prepare("UPDATE verifications SET answer1=?, answer2=?, status='answered' WHERE id=?");
    $stmt->bind_param("ssi", $user_answer1, $user_answer2, $ver_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Answers submitted!'); window.location='answer_verification.php';</script>";
}