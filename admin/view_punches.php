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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View & Adjust Punches</title>
        <link rel="stylesheet" href="../css/timesheet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
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
    <div class="container card">
        <h2 class="card-header">Select Employee and Date Range</h2>
        <div class="card-body">
            <form method="GET" class="summary-filter">
            <div class="filterrow">
                <div class="field">
                    <label for="weekFrom">From:</label>
                    <input type="text" name="from" id="weekFrom" value="<?= htmlspecialchars(date('m/d/Y', strtotime($from))) ?>">
                </div>
                <div class="field">
                    <label for="weekTo">To:</label>
                    <input type="text" name="to" id="weekTo" value="<?= htmlspecialchars(date('m/d/Y', strtotime($to))) ?>">
                </div>
                <div class="field">
                    <label for="emp">Employee:</label>
                    <select name="emp" id="emp" required>
                        <option value="">Select an Employee...</option>
                        <?php while ($emp = $employeeList->fetch_assoc()): ?>
                            <option value="<?= $emp['ID'] ?>" <?= ($emp['ID'] == $employeeID ? 'selected' : '') ?>>
                                <?= htmlspecialchars($emp['LastName'] . ', ' . $emp['FirstName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="buttons">
                <button type="submit">Load Timesheet</button>
                <a href="view_punches.php" class="btn-reset">Reset</a>
            </div>
        </form>
        </div>

        <?php if (!empty($employeeID)): ?>
        <hr>
        <div class="card-body">
            <form method="POST" action="save_punches.php">
            <input type="hidden" name="employeeID" value="<?= $employeeID ?>">
            <input type="hidden" name="from" value="<?= $from ?>">
            <input type="hidden" name="to" value="<?= $to ?>">

            <div class="table-responsive">
                <table class="timesheet-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>In Location</th>
                            <th>Lunch Out</th>
                            <th>Lunch Out Loc</th>
                            <th>Lunch In</th>
                            <th>Lunch In Loc</th>
                            <th>Clock Out</th>
                            <th>Out Location</th>
                            <th>Total Hours</th>
                            <th>Adj. Reason</th>
                            <th>Save</th>
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
                            $punch = $stmt->get_result()->fetch_assoc();

                            $clockIn   = !empty($punch['TimeIN'])      ? date('H:i', strtotime($punch['TimeIN']))     : '';
                            $clockOut  = !empty($punch['TimeOut'])     ? date('H:i', strtotime($punch['TimeOut']))    : '';
                            $lunchOut  = !empty($punch['LunchStart'])  ? date('H:i', strtotime($punch['LunchStart'])) : '';
                            $lunchIn   = !empty($punch['LunchEnd'])    ? date('H:i', strtotime($punch['LunchEnd']))   : '';
                        ?>
                        <tr>
                            <td><strong><?= $day->format('D, m/d') ?></strong></td>
                            <td><input type="time" name="clockin[<?= $date ?>]" value="<?= $clockIn ?>" step="60"></td>
                            <td>
                                <?php if (!empty($punch['LatitudeIN']) && !empty($punch['LongitudeIN'])) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $punch['LatitudeIN'] ?>,<?= $punch['LongitudeIN'] ?>" target="_blank" class="confirm-btn small-btn">View</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><input type="time" name="lunchout[<?= $date ?>]" value="<?= $lunchOut ?>" step="60"></td>
                             <td>
                                <?php if (!empty($punch['LatitudeLunchStart']) && !empty($punch['LongitudeLunchStart'])) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $punch['LatitudeLunchStart'] ?>,<?= $punch['LongitudeLunchStart'] ?>" target="_blank" class="confirm-btn small-btn">View</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><input type="time" name="lunchin[<?= $date ?>]" value="<?= $lunchIn ?>" step="60"></td>
                            <td>
                                <?php if (!empty($punch['LatitudeLunchEnd']) && !empty($punch['LongitudeLunchEnd'])) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $punch['LatitudeLunchEnd'] ?>,<?= $punch['LongitudeLunchEnd'] ?>" target="_blank" class="confirm-btn small-btn">View</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><input type="time" name="clockout[<?= $date ?>]" value="<?= $clockOut ?>" step="60"></td>
                            <td>
                                <?php if (!empty($punch['LatitudeOut']) && !empty($punch['LongitudeOut'])) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $punch['LatitudeOut'] ?>,<?= $punch['LongitudeOut'] ?>" target="_blank" class="confirm-btn small-btn">View</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="total-cell" id="total-<?= $date ?>">0.00</td>
                            <td>
                                <select name="reason[<?= $date ?>]" class="reason-dropdown">
                                    <option value="">Select...</option>
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
            </div>

            <div class="card-footer totals">
                <strong>Total Week:</strong> <span id="weekly-total" class="total-highlight">0.00h</span> |
                <strong>Overtime:</strong> <span id="weekly-overtime" class="overtime-highlight">0.00h</span>
            </div>
        </form>
        </div>
        <?php else: ?>
            <div class="alert" style="margin-top: 2rem;">Please select an employee and date range to view their timesheet.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
<script>
    const picker = new Litepicker({ 
        element: document.getElementById('weekFrom'),
        elementEnd: document.getElementById('weekTo'),
        singleMode: false,
        format: 'MM/DD/YYYY',
        allowRepick: true,
        setup: (picker) => {
            picker.on('selected', (date1, date2) => {
                // your code
            });
        }
    });

    function toMinutes(timeStr) {
        if (!timeStr) return 0;
        const [h, m] = timeStr.split(':');
        return parseInt(h, 10) * 60 + parseInt(m, 10);
    }

    function updateTotals() {
        let weeklyTotalMinutes = 0;
        document.querySelectorAll('tbody tr').forEach(row => {
            const inTime = row.querySelector('input[name^="clockin"]')?.value;
            const outTime = row.querySelector('input[name^="clockout"]')?.value;
            const lunchOut = row.querySelector('input[name^="lunchout"]')?.value;
            const lunchIn = row.querySelector('input[name^="lunchin"]')?.value;

            let totalMins = 0;
            const start = toMinutes(inTime);
            const end = toMinutes(outTime);

            if (start && end && end > start) {
                totalMins = end - start;
                const lOut = toMinutes(lunchOut);
                const lIn = toMinutes(lunchIn);
                if (lOut && lIn && lIn > lOut) {
                    totalMins -= (lIn - lOut);
                }
            }
            
            const hours = (totalMins / 60).toFixed(2);
            row.querySelector('.total-cell').innerText = hours;
            weeklyTotalMinutes += totalMins;
        });

        const weeklyTotalHours = weeklyTotalMinutes / 60;
        const overtimeHours = Math.max(0, weeklyTotalHours - 40);

        document.getElementById('weekly-total').innerText = weeklyTotalHours.toFixed(2) + "h";
        document.getElementById('weekly-overtime').innerText = overtimeHours.toFixed(2) + "h";
    }

    document.querySelectorAll('input[type="time"]').forEach(input => {
        input.addEventListener('change', updateTotals);
    });

    // Initial calculation on page load
    if (document.querySelector('tbody tr')) {
        updateTotals();
    }
</script>

</body>
</html>
