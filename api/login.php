
<?php

require_once __DIR__ . '/../includes/account-auth.php';

// Initialise login session

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $user = currentUser($conn);

    respond([
        "success" => true,
        "csrf_token" => csrfToken(),
        "logged_in" => $user !== null,
        "redirect" => $user
            ? dashboardPage($user['Account_Role'])
            : null
    ]);
}


// Receive login information

$data = readInput();

verifyCsrf($data);

$identifier = trim(field($data, 'identifier'));
$password = field($data, 'password');
$role = field($data, 'role');


// Validate input

if (
    $identifier === "" ||
    strlen($identifier) > 100 ||
    $password === "" ||
    strlen($password) > 72 ||
    !in_array($role, ['Admin', 'Staff'], true)
) {

    respond([
        "success" => false,
        "message" => "Please enter valid login details."
    ], 400);
}


// Search account in database

$sql = "
    SELECT
        ua.Account_ID,
        ua.Staff_ID,
        ua.Username,
        ua.Password_Hash,
        ua.Account_Role,
        ua.Account_Status,
        s.Employment_Status
    FROM user_account ua
    INNER JOIN staff s
        ON ua.Staff_ID = s.Staff_ID
    WHERE ua.Username = ?
       OR s.Email = ?
    LIMIT 2
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ss",
    $identifier,
    $identifier
);

$stmt->execute();

$result = $stmt->get_result();

$account = $result->num_rows === 1
    ? $result->fetch_assoc()
    : null;

$stmt->close();


// Validate account credentials

if (
    !$account ||
    !password_verify(
        $password,
        $account['Password_Hash'] ?? ''
    ) ||
    $account['Account_Role'] !== $role ||
    $account['Account_Status'] !== 'Active' ||
    $account['Employment_Status'] !== 'Active'
) {

    respond([
        "success" => false,
        "message" => "Invalid username, password or account role."
    ], 401);
}


// Update last login

$stmt = $conn->prepare("
    UPDATE user_account
    SET Last_Login = CURDATE()
    WHERE Account_ID = ?
");

$stmt->bind_param(
    "s",
    $account['Account_ID']
);

$stmt->execute();

$stmt->close();


// Create authenticated session

session_regenerate_id(true);

$_SESSION = [];

$_SESSION['Account_ID'] =
    $account['Account_ID'];

$_SESSION['Staff_ID'] =
    $account['Staff_ID'];

$_SESSION['Account_Role'] =
    $account['Account_Role'];

csrfToken();


// Return JSON response

respond([
    "success" => true,
    "message" => "Login successful.",
    "role" => $account['Account_Role'],
    "redirect" => dashboardPage(
        $account['Account_Role']
    )
]);
