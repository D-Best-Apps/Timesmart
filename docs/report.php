<?php require '../db.php'; 
date_default_timezone_set('America/Chicago'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Report Issues - D-Best TimeClock</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#126ab3">
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
    <a href="../user/login.php">🔐 User Login</a>
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
  <a href="../user/login.php">🔐 User Login</a>
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
  <main class="main card" style="max-width: 600px; margin: auto;">
    <h1>Report an Issue</h1>
    <form action="submit_issue.php" method="POST">
      <label for="name">Your Name</label>
      <input type="text" id="name" name="name" required>

      <label for="email">Your Email</label>
      <input type="email" id="email" name="email" required>

      <label for="issue">Describe the issue</label>
      <textarea id="issue" name="issue" rows="6" required></textarea>

      <button type="submit">Submit Report</button>
    </form>
  </main>
</div>

<footer>
  <p>D-BEST TimeSmart &copy; <?= date('Y') ?>. All rights reserved.</p>
  <p style="margin-top: 0.3rem;">
    <a href="/docs/privacy.php" style="color:#e0e0e0; text-decoration:none; margin-right:15px;">Privacy Policy</a>
    <a href="/docs/terms.php" style="color:#e0e0e0; text-decoration:none;">Terms of Use</a>
    <a href="/docs/report.php" style="color:#e0e0e0; text-decoration:none;">Report Issues</a>
  </p>
</footer>

<script src="../js/script.js"></script>
</body>
</html>