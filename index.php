<?php
// index.php - redireciona para dashboard ou login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: /ranksistem/pages/login.php');
    exit;
}
header('Location: /ranksistem/pages/dashboard.php');
exit;
