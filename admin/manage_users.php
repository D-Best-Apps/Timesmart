<?php
require_once 'header.php';

// Handle form submission to update setting before fetching data for display
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_gps'])) {
    $newValue = isset($_POST['EnforceGPS']) ? '1' : '0';
    $stmt = $conn->prepare("INSERT INTO settings (SettingKey, SettingValue)
                            VALUES ('EnforceGPS', ?)
                            ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
    $stmt->bind_param("s", $newValue);
    $stmt->execute();
    // Redirect to prevent form resubmission and ensure the change is immediately visible
    header("Location: manage_users.php");
    exit;
}

$users = $conn->query("SELECT ID, FirstName, LastName, TagID, ClockStatus, Office, TwoFAEnabled, AdminOverride2FA FROM users ORDER BY LastName");

// Fetch current GPS setting
$gpsSetting = $conn->query("SELECT SettingValue FROM settings WHERE SettingKey = 'EnforceGPS'")->fetch_assoc();
$gpsEnforced = isset($gpsSetting['SettingValue']) && $gpsSetting['SettingValue'] === '1';
?>

<div class="admin-content">
    <div style="text-align: center; margin-bottom: 1rem;">
    <form method="POST" action="generate_backup_codes.php" style="display:inline-block;">
        <input type="hidden" name="mode" value="all">
        <button type="submit" class="btn primary">🔐 Generate Codes for All Users</button>
    </form>

    <form method="POST" action="generate_backup_codes.php" style="display:inline-block; margin-left:1rem;">
        <input type="hidden" name="userID" placeholder="User ID" required style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc;">
        <button type="submit" class="btn warning">🔐 Generate for User ID</button>
    </form>
</div>
    <div class="uman-header">
        <h2>User Management</h2>
        <button class="btn primary" onclick="document.getElementById('addUserModal').style.display='block'">+ Add User</button>
    </div>

   <form method="POST" style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <input type="hidden" name="toggle_gps" value="1">
    
    <label class="switch-label">Require GPS for All Punches</label>
    <label class="switch">
        <input type="checkbox" name="EnforceGPS" value="1" <?= $gpsEnforced ? 'checked' : '' ?>>
        <span class="slider"></span>
    </label>

    <button type="submit" class="btn primary small">Save</button>
</form>




    <table class="uman-table">
        <thead>
            <tr>
                <th>Tag ID</th>
                <th>Full Name</th>
                <th>Clock Status</th>
                <th>Office</th>
                <th>2FA</th>
                <th>Override</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($user['TagID']) ?></td>
                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?></td>
                <td><?= htmlspecialchars($user['ClockStatus'] ?? 'Out') ?></td>
                <td><?= htmlspecialchars($user['Office'] ?? 'N/A') ?></td>
                <td><?= $user['TwoFAEnabled'] ? '✅ Enabled' : '❌ Disabled' ?></td>
                <td><?= $user['AdminOverride2FA'] ? 'Yes' : 'No' ?></td>
                <td>
                    <button class="btn warning small" onclick="location.href='edit_user.php?id=<?= $user['ID'] ?>'">Edit</button>
                    <button class="btn danger small" onclick="showResetModal(<?= $user['ID'] ?>)">Reset</button>
                    <button class="btn small" onclick="open2FAModal(<?= $user['ID'] ?>)">2FA Options</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <form action="add_user.php" method="POST">
            <h3>Add New User</h3>
            <input type="text" name="FirstName" placeholder="First Name" required>
            <input type="text" name="LastName" placeholder="Last Name" required>
            <input type="email" name="Email" placeholder="Email">
            <input type="text" name="TagID" placeholder="Tag ID">
            <select name="Office" required>
                <option value="">Select Office</option>
                <option value="Fort Smith">Fort Smith</option>
                <option value="Fayetteville">Fayetteville</option>
                <option value="Overseas">Overseas</option>
            </select>
            <input type="password" name="Password" placeholder="Initial Password" required>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <h3>Reset Password</h3>
        <p>Are you sure you want to reset this user's password to the default?</p>
        <div class="modal-actions">
            <button class="btn" onclick="closeResetModal()">Cancel</button>
            <button class="btn primary" id="confirmResetBtn">Yes, Reset</button>
        </div>
    </div>
</div>

<!-- 2FA Modal -->
<div id="modal2FA" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-btn" onclick="close2FAModal()">&times;</span>
        <h3 style="text-align: center; margin-bottom: 0.5rem;">🔐 2FA Management</h3>
        <p style="text-align: center; font-size: 0.95rem; color: #444;">
            Choose an action for this user's two-factor authentication.
        </p>

        <form id="form2FA" method="POST" action="update_2fa_status.php">
            <input type="hidden" name="id" id="2faUserId">

            <div class="modal-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn small primary" onclick="confirm2FA('enable')">✅ Enable 2FA</button>
                <button type="button" class="btn small danger" onclick="confirm2FA('disable')">❌ Disable 2FA</button>
                <button type="button" class="btn small warning" onclick="confirm2FA('lock')">🔒 Lock User Control</button>
                <button type="button" class="btn small" style="background:#ddd;" onclick="confirm2FA('unlock')">🔓 Unlock Control</button>
            </div>
        </form>
    </div>
</div>


<script>
let resetUserId = null;

function showResetModal(id) {
    resetUserId = id;
    document.getElementById('resetModal').style.display = 'block';
}

function closeResetModal() {
    resetUserId = null;
    document.getElementById('resetModal').style.display = 'none';
}

document.getElementById('confirmResetBtn').addEventListener('click', () => {
    if (!resetUserId) return;
    fetch('reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + resetUserId
    }).then(res => {
        closeResetModal();
        window.location.reload();
    });
});

// 2FA Modal Logic
function open2FAModal(userId) {
    document.getElementById('2faUserId').value = userId;
    document.getElementById('modal2FA').style.display = 'block';
}

function close2FAModal() {
    document.getElementById('modal2FA').style.display = 'none';
}

function confirm2FA(action) {
    const labels = {
        enable: 'Enable 2FA for this user?',
        disable: 'Disable 2FA and remove all secrets for this user?',
        lock: 'Lock user from managing 2FA?',
        unlock: 'Allow user to manage their own 2FA?'
    };
    if (confirm(labels[action])) {
        const form = document.getElementById('form2FA');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = action;
        form.appendChild(input);
        form.submit();
    }
}
</script>

<?php require_once 'footer.php'; ?>