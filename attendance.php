
<?php
require_once 'includes/account-auth.php';
require_once 'includes/account-db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$currentUser = requireRole($conn, 'Admin');

// Search and filter
$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');
$status = trim($_GET['status'] ?? '');

// Retrieve attendance and shift records
$sql = "
    SELECT
        ca.Assignment_ID,
        ca.Assignment_Date,
        ca.Assignment_Status,
        ca.Duty_Position,
        s.Staff_ID,
        s.Full_Name,
        tr.Train_Run_ID,
        tr.Departure_DateTime,
        tr.Arrival_DateTime,
        tr.Run_Status,
        t.Train_Name,
        t.Train_Number,
        a.Attendance_ID,
        a.Sign_In_Time,
        a.Sign_Off_Time,
        a.Attendance_Status
    FROM crew_assignment ca
    JOIN staff s
        ON ca.Staff_ID = s.Staff_ID
    LEFT JOIN train_run tr
        ON ca.Train_Run_ID = tr.Train_Run_ID
    LEFT JOIN train t
        ON tr.Train_ID = t.Train_ID
    LEFT JOIN attendance a
        ON ca.Assignment_ID = a.Assignment_ID
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (s.Staff_ID LIKE ? OR s.Full_Name LIKE ?)";
    $keyword = "%$search%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

if ($date !== '') {
    $sql .= " AND ca.Assignment_Date = ?";
    $params[] = $date;
    $types .= "s";
}

if ($status !== '') {
    if ($status === 'Absent') {
        $sql .= " AND a.Attendance_ID IS NULL";
    } else {
        $sql .= " AND a.Attendance_Status = ?";
        $params[] = $status;
        $types .= "s";
    }
}

$sql .= " ORDER BY ca.Assignment_Date DESC, ca.Assignment_ID DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query error: " . htmlspecialchars($conn->error));
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$records = [];

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

$stmt->close();

// Calculate summary
$total = count($records);
$present = 0;
$late = 0;
$incomplete = 0;
$absent = 0;
$missingSignIn = 0;
$missingSignOff = 0;
$completedShifts = 0;

foreach ($records as $row) {

    if ($row['Attendance_ID'] === null) {
        $absent++;
        $missingSignIn++;
    } else {

        if ($row['Attendance_Status'] === 'Present') {
            $present++;
        } elseif ($row['Attendance_Status'] === 'Late') {
            $late++;
        } elseif ($row['Attendance_Status'] === 'Incomplete') {
            $incomplete++;
        }

        if (empty($row['Sign_In_Time'])) {
            $missingSignIn++;
        }

        if (empty($row['Sign_Off_Time'])) {
            $missingSignOff++;
        }
    }

    if (!empty($row['Sign_In_Time']) &&
        !empty($row['Sign_Off_Time'])) {
        $completedShifts++;
    }
}

function displayValue($value) {
    return htmlspecialchars((string)($value ?? '-'));
}

function displayStatus($row) {
    if ($row['Attendance_ID'] === null) {
        return 'Absent';
    }

    return $row['Attendance_Status'] ?: 'Incomplete';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Attendance & Shift Records</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 25px;
    color: #333;
}

.container {
    max-width: 1500px;
    margin: auto;
}

h1 {
    color: #26374a;
    margin-bottom: 8px;
}

.subtitle {
    color: #666;
    margin-bottom: 25px;
    font-size: 14px;
}

/* Summary Cards */

.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 5px solid #26374a;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.summary-card h3 {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.summary-card p {
    margin: 12px 0 0;
    font-size: 27px;
    font-weight: bold;
    color: #26374a;
}

/* Sections */

.section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.section h2 {
    margin-top: 0;
    color: #26374a;
    font-size: 20px;
}

/* Search */

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

input, select, button, .reset-btn {
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
}

input, select {
    min-width: 180px;
}

button {
    background: #26374a;
    color: white;
    border: none;
    cursor: pointer;
}

button:hover {
    background: #405a75;
}

.reset-btn {
    background: #e5e7eb;
    color: #333;
    text-decoration: none;
}

/* Tables */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
    white-space: nowrap;
    font-size: 13px;
}

th {
    background: #26374a;
    color: white;
}

tr:nth-child(even) {
    background: #f2f2f2;
}

/* Status */

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.present {
    background: #d4edda;
    color: #155724;
}

.late {
    background: #fff3cd;
    color: #856404;
}

.absent {
    background: #f8d7da;
    color: #721c24;
}

.incomplete {
    background: #d1ecf1;
    color: #0c5460;
}

.empty {
    padding: 20px;
    background: #fff3cd;
    border-radius: 5px;
}

/* Report */

.report-btn {
    margin-top: 10px;
    padding: 12px 20px;
}

/* Responsive Layout */

@media (max-width: 700px) {

    body {
        padding: 15px;
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .section {
        padding: 15px;
    }

}

/* A4 Landscape PDF Printing */

@media print {

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    body {
        background: white;
        padding: 0;
        font-family: Arial, sans-serif;
        font-size: 9px;
        color: black;
    }

    .container {
        width: 100%;
        max-width: 100%;
    }

    h1 {
        font-size: 18px;
        margin-bottom: 4px;
    }

    .subtitle {
        font-size: 10px;
        margin-bottom: 12px;
    }

    .summary {
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
        margin-bottom: 12px;
    }

    .summary-card {
        padding: 8px;
        box-shadow: none;
        border: 1px solid #ccc;
        border-left: 3px solid #26374a;
        border-radius: 3px;
    }

    .summary-card h3 {
        font-size: 9px;
    }

    .summary-card p {
        font-size: 16px;
        margin-top: 5px;
    }

    .filters,
    .report-btn {
        display: none !important;
    }

    .section {
        padding: 10px;
        margin-bottom: 12px;
        box-shadow: none;
        border: 1px solid #ddd;
        border-radius: 3px;
    }

    .section h2 {
        font-size: 13px;
        margin-bottom: 6px;
    }

    .table-wrapper {
        overflow: visible;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
        margin-top: 6px;
    }

    th, td {
        padding: 5px 4px;
        font-size: 8px;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    th {
        background: #26374a !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    tr {
        break-inside: avoid;
    }

    .status {
        padding: 2px 4px;
        font-size: 8px;
    }

}

</style>

</head>

<body>

<div class="container">

    <h1>Attendance & Shift Records</h1>

    <p class="subtitle">
        Review crew attendance, monitor completed shifts
        and identify missing attendance records.
    </p>

    <!-- Attendance Summary -->

    <div class="summary">

        <div class="summary-card">
            <h3>Total Assignments</h3>
            <p><?= $total ?></p>
        </div>

        <div class="summary-card">
            <h3>Present</h3>
            <p><?= $present ?></p>
        </div>

        <div class="summary-card">
            <h3>Late</h3>
            <p><?= $late ?></p>
        </div>

        <div class="summary-card">
            <h3>Absent</h3>
            <p><?= $absent ?></p>
        </div>

        <div class="summary-card">
            <h3>Incomplete</h3>
            <p><?= $incomplete ?></p>
        </div>

        <div class="summary-card">
            <h3>Completed Shifts</h3>
            <p><?= $completedShifts ?></p>
        </div>

    </div>

    <!-- Search and Filter -->

    <div class="section">

        <h2>Search and Filter Records</h2>

        <form method="GET" class="filters">

            <input
                type="text"
                name="search"
                placeholder="Search Staff ID or Name"
                value="<?= displayValue($search) ?>"
            >

            <input
                type="date"
                name="date"
                value="<?= displayValue($date) ?>"
            >

            <select name="status">

                <option value="">All Statuses</option>

                <option value="Present"
                    <?= $status === 'Present' ? 'selected' : '' ?>>
                    Present
                </option>

                <option value="Late"
                    <?= $status === 'Late' ? 'selected' : '' ?>>
                    Late
                </option>

                <option value="Absent"
                    <?= $status === 'Absent' ? 'selected' : '' ?>>
                    Absent
                </option>

                <option value="Incomplete"
                    <?= $status === 'Incomplete' ? 'selected' : '' ?>>
                    Incomplete
                </option>

            </select>

            <button type="submit">Search</button>

            <a href="attendance.php" class="reset-btn">Reset</a>

        </form>

    </div>

    <!-- Attendance Records -->

    <div class="section">

        <h2>Attendance Records</h2>

        <?php if ($total > 0): ?>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Assignment ID</th>
                        <th>Staff ID</th>
                        <th>Staff Name</th>
                        <th>Train Run ID</th>
                        <th>Train Name</th>
                        <th>Train Number</th>
                        <th>Duty Position</th>
                        <th>Assignment Date</th>
                        <th>Sign-In Time</th>
                        <th>Sign-Off Time</th>
                        <th>Attendance Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($records as $row): ?>

                    <?php
                    $currentStatus = displayStatus($row);
                    $statusClass = strtolower($currentStatus);
                    ?>

                    <tr>

                        <td><?= displayValue($row['Assignment_ID']) ?></td>
                        <td><?= displayValue($row['Staff_ID']) ?></td>
                        <td><?= displayValue($row['Full_Name']) ?></td>
                        <td><?= displayValue($row['Train_Run_ID']) ?></td>
                        <td><?= displayValue($row['Train_Name']) ?></td>
                        <td><?= displayValue($row['Train_Number']) ?></td>
                        <td><?= displayValue($row['Duty_Position']) ?></td>
                        <td><?= displayValue($row['Assignment_Date']) ?></td>
                        <td><?= displayValue($row['Sign_In_Time']) ?></td>
                        <td><?= displayValue($row['Sign_Off_Time']) ?></td>

                        <td>
                            <span class="status <?= displayValue($statusClass) ?>">
                                <?= displayValue($currentStatus) ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php else: ?>

            <p class="empty">No attendance or assignment records found.</p>

        <?php endif; ?>

    </div>

    <!-- Historical and Completed Shift Records -->

    <div class="section">

        <h2>Historical and Completed Shift Records</h2>

        <?php if ($total > 0): ?>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Staff Name</th>
                        <th>Train Run ID</th>
                        <th>Train Number</th>
                        <th>Duty Position</th>
                        <th>Assignment Date</th>
                        <th>Scheduled Departure</th>
                        <th>Scheduled Arrival</th>
                        <th>Actual Sign-In</th>
                        <th>Actual Sign-Off</th>
                        <th>Shift Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($records as $row): ?>

                    <?php
                    $completed = !empty($row['Sign_In_Time']) &&
                                 !empty($row['Sign_Off_Time']);
                    ?>

                    <tr>

                        <td><?= displayValue($row['Staff_ID']) ?></td>
                        <td><?= displayValue($row['Full_Name']) ?></td>
                        <td><?= displayValue($row['Train_Run_ID']) ?></td>
                        <td><?= displayValue($row['Train_Number']) ?></td>
                        <td><?= displayValue($row['Duty_Position']) ?></td>
                        <td><?= displayValue($row['Assignment_Date']) ?></td>
                        <td><?= displayValue($row['Departure_DateTime']) ?></td>
                        <td><?= displayValue($row['Arrival_DateTime']) ?></td>
                        <td><?= displayValue($row['Sign_In_Time']) ?></td>
                        <td><?= displayValue($row['Sign_Off_Time']) ?></td>

                        <td>
                            <?= $completed ? 'Completed' : 'Incomplete' ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php else: ?>

            <p class="empty">No shift records found.</p>

        <?php endif; ?>

    </div>

    <!-- Missing Attendance Records -->

    <div class="section">

        <h2>Missing Attendance Records</h2>

        <p>
            Missing sign-in records:
            <strong><?= $missingSignIn ?></strong>
        </p>

        <p>
            Missing sign-off records:
            <strong><?= $missingSignOff ?></strong>
        </p>

    </div>

    <!-- Attendance Report -->

    <div class="section">

        <h2>Attendance Report</h2>

        <p>
            Generate a printable report using the current
            search and filter results.
        </p>

        <button class="report-btn" onclick="window.print()">
            Print / Save Report
        </button>

    </div>

</div>

</body>
</html>
