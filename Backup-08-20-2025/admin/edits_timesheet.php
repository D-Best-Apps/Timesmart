<?php 
session_start();
require '../db.php';
date_default_timezone_set('America/Chicago');

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Fetch all pending edits with user info
$stmt = $conn->prepare("SELECT pe.*, u.FirstName, u.LastName FROM pending_edits pe 
                        JOIN users u ON pe.EmployeeID = u.ID 
                        WHERE pe.Status = 'Pending' 
                        ORDER BY pe.SubmittedAt DESC");
$stmt->execute();
$result = $stmt->get_result();

$edits = [];
while ($row = $result->fetch_assoc()) {
    $empID = $row['EmployeeID'];
    $date = $row['Date'];

    // Get original row from timepunches
    $origStmt = $conn->prepare("SELECT * FROM timepunches WHERE EmployeeID = ? AND Date = ?");
    $origStmt->bind_param("is", $empID, $date);
    $origStmt->execute();
    $original = $origStmt->get_result()->fetch_assoc();

    if (!$original) continue;

    // Check the pending_edits
    foreach (['TimeIN', 'LunchStart', 'LunchEnd', 'TimeIN', 'TimeOut'] as $field) {
        if (array_key_exists($field, $row) && !is_null($row[$field]) && $row[$field] !== '' && $row[$field] !== $original[$field]) {
            $edits[] = [
                'ID' => $row['ID'],
                'FirstName' => $row['FirstName'],
                'LastName' => $row['LastName'],
                'Date' => $date,
                'Field' => $field,
                'Original' => $original[$field] ?? '',
                'Requested' => $row[$field],
                'Note' => $row['Note'],
                'Reason' => $row['Reason'],
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Punch Adjustments</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: "Segoe UI", sans-serif;
            margin: 0;
        }
        header {
            background-color: #0078D7;
            color: white;
            padding: 2rem 1rem 0.5rem;
            text-align: center;
        }
        .logo {
            height: 60px;
            margin-bottom: 0.5rem;
        }
        h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }
        nav {
            background-color: #006dd1;
            text-align: center;
            padding: 0.75rem 1rem;
            margin-top: 0;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin: 0 1rem;
            font-weight: bold;
            font-size: 15px;
            padding: 0.5rem 0.75rem;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        .dashboard-container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 15px;
        }
        .approval-table th,
        .approval-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        .approval-table th {
            background-color: #e9edf5;
            color: #333;
        }
        .action-buttons button {
            margin: 0 3px;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .approve-btn {
            background-color: #28a745;
            color: white;
        }
        .reject-btn {
            background-color: #dc3545;
            color: white;
        }
        .note-box {
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        .no-edits {
            text-align: center;
            color: #555;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            margin: 30px auto;
            max-width: 600px;
        }
    </style>
</head>
<body>
<header>
    <img src="../images/D-Best.png" alt="Logo" class="logo">
    <h1>Employee Punch Adjustments</h1>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="view_punches.php">Timesheets</a>
    <a href="summary.php">Summary</a>
    <a href="manage_users.php">Users</a>
    <a href="manage_admins.php">Admins</a>
    <a href="../logout.php">Logout</a>
</nav>

<div class="dashboard-container">
    <?php if (count($edits) === 0): ?>
        <p class="no-edits">✅ No pending time edits to review at the moment.</p>
    <?php else: ?>
        <form method="POST" action="process_edits.php">
            <table class="approval-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Original</th>
                        <th>Requested</th>
                        <th>Note</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edits as $edit): ?>
                        <tr>
                            <td><?= htmlspecialchars($edit['FirstName'] . ' ' . $edit['LastName']) ?></td>
                            <td><?= htmlspecialchars($edit['Date']) ?></td>
                            <td><strong><?= htmlspecialchars($edit['Field']) ?></strong></td>
                            <td><?= htmlspecialchars($edit['Original']) ?></td>
                            <td style="color:#0078D7;"><strong><?= htmlspecialchars($edit['Requested']) ?></strong></td>
                            <td><?= htmlspecialchars($edit['Note']) ?: '-' ?></td>
                            <td class="note-box"><?= htmlspecialchars($edit['Reason']) ?></td>
                            <td class="action-buttons">
                                <button type="submit" class="approve-btn" name="action[<?= $edit['ID'] ?>][<?= $edit['Field'] ?>]" value="approve">Approve</button>
                                <button type="submit" class="reject-btn" name="action[<?= $edit['ID'] ?>][<?= $edit['Field'] ?>]" value="reject">Reject</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
