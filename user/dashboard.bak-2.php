<?php
date_default_timezone_set('America/Chicago');
session_start();
require '../db.php';

if (!isset($_SESSION['EmployeeID'])) {
    header("Location: login.php");
    exit;
}

$empID = $_SESSION['EmployeeID'];
$name = $_SESSION['FirstName'];

// Avatar logic
$avatarPath = "../images/default_avatar.png";
$extensions = ['png', 'jpg', 'jpeg', 'webp'];
foreach ($extensions as $ext) {
    $try = "../avatars/{$empID}_pro.$ext";
    if (file_exists($try)) {
        $avatarPath = $try;
        break;
    }
}

// Punch history
$start = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week'));
$end   = $_GET['end'] ?? date('Y-m-d', strtotime('friday this week'));

$query = $conn->prepare("SELECT Date, TimeIN, LunchStart, LunchEnd, TimeOUT, Note FROM timepunches WHERE EmployeeID = ? AND Date BETWEEN ? AND ? ORDER BY Date DESC, TimeIN DESC");
$query->bind_param("sss", $empID, $start, $end);
$query->execute();
$result = $query->get_result();

$punches = [];
$totalSeconds = 0;

while ($row = $result->fetch_assoc()) {
    $date = $row['Date'];
    $in   = $row['TimeIN'] ? strtotime($row['Date'] . ' ' . $row['TimeIN']) : 0;
    $out  = $row['TimeOUT'] ? strtotime($row['Date'] . ' ' . $row['TimeOUT']) : 0;
    $worked = ($in && $out) ? $out - $in : 0;
    $totalSeconds += $worked;

    $punches[] = [
        'date' => date("m/d/Y", strtotime($date)),
        'in' => $in ? date("g:i A", $in) : '-',
        'out' => $out ? date("g:i A", $out) : '-',
        'hours' => $worked ? round($worked / 3600, 2) : '-',
        'note' => $row['Note'] ?? '-'
    ];
}

$totalHours = round($totalSeconds / 3600, 2);

// Last punch info
$last = $conn->query("SELECT * FROM timepunches WHERE EmployeeID = '$empID' ORDER BY Date DESC, TimeIN DESC LIMIT 1")->fetch_assoc();
$lastLabel = $lastTime = $lastDate = $lastNote = '-';
$clockInUnix = null;

if ($last) {
    $lastDate = $last['Date'];
    $lastNote = $last['Note'] ?? '-';

    if (!empty($last['TimeOUT'])) {
        $lastLabel = "Clock Out";
        $lastTime = date("g:i A", strtotime($last['TimeOUT']));
    } elseif (!empty($last['LunchEnd'])) {
        $lastLabel = "Lunch End";
        $lastTime = date("g:i A", strtotime($last['LunchEnd']));
    } elseif (!empty($last['LunchStart'])) {
        $lastLabel = "Lunch Start";
        $lastTime = date("g:i A", strtotime($last['LunchStart']));
    } elseif (!empty($last['TimeIN'])) {
        $lastLabel = "Clock In";
        $lastTime = date("g:i A", strtotime($last['TimeIN']));
        if (empty($last['TimeOUT'])) {
            $clockInUnix = strtotime($last['Date'] . ' ' . $last['TimeIN']);
        }
    }
}

// Edit requests
$editStmt = $conn->prepare("SELECT Date, TimeIN, LunchStart, LunchEnd, TimeOUT, Note, Reason, Status FROM pending_edits WHERE EmployeeID = ? ORDER BY SubmittedAt DESC LIMIT 10");
$editStmt->bind_param("i", $empID);
$editStmt->execute();
$editResults = $editStmt->get_result();
$editRequests = [];
while ($row = $editResults->fetch_assoc()) {
    $editRequests[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
        }
        .badge-pending { background-color: #FFA500; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .badge-unknown { background-color: #6c757d; }

        .edit-status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .edit-status-table th,
        .edit-status-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #ccc;
            text-align: left;
        }
    </style>
    </style>
</head>
<body>
    <?php include('../modal.html'); ?>

<header class="topnav desktop-only">
  <div class="topnav-left">
    <img src="../images/D-Best-favicon.webp" class="nav-logo" alt="Logo">
    <span class="nav-title">D-BEST TimeSmart</span>
  </div>
  <div class="topnav-right">
    <span class="nav-date"><?= date('F j, Y') ?></span>
    <div class="profile-dropdown">
      <div class="profile-trigger" onclick="toggleDropdown()">
        <img src="<?= $avatarPath ?>" alt="Avatar" class="profile-avatar">
        <span class="profile-name"><?= htmlspecialchars($name) ?></span>
      </div>
      <div id="profileMenu" class="dropdown-menu hidden">
        <a href="settings.php">👤 Settings</a>
        <a href="timesheet.php">📄 My Timesheet</a>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
      </div>
    </div>
  </div>
</header>

<nav class="mobile-nav mobile-only">
  <a href="timesheet.php">📄 Sheet</a>
  <a href="dashboard.php">🏠 Home</a>
  <a href="../logout.php">🚪 Logout</a>
  <a href="settings.php">⚙️</a>
</nav>

<div class="wrapper">
    <div class="main">
        <h2>Welcome, <?= htmlspecialchars($name) ?>!</h2>

        <button class="toggle-punch" onclick="togglePunch()">⏱ Show Punch In / Out</button>
        <div class="card punch-area" id="punchArea" style="display: none;">
            <h3>Punch In / Out</h3>
            <table>
                <thead><tr><th>Type</th><th>Time</th><th>Date</th><th>Note</th></tr></thead>
                <tbody><tr>
                    <td><?= $lastLabel ?></td>
                    <td><?= $lastTime ?></td>
                    <td><?= $lastDate ?></td>
                    <td><?= htmlspecialchars($lastNote) ?></td>
                </tr></tbody>
            </table>
            <?php if ($clockInUnix !== null): ?>
            <p class="hours-live" id="liveHours">⏳ Hours Worked Since Clock In: <strong>Loading...</strong></p>
            <script>
                const clockInTime = <?= $clockInUnix * 1000 ?>;
                function formatTimeSince(start) {
                    const now = Date.now();
                    const diff = now - start;
                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    return `${hours} hrs ${minutes} mins ${seconds} secs`;
                }
                function updateLiveHours() {
                    document.querySelector("#liveHours strong").textContent = formatTimeSince(clockInTime);
                }
                updateLiveHours();
                setInterval(updateLiveHours, 1000);
            </script>
            <?php endif; ?>
            <input type="text" id="note" placeholder="Optional note...">
            <div class="punch-buttons">
                <button class="clockin" onclick="startPunch('clockin')">Clock In</button>
                <button class="lunchstart" onclick="startPunch('lunchstart')">Lunch Start</button>
                <button class="lunchend" onclick="startPunch('lunchend')">Lunch End</button>
                <button class="clockout" onclick="startPunch('clockout')">Clock Out</button>
            </div>
        </div>

        <form method="get" class="date-range">
            <label>From:</label>
            <input type="date" name="start" value="<?= $start ?>">
            <label>To:</label>
            <input type="date" name="end" value="<?= $end ?>">
            <button type="submit">Apply</button>
        </form>

        <table>
            <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th></tr></thead>
            <tbody>
                <?php foreach ($punches as $p): ?>
                <tr>
                    <td><?= $p['date'] ?></td>
                    <td><?= $p['in'] ?></td>
                    <td><?= $p['out'] ?></td>
                    <td><?= $p['hours'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4>Total Hours This Range: <strong><?= $totalHours ?> hrs</strong></h4>

        <?php if (!empty($editRequests)): ?>
        <div class="card">
            <h3>Your Recent Edit Requests</h3>
            <table class="edit-status-table">
                <thead><tr><th>Date</th><th>Field</th><th>New Value</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($editRequests as $edit): ?>
                    <?php
                        $status = strtolower($edit['Status']);
                        $badgeClass = match ($status) {
                            'pending' => 'badge-pending',
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            default => 'badge-unknown'
                        };
                        $field = '-';
                        $value = '-';
                        foreach (['TimeIN', 'LunchStart', 'LunchEnd', 'TimeOUT', 'Note'] as $key) {
                            if (!empty($edit[$key])) {
                                $field = $key;
                                $value = htmlspecialchars($edit[$key]);
                                break;
                            }
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($edit['Date']) ?></td>
                        <td><?= $field ?></td>
                        <td><?= $value ?></td>
                        <td><?= htmlspecialchars($edit['Reason']) ?></td>
                        <td><span class="status-badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDropdown() {
    document.getElementById("profileMenu").classList.toggle("hidden");
}

window.addEventListener('click', function(e) {
    const menu = document.getElementById("profileMenu");
    const trigger = document.querySelector(".profile-trigger");
    if (!menu.classList.contains("hidden") && !trigger.contains(e.target)) {
        menu.classList.add("hidden");
    }
});
let selectedAction = "";

function togglePunch() {
    const area = document.getElementById("punchArea");
    area.style.display = area.style.display === "block" ? "none" : "block";
}

function startPunch(action) {
    selectedAction = action;
    const now = new Date();
    const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    document.getElementById("confirmTimeText").textContent = time;
    document.getElementById("confirmPopup").classList.remove("hidden");
}

function openAdjuster() {
    document.getElementById("confirmPopup").classList.add("hidden");
    const now = new Date();
    const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    document.getElementById("adjustTimeInput").value = time;
    document.getElementById("adjustPopup").classList.remove("hidden");
}

function confirmSubmit() {
    const note = document.getElementById("note").value;
    // Get client's full timestamp in ISO 8601 format (UTC)
    const clientTimestamp = new Date().toISOString();
    const params = new URLSearchParams({
        EmployeeID: <?= json_encode($empID) ?>,
        action: selectedAction,
        note: note,
        clientTime: clientTimestamp
    });

    fetch("../clock_action.php", { // Corrected
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params.toString()
    }).then(res => res.text())
      .then(data => {
        document.getElementById("confirmPopup").classList.add("hidden");
        showPopup(data);
        setTimeout(() => location.reload(), 2000);
    });
}

function submitWithAdjustment() {
    const customTime = document.getElementById("adjustTimeInput").value;
    const note = document.getElementById("note").value;

    const params = new URLSearchParams({
        EmployeeID: <?= json_encode($empID) ?>,
        action: selectedAction,
        note: note,
        time: customTime
    });

    fetch("../clock_action.php", { // Corrected
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params.toString()
    }).then(res => res.text())
      .then(data => {
        document.getElementById("adjustPopup").classList.add("hidden");
        showPopup(data);
        setTimeout(() => location.reload(), 2000);
    });
}

function showPopup(message) {
    document.getElementById("popupMessage").textContent = message;
    document.getElementById("popupFeedback").classList.remove("hidden");
}

function closePopup() {
    document.getElementById("popupFeedback").classList.add("hidden");
}

window.addEventListener('click', function(event) {
    const modals = ['confirmPopup', 'adjustPopup', 'popupFeedback'];
    modals.forEach(id => {
        const modal = document.getElementById(id);
        if (!modal.classList.contains('hidden') && event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>

<div id="confirmPopup" class="popup-container hidden">
    <div class="popup-content">
        <h3>Confirm Punch</h3>
        <p>Do you want to punch <strong><span id="confirmActionText"></span></strong> at <span id="confirmTimeText"></span>?</p>
        <button onclick="confirmSubmit()">Yes</button>
        <button onclick="openAdjuster()">Adjust Time</button>
        <button onclick="document.getElementById('confirmPopup').classList.add('hidden')">Cancel</button>
    </div>
</div>

<div id="adjustPopup" class="popup-container hidden">
    <div class="popup-content">
        <h3>Adjust Time</h3>
        <input type="time" id="adjustTimeInput">
        <button onclick="submitWithAdjustment()">Submit Adjusted Time</button>
        <button onclick="document.getElementById('adjustPopup').classList.add('hidden')">Cancel</button>
    </div>
</div>

<div id="popupFeedback" class="popup-container hidden">
    <div class="popup-content">
        <p id="popupMessage"></p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>