
const loginForm = document.getElementById("loginForm");

const loginButton = document.getElementById("loginButton");

const loginMessage = document.getElementById("loginMessage");

const passwordInput = document.getElementById("password");

const togglePassword = document.getElementById("togglePassword");

let csrfToken = "";


// Display error message

function showError(message) {

    loginMessage.textContent = message;
    loginMessage.hidden = false;
}


// Initialise login session

async function initialiseLogin() {

    try {

        const response = await fetch("api/login.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        const result = await response.json();

        if (!response.ok || !result.success) {

            throw new Error(
                result.message || "Server request failed."
            );
        }

        if (result.logged_in) {

            window.location.replace(result.redirect);
            return;
        }

        csrfToken = result.csrf_token;

        loginButton.disabled = false;

        loginMessage.hidden = true;

    } catch (error) {

        showError(
            "Unable to connect to the server. Please try again."
        );
    }
}


// Show / hide password

togglePassword.addEventListener("click", function () {

    const hidden = passwordInput.type === "password";

    passwordInput.type = hidden ? "text" : "password";

    togglePassword.querySelector("i").className =
        hidden
        ? "fa-solid fa-eye-slash"
        : "fa-solid fa-eye";

    togglePassword.setAttribute(
        "aria-label",
        hidden ? "Hide password" : "Show password"
    );
});


// Submit login form

loginForm.addEventListener("submit", async function (event) {

    event.preventDefault();

    loginMessage.hidden = true;

    loginButton.disabled = true;
    loginButton.textContent = "Signing in...";

    const role = document.querySelector(
        'input[name="role"]:checked'
    );

    const data = {

        identifier: document
            .getElementById("identifier")
            .value.trim(),

        password: passwordInput.value,

        role: role ? role.value : "",

        csrf_token: csrfToken
    };

    try {

        const response = await fetch("api/login.php", {

            method: "POST",

            credentials: "same-origin",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok && result.success) {

            window.location.replace(result.redirect);

        } else {

            showError(result.message);
        }

    } catch (error) {

        showError(
            "Unable to connect to the server. Please try again."
        );

    } finally {

        loginButton.disabled = false;
        loginButton.textContent = "Sign In";
    }
});

initialiseLogin();
