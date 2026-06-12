<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";

require_user();

$user_id = (int)($_SESSION["user_id"] ?? 0);

function back_with_error(string $msg): void {
    header("Location: upload_lost_item.php?error=" . urlencode($msg));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: upload_lost_item.php");
    exit;
}

$item_name   = trim($_POST["item_name"] ?? "");
$description = trim($_POST["description"] ?? "");
$item_type   = strtolower(trim($_POST["item_type"] ?? ""));

if ($item_name === "") {
    back_with_error("Item name is required.");
}

if (!in_array($item_type, ["lost", "found"], true)) {
    back_with_error("Invalid item type.");
}

/*
  FOUND items must include an image.
*/
if ($item_type === "found") {
    if (!isset($_FILES["picture"]) || ($_FILES["picture"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        back_with_error("For FOUND items, uploading a picture is required.");
    }
}

$pictureName = null;

/*
  Handle image upload (optional for LOST, required for FOUND).
*/
if (isset($_FILES["picture"]) && ($_FILES["picture"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    if (($_FILES["picture"]["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        back_with_error("Upload error. Please try again.");
    }

    // Max 5MB
    if (($_FILES["picture"]["size"] ?? 0) > 5 * 1024 * 1024) {
        back_with_error("Image too large. Maximum 5MB.");
    }

    if (!isset($_FILES["picture"]["tmp_name"]) || !is_uploaded_file($_FILES["picture"]["tmp_name"])) {
        back_with_error("Upload failed. Please try again.");
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES["picture"]["tmp_name"]);
    finfo_close($finfo);

    $allowed = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    if (!in_array($mime, $allowed, true)) {
        back_with_error("Invalid image type. Only JPG, PNG, GIF, WEBP allowed.");
    }

    // Create uploads folder if not exists
    $uploadDir = __DIR__ . "/uploads";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES["picture"]["name"], PATHINFO_EXTENSION));
    if ($ext === "") {
        // fallback extension based on mime
        $ext = match ($mime) {
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/gif"  => "gif",
            "image/webp" => "webp",
            default => "img"
        };
    }

    $pictureName = "item_" . $user_id . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;

    $destination = $uploadDir . "/" . $pictureName;

    if (!move_uploaded_file($_FILES["picture"]["tmp_name"], $destination)) {
        back_with_error("Failed to save image.");
    }
}

// Insert into DB
$stmt = $conn->prepare("
  INSERT INTO lost_items (user_id, item_name, description, item_type, picture, date_lost)
  VALUES (?, ?, ?, ?, ?, NOW())
");
if (!$stmt) {
    back_with_error("Database error. Please try again.");
}

$stmt->bind_param("issss", $user_id, $item_name, $description, $item_type, $pictureName);
$stmt->execute();

add_notification(
    $user_id,
    "Item Submitted",
    "Your item '{$item_name}' was submitted as '" . strtoupper($item_type) . "'."
);

header("Location: user_dashboard.php");
exit;
