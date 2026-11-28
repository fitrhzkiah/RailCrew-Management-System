
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


    // Check assignment belongs to the logged-in staff
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


    // Check existing sign-in record
    $stmt = $conn->prepare("
        SELECT Attendance_ID, Sign_In_Time, Sign_Off_Time
        FROM attendance
        WHERE Assignment_ID = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param("s", $assignmentID);

    $stmt->execute();

    $attendance = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    // Staff must sign in first
    if (!$attendance || empty($attendance['Sign_In_Time'])) {

        $conn->rollback();

        respond([
            "success" => false,
            "message" => "Please sign in before signing off."
        ], 400);
    }


    // Prevent duplicate sign-off
    if (!empty($attendance['Sign_Off_Time'])) {

        $conn->rollback();

        respond([
            "success" => false,
            "message" => "You have already signed off."
        ], 409);
    }


    // Record sign-off and calculate attendance status
    // Compare actual sign-in with scheduled train departure.
    $stmt = $conn->prepare("
        UPDATE attendance a

        INNER JOIN crew_assignment ca
            ON a.Assignment_ID = ca.Assignment_ID

        INNER JOIN train_run tr
            ON ca.Train_Run_ID = tr.Train_Run_ID

        SET
            a.Sign_Off_Time = NOW(),

            a.Attendance_Status =
                CASE
                    WHEN a.Sign_In_Time > tr.Departure_DateTime
                    THEN 'Late'
                    ELSE 'Present'
                END

        WHERE a.Attendance_ID = ?
          AND a.Sign_Off_Time IS NULL
    ");

    $stmt->bind_param(
        "s",
        $attendance['Attendance_ID']
    );

    $stmt->execute();

    $updated = $stmt->affected_rows;

    $stmt->close();


    if ($updated !== 1) {

        $conn->rollback();

        respond([
            "success" => false,
            "message" => "Unable to update attendance. Please try again."
        ], 409);
    }


    $conn->commit();


    respond([
        "success" => true,
        "message" => "You have successfully signed off."
    ]);


} catch (Throwable $e) {

    $conn->rollback();

    error_log($e->getMessage());

    respond([
        "success" => false,
        "message" => "Unable to record sign-off. Please try again."
    ], 500);
}
