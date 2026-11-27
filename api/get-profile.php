
<?php

require_once __DIR__ . '/../includes/account-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    respond([
        "success" => false,
        "message" => "Invalid request method."
    ], 405);
}

$user = requireLogin($conn);

// Never send password hash to frontend

unset($user['Password_Hash']);

respond([
    "success" => true,
    "user" => $user,
    "csrf_token" => csrfToken()
]);
