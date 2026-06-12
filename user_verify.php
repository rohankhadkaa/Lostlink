<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "lost_found_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get item ID from URL
$item_id = $_GET['item_id'] ?? 0;

// Fetch item info
$item_result = $conn->query("SELECT * FROM lost_items WHERE id=$item_id AND user_id=".$_SESSION['user_id']);
$item = $item_result->fetch_assoc();

if (!$item) {
    die("Item not found or not allowed.");
}

if (isset($_POST['submit'])) {
    $answer1 = $_POST['answer1'];
    $answer2 = $_POST['answer2'];

    // Insert verification record, status is pending by default
    $stmt = $conn->prepare("INSERT INTO verifications (item_id, user_id, answer1, answer2, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iiss", $item['id'], $_SESSION['user_id'], $answer1, $answer2);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Answers submitted. Waiting for admin approval.');window.location='user_dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Item</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Verify Lost Item: <?= htmlspecialchars($item['item_name']) ?></h2>
    <p><?= htmlspecialchars($item['description']) ?></p>
    <?php if ($item['picture']): ?>
        <img src="uploads/<?= $item['picture'] ?>" style="max-width:200px;">
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Question 1</label>
            <input type="text" name="answer1" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Question 2</label>
            <input type="text" name="answer2" class="form-control" required>
        </div>
        <button type="submit" name="submit" class="btn btn-primary">Submit Answers</button>
    </form>
</div>
</body>
</html>