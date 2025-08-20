<?php
session_start();
require '../db.php';
require '../vendor/autoload.php';

use OTPHP\TOTP;

if (!isset($_SESSION['temp_user_id'])) {
    header("Location: login.php");
    exit;
}

// --- Brute-Force Protection ---
const MAX_2FA_ATTEMPTS = 5;
if (!isset($_SESSION['2fa_attempts'])) {
    $_SESSION['2fa_attempts'] = 0;
}

if ($_SESSION['2fa_attempts'] >= MAX_2FA_ATTEMPTS) {
    $msg = "❌ Too many failed attempts. Please contact your admin.";
    unset($_SESSION['temp_user_id']); // Force re-login
} else {

$empID = $_SESSION['temp_user_id'];

// Load user data
$stmt = $conn->prepare("SELECT FirstName, TwoFASecret, TwoFARecoveryCode FROM users WHERE ID = ?");
$stmt->bind_param("i", $empID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if user exists and has 2FA configured
if (!$user || empty($user['TwoFASecret'])) {
    // This case should ideally not be reached if the login flow is correct.
    unset($_SESSION['temp_user_id'], $_SESSION['2fa_attempts']);
    header("Location: login.php");
    exit;
}

$totp = TOTP::create($user['TwoFASecret']);
$msg = "";

function login_user($empID, $firstName) {
    $_SESSION['EmployeeID'] = $empID;
    $_SESSION['FirstName'] = $firstName;
    unset($_SESSION['temp_user_id']);
    unset($_SESSION['2fa_attempts']);
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    if ($totp->verify($code)) {
        // TOTP code is valid
        login_user($empID, $user['FirstName']);
    } else {
        // Check recovery codes
        $recoveryCodes = !empty($user['TwoFARecoveryCode']) ? json_decode($user['TwoFARecoveryCode'], true) : [];
        if (is_array($recoveryCodes) && in_array($code, $recoveryCodes, true)) {
            // Recovery code is valid, remove it from the list
            $newCodes = array_values(array_diff($recoveryCodes, [$code]));
            $newCodesJson = json_encode($newCodes);

            // Update the database with the remaining codes
            $updateStmt = $conn->prepare("UPDATE users SET TwoFARecoveryCode = ? WHERE ID = ?");
            $updateStmt->bind_param("si", $newCodesJson, $empID);
            $updateStmt->execute();

            login_user($empID, $user['FirstName']);
        } else {
            // Invalid code, increment failed attempts
            $_SESSION['2fa_attempts']++;
            $msg = "❌ Invalid code. Please try again.";
        }
    }
}
} // Closing the else from brute-force check
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f4f6f9;
            margin: 0;
        }
        .verify-box {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            width: 100%;
            max-width: 380px;
        }
        .verify-box img.logo {
            width: 150px;
            margin-bottom: 1.5rem;
        }
        .verify-box h2 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        .verify-box p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        input {
            padding: 0.8rem;
            width: 100%;
            font-size: 1rem;
            margin-top: 1rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        input:focus {
            border-color: #0078D7;
            outline: none;
        }
        button {
            margin-top: 1rem;
            padding: 0.8rem 1.5rem;
            background: #0078D7;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background: #005fa3;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            margin-top: 1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="verify-box">
        <img src="../images/D-Best.png" alt="Logo" class="logo">
        <h2>Two-Factor Verification</h2>
        <p>Enter the code from your authenticator app.</p>
        <form method="POST">
            <input type="text" name="code" id="2fa_code" maxlength="12" inputmode="numeric" required placeholder="Enter 6-digit or recovery code" autofocus>
            <button type="submit">Verify</button>
        </form>
        <?php if ($msg): ?>
            <div class="error"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
    </div>

    <script>
        // Ensure the input field is focused on page load for better UX.
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('2fa_code');
            if (codeInput) {
                codeInput.focus();
            }
        });
    </script>
</body>
</html>
