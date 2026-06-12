<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (empty($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function require_admin() {
    require_login();
    $role = strtolower(trim($_SESSION["role"] ?? ""));
    if ($role !== "admin") {
        header("Location: dashboard.php");
        exit;
    }
}

function require_user() {
    require_login();
    $role = strtolower(trim($_SESSION["role"] ?? ""));
    if ($role !== "user") {
        header("Location: dashboard.php");
        exit;
    }
}