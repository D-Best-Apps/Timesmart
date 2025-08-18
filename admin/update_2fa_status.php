<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_POST['id']);
$action = $_POST['action'];

$sql = '';
$params = [];

switch ($action) {
    case 'enable':
        $sql = "UPDATE users SET TwoFAEnabled = 1 WHERE ID = ?";
        break;
    case 'disable':
        $sql = "UPDATE users SET TwoFAEnabled = 0, TwoFASecret = NULL, TwoFARecoveryCode = NULL WHERE ID = ?";
        break;
    case 'lock':
        $sql = "UPDATE users SET AdminOverride2FA = 0 WHERE ID = ?";
        break;
    case 'unlock':
        $sql = "UPDATE users SET AdminOverride2FA = 1 WHERE ID = ?";
        break;
}

if ($sql) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: manage_users.php");
exit;
