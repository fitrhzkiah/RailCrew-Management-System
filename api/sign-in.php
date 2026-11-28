
<?php

require_once __DIR__ . '/../includes/account-auth.php';

// Use Malaysia local time
$conn->query("SET time_zone = '+08:00'");


// Allow logged-in staff only
$user = requireRole($conn, 'Staff');


// Receive request
$data = readInput();

verifyCsrf($data);

$assignmentID = trim(field($data, 'assignment_id'));

if ($assignmentID === '' || strlen($assignmentID) > 10) {
    respond([
        "success" => false,
        "message" => "Invalid assignment ID."
    ], 400);
}


try {

    $conn->begin_transaction();

    // Check that this assignment belongs to the logged-in staff
    // and is scheduled for today.
    $stmt = $conn->prepare("
        SELECT Assignment_ID
        FROM crew_assignment
        WHERE Assignment_ID = ?
          AND Staff_ID = ?
          AND Assignment_Date = CURDATE()
          AND (Assignment_Status IS NULL
               OR Assignment_Status <> 'Cancelled')
        FOR UPDATE
    ");

    $stmt->bind_param(
        "ss",
        $assignmentID,
        $user['Staff_ID']
    );

    $stmt->execute();

    $assignment = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if (!$assignment) {

        $conn->rollback();

        respond([
            "success" => false,
            "message" => "Assignment not found or not available today."
        ], 404);
    }


    // Prevent duplicate sign-in
    $stmt = $conn->prepare("
        SELECT Attendance_ID, Sign_In_Time
        FROM attendance
        WHERE Assignment_ID = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $assignmentID);

    $stmt->execute();

    $existing = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if ($existing) {

        $conn->rollback();

        respond([
            "success" => false,
            "message" => "You have already signed in for this assignment."
        ], 409);
    }


    // Generate a unique attendance ID
    $attendanceID = strtoupper(bin2hex(random_bytes(5)));


    // Record actual sign-in time
    $stmt = $conn->prepare("
        INSERT INTO attendance (
            Attendance_ID,
            Assignment_ID,
            Sign_In_Time,
            Sign_Off_Time,
            Attendance_Status
        )
        VALUES (?, ?, NOW(), NULL, 'Incomplete')
    ");

    $stmt->bind_param(
        "ss",
        $attendanceID,
        $assignmentID
    );

    $stmt->execute();

    $stmt->close();

    $conn->commit();


    respond([
        "success" => true,
        "message" => "You have successfully signed in."
    ]);


} catch (Throwable $e) {

    $conn->rollback();

    error_log($e->getMessage());

    respond([
        "success" => false,
        "message" => "Unable to record sign-in. Please try again."
    ], 500);
}
