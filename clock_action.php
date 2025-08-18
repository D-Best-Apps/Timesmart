<?php
// clock_action.php
// --- SETUP AND DEPENDENCIES ---
require 'db.php';
date_default_timezone_set('America/Chicago');
header('Content-Type: application/json');

/**
 * Gets the real client IP address, accounting for proxies.
 * @return string|null The client's IP address or null if not found.
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    }
    return $ip ? trim($ip) : null;
}

/**
 * Sends a JSON response and exits.
 * @param bool $success Whether the operation was successful.
 * @param string $message The message to send to the client.
 * @param int $http_code The HTTP status code to send.
 * @param string|null $log_error An optional internal error message to log.
 */
function send_json_response($success, $message, $http_code = 200, $log_error = null) {
    if ($log_error !== null) {
        error_log('TimeClock API Error: ' . $log_error);
    }
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/**
 * Updates the user's clock status.
 * @param mysqli $conn The database connection object.
 * @param int $empID The ID of the employee to update.
 * @param string $status The new status ('In', 'Out', 'Lunch').
 */
function setClockStatus($conn, $empID, $status) {
    $stmt = $conn->prepare("UPDATE users SET ClockStatus = ? WHERE ID = ?");
    if ($stmt === false) { send_json_response(false, "DB prepare error (setClockStatus)", 500, $conn->error); }
    $stmt->bind_param("si", $status, $empID);
    if (!$stmt->execute()) {
        send_json_response(false, "DB execute error (setClockStatus)", 500, $stmt->error);
    }
    $stmt->close();
}

/**
 * Calculates total work hours, excluding lunch.
 * @param string|null $clockIn
 * @param string|null $lunchOut
 * @param string|null $lunchIn
 * @param string|null $clockOut
 * @return float|null
 */
function calculateTotalHours($clockIn, $lunchOut, $lunchIn, $clockOut) {
    if (empty($clockIn) || empty($clockOut)) { return null; }
    
    $start = strtotime($clockIn);
    $end = strtotime($clockOut);
    if ($end <= $start) { return 0.0; } // Return 0 if end time is before start time

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

$empID        = (int) ($input['EmployeeID'] ?? 0);
$action       = $input['action'] ?? '';
$note         = trim($input['note'] ?? '');
$adjustedTime = $input['time'] ?? '';
$clientTime   = $input['clientTime'] ?? '';

$lat      = (isset($input['latitude']) && $input['latitude'] !== '') ? (float) $input['latitude'] : null;
$lon      = (isset($input['longitude']) && $input['longitude'] !== '') ? (float) $input['longitude'] : null;
$accuracy = (isset($input['accuracy']) && $input['accuracy'] !== '') ? (float) $input['accuracy'] : null;
$ip       = get_client_ip();

// --- Validation ---
if (!$empID || !$action) {
    send_json_response(false, "❌ Missing employee ID or action.", 400);
}

// Fetch GPS requirement from settings
$gpsRequired = false;
$gpsQuery = $conn->prepare("SELECT SettingValue FROM settings WHERE SettingKey = 'EnforceGPS' LIMIT 1");
if ($gpsQuery) {
    $gpsQuery->execute();
    $gpsQuery->bind_result($value);
    if ($gpsQuery->fetch()) {
        $gpsRequired = ($value === '1');
    }
    $gpsQuery->close();
}

// If GPS is required, we must have EITHER valid GPS coordinates OR a valid IP address.
if ($gpsRequired && ($lat === null || $lon === null) && $ip === null) {
    send_json_response(false, "📍 Location is required, but neither GPS nor IP address could be determined.", 400);
}

// --- Time Calculation ---
try {
    // Prioritize the precise clientTime from JavaScript
    if ($clientTime) {
        $dateTime = new DateTime($clientTime);
        $dateTime->setTimezone(new DateTimeZone('America/Chicago'));
    } elseif ($adjustedTime) {
        // Fallback to adjusted time, assuming today's date
        $dateTime = new DateTime(date('Y-m-d') . ' ' . $adjustedTime, new DateTimeZone('America/Chicago'));
    } else {
        // Default to current server time
        $dateTime = new DateTime('now', new DateTimeZone('America/Chicago'));
    }
    $now  = $dateTime->format('H:i:s');
    $date = $dateTime->format('Y-m-d');
} catch (Exception $e) {
    send_json_response(false, "Invalid time format provided.", 400, $e->getMessage());
}

// --- Get User Status & Latest Punch ---

// Get current user status
$userStmt = $conn->prepare("SELECT ClockStatus FROM users WHERE ID = ?");
if ($userStmt === false) { send_json_response(false, "DB prepare error (user)", 500, $conn->error); }
$userStmt->bind_param("i", $empID);
if (!$userStmt->execute()) { send_json_response(false, "DB execute error (user)", 500, $userStmt->error); }
$userStmt->store_result();
$userStmt->bind_result($userStatus);
if (!$userStmt->fetch()) {
    send_json_response(false, "Employee with ID " . $empID . " not found.", 404);
}
$userStatus = $userStatus ?? 'Out'; // Default to 'Out' if ClockStatus is NULL
$userStmt->close();


// Find the last open punch record for the user to handle any ongoing shift (e.g., overnight)
$punchStmt = $conn->prepare("SELECT TimeIN, LunchStart, LunchEnd FROM timepunches WHERE EmployeeID = ? AND TimeOUT IS NULL ORDER BY Date DESC, TimeIN DESC LIMIT 1");
if ($punchStmt === false) { send_json_response(false, "DB prepare error (get open punch)", 500, $conn->error); }
$punchStmt->bind_param("i", $empID);
if (!$punchStmt->execute()) { send_json_response(false, "DB execute error (get open punch)", 500, $punchStmt->error); }
$punchStmt->store_result();

$lastPunch = null;
if ($punchStmt->num_rows > 0) {
    $punchStmt->bind_result($timeIn, $lunchStart, $lunchEnd);
    $punchStmt->fetch();
    $lastPunch = [
        'TimeIN' => $timeIn,
        'LunchStart' => $lunchStart,
        'LunchEnd' => $lunchEnd
    ];
}
$punchStmt->close();


// --- Handle Actions ---
switch ($action) {
    case "clockin":
        if ($lastPunch) {
            $clockInTime = date("g:i A", strtotime($lastPunch['TimeIN']));
            send_json_response(false, "⚠️ You are already clocked in from " . $clockInTime . ". Please clock out first.", 409); // 409 Conflict
        }
        $stmt = $conn->prepare("INSERT INTO timepunches (EmployeeID, Date, TimeIN, Note, LatitudeIN, LongitudeIN, AccuracyIN, IPAddressIN) VALUES (?, ?, ?, ?, ?, ?, ?, INET_ATON(?))");
        $stmt->bind_param("isssddds", $empID, $date, $now, $note, $lat, $lon, $accuracy, $ip);
        if (!$stmt->execute()) {
            send_json_response(false, "DB execute error (clockin)", 500, $stmt->error);
        }
        setClockStatus($conn, $empID, 'In');
        send_json_response(true, "✅ Clocked in at " . date("g:i A", strtotime($now)));
        break;

    case "lunchstart":
        if ($userStatus !== 'In') { send_json_response(false, "⚠️ You must be clocked in to start lunch.", 400); }
        if (!$lastPunch || !empty($lastPunch['LunchStart'])) { send_json_response(false, "⚠️ No active punch, or lunch already started.", 400); }
        
        $stmt = $conn->prepare("UPDATE timepunches SET LunchStart = ?, Note = CONCAT(Note, ?), LatitudeLunchStart = ?, LongitudeLunchStart = ?, AccuracyLunchStart = ?, IPAddressLunchStart = INET_ATON(?) WHERE EmployeeID = ? AND TimeOUT IS NULL");
        $full_note = "\nLunch Start Note: " . $note;
        $stmt->bind_param("ssdddsi", $now, $full_note, $lat, $lon, $accuracy, $ip, $empID);
        if (!$stmt->execute()) {
            send_json_response(false, "DB execute error (lunchstart)", 500, $stmt->error);
        }

        setClockStatus($conn, $empID, 'Lunch');
        send_json_response(true, "🍽️ Lunch started at " . date("g:i A", strtotime($now)));
        break;

    case "lunchend":
        if ($userStatus !== 'Lunch') { send_json_response(false, "⚠️ You are not on lunch.", 400); }
        if (!$lastPunch || empty($lastPunch['LunchStart']) || !empty($lastPunch['LunchEnd'])) { send_json_response(false, "⚠️ No active lunch punch found.", 400); }

        $stmt = $conn->prepare("UPDATE timepunches SET LunchEnd = ?, Note = CONCAT(Note, ?), LatitudeLunchEnd = ?, LongitudeLunchEnd = ?, AccuracyLunchEnd = ?, IPAddressLunchEnd = INET_ATON(?) WHERE EmployeeID = ? AND TimeOUT IS NULL");
        $full_note = "\nLunch End Note: " . $note;
        $stmt->bind_param("ssdddsi", $now, $full_note, $lat, $lon, $accuracy, $ip, $empID);
        if (!$stmt->execute()) { send_json_response(false, "DB execute error (lunchend)", 500, $stmt->error); }

        setClockStatus($conn, $empID, 'In');
        send_json_response(true, "✅ Lunch ended at " . date("g:i A", strtotime($now)));
        break;

    case "clockout":
        if (!$lastPunch) {
            send_json_response(false, "⚠️ No active punch to clock out from.", 400);
        }

        // Ensure lunch is properly ended if user clocks out while on lunch
        if ($userStatus === 'Lunch' && empty($lastPunch['LunchEnd'])) {
            $lastPunch['LunchEnd'] = $now;
        }

        $totalHours = calculateTotalHours($lastPunch['TimeIN'], $lastPunch['LunchStart'], $lastPunch['LunchEnd'], $now);

        if (!empty($note)) {
            $stmt = $conn->prepare("UPDATE timepunches SET TimeOUT = ?, LunchEnd = ?, TotalHours = ?, Note = CONCAT(Note, ?), LatitudeOut = ?, LongitudeOut = ?, AccuracyOut = ?, IPAddressOut = INET_ATON(?) WHERE EmployeeID = ? AND TimeOUT IS NULL");
            $full_note = "\nClock Out Note: " . $note;
            $stmt->bind_param("ssdsdddsi", $now, $lastPunch['LunchEnd'], $totalHours, $full_note, $lat, $lon, $accuracy, $ip, $empID);
        } else {
            $stmt = $conn->prepare("UPDATE timepunches SET TimeOUT = ?, LunchEnd = ?, TotalHours = ?, LatitudeOut = ?, LongitudeOut = ?, AccuracyOut = ?, IPAddressOut = INET_ATON(?) WHERE EmployeeID = ? AND TimeOUT IS NULL");
            $stmt->bind_param("ssdddsii", $now, $lastPunch['LunchEnd'], $totalHours, $lat, $lon, $accuracy, $ip, $empID);
        }

        if (!$stmt->execute()) {
            send_json_response(false, "DB execute error (clockout)", 500, $stmt->error);
        }

        setClockStatus($conn, $empID, 'Out');
        $message = "🕔 Clocked out at " . date("g:i A", strtotime($now)) . ". Total Hours: " . number_format($totalHours, 2);
        send_json_response(true, $message);
        break;

    default:
        send_json_response(false, "❌ Invalid action specified.", 400);
        break;
}
