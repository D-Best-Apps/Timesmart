<?php
/*************************************************
 * Employee Login (hardened + fixed autocomplete)
 *************************************************/

// ---------- Security Headers ----------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
// IMPORTANT: allow inline script because this page uses inline <script>
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");

// ---------- Error Handling ----------
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ---------- Session Cookie Hardening ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',        // set if needed
    'secure'   => true,      // true in production (HTTPS required)
    'httponly' => true,
    'samesite' => 'Strict',  // 'Lax' if you need cross-site flows
]);
session_start();

// ---------- DB ----------
require '../db.php'; // provides $conn (mysqli)

// ---------- Constants ----------
const MAX_LOGIN_ATTEMPTS_USER = 5;
const LOCKOUT_SECONDS_USER    = 15 * 60;       // 15 minutes
const FAILURE_DELAY_US_LOGIN  = 350000;        // ~350ms

// ---------- Helpers ----------
function ensure_csrf_token(): void {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
function csrf_valid(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}
function login_is_locked(): bool {
    $until = $_SESSION['user_lock_until'] ?? 0;
    return is_int($until) && time() < $until;
}
function record_failure_and_maybe_lock(): void {
    $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS_USER) {
        $_SESSION['user_lock_until'] = time() + LOCKOUT_SECONDS_USER;
    }
}
function clear_failures(): void {
    unset($_SESSION['login_attempts'], $_SESSION['user_lock_until']);
}
function parse_name(string $name): array {
    // Accepts "First Last" or "Last, First"
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if (strpos($name, ',') !== false) {
        [$last, $first] = array_map('trim', explode(',', $name, 2));
    } else {
        $parts = explode(' ', $name, 2);
        $first = trim($parts[0] ?? '');
        $last  = trim($parts[1] ?? '');
    }
    return [$first, $last];
}

// ---------- Init ----------
ensure_csrf_token();
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
$error = '';

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = "Invalid request.";
    } elseif (login_is_locked()) {
        $error = "Too many failed login attempts. Please try again later.";
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || $password === '') {
            $error = "Please enter both name and password.";
        } else {
            [$first, $last] = parse_name($name);

            if ($first !== '' && $last !== '') {
                $stmt = $conn->prepare("SELECT ID, FirstName, LastName, Pass, TwoFAEnabled FROM users WHERE FirstName = ? AND LastName = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $first, $last);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user   = $result ? $result->fetch_assoc() : null;
                    if ($result) { $result->free(); }
                    $stmt->close();
                } else {
                    $user = null; // do not leak DB errors
                }

                $valid = false;
                if ($user && is_string($user['Pass']) && password_verify($password, $user['Pass'])) {
                    $valid = true;
                }

                if ($valid) {
                    clear_failures();
                    session_regenerate_id(true);

                    // Optional: record login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $logStmt = $conn->prepare("INSERT INTO login_logs (EmployeeID, IP) VALUES (?, ?)");
                    if ($logStmt) {
                        $logStmt->bind_param("is", $user['ID'], $ip);
                        $logStmt->execute();
                        $logStmt->close();
                    }

                    if (!empty($user['TwoFAEnabled'])) {
                        // 2FA flow
                        $_SESSION['temp_user_id']     = (int)$user['ID'];
                        $_SESSION['temp_first_name']  = (string)$user['FirstName'];
                        $_SESSION['user_2fa_pending'] = true;
                        header("Location: verify_2fa.php");
                        exit;
                    } else {
                        // Normal login
                        $_SESSION['EmployeeID'] = (int)$user['ID'];
                        $_SESSION['FirstName']  = (string)$user['FirstName'];
                        header("Location: dashboard.php");
                        exit;
                    }
                }
            }

            // Failure path
            usleep(FAILURE_DELAY_US_LOGIN);
            record_failure_and_maybe_lock();
            $error = "Invalid login.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">

    <!-- Existing CSS files -->
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/style.css">

    <!-- Unified look + fixed autocomplete styling -->
    <style>
        :root {
            --bg-color: DarkGray;
            --card-bg: #111827;
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background-color: var(--bg-color);
            color: #e5e7eb;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        }
        .wrapper { display: flex; justify-content: center; }
        .main { width: 100%; display: flex; justify-content: center; }
        .login-container {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 520px;
            margin: 2rem 1rem 3rem;
            text-align: center;
            overflow: visible; /* make sure suggestions are not clipped */
            position: relative; /* for stacking context */
            z-index: 1;
        }
        .login-logo-wrap {
            display: flex; justify-content: center; align-items: center;
            margin-bottom: 1rem;
        }
        .login-logo-wrap img {
            display: block;
            max-width: 200px; width: 100%; height: auto; object-fit: contain;
        }
        .login-container h2 {
            margin: 0.25rem 0 1rem 0;
            color: var(--primary-color);
            letter-spacing: 0.3px;
        }
        .login-container .error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fecaca;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin: 0 0 1rem 0;
            text-align: left;
        }
        .field { text-align: left; margin-bottom: 1rem; position: relative; }
        label { display: block; font-size: 0.9rem; margin-bottom: 0.4rem; color: #cbd5e1; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #374151;
            background: #0b1220;
            color: #000000ff;
            font-size: 1rem;
            line-height: 1.5;
            outline: none;
        }
        input::placeholder { color: #000000ff; }
        input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
        }
        button[type="submit"] {
            width: 100%;
            padding: 0.9rem 1rem;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }
        button[type="submit"]:hover { background-color: var(--primary-hover); }

        /* --- Autocomplete Dropdown (fixed) --- */
        .suggestion-box {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #0b1220;
            border: 1px solid #374151;
            border-radius: 10px;
            list-style: none;
            margin: 0;
            padding: 0.25rem 0;
            max-height: 260px;
            overflow-y: auto;
            box-shadow: var(--box-shadow);
            z-index: 9999;            /* sit above nav/footer */
            display: none;            /* toggled via JS */
        }
        .suggestion-box.show { display: block; }
        .suggestion-box li {
            padding: 0.7rem 0.9rem;
            cursor: pointer;
            color: #e5e7eb;
            outline: none;
        }
        .suggestion-box li:hover,
        .suggestion-box li[aria-selected="true"] {
            background: #111827;
        }

        /* Footer */
        footer { text-align: center; color: #cbd5e1; padding: 1.5rem 0 2rem; }
        footer a { color: #e0e0e0; text-decoration: none; margin: 0 0.5rem; }
        footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- 🌐 Desktop Nav -->
<header class="topnav desktop-only">
  <div class="topnav-left">
    <img src="../images/D-Best.png" class="nav-logo" alt="Logo">
    <span class="nav-title">D-BEST TimeSmart</span>
  </div>
  <div class="topnav-right">
    <span class="nav-date"><?= htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8') ?></span>
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
    <div class="login-container" role="main" aria-labelledby="login-title">
      <div class="login-logo-wrap">
        <img src="../images/D-Best.png" alt="Company Logo">
      </div>

      <h2 id="login-title">Employee Login</h2>

      <?php if (!empty($error)): ?>
        <div class="error" aria-live="assertive"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="field" id="nameField">
          <label for="nameInput">Name</label>
          <input
            type="text"
            name="name"
            id="nameInput"
            placeholder="Start typing name..."
            required
            autocomplete="name"
            spellcheck="false"
            autocapitalize="words"
            aria-autocomplete="list"
            aria-controls="suggestions"
            aria-expanded="false">
          <ul id="suggestions" class="suggestion-box" role="listbox" aria-label="Name suggestions"></ul>
        </div>

        <div class="field">
          <label for="passInput">PIN / Password</label>
          <input
            type="password"
            name="password"
            id="passInput"
            placeholder="PIN / Password"
            required
            autocomplete="current-password">
        </div>

        <button type="submit">Login</button>

        <div class="help" style="margin-top:0.75rem; font-size:0.85rem; color:#9ca3af;" aria-live="polite">
            <?php if (isset($_SESSION['user_lock_until']) && time() < ($_SESSION['user_lock_until'])):
                $remaining = $_SESSION['user_lock_until'] - time();
                $mins = floor($remaining / 60);
                $secs = $remaining % 60;
            ?>
                Too many attempts. Try again in ~<?= (int)$mins ?>m <?= (int)$secs ?>s.
            <?php else: ?>
                Tip: You can type “Last, First” or “First Last”.
            <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<footer>
  <p>D-BEST TimeSmart &copy; <?= htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
  <p style="margin-top: 0.3rem;">
    <a href="/docs/privacy.php">Privacy Policy</a>
    <a href="/docs/terms.php">Terms of Use</a>
    <a href="/docs/report.php">Report Issues</a>
  </p>
</footer>

<script>
// ===== Autocomplete (with debounce, abort, keyboard nav) =====
const nameInput = document.getElementById("nameInput");
const suggestionsBox = document.getElementById("suggestions");
const nameField = document.getElementById("nameField");

let acAbort = null;
let debounceTimer = null;
let activeIndex = -1;

function clearSuggestions() {
  suggestionsBox.innerHTML = '';
  suggestionsBox.classList.remove('show');
  nameInput.setAttribute('aria-expanded', 'false');
  activeIndex = -1;
}
function showSuggestions(items) {
  clearSuggestions();
  if (!items || !items.length) return;

  suggestionsBox.classList.add('show');
  nameInput.setAttribute('aria-expanded', 'true');

  items.forEach((fullName, idx) => {
    const li = document.createElement("li");
    li.textContent = fullName;
    li.role = "option";
    li.id = `sug-${idx}`;
    li.tabIndex = -1;
    li.addEventListener("mousedown", (e) => { // mousedown avoids blur race
      e.preventDefault();
      nameInput.value = fullName;
      clearSuggestions();
    });
    suggestionsBox.appendChild(li);
  });
}
function highlight(index) {
  Array.from(suggestionsBox.children).forEach((li, i) => {
    if (i === index) {
      li.setAttribute('aria-selected', 'true');
      li.scrollIntoView({ block: 'nearest' });
    } else {
      li.removeAttribute('aria-selected');
    }
  });
}
function fetchNames(q) {
  if (acAbort) acAbort.abort();
  acAbort = new AbortController();
  fetch(`autocomplete.php?search=${encodeURIComponent(q)}`, { signal: acAbort.signal })
    .then(res => res.ok ? res.json() : [])
    .then(data => showSuggestions(Array.isArray(data) ? data : []))
    .catch(() => {}); // silent on abort / network
}
nameInput.addEventListener("input", () => {
  const q = nameInput.value.trim();
  if (q.length < 2) { clearSuggestions(); return; }
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchNames(q), 180);
});
nameInput.addEventListener("keydown", (e) => {
  const items = Array.from(suggestionsBox.children);
  if (!items.length) return;

  if (e.key === "ArrowDown") {
    e.preventDefault();
    activeIndex = (activeIndex + 1) % items.length;
    highlight(activeIndex);
  } else if (e.key === "ArrowUp") {
    e.preventDefault();
    activeIndex = (activeIndex - 1 + items.length) % items.length;
    highlight(activeIndex);
  } else if (e.key === "Enter") {
    if (activeIndex >= 0 && activeIndex < items.length) {
      e.preventDefault();
      nameInput.value = items[activeIndex].textContent;
      clearSuggestions();
    }
  } else if (e.key === "Escape") {
    clearSuggestions();
  }
});
document.addEventListener("click", (e) => {
  if (!nameField.contains(e.target)) clearSuggestions();
});
</script>
<script src="../js/script.js"></script>
</body>
</html>
