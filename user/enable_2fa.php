<?php
require_once 'header.php';
require '../vendor/autoload.php';

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

$stmt = $conn->prepare("SELECT FirstName, Email, TwoFAEnabled FROM users WHERE ID = ?");
$stmt->bind_param("i", $empID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || empty($user['Email'])) {
    die("User not found or missing email.");
}

// Generate or reuse TOTP secret
if (empty($_SESSION['2fa_secret'])) {
    $totp = TOTP::create();
    $totp->setLabel($user['Email']);
    $totp->setIssuer('TimeClock');
    $_SESSION['2fa_secret'] = $totp->getSecret();
} else {
    $totp = TOTP::create($_SESSION['2fa_secret']);
    $totp->setLabel($user['Email']);
    $totp->setIssuer('TimeClock');
}

// Build QR Code
$logoPath = realpath(__DIR__ . '/../images/D-Best.png');

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
    logoPath: $logoPath,
    logoResizeToWidth: 60,
    logoPunchoutBackground: true,
    labelText: 'Scan with Authenticator',
    labelFont: new OpenSans(16),
    labelAlignment: LabelAlignment::Center
);

$result = $builder->build();
$imgData = base64_encode($result->getString());

// Verification
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $inputCode = trim($_POST['code']);
    if ($totp->verify($inputCode)) {
        $secret = $_SESSION['2fa_secret'];
        $stmt = $conn->prepare("UPDATE users SET TwoFASecret = ?, TwoFAEnabled = 1 WHERE ID = ?");
        $stmt->bind_param("si", $secret, $empID);
        $stmt->execute();
        unset($_SESSION['2fa_secret']);
        header("Location: dashboard.php?2fa=enabled");
        exit;
    } else {
        $msg = "<span style='color: red;'>❌ Invalid code. Please try again.</span>";
    }
}
?>
<style>
    .container {
        background: white;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    img {
        margin: 20px auto;
        display: block;
    }
    input[type="text"] {
        padding: 0.75rem;
        font-size: 1.2rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        width: 250px;
        text-align: center;
    }
    button {
        margin-top: 1rem;
        padding: 0.75rem 1.5rem;
        background-color: #0078D7;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        cursor: pointer;
    }
    button:hover {
        background-color: #005fa3;
    }
    .message {
        margin-top: 1rem;
        font-weight: bold;
    }
</style>
        <div class="container">
            <h2>Enable Two-Factor Authentication</h2>
            <?php if ($user['TwoFAEnabled']): ?>
                <p style="color: green;">✅ 2FA is already enabled on your account.</p>
            <?php else: ?>
                <p>Scan the QR code below using Google Authenticator or Authy:</p>
                <img src="data:image/png;base64,<?= $imgData ?>" alt="2FA QR Code">

                <form method="POST">
                    <label>Enter 6-digit code from your app:</label><br>
                    <input type="text" name="code" maxlength="6" pattern="\d{6}" required><br>
                    <button type="submit">Verify & Enable 2FA</button>
                </form>
                <div class="message"><?= $msg ?></div>
            <?php endif; ?>
        </div>
<script src="../js/script.js"></script>
<?php require_once 'footer.php'; ?>
