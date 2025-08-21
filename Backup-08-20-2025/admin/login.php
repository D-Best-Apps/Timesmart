<?php
/*************************************************
 * Admin Login (hardened)
 * - CSRF protection
 * - Session cookie hardening
 * - Simple cooldown lockout on failures
 * - Neutral errors (no user enumeration)
 * - Optional password rehash on login
 * - Security headers
 *************************************************/

// ---------- Security Headers (adjust CSP if you load externals) ----------
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

// ---------- Error Handling (prod defaults) ----------
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ---------- Session Cookie Hardening ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',        // set your domain if needed, e.g. 'admin.example.com'
    'secure'   => true,      // true in production (requires HTTPS)
    'httponly' => true,
    'samesite' => 'Strict',  // 'Lax' if you need cross-site GET flows
]);
session_start();

// ---------- Constants ----------
const MAX_LOGIN_ATTEMPTS           = 5;
const LOCKOUT_SECONDS              = 15 * 60;   // 15 minutes
const FAILURE_DELAY_MICROSECONDS   = 350000;    // ~350ms artificial delay on failures

// ---------- DB ----------
require '../db.php'; // must set $conn (mysqli)

// ---------- Helpers ----------
function current_username_from_post(): string {
    return trim($_POST['username'] ?? '');
}
function is_locked_out(): bool {
    $until = $_SESSION['admin_lock_until'] ?? 0;
    return is_int($until) && time() < $until;
}
function record_failure_and_maybe_lock(): void {
    $_SESSION['admin_login_attempts'] = (int)($_SESSION['admin_login_attempts'] ?? 0) + 1;
    if ($_SESSION['admin_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['admin_lock_until'] = time() + LOCKOUT_SECONDS;
    }
}
function clear_failures(): void {
    unset($_SESSION['admin_login_attempts'], $_SESSION['admin_lock_until']);
}
function ensure_csrf_token(): void {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
function csrf_valid(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

// ---------- Initialize state ----------
$error = '';
ensure_csrf_token();

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!csrf_valid()) {
        $error = "Invalid request.";
    } elseif (is_locked_out()) {
        $error = "Too many failed login attempts. Please try again later.";
    } else {
        $username = current_username_from_post();
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = "Please enter both username and password.";
        } else {
            // Lookup user
            $stmt = $conn->prepare("SELECT username, password, TwoFAEnabled FROM admins WHERE username = ?");
            if ($stmt === false) {
                // do not leak DB errors to user
                $error = "Something went wrong. Please try again.";
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                $admin = $result ? $result->fetch_assoc() : null;

                // Default to invalid; only flip to success after verify
                $valid = false;

                if ($admin && is_string($admin['password']) && password_verify($password, $admin['password'])) {
                    $valid = true;

                    // Optional: upgrade hash if algorithm/cost changed
                    if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
                        if ($upd) {
                            $upd->bind_param("ss", $newHash, $admin['username']);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                }

                // Tidy up statement/result
                if ($result) { $result->free(); }
                $stmt->close();

                if ($valid) {
                    clear_failures();
                    session_regenerate_id(true); // prevent fixation

                    if (!empty($admin['TwoFAEnabled'])) {
                        // 2FA flow
                        $_SESSION['2fa_admin_username'] = $admin['username'];
                        $_SESSION['2fa_pending'] = true;
                        header("Location: verify_2fa.php");
                        exit;
                    } else {
                        // Normal login
                        $_SESSION['admin'] = $admin['username'];
                        header("Location: dashboard.php");
                        exit;
                    }
                } else {
                    // Slow down brute-force slightly (uniform delay on any failure)
                    usleep(FAILURE_DELAY_MICROSECONDS);
                    record_failure_and_maybe_lock();
                    $error = "Invalid credentials";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/admin.css">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root {
            --bg-color: DarkGray;
            --card-bg: #111827;
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg-color);
            color: #e5e7eb;
        }
        .login-box {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .login-box h2 {
            margin: 0 0 1.25rem 0;
            color: var(--primary-color);
            letter-spacing: 0.3px;
        }
        .field {
            text-align: left;
            margin-bottom: 1rem;
        }
        .field label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
            color: #cbd5e1;
        }
        .login-box input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #374151;
            background: #0b1220;
            color: #e5e7eb;
            font-size: 1rem;
            outline: none;
        }
        .login-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
        }
        .login-box button {
            width: 100%;
            padding: 0.8rem 1rem;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }
        .login-box button:hover { background-color: var(--primary-hover); }
        .error {
            margin-top: 1rem;
            color: #fca5a5;
            font-weight: 600;
        }
        .help {
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="login-box" role="main" aria-labelledby="login-title">
    <h2 id="login-title">Admin Login</h2>
    <form method="POST" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
            <label for="username">Username</label>
            <input
                id="username"
                name="username"
                type="text"
                placeholder="Username"
                required
                autocomplete="username"
                value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                placeholder="Password"
                required
                autocomplete="current-password">
        </div>

        <button type="submit">Login</button>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="help" aria-live="polite">
            <?php if (isset($_SESSION['admin_lock_until']) && time() < ($_SESSION['admin_lock_until'])):
                $remaining = $_SESSION['admin_lock_until'] - time();
                $mins = floor($remaining / 60);
                $secs = $remaining % 60;
            ?>
                Too many attempts. Try again in ~<?= (int)$mins ?>m <?= (int)$secs ?>s.
            <?php else: ?>
                Use a strong, unique password. 2FA is supported.
            <?php endif; ?>
        </div>
    </form>
</div>

</body>
</html>
