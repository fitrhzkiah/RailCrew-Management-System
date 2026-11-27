
<?php

require_once __DIR__ . '/../includes/account-auth.php';

$user = requireLogin($conn);

$data = readInput();

verifyCsrf($data);

$fullName = trim(field($data, 'full_name'));
$email = trim(field($data, 'email'));
$phone = trim(field($data, 'phone'));

$phoneDigits = preg_replace('/\D/', '', $phone);


// Validate name

if (
    mb_strlen($fullName) < 2 ||
    mb_strlen($fullName) > 100
) {

    respond([
        "success" => false,
        "message" => "Please enter a valid full name."
    ], 400);
}


// Validate email

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 100
) {

    respond([
        "success" => false,
        "message" => "Please enter a valid email address."
    ], 400);
}


// Validate phone number

if (
    !preg_match('/^\+?[0-9\s-]+$/', $phone) ||
    strlen($phoneDigits) < 9 ||
    strlen($phoneDigits) > 15 ||
    strlen($phone) > 20
) {

    respond([
        "success" => false,
        "message" => "Please enter a valid phone number."
    ], 400);
}


// Check duplicate email

$stmt = $conn->prepare("
    SELECT Staff_ID
    FROM staff
    WHERE Email = ?
      AND Staff_ID != ?
");

$stmt->bind_param(
    "ss",
    $email,
    $user['Staff_ID']
);

$stmt->execute();

$duplicate = $stmt->get_result()->num_rows > 0;

$stmt->close();


// Check conflict with another username

$stmt = $conn->prepare("
    SELECT Account_ID
    FROM user_account
    WHERE Username = ?
      AND Account_ID != ?
");

$stmt->bind_param(
    "ss",
    $email,
    $user['Account_ID']
);

$stmt->execute();

$duplicateUsername =
    $stmt->get_result()->num_rows > 0;

$stmt->close();


if ($duplicate || $duplicateUsername) {

    respond([
        "success" => false,
        "message" => "This email is already used by another account."
    ], 409);
}


// Update authorised personal details

try {

    $stmt = $conn->prepare("
        UPDATE staff
        SET Full_Name = ?,
            Email = ?,
            Phone_Number = ?
        WHERE Staff_ID = ?
    ");

    $stmt->bind_param(
        "ssss",
        $fullName,
        $email,
        $phone,
        $user['Staff_ID']
    );

    $stmt->execute();
    $stmt->close();

    respond([
        "success" => true,
        "message" => "Personal details updated successfully."
    ]);

} catch (mysqli_sql_exception $e) {

    error_log($e->getMessage());

    respond([
        "success" => false,
        "message" => "Unable to update personal details."
    ], 500);
}
