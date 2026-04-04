<?php
require_once __DIR__ . '/db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit();
}
?>