<?php
require_once __DIR__ . "/auth.php";
require_login();

if (($_SESSION["role"] ?? "") === "admin") {
  header("Location: admin_dashboard.php");
  exit;
}
header("Location: user_dashboard.php"); // if you already have it
exit;