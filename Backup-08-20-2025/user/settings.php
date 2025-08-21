<?php
require_once 'header.php';

$msg = "";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['FirstName']);
    $lastName = trim($_POST['LastName']);
    $email = trim($_POST['Email']);
    $tagID = trim($_POST['TagID']);
    $jobTitle = trim($_POST['JobTitle']);
    $phone = trim($_POST['PhoneNumber']);
    $theme = $_POST['ThemePref'];

    if (!$firstName || !$lastName) {
        $errors[] = "First and Last Name are required.";
    }

    if (!empty($_POST['NewPassword']) || !empty($_POST['ConfirmPassword'])) {
        if ($_POST['NewPassword'] !== $_POST['ConfirmPassword']) {
            $errors[] = "Passwords do not match.";
        } elseif (strlen($_POST['NewPassword']) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        } else {
            $newPass = password_hash($_POST['NewPassword'], PASSWORD_BCRYPT, ['cost' => 14]);
            $stmt = $conn->prepare("UPDATE users SET Pass=? WHERE ID=?");
            $stmt->bind_param("si", $newPass, $empID);
            $stmt->execute();
        }
    }

    if (isset($_FILES['ProfilePhoto']) && $_FILES['ProfilePhoto']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['ProfilePhoto']['type'], $allowedTypes)) {
            $errors[] = "Invalid image type. Only JPG, PNG, WEBP allowed.";
        } elseif ($_FILES['ProfilePhoto']['size'] > 2 * 1024 * 1024) {
            $errors[] = "File must be under 2MB.";
        } else {
            $ext = pathinfo($_FILES['ProfilePhoto']['name'], PATHINFO_EXTENSION);
            $newName = "profile_$empID." . $ext;
            $uploadPath = "../uploads/" . $newName;
            move_uploaded_file($_FILES['ProfilePhoto']['tmp_name'], $uploadPath);
            $stmt = $conn->prepare("UPDATE users SET ProfilePhoto=? WHERE ID=?");
            $stmt->bind_param("si", $newName, $empID);
            $stmt->execute();
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET FirstName=?, LastName=?, Email=?, TagID=?, JobTitle=?, PhoneNumber=?, ThemePref=? WHERE ID=?");
        $stmt->bind_param("sssssssi", $firstName, $lastName, $email, $tagID, $jobTitle, $phone, $theme, $empID);
        $stmt->execute();
        $msg = "Profile updated successfully.";
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE ID = ?");
$stmt->bind_param("i", $empID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$logStmt = $conn->prepare("SELECT IP, Timestamp FROM login_logs WHERE EmployeeID = ? ORDER BY Timestamp DESC LIMIT 5");
$logStmt->bind_param("i", $empID);
$logStmt->execute();
$logs = $logStmt->get_result();
?>
<style>
    :root {
        --primary-color: #0078D7;
        --primary-hover: #005fa3;
        --success-color: #28a745;
        --error-color: #dc3545;
        --bg-color: #f4f6f9;
        --card-bg: #ffffff;
        --border-radius: 10px;
        --input-bg: #fcfcfc;
        --input-border: #ced4da;
        --input-focus: #80bdff;
    }

    .tabs {
        display: flex;
        justify-content: center;
        margin: 1.5rem 0 0;
        gap: 1rem;
    }

    .tabs button {
        padding: 0.8rem 1.5rem;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px 8px 0 0;
        cursor: pointer;
        font-size: 1rem;
        transition: background 0.3s ease;
    }

    .tabs button.active,
    .tabs button:hover {
        background: var(--primary-hover);
    }

    .tab-content {
        display: none;
        max-width: 1200px; /* Changed */
        margin: 0 auto;
        background: var(--card-bg);
        padding: 2rem;
        box-shadow: 0 0 12px rgba(0,0,0,0.1);
        border-radius: 0 0 12px 12px;
    }

    .tab-content.active {
        display: block;
    }

    form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem 2rem; /* Adjusted gap */
    }

    form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.4rem;
        color: #333;
    }

    form input,
    form select {
        width: 100%;
        padding: 0.8rem 1rem; /* Adjusted padding */
        border-radius: 8px; /* Changed to fixed value */
        border: 1px solid var(--input-border);
        background: var(--input-bg);
        font-size: 1rem; /* Adjusted font size */
        transition: border-color 0.3s ease;
    }

    form input:focus,
    form select:focus {
        border-color: var(--input-focus);
        outline: none;
    }

    .full {
        grid-column: 1 / -1;
    }

    .actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1rem;
    }

    .actions button, .actions a.button { /* Added a.button for consistency */
        padding: 0.8rem 1.8rem;
        font-size: 1rem;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.3s ease;
        text-decoration: none; /* Ensure links don't have underlines */
        display: inline-block; /* Make links behave like buttons */
        text-align: center;
    }

    .btn-save {
        background: var(--primary-color);
        color: #fff;
    }

    .btn-save:hover {
        background: var(--primary-hover); /* Use variable for consistency */
    }

    .btn-cancel {
        background: var(--error-color); /* Use variable for consistency */
        color: #fff;
    }

    .btn-cancel:hover {
        background: #c82333; /* Darker red for hover */
    }

    .btn-done {
        background: var(--success-color); /* Use variable for consistency */
        color: #fff;
    }

    .btn-done:hover {
        background: #218838; /* Darker green for hover */
    }

    .logs {
        margin-top: 2rem;
    }

    .message {
        background: #d4edda;
        color: var(--success-color);
        padding: 1rem;
        margin: 1rem auto;
        max-width: 900px;
        border-radius: var(--border-radius);
        text-align: center;
    }

    .error {
        background: #f8d7da;
        color: var(--error-color);
        padding: 1rem;
        margin: 1rem auto;
        max-width: 900px;
        border-radius: var(--border-radius);
        text-align: center;
    }

    .profile-photo-container {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    img.profile-preview {
        border-radius: 50%;
        width: 100px;
        height: 100px;
        object-fit: cover;
        border: 3px solid var(--primary-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    /* Password Inputs */
.password-box {
    position: relative;
    margin-bottom: 1.5rem;
}

.password-box input {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #fff;
    transition: border 0.3s;
}

.password-box input:focus {
    border-color: #0078D7;
    outline: none;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    font-size: 1.1rem;
    color: #888;
    cursor: pointer;
    user-select: none;
}

.toggle-password:hover {
    color: #0078D7;
}

/* 2FA Box */
.twofa-box {
    background: #e8f3ff;
    padding: 1.5rem;
    margin-top: 1.5rem;
    border-left: 6px solid #0078D7;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.twofa-box h3 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: #333;
}

.twofa-box p {
    font-size: 0.95rem;
    color: #444;
    margin: 0.5rem 0 1rem;
}

.twofa-box .btn-save {
    padding: 0.5rem 1rem;
    background-color: #0078D7;
    color: #fff;
    font-size: 0.95rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: background 0.3s;
}

.twofa-box .btn-save:hover {
    background-color: #005fa3;
}

.twofa-box .icon {
    font-size: 1.1rem;
    margin-right: 6px;
    vertical-align: middle;
}

</style>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tabs button').forEach(btn => btn.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        document.getElementById(tabId + '-btn').classList.add('active');
    }

    function togglePassword(fieldId) {
        const input = document.getElementById(fieldId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    window.onload = function () {
        switchTab('profile');
        setTimeout(() => {
            const messages = document.querySelectorAll('.message, .error');
            messages.forEach(m => m.style.display = 'none');
        }, 5000);
    }
</script>
        <h1>User Settings Dashboard</h1>

        <?php if ($msg): ?>
            <div class="message"><?= $msg ?></div>
        <?php elseif ($errors): ?>
            <div class="error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button id="profile-btn" onclick="switchTab('profile')">Profile</button>
            <button id="security-btn" onclick="switchTab('security')">Security</button>
            <button id="logs-btn" onclick="switchTab('logs')">Login History</button>
        </div>

        <div class="tab-content" id="profile">
            <form method="POST" enctype="multipart/form-data">
                <div><label>First Name</label><input type="text" name="FirstName" value="<?= htmlspecialchars($user['FirstName']) ?>" required></div>
                <div><label>Last Name</label><input type="text" name="LastName" value="<?= htmlspecialchars($user['LastName']) ?>" required></div>
                <div><label>Email</label><input type="email" name="Email" value="<?= htmlspecialchars($user['Email']) ?>"></div>
                <div><label>Tag ID</label><input type="text" name="TagID" value="<?= htmlspecialchars($user['TagID']) ?>"></div>
                <div><label>Job Title</label><input type="text" name="JobTitle" value="<?= htmlspecialchars($user['JobTitle']) ?>"></div>
                <div><label>Phone Number</label><input type="text" name="PhoneNumber" value="<?= htmlspecialchars($user['PhoneNumber']) ?>"></div>
                <div>
                    <label>Theme Preference</label>
                    <select name="ThemePref">
                        <option value="light" <?= $user['ThemePref'] === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $user['ThemePref'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
                <div class="full">
                    <label>Profile Photo</label>
                    <input type="file" name="ProfilePhoto" accept="image/*">
                    <?php if ($user['ProfilePhoto']): ?>
                        <img src="../uploads/<?= $user['ProfilePhoto'] ?>" alt="Profile" class="profile-preview">
                    <?php endif; ?>
                </div>

                <div class="actions full">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="reset" class="btn-cancel">Cancel</button>
                    <a href="dashboard.php" class="button btn-done">Done</a>
                </div>
            </form>
        </div>

        <div class="tab-content" id="security">
            <div class="password-box full">
                <label>New Password</label>
                <input type="password" name="NewPassword" id="NewPassword">
                <span class="toggle-password" onclick="togglePassword('NewPassword')">👁</span>
            </div>
            <div class="password-box full">
                <label>Confirm Password</label>
                <input type="password" name="ConfirmPassword" id="ConfirmPassword">
                <span class="toggle-password" onclick="togglePassword('ConfirmPassword')">👁</span>
            </div>

            <div class="twofa-box full">
                <h3>Two-Factor Authentication</h3>
                <?php if ($user['TwoFAEnabled']): ?>
                    <p>✅ 2FA is enabled.</p>
                    <?php if ($user['AdminOverride2FA']): ?>
                        <form action="disable_2fa.php" method="POST">
                            <button class="btn-cancel" type="submit">Disable 2FA</button>
                        </form>
                    <?php else: ?>
                        <p><em>Admin has locked 2FA settings.</em></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>❌ 2FA is not enabled.</p>
                    <?php if ($user['AdminOverride2FA']): ?>
                        <a href="enable_2fa.php" class="button btn-save">Enable 2FA</a>
                    <?php else: ?>
                        <p><em>Admin has locked 2FA settings.</em></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="logs">
            <div class="logs full">
                <h3>Recent Logins</h3>
                <?php while ($row = $logs->fetch_assoc()): ?>
                    <p><?= htmlspecialchars($row['Timestamp']) ?> — <?= htmlspecialchars($row['IP']) ?></p>
                <?php endwhile; ?>
            </div>
        </div>
<script src="../js/script.js"></script>
<?php require_once 'footer.php'; ?>
