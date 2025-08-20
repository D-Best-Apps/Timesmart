<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D-Best Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<header>
    <img src="../images/D-Best.png" alt="D-Best Logo" class="logo">
    <h1>Admin Dashboard</h1>
    <nav class="admin-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_users.php">Users</a>
        <a href="manage_admins.php">Admins</a>
        <a href="edits_timesheet.php">Approvals</a>
        <a href="reports.php">Reports</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>
<main class="admin-content">
