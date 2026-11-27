
<?php

require_once __DIR__ . '/../includes/account-auth.php';

$user = requireLogin($conn);

$data = readInput();

verifyCsrf($data);

$currentPassword = field($data, 'current_password');

$newPassword = field($data, 'new_password');

$confirmPassword = field($data, 'confirm_password');


// Check current password

if (
    strlen($currentPassword) > 72 ||
    !password_verify(
        $currentPassword,
        $user['Password_Hash'] ?? ''
    )
) {

    respond([
        "success" => false,
        "message" => "Current password is incorrect."
    ], 400);
}


// Validate new password

if (
    strlen($newPassword) < 8 ||
    strlen($newPassword) > 72 ||
    strpos($newPassword, "\0") !== false ||
    !preg_match('/[A-Z]/', $newPassword) ||
    !preg_match('/[a-z]/', $newPassword) ||
    !preg_match('/[0-9]/', $newPassword)
) {

    respond([
        "success" => false,
        "message" => "Password must contain 8–72 bytes, uppercase, lowercase and a number."
    ], 400);
}


// Confirm password

if ($newPassword !== $confirmPassword) {

    respond([
        "success" => false,
        "message" => "New passwords do not match."
    ], 400);
}


// Prevent same password

if (password_verify(
    $newPassword,
    $user['Password_Hash']
)) {

    respond([
        "success" => false,
        "message" => "New password must be different from current password."
    ], 400);
}


// Generate password hash

$newHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);


try {

    $stmt = $conn->prepare("
        UPDATE user_account
        SET Password_Hash = ?
        WHERE Account_ID = ?
          AND Password_Hash = ?
    ");

    $stmt->bind_param(
        "sss",
        $newHash,
        $user['Account_ID'],
        $user['Password_Hash']
    );

    $stmt->execute();

    $updated = $stmt->affected_rows === 1;

    $stmt->close();

    if (!$updated) {

        respond([
            "success" => false,
            "message" => "Password has changed. Refresh and try again."
        ], 409);
    }

    session_regenerate_id(true);

    unset($_SESSION['csrf_token']);

    respond([
        "success" => true,
        "message" => "Password changed successfully.",
        "csrf_token" => csrfToken()
    ]);

} catch (mysqli_sql_exception $e) {

    error_log($e->getMessage());

    respond([
        "success" => false,
        "message" => "Unable to change password."
    ], 500);
}
