<?php
session_start();
$conn = new mysqli("localhost", "root", "", "lost_found_db");

$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $result = $conn->query("
        SELECT li.*, v.id AS verification_id, v.status AS v_status
        FROM lost_items li
        LEFT JOIN verifications v ON li.id = v.item_id
        WHERE li.user_id={$_SESSION['user_id']}
        AND (li.item_name LIKE '%$search%' OR li.description LIKE '%$search%')
        ORDER BY li.date_lost DESC
    ");
} else {
    $result = $conn->query("
        SELECT li.*, v.id AS verification_id, v.status AS v_status
        FROM lost_items li
        LEFT JOIN verifications v ON li.id = v.item_id
        WHERE li.user_id={$_SESSION['user_id']}
        ORDER BY li.date_lost DESC
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2>Welcome <?= htmlspecialchars($_SESSION['name']) ?></h2>

    <a href="logout.php" class="btn btn-danger mb-3">Logout</a>
    <a href="submit_lost_item.php" class="btn btn-primary mb-3">Upload Lost Item</a>

    <!-- SEARCH BAR -->
    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" placeholder="Search your lost items..." value="<?= htmlspecialchars($search) ?>">
    </form>

    <table class="table table-bordered bg-white">
        <thead class="table-dark">
            <tr>
                <th>Item</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row=$result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= $row['date_lost'] ?></td>
                <td>
                    <?php
                        if ($row['v_status']=='approved') {
                            echo "<span class='badge bg-success'>Approved</span>";
                        } elseif ($row['v_status']=='rejected') {
                            echo "<span class='badge bg-danger'>Rejected</span>";
                        } elseif ($row['v_status']=='answered') {
                            echo "<span class='badge bg-info'>Waiting Admin</span>";
                        } else {
                            echo "<span class='badge bg-warning'>Pending</span>";
                        }
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>