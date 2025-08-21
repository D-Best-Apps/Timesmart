<?php
// kiosk_action.php
// --- SETUP AND DEPENDENCIES ---
require 'db.php';
date_default_timezone_set('America/Chicago');
header('Content-Type: application/json');

function send_json_response($success, $message, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function setClockStatus($conn, $empID, $status) {
    $stmt = $conn->prepare("UPDATE users SET ClockStatus = ? WHERE ID = ?");
    $stmt->bind_param("si", $status, $empID);
    $stmt->execute();
    $stmt->close();
}

function calculateTotalHours($clockIn, $lunchOut, $lunchIn, $clockOut) {
    if (empty($clockIn) || empty($clockOut)) { return null; }
    
    $start = strtotime($clockIn);
    $end = strtotime($clockOut);
    if ($end <= $start) { return 0.0; }

    $totalSeconds = ($end - $start);

    if (!empty($lunchOut) && !empty($lunchIn)) {
        $lStart = strtotime($lunchOut);
        $lEnd = strtotime($lunchIn);
        if ($lEnd > $lStart) {
            $totalSeconds -= ($lEnd - $lStart);
        }
    }
    return round($totalSeconds / 3600, 2);
}

// --- Input Processing ---
$input = json_decode(file_get_contents('php://input'), true);

$tagID        = $input['tagID'] ?? '';
$action       = $input['action'] ?? '';

// --- Validation ---
if (!$tagID || !$action) {
    send_json_response(false, "❌ Missing Tag ID or action.", 400);
}

// --- Get User from Tag ID ---
$userStmt = $conn->prepare("SELECT ID, ClockStatus, FirstName, LastName FROM users WHERE TagID = ?");
$userStmt->bind_param("s", $tagID);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    send_json_response(false, "Invalid Tag ID.", 404);
}

$user = $userResult->fetch_assoc();
$empID = $user['ID'];
$userStatus = $user['ClockStatus'] ?? 'Out';
$fullName = $user['FirstName'] . ' ' . $user['LastName'];
$userStmt->close();

// --- Time Calculation ---
$dateTime = new DateTime('now', new DateTimeZone('America/Chicago'));
$now  = $dateTime->format('H:i:s');
$date = $dateTime->format('Y-m-d');

// --- Get Latest Punch ---
$punchStmt = $conn->prepare("SELECT TimeIN, LunchStart, LunchEnd FROM timepunches WHERE EmployeeID = ? AND TimeOUT IS NULL ORDER BY Date DESC, TimeIN DESC LIMIT 1");
$punchStmt->bind_param("i", $empID);
$punchStmt->execute();
$punchResult = $punchStmt->get_result();
$lastPunch = ($punchResult->num_rows > 0) ? $punchResult->fetch_assoc() : null;
$punchStmt->close();

// --- Handle Actions ---
switch ($action) {
    case "in":
        if ($lastPunch) {
            send_json_response(false, "⚠️ You are already clocked in.", 409);
        }
        $stmt = $conn->prepare("INSERT INTO timepunches (EmployeeID, Date, TimeIN) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $empID, $date, $now);
        $stmt->execute();
        setClockStatus($conn, $empID, 'In');
        send_json_response(true, "✅ $fullName clocked in at " . date("g:i A", strtotime($now)));
        break;

    case "out":
        if (!$lastPunch) {
            send_json_response(false, "⚠️ No active punch to clock out from.", 400);
        }
        $totalHours = calculateTotalHours($lastPunch['TimeIN'], $lastPunch['LunchStart'], $lastPunch['LunchEnd'], $now);
        $stmt = $conn->prepare("UPDATE timepunches SET TimeOUT = ?, TotalHours = ? WHERE EmployeeID = ? AND TimeOUT IS NULL");
        $stmt->bind_param("sdi", $now, $totalHours, $empID);
        $stmt->execute();
        setClockStatus($conn, $empID, 'Out');
        send_json_response(true, "🕔 $fullName clocked out at " . date("g:i A", strtotime($now)) . ". Total Hours: " . number_format($totalHours, 2));
        break;

    case "lunch_start":
        if ($userStatus !== 'In') { send_json_response(false, "⚠️ You must be clocked in to start lunch.", 400); }
        if (!$lastPunch || !empty($lastPunch['LunchStart'])) { send_json_response(false, "⚠️ No active punch, or lunch already started.", 400); }
        
        $stmt = $conn->prepare("UPDATE timepunches SET LunchStart = ? WHERE EmployeeID = ? AND TimeOUT IS NULL");
        $stmt->bind_param("si", $now, $empID);
        $stmt->execute();
        setClockStatus($conn, $empID, 'Lunch');
        send_json_response(true, "🍽️ $fullName started lunch at " . date("g:i A", strtotime($now)));
        break;

    case "lunch_end":
        if ($userStatus !== 'Lunch') { send_json_response(false, "⚠️ You are not on lunch.", 400); }
        if (!$lastPunch || empty($lastPunch['LunchStart']) || !empty($lastPunch['LunchEnd'])) { send_json_response(false, "⚠️ No active lunch punch found.", 400); }

        $stmt = $conn->prepare("UPDATE timepunches SET LunchEnd = ? WHERE EmployeeID = ? AND TimeOUT IS NULL");
        $stmt->bind_param("si", $now, $empID);
        $stmt->execute();
        setClockStatus($conn, $empID, 'In');
        send_json_response(true, "✅ $fullName ended lunch at " . date("g:i A", strtotime($now)));
        break;

    default:
        send_json_response(false, "❌ Invalid action specified.", 400);
        break;
}
