<?php
require_once 'header.php';            // must start session and set $conn, $empID
require_once '../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use OTPHP\TOTP;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

// --- Load user ---
$stmt = $conn->prepare("SELECT FirstName, Email, TwoFAEnabled FROM users WHERE ID = ?");
$stmt->bind_param("i", $empID);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result ? $result->fetch_assoc() : null;
if ($result) { $result->free(); }
$stmt->close();

if (!$user || empty($user['Email'])) {
    die("User not found or missing email.");
}

// --- Build or reuse TOTP (persist secret during setup) ---
$ISSUER = 'D-Best Timeclock'; // <- exact issuer text requested
if (empty($_SESSION['2fa_secret'])) {
    $totp = TOTP::create(); // 30s period, 6 digits
    $totp->setLabel($user['Email']);
    $totp->setIssuer($ISSUER);
    $_SESSION['2fa_secret'] = $totp->getSecret();
} else {
    $totp = TOTP::create($_SESSION['2fa_secret']);
    $totp->setLabel($user['Email']);
    $totp->setIssuer($ISSUER);
}

// --- QR (constructor style for your package version) ---
$logoPath = realpath(__DIR__ . '/../images/D-Best.png'); // keep PNG logo

$builder = new Builder(
    writer: new PngWriter(),
    writerOptions: [],
    validateResult: false,
    data: $totp->getProvisioningUri(),
    encoding: new Encoding('UTF-8'),
    errorCorrectionLevel: ErrorCorrectionLevel::High,
    size: 300,
    margin: 10,
    roundBlockSizeMode: RoundBlockSizeMode::Margin,
    logoPath: $logoPath && is_readable($logoPath) ? $logoPath : null,
    logoResizeToWidth: 60,
    logoPunchoutBackground: true,
    labelText: 'Scan with Authenticator',
    labelFont: new OpenSans(16),
    labelAlignment: LabelAlignment::Center
);

$result = $builder->build();
$imgData = base64_encode($result->getString());

// --- Verify code ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    // Allow spaces/dashes in UI; strip for verification
    $inputCode = preg_replace('/[\s-]+/', '', trim($_POST['code']));

    // Allow 1 time window drift (helps with slight clock skew)
    $isValid = $totp->verify($inputCode, null, 1);

    if ($isValid) {
        $secret = $_SESSION['2fa_secret'];
        $upd = $conn->prepare("UPDATE users SET TwoFASecret = ?, TwoFAEnabled = 1 WHERE ID = ?");
        if ($upd) {
            $upd->bind_param("si", $secret, $empID);
            $upd->execute();
            $upd->close();
        }
        unset($_SESSION['2fa_secret']);
        header("Location: dashboard.php?2fa=enabled");
        exit;
    } else {
        $msg = "<span style='color: #dc2626;'>❌ Invalid code. Please try again.</span>";
    }
}
?>
<style>
    .container {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        text-align: center;
        max-width: 520px;
        margin: 2rem auto;
    }
    .container h2 { margin: 0 0 .5rem 0; font-size: 1.6rem; }
    .container p.sub { color: #4b5563; margin: 0 0 1rem 0; }
    img.qr { margin: 20px auto 10px; display: block; width: 300px; height: 300px; }
    .field { margin-top: 1rem; text-align: left; display:inline-block; }
    .field label { display:block; font-size:.95rem; color:#374151; margin-bottom:.4rem; }
    input[type="text"]{
        padding:.8rem; font-size:1.2rem; border:1px solid #cbd5e1; border-radius:8px;
        width: 100%; max-width: 280px; text-align:center; outline:none;
        transition: box-shadow .2s ease, border-color .2s ease;
    }
    input[type="text"]:focus{ border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.25); }
    button{
        margin-top:1rem; padding:.8rem 1.4rem; background:#3b82f6; color:#fff; border:none;
        border-radius:8px; font-size:1rem; cursor:pointer; font-weight:600;
    }
    button:hover{ background:#2563eb; }
    .message{ margin-top:1rem; font-weight:600; }
    .already{ color:#16a34a; font-weight:600; }
</style>

<div class="container">
    <h2>Enable Two-Factor Authentication</h2>

    <?php if (!empty($user['TwoFAEnabled'])): ?>
        <p class="already">✅ 2FA is already enabled on your account.</p>
    <?php else: ?>
        <p class="sub">Scan this QR with your authenticator app, then enter the 6-digit code.</p>

        <img class="qr" src="data:image/png;base64,<?= htmlspecialchars($imgData, ENT_QUOTES, 'UTF-8') ?>" alt="2FA QR Code">

        <form method="POST" autocomplete="off" novalidate>
            <div class="field">
                <label for="code">Enter 6-digit code</label>
                <input type="text" name="code" id="code" maxlength="6" inputmode="numeric" pattern="\d{6}" placeholder="123 456" required>
            </div>
            <button type="submit">Verify & Enable 2FA</button>
        </form>

        <div class="message"><?= $msg ?></div>
    <?php endif; ?>
</div>

<script>
// Allow typing spaces/dashes; server strips them before verify
document.getElementById('code')?.addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/[^\d\s-]/g, '').slice(0, 7);
});
</script>
<script src="../js/script.js"></script>
<?php require_once 'footer.php'; ?>
