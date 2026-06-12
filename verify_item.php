<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "lost_found_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get verification ID
$verification_id = (int)($_GET['verification_id'] ?? 0);

// Fetch verification + lost item + user
$sql = "SELECT v.*, li.item_name, li.description, li.picture, u.full_name
        FROM verifications v
        JOIN lost_items li ON v.item_id = li.id
        JOIN users u ON v.user_id = u.id
        WHERE v.id=$verification_id";

$result = $conn->query($sql);
$record = $result->fetch_assoc();

if (!$record) die("Record not found.");

// Handle admin approve/reject
if (isset($_POST['update_status'])) {
    $status = $_POST['status']; // approved or rejected

    // Update verification status
    $stmt = $conn->prepare("UPDATE verifications SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $verification_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Verification $status'); window.location='admin_dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Verification</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Review Answers for: <?= htmlspecialchars($record['item_name']) ?></h2>
    <p><strong>Description:</strong> <?= htmlspecialchars($record['description']) ?></p>
    <p><strong>Submitted by:</strong> <?= htmlspecialchars($record['full_name']) ?></p>
    <?php if($record['picture']): ?>
        <img src="uploads/<?= $record['picture'] ?>" style="max-width:200px;"><br>
    <?php endif; ?>

    <h4>User Answers</h4>
    <p><strong>Answer 1:</strong> <?= htmlspecialchars($record['answer1']) ?></p>
    <p><strong>Answer 2:</strong> <?= htmlspecialchars($record['answer2']) ?></p>

    <form method="POST">
        <div class="mb-3">
            <label>Update Status</label>
            <select name="status" class="form-control" required>
                <option value="approved" <?= $record['status']=='approved'?'selected':'' ?>>Approve</option>
                <option value="rejected" <?= $record['status']=='rejected'?'selected':'' ?>>Reject</option>
            </select>
        </div>
        <button type="submit" name="update_status" class="btn btn-success">Update</button>
    </form>
</div>
</body>
</html>