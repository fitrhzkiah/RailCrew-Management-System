<?php

require_once __DIR__ . '/includes/account-auth.php';

$user = currentUser($conn);

if (!$user) {
    header("Location: login.html");
    exit;
}

header("Cache-Control: no-store");

$token = htmlspecialchars(
    csrfToken(),
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="<?= $token ?>">

    <title>RailCrew | Sign Out</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #F5F5F0;
        }

        .logout-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            width: 360px;
            max-width: 85%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .logout-card img {
            width: 180px;
            margin-bottom: 15px;
        }

        .logout-card h2 {
            color: #263746;
        }

        .logout-card p {
            color: #737B80;
            margin: 20px 0;
        }

        .logout-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        #logoutMessage {
            color: #B64A4A;
        }

    </style>

</head>

<body>

    <div class="logout-card">

        <img src="image/logo.png" alt="RailCrew Logo">

        <h2>Sign Out</h2>

        <p>Are you sure you want to sign out?</p>

        <div class="logout-actions">

            <a href="settings.html"
               class="btn btn-secondary">
                Cancel
            </a>

            <button type="button"
                    class="btn btn-primary"
                    id="confirmLogout">
                Sign Out
            </button>

        </div>

        <p id="logoutMessage"></p>

    </div>

    <script>

        const button = document.getElementById(
            "confirmLogout"
        );

        const message = document.getElementById(
            "logoutMessage"
        );

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        ).content;

        button.addEventListener("click", async () => {

            button.disabled = true;

            message.textContent = "";

            try {

                const response = await fetch(
                    "api/logout.php",
                    {
                        method: "POST",

                        credentials: "same-origin",

                        cache: "no-store",

                        headers: {
                            "Content-Type": "application/json"
                        },

                        body: JSON.stringify({
                            csrf_token: csrfToken
                        })
                    }
                );

                if (response.status === 401) {

                    window.location.replace("login.html");

                    return;
                }

                const result = await response.json();

                if (!response.ok || !result.success) {

                    throw new Error(
                        result.message || "Unable to sign out."
                    );
                }

                window.location.replace("login.html");

            } catch (error) {

                message.textContent = error.message;

                button.disabled = false;
            }
        });

    </script>

</body>

</html>