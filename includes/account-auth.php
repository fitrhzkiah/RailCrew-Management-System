
<?php

require_once __DIR__ . '/account-db.php';

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);

    session_start();
}

header("Cache-Control: no-store");


// Return JSON response

function respond($data, $status = 200) {

    http_response_code($status);

    header("Content-Type: application/json; charset=utf-8");

    echo json_encode($data);

    exit;
}


// Read JSON request from JavaScript

function readInput() {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        respond([
            "success" => false,
            "message" => "Invalid request method."
        ], 405);
    }

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {

        respond([
            "success" => false,
            "message" => "Invalid request."
        ], 400);
    }

    return $data;
}


// Read string safely

function field($data, $key) {

    return isset($data[$key])
        && is_string($data[$key])
        ? $data[$key]
        : "";
}


// Generate CSRF token

function csrfToken() {

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


// Validate CSRF token

function verifyCsrf($data) {

    $token = field($data, 'csrf_token');

    if (!hash_equals(csrfToken(), $token)) {

        respond([
            "success" => false,
            "message" => "Invalid session token. Refresh the page."
        ], 403);
    }
}


// Retrieve current logged-in user

function currentUser($conn) {

    if (empty($_SESSION['Account_ID'])) {

        return null;
    }

    $sql = "
        SELECT
            ua.Account_ID,
            ua.Staff_ID,
            ua.Username,
            ua.Password_Hash,
            ua.Account_Role,
            ua.Account_Status,
            ua.Created_At,
            ua.Last_Login,
            s.Full_Name,
            s.Email,
            s.Phone_Number,
            s.Department,
            s.Job_Position,
            s.Employment_Status
        FROM user_account ua
        INNER JOIN staff s
            ON ua.Staff_ID = s.Staff_ID
        WHERE ua.Account_ID = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "s",
        $_SESSION['Account_ID']
    );

    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (
        !$user ||
        $user['Account_Status'] !== 'Active' ||
        $user['Employment_Status'] !== 'Active' ||
        !in_array(
            $user['Account_Role'],
            ['Admin', 'Staff'],
            true
        )
    ) {

        $_SESSION = [];

        return null;
    }

    return $user;
}


// Protect backend APIs

function requireLogin($conn) {

    $user = currentUser($conn);

    if (!$user) {

        respond([
            "success" => false,
            "message" => "Please log in first."
        ], 401);
    }

    return $user;
}


// Protect role-specific APIs

function requireRole($conn, $role) {

    $user = requireLogin($conn);

    if ($user['Account_Role'] !== $role) {

        respond([
            "success" => false,
            "message" => "Access denied."
        ], 403);
    }

    return $user;
}


// Choose landing page

function dashboardPage($role) {

    if ($role === 'Admin') {

        return 'admin_dashboard.html';
    }

    return 'settings.html';
}
