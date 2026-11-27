
<?php

require_once __DIR__ . '/../includes/account-auth.php';

requireLogin($conn);

$data = readInput();

verifyCsrf($data);


// Clear session data

$_SESSION = [];


// Remove session cookie

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Lax'
    ]);
}


// Destroy session

session_destroy();

respond([
    "success" => true,
    "message" => "Logged out successfully.",
    "redirect" => "login.html"
]);
