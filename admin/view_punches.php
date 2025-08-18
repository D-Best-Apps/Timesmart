<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('America/Chicago');

$employeeList = $conn->query("SELECT ID, FirstName, LastName FROM users ORDER BY LastName");

$from = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
$to = $_GET['to'] ?? date('Y-m-d', strtotime('sunday this week'));
$employeeID = $_GET['emp'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Punches</title>
    <link rel="stylesheet" href="../css/timesheet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
    <style>
        select.reason-dropdown {
            width: 100%;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
    </style>
</head>
<body>

<header>
    <img src="../images/D-Best.png" alt="D-Best Logo" class="logo">
    <h1>Employee Punch Adjustments</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="view_punches.php" class="active">Timesheets</a>
        <a href="summary.php">Summary</a>
        <a href="reports.php">Reports</a>
        <a href="manage_users.php">Users</a>
        <a href="attendance.php">Attendance</a>
        <a href="manage_admins.php">Admins</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="dashboard-container">
    <div class="container">
        <form method="GET" class="summary-filter">
            <div class="field">
                <label>Date Range From:
                    <input type="text" name="from" id="weekFrom" value="<?= htmlspecialchars(date('m/d/Y', strtotime($from))) ?>">
                </label>
            </div>
            <div class="field">
                <label>To:
                    <input type="text" name="to" id="weekTo" value="<?= htmlspecialchars(date('m/d/Y', strtotime($to))) ?>">
                </label>
            </div>
            <div class="field">
                <label>Employee:
                    <select name="emp" required>
                        <option value="">Select Employee</option>
                        <?php while ($emp = $employeeList->fetch_assoc()): ?>
                            <option value="<?= $emp['ID'] ?>" <?= ($emp['ID'] == $employeeID ? 'selected' : '') ?>>
                                <?= htmlspecialchars($emp['FirstName'] . ' ' . $emp['LastName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </label>
            </div>
            <div class="buttons">
                <button type="submit">Submit</button>
                <a href="view_punches.php" class="btn-reset">Reset</a>
            </div>
        </form>

        <?php if (!empty($employeeID)): ?>
        <form method="POST" action="save_punches.php">
            <input type="hidden" name="employeeID" value="<?= $employeeID ?>">
            <input type="hidden" name="from" value="<?= $from ?>">
            <input type="hidden" name="to" value="<?= $to ?>">

            <table class="timesheet-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Lunch Out</th>
                        <th>Lunch In</th>
                        <th>Clock Out</th>
                        <th>Total</th>
                        <th>Reason for Adjustment</th>
                        <th>Confirm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $start = new DateTime($from);
                    $end = new DateTime($to);
                    $interval = new DateInterval('P1D');
                    $range = new DatePeriod($start, $interval, $end->modify('+1 day'));

                    foreach ($range as $day):
                        $date = $day->format('Y-m-d');

                        $stmt = $conn->prepare("SELECT * FROM timepunches WHERE EmployeeID = ? AND Date = ? ORDER BY TimeIN DESC LIMIT 1");
                        $stmt->bind_param("is", $employeeID, $date);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $punch = $result->fetch_assoc();

                        $clockIn   = !empty($punch['TimeIN'])      ? date('H:i', strtotime($punch['TimeIN']))     : '';
                        $clockOut  = !empty($punch['TimeOut'])     ? date('H:i', strtotime($punch['TimeOut']))    : '';
                        $lunchOut  = !empty($punch['LunchStart'])  ? date('H:i', strtotime($punch['LunchStart'])) : '';
                        $lunchIn   = !empty($punch['LunchEnd'])    ? date('H:i', strtotime($punch['LunchEnd']))   : '';
                    ?>
                    <tr>
                        <td><?= $day->format('m/d/Y') ?></td>
                        <td><input type="time" name="clockin[<?= $date ?>]" value="<?= $clockIn ?>" step="60"></td>
                        <td><input type="time" name="lunchout[<?= $date ?>]" value="<?= $lunchOut ?>" step="60"></td>
                        <td><input type="time" name="lunchin[<?= $date ?>]" value="<?= $lunchIn ?>" step="60"></td>
                        <td><input type="time" name="clockout[<?= $date ?>]" value="<?= $clockOut ?>" step="60"></td>
                        <td class="total-cell" id="total-<?= $date ?>">0.00</td>
                        <td>
                            <select name="reason[<?= $date ?>]" class="reason-dropdown">
                                <option value="">Select reason...</option>
                                <option value="Forgot to punch">Forgot to punch</option>
                                <option value="Shift change">Shift change</option>
                                <option value="System error">System error</option>
                                <option value="Time correction">Time correction</option>
                                <option value="Late arrival">Late arrival</option>
                                <option value="Early departure">Early departure</option>
                                <option value="Manual update">Manual update</option>
                            </select>
                        </td>
                        <td><button type="submit" name="confirm[]" value="<?= $date ?>" class="confirm-btn">✔</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <strong>Total Week:</strong> <span id="weekly-total">0.00h</span> |
                <strong>Overtime:</strong> <span id="weekly-overtime">0.00h</span>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
<script>
    new Litepicker({ element: document.getElementById('weekFrom'), singleMode: true, format: 'MM/DD/YYYY' });
    new Litepicker({ element: document.getElementById('weekTo'), singleMode: true, format: 'MM/DD/YYYY' });

    function toMinutes(timeStr) {
        if (!timeStr) return null;
        const [h, m] = timeStr.split(':');
        return parseInt(h) * 60 + parseInt(m);
    }

    function updateTotals() {
        let weeklyTotal = 0;
        document.querySelectorAll('tbody tr').forEach(row => {
            const inTime = row.querySelector('input[name^="clockin"]')?.value;
            const outTime = row.querySelector('input[name^="clockout"]')?.value;
            const lunchOut = row.querySelector('input[name^="lunchout"]')?.value;
            const lunchIn = row.querySelector('input[name^="lunchin"]')?.value;

            let totalMins = 0;
            const start = toMinutes(inTime);
            const end = toMinutes(outTime);

            if (start !== null && end !== null && end > start) {
                totalMins = end - start;
                const lOut = toMinutes(lunchOut);
                const lIn = toMinutes(lunchIn);
                if (lOut !== null && lIn !== null && lIn > lOut) {
                    totalMins -= (lIn - lOut);
                }
                const hours = (totalMins / 60).toFixed(2);
                row.querySelector('.total-cell').innerText = hours;
                weeklyTotal += parseFloat(hours);
            } else {
                row.querySelector('.total-cell').innerText = "0.00";
            }
        });

        document.getElementById('weekly-total').innerText = weeklyTotal.toFixed(2) + "h";
        document.getElementById('weekly-overtime').innerText = (weeklyTotal > 40 ? (weeklyTotal - 40).toFixed(2) : "0.00") + "h";
    }

    document.querySelectorAll('input[type="time"]').forEach(input => {
        input.addEventListener('change', updateTotals);
    });

    updateTotals();
</script>

</body>
</html>
