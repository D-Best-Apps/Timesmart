<?php
require_once '../db.php';
session_start();


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: manage_users.php");
    exit;
}

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE ID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit;
}

// Check if user is admin
$adminUsername = $user['FirstName'] . strtoupper(substr($user['LastName'], 0, 1));
$checkAdmin = $conn->prepare("SELECT ID FROM admins WHERE username = ?");
$checkAdmin->bind_param("s", $adminUsername);
$checkAdmin->execute();
$checkAdmin->store_result();
$isAdmin = $checkAdmin->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['FirstName']);
    $lastName = trim($_POST['LastName']);
    $email = empty(trim($_POST['Email'])) ? NULL : trim($_POST['Email']);
    $tagID = empty(trim($_POST['TagID'])) ? NULL : trim($_POST['TagID']);
    $clockStatus = trim($_POST['ClockStatus']);
    $office = trim($_POST['Office']);
    $jobTitle = empty(trim($_POST['JobTitle'])) ? NULL : trim($_POST['JobTitle']);
    $phone = empty(trim($_POST['PhoneNumber'])) ? NULL : trim($_POST['PhoneNumber']);
    $password = $_POST['Password'];
    $makeAdmin = isset($_POST['MakeAdmin']);
    $enable2FA = isset($_POST['Enable2FA']);
    $recoveryCode = empty(trim($_POST['RecoveryCode'])) ? NULL : trim($_POST['RecoveryCode']);

    if ($firstName && $lastName && $clockStatus && $office) { // Removed email from required fields
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("UPDATE users SET FirstName = ?, LastName = ?, Email = ?, TagID = ?, ClockStatus = ?, Office = ?, Pass = ?, JobTitle = ?, PhoneNumber = ?, TwoFAEnabled = ?, TwoFARecoveryCode = ? WHERE ID = ?");
            $stmt->bind_param("ssssssssissi", $firstName, $lastName, $email, $tagID, $clockStatus, $office, $hashed, $jobTitle, $phone, $enable2FA, $recoveryCode, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET FirstName = ?, LastName = ?, Email = ?, TagID = ?, ClockStatus = ?, Office = ?, JobTitle = ?, PhoneNumber = ?, TwoFAEnabled = ?, TwoFARecoveryCode = ? WHERE ID = ?");
            $stmt->bind_param("sssssssissi", $firstName, $lastName, $email, $tagID, $clockStatus, $office, $jobTitle, $phone, $enable2FA, $recoveryCode, $id);
        }

        $stmt->execute();

        // Handle Admin switch
        if ($makeAdmin) {
            $check = $conn->prepare("SELECT ID FROM admins WHERE username = ?");
            $check->bind_param("s", $adminUsername);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $adminPass = !empty($password) ? $hashed : $user['Pass'];
                $insert = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $insert->bind_param("ss", $adminUsername, $adminPass);
                $insert->execute();
            }
        } else {
            $delete = $conn->prepare("DELETE FROM admins WHERE username = ?");
            $delete->bind_param("s", $adminUsername);
            $delete->execute();
        }

        header("Location: manage_users.php");
        exit;
    } else {
        $error = "All fields except password are required.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User - D-Best TimeClock</title>
    <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #f4f6f9;
        margin: 0;
        padding: 0;
        color: #333;
    }

    .banner {
        background-color: #0078D7;
        color: white;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .logo {
        height: 60px;
        margin-bottom: 1rem;
    }

    nav {
        margin-top: 1rem;
    }

    nav a {
        color: white;
        text-decoration: none;
        margin: 0 1rem;
        font-weight: bold;
    }

    nav a:hover {
        text-decoration: underline;
    }

    .uman-container {
        max-width: 700px;
        margin: 2rem auto;
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .uman-container h2 {
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .uman-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .uman-form label {
        font-weight: bold;
        display: block;
        margin-bottom: 0.25rem;
    }

    .uman-form input,
    .uman-form select {
        width: 100%;
        padding: 0.7rem;
        font-size: 1rem;
        border-radius: 6px;
        border: 1px solid #ccc;
    }

    .switch-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1rem;
        padding: 0.5rem 0;
        border-top: 1px solid #eee;
    }

    .switch-label {
        font-weight: bold;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #0078D7;
    }

    input:checked + .slider:before {
        transform: translateX(22px);
    }

    .modal-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 2rem;
    }

    .btn {
        padding: 0.7rem 1.4rem;
        font-size: 1rem;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn.primary {
        background-color: #0078D7;
        color: white;
    }

    .btn.primary:hover {
        background-color: #005fa3;
    }

    .btn.danger {
        background-color: #dc3545;
        color: white;
    }

    .alert.error {
        background-color: #ffecec;
        color: #b30000;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        font-weight: bold;
    }
</style>

</head>
<body>

<header class="banner">
    <img src="../images/D-Best.png" alt="D-Best Logo" class="logo">
    <h1>Edit User - <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?></h1>
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

<div class="uman-container">
    <h2>Edit User</h2>
    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="uman-form">
        <label>First Name
            <input type="text" name="FirstName" value="<?= htmlspecialchars($user['FirstName']) ?>" required>
        </label>

        <label>Last Name
            <input type="text" name="LastName" value="<?= htmlspecialchars($user['LastName']) ?>" required>
        </label>

        <label>Email
            <input type="text" name="Email" value="<?= htmlspecialchars($user['Email']) ?>">
        </label>

        <label>Tag ID
            <input type="text" name="TagID" value="<?= htmlspecialchars($user['TagID']) ?>">
        </label>

        <label>Clock Status
            <select name="ClockStatus" required>
                <option value="In" <?= $user['ClockStatus'] === 'In' ? 'selected' : '' ?>>In</option>
                <option value="Out" <?= $user['ClockStatus'] === 'Out' ? 'selected' : '' ?>>Out</option>
                <option value="Break" <?= $user['ClockStatus'] === 'Break' ? 'selected' : '' ?>>Break</option>
                <option value="Lunch" <?= $user['ClockStatus'] === 'Lunch' ? 'selected' : '' ?>>Lunch</option>
            </select>
        </label>

        <label>Office
            <select name="Office" required>
                <option value="Fort Smith" <?= $user['Office'] === 'Fort Smith' ? 'selected' : '' ?>>Fort Smith</option>
                <option value="Fayetteville" <?= $user['Office'] === 'Fayetteville' ? 'selected' : '' ?>>Fayetteville</option>
                <option value="Overseas" <?= $user['Office'] === 'Overseas' ? 'selected' : '' ?>>Overseas</option>
            </select>
        </label>

        <label>Job Title
            <input type="text" name="JobTitle" value="<?= htmlspecialchars($user['JobTitle']) ?>">
        </label>

        <label>Phone Number
            <input type="text" name="PhoneNumber" value="<?= htmlspecialchars($user['PhoneNumber']) ?>">
        </label>

        <label>Password <small>(leave blank to keep current)</small>
            <input type="password" name="Password">
        </label>

        <label>Recovery Code
            <input type="text" name="RecoveryCode" value="<?= htmlspecialchars($user['TwoFARecoveryCode'] ?? '') ?>">
        </label>

        <div class="switch-wrapper">
    <span class="switch-label">Enable 2FA</span>
    <label class="switch">
        <input type="checkbox" name="Enable2FA" <?= $user['TwoFAEnabled'] ? 'checked' : '' ?>>
        <span class="slider"></span>
    </label>
</div>

<div class="switch-wrapper">
    <span class="switch-label">Make Admin (<?= $adminUsername ?>)</span>
    <label class="switch">
        <input type="checkbox" name="MakeAdmin" <?= $isAdmin ? 'checked' : '' ?>>
        <span class="slider"></span>
    </label>
</div>


        <div class="modal-actions">
            <a href="manage_users.php" class="btn danger">Cancel</a>
            <button type="submit" class="btn primary">Save Changes</button>
        </div>
    </form>
</div>

</body>
</html>