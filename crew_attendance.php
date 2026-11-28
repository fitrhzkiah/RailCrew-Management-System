
<?php
require_once __DIR__ . '/includes/account-auth.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn->query("SET time_zone = '+08:00'");

// Check login
$user = currentUser($conn);

if (!$user) {
    header("Location: login.html");
    exit;
}

// Only staff can access this page
if ($user['Account_Role'] !== 'Staff') {
    header("Location: admin_dashboard.html");
    exit;
}

// Generate security token
$csrfToken = csrfToken();

// Get today's assignments for the logged-in staff
$sql = "
    SELECT
        ca.Assignment_ID,
        ca.Duty_Position,
        ca.Assignment_Date,
        ca.Assignment_Status,
        tr.Train_Run_ID,
        tr.Departure_DateTime,
        tr.Arrival_DateTime,
        t.Train_Number,
        t.Train_Name,
        r.Origin_Station,
        r.Destination_Station,
        a.Attendance_ID,
        a.Sign_In_Time,
        a.Sign_Off_Time,
        a.Attendance_Status

    FROM crew_assignment ca

    LEFT JOIN train_run tr
        ON ca.Train_Run_ID = tr.Train_Run_ID

    LEFT JOIN train t
        ON tr.Train_ID = t.Train_ID

    LEFT JOIN route r
        ON tr.Route_ID = r.Route_ID

    LEFT JOIN attendance a
        ON ca.Assignment_ID = a.Assignment_ID

    WHERE ca.Staff_ID = ?
      AND ca.Assignment_Date = CURDATE()
      AND (ca.Assignment_Status IS NULL
           OR ca.Assignment_Status != 'Cancelled')

    ORDER BY tr.Departure_DateTime ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['Staff_ID']);
$stmt->execute();

$result = $stmt->get_result();

$assignments = [];

while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

$stmt->close();

// Safe display function
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Format date and time
function formatDateTime($value) {
    if (!$value) {
        return "Not recorded";
    }

    return date("d M Y, h:i A", strtotime($value));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RailCrew | Crew Attendance</title>

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="css/style.css">

    <style>
        .attendance-page {
            padding: 30px;
            font-family: 'Inter', sans-serif;
        }

        .attendance-header {
            margin-bottom: 25px;
        }

        .attendance-header h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }

        .attendance-header p {
            color: #64748b;
        }

        .staff-info {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .staff-info h3 {
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item small {
            display: block;
            color: #64748b;
            margin-bottom: 5px;
        }

        .info-item strong {
            color: #1e293b;
        }

        .assignment-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .assignment-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .assignment-title h3 {
            margin: 0;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e2e8f0;
            color: #475569;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status.present {
            background: #dcfce7;
            color: #166534;
        }

        .status.late {
            background: #ffedd5;
            color: #9a3412;
        }

        .status.incomplete {
            background: #fee2e2;
            color: #991b1b;
        }

        .attendance-times {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .time-box {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
        }

        .time-box small {
            display: block;
            color: #64748b;
            margin-bottom: 8px;
        }

        .time-box strong {
            font-size: 15px;
        }

        .attendance-buttons {
            display: flex;
            gap: 12px;
        }

        .attendance-buttons button {
            border: none;
            border-radius: 8px;
            padding: 12px 22px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .sign-in-btn {
            background: #16a34a;
            color: white;
        }

        .sign-off-btn {
            background: #2563eb;
            color: white;
        }

        .attendance-buttons button:disabled {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            display: none;
        }

        .message.success {
            display: block;
            background: #dcfce7;
            color: #166534;
        }

        .message.error {
            display: block;
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-message {
            background: white;
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 650px) {
            .attendance-page {
                padding: 18px;
            }

            .info-grid,
            .attendance-times {
                grid-template-columns: 1fr;
            }

            .attendance-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="attendance-page">

    <div class="attendance-header">
        <h1>Crew Sign-In / Sign-Off</h1>
        <p>Record your attendance for today's assigned duties.</p>
    </div>

    <div class="staff-info">

        <h3>Staff Information</h3>

        <div class="info-grid">

            <div class="info-item">
                <small>Staff ID</small>
                <strong><?= e($user['Staff_ID']) ?></strong>
            </div>

            <div class="info-item">
                <small>Staff Name</small>
                <strong><?= e($user['Full_Name']) ?></strong>
            </div>

            <div class="info-item">
                <small>Department</small>
                <strong><?= e($user['Department']) ?></strong>
            </div>

            <div class="info-item">
                <small>Job Position</small>
                <strong><?= e($user['Job_Position']) ?></strong>
            </div>

        </div>

    </div>

    <div id="message" class="message" role="alert"></div>

    <h2 style="margin-bottom: 18px;">Today's Assigned Duties</h2>

    <?php if (count($assignments) > 0): ?>

        <?php foreach ($assignments as $assignment): ?>

            <?php
                if (empty($assignment['Sign_In_Time'])) {
                    $status = 'Pending';
                    $statusClass = 'pending';
                } elseif (empty($assignment['Sign_Off_Time'])) {
                    $status = 'Incomplete';
                    $statusClass = 'incomplete';
                } else {
                    $status = $assignment['Attendance_Status'] ?: 'Present';
                    $statusClass = strtolower($status);
                }
            ?>

            <div class="assignment-card">

                <div class="assignment-title">

                    <h3>
                        Train Run:
                        <?= e($assignment['Train_Run_ID']) ?>
                    </h3>

                    <span class="status <?= e($statusClass) ?>">
                        <?= e($status) ?>
                    </span>

                </div>

                <div class="info-grid">

                    <div class="info-item">
                        <small>Assignment ID</small>
                        <strong><?= e($assignment['Assignment_ID']) ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Train Number</small>
                        <strong><?= e($assignment['Train_Number'] ?: '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Duty Position</small>
                        <strong><?= e($assignment['Duty_Position']) ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Train Name</small>
                        <strong><?= e($assignment['Train_Name'] ?: '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Origin</small>
                        <strong><?= e($assignment['Origin_Station'] ?: '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Destination</small>
                        <strong><?= e($assignment['Destination_Station'] ?: '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Scheduled Departure</small>
                        <strong><?= e(formatDateTime($assignment['Departure_DateTime'])) ?></strong>
                    </div>

                    <div class="info-item">
                        <small>Scheduled Arrival</small>
                        <strong><?= e(formatDateTime($assignment['Arrival_DateTime'])) ?></strong>
                    </div>

                </div>

                <hr style="margin: 22px 0; border: 0; border-top: 1px solid #e2e8f0;">

                <div class="attendance-times">

                    <div class="time-box">
                        <small>
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Sign-In Time
                        </small>

                        <strong>
                            <?= e(formatDateTime($assignment['Sign_In_Time'])) ?>
                        </strong>
                    </div>

                    <div class="time-box">
                        <small>
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Sign-Off Time
                        </small>

                        <strong>
                            <?= e(formatDateTime($assignment['Sign_Off_Time'])) ?>
                        </strong>
                    </div>

                </div>

                <div class="attendance-buttons">

                    <button
                        class="sign-in-btn"
                        onclick="recordAttendance('sign-in', '<?= e($assignment['Assignment_ID']) ?>')"
                        <?= !empty($assignment['Sign_In_Time']) ? 'disabled' : '' ?>
                    >
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?= !empty($assignment['Sign_In_Time']) ? 'Already Signed In' : 'Sign In' ?>
                    </button>

                    <button
                        class="sign-off-btn"
                        onclick="recordAttendance('sign-off', '<?= e($assignment['Assignment_ID']) ?>')"
                        <?= empty($assignment['Sign_In_Time']) || !empty($assignment['Sign_Off_Time']) ? 'disabled' : '' ?>
                    >
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <?= !empty($assignment['Sign_Off_Time']) ? 'Already Signed Off' : 'Sign Off' ?>
                    </button>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-message">

            <i class="fa-solid fa-calendar-xmark"
               style="font-size: 35px; margin-bottom: 15px;"></i>

            <h3>No Assigned Duties Today</h3>

            <p>You currently have no train assignments for today.</p>

        </div>

    <?php endif; ?>

</div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;

async function recordAttendance(action, assignmentId) {

    const confirmation = action === 'sign-in'
        ? 'Are you sure you want to sign in?'
        : 'Are you sure you want to sign off?';

    if (!confirm(confirmation)) {
        return;
    }

    const message = document.getElementById('message');

    const endpoint = action === 'sign-in'
        ? 'api/sign-in.php'
        : 'api/sign-off.php';

    try {

        const response = await fetch(endpoint, {

            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            credentials: 'same-origin',

            body: JSON.stringify({
                assignment_id: assignmentId,
                csrf_token: csrfToken
            })

        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Attendance action failed.');
        }

        alert(data.message);

        window.location.reload();

    } catch (error) {

        message.textContent = error.message;
        message.className = 'message error';

    }
}
</script>

</body>
</html>