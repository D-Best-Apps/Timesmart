<?php
session_start();
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Brute-Force Protection ---
    $MAX_LOGIN_ATTEMPTS = 5;
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }

    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $error = '';

    if ($_SESSION['login_attempts'] >= $MAX_LOGIN_ATTEMPTS) {
        $error = "Too many failed login attempts. Please try again later.";
    } elseif (empty($name) || empty($password)) {
        $error = "Please enter both name and password.";
    } else {
        $parts = explode(' ', $name);
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        if ($first && $last) {
            $stmt = $conn->prepare("SELECT ID, FirstName, LastName, Pass, TwoFAEnabled FROM users WHERE FirstName = ? AND LastName = ?");
            $stmt->bind_param("ss", $first, $last);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['Pass'])) {
                    unset($_SESSION['login_attempts']); // Clear attempts on success
                    session_regenerate_id(true);

                    // If 2FA is enabled, redirect to verify_2fa.php
                    if ($user['TwoFAEnabled']) {
                        $_SESSION['temp_user_id'] = $user['ID'];
                        $_SESSION['temp_first_name'] = $user['FirstName'];
                        header("Location: verify_2fa.php");
                        exit;
                    }

                    // Normal login (no 2FA)
                    $_SESSION['EmployeeID'] = $user['ID'];
                    $_SESSION['FirstName'] = $user['FirstName'];

                    // Optional: record login
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $logStmt = $conn->prepare("INSERT INTO login_logs (EmployeeID, IP) VALUES (?, ?)");
                    $logStmt->bind_param("is", $user['ID'], $ip);
                    $logStmt->execute();

                    header("Location: dashboard.php");
                    exit;
                }
            }
        }

        $_SESSION['login_attempts']++; // Increment attempts on failure
        $error = "Invalid login.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<!-- 🌐 Desktop Nav -->
<header class="topnav desktop-only">
  <div class="topnav-left">
    <img src="../images/D-Best.png" class="nav-logo" alt="Logo">
    <span class="nav-title">D-BEST TimeSmart</span>
  </div>
  <div class="topnav-right">
    <span class="nav-date"><?= date('F j, Y') ?></span>
    <a href="../index.php">🏠 Home</a>
    <a href="./login.php">🔐 User Login</a>
    <div class="dropdown">
      <button class="dropbtn">⏱ Settings ▾</button>
      <div class="dropdown-content">
        <a href="../admin/login.php">Admin Login</a>
        <a href="../admin/reports.php">Timeclock Reports</a>
      </div>
    </div>
  </div>
</header>

<!-- 📱 Mobile Banner -->
<div class="mobile-banner mobile-only">
  <img src="../images/D-Best.png" alt="Logo" class="nav-logo">
  <span class="nav-title">D-BEST TimeSmart</span>
</div>

<!-- 📱 Mobile Menu Trigger -->
<nav class="mobile-nav mobile-only">
  <a href="../index.php">🏠 Home</a>
  <a href="./login.php">🔐 User Login</a>
  <button class="menu-toggle" id="menuBtn">☰</button>
</nav>

<!-- 📱 Modal Mobile Menu -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay">
  <div class="mobile-menu-content">
    <button class="close-mobile-menu" id="closeMenu">&times;</button>
    <a href="../admin/admin.php">🛠️ Administration</a>
    <a href="../reports.php">📊 Reports</a>
    <a href="../clock.php">⏱️ Export Hours</a>
  </div>
</div>

<div class="wrapper">
    <div class="main">
        <div class="login-container">
            <h2>Employee Login</h2>
            <?php if (!empty($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            <form method="POST">
                <input type="text" name="name" id="nameInput" placeholder="Start typing name..." required autocomplete="off">
                <ul id="suggestions" class="suggestion-box"></ul>
                <input type="password" name="password" placeholder="PIN (usually Employee ID)" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</div>

<footer>
  <p>D-BEST TimeSmart &copy; <?= date('Y') ?>. All rights reserved.</p>
  <p style="margin-top: 0.3rem;">
    <a href="/docs/privacy.php" style="color:#e0e0e0; text-decoration:none; margin-right:15px;">Privacy Policy</a>
    <a href="/docs/terms.php" style="color:#e0e0e0; text-decoration:none;">Terms of Use</a>
    <a href="/docs/report.php" style="color:#e0e0e0; text-decoration:none;">Report Issues</a>
  </p>
</footer>

<script>
const nameInput = document.getElementById("nameInput");
const suggestionsBox = document.getElementById("suggestions");

nameInput.addEventListener("input", () => {
    const query = nameInput.value.trim();
    if (query.length < 2) {
        suggestionsBox.innerHTML = '';
        return;
    }

    fetch(`autocomplete.php?search=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            suggestionsBox.innerHTML = '';
            data.forEach(name => {
                const li = document.createElement("li");
                li.textContent = name;
                li.addEventListener("click", () => {
                    nameInput.value = name;
                    suggestionsBox.innerHTML = '';
                });
                suggestionsBox.appendChild(li);
            });
        });
});
</script>
<script src="../js/script.js"></script>
</body>
</html>