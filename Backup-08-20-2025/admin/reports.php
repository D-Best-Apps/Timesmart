<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Dashboard</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .reports-container {
            padding: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .report-card {
            flex: 1 1 300px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .report-card:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-4px);
        }

        .report-card h3 {
            margin-bottom: 0.5rem;
            color: #0078D7;
        }

        .report-card p {
            color: #555;
            margin-bottom: 1rem;
        }

        .report-card a {
            display: inline-block;
            background-color: #0078D7;
            color: #fff;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            text-decoration: none;
        }

        .report-card a:hover {
            background-color: #005fa3;
        }
    </style>
</head>
<body>

<header>
    <img src="../images/D-Best.png" alt="D-Best Logo" class="logo">
    <h1>Reports Dashboard</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="view_punches.php">Timesheets</a>
        <a href="summary.php">Summary</a>
        <a href="reports.php" class="active">Reports</a>
        <a href="manage_users.php">Users</a>
        <a href="attendance.php">Attendance</a>
        <a href="manage_admins.php">Admins</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="dashboard-container">
    <div class="container">
        <h2>Available Reports</h2>
        <div class="reports-container">
            <div class="report-card">
                <h3>Summary Report</h3>
                <p>View total hours, regular, and overtime hours across all employees or individually.</p>
                <a href="summary.php">Open Summary</a>
            </div>

            <div class="report-card">
                <h3>Timesheet Report</h3>
                <p>View and edit detailed punch logs including lunch and break periods per employee.</p>
                <a href="view_punches.php">Open Timesheets</a>
            </div>

            <div class="report-card">
                <h3>Export History <em>(Coming Soon)</em></h3>
                <p>View previously exported PDF or Excel reports with download links and filters.</p>
                <a href="#">Not Available</a>
            </div>

            <div class="report-card">
                <h3>Custom Date Reports <em>(Coming Soon)</em></h3>
                <p>Generate custom reports by selecting exact dates, users, and format preferences.</p>
                <a href="#">Not Available</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
