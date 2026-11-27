let csrfToken = "";
let currentProfile = null;

const $ = id => document.getElementById(id);

const profileForm = $("profileForm");
const passwordForm = $("passwordForm");


// Display message

function showMessage(message, success = false) {

    const box = $("settingsMessage");

    box.textContent = message;

    box.className = success
        ? "settings-alert settings-success"
        : "settings-alert settings-error";

    box.hidden = false;

    box.scrollIntoView({
        behavior: "smooth",
        block: "nearest"
    });
}


// Send request to PHP backend

async function apiRequest(url, data = null) {

    const options = {
        credentials: "same-origin",
        cache: "no-store"
    };

    if (data !== null) {

        options.method = "POST";

        options.headers = {
            "Content-Type": "application/json"
        };

        options.body = JSON.stringify({
            ...data,
            csrf_token: csrfToken
        });
    }

    const response = await fetch(url, options);

    if (response.status === 401) {

        window.location.replace("login.html");

        throw new Error("Please log in again.");
    }

    const result = await response.json();

    if (!response.ok || !result.success) {

        throw new Error(
            result.message || "Request failed."
        );
    }

    return result;
}


// Generate sidebar navigation

function buildSidebar(role) {

    const adminMenu = [

        ["Dashboard", "fa-gauge-high"],

        ["Staff Directory", "fa-id-card"],

        ["Train Fleet", "fa-train"],

        ["Train Operations", "fa-route"],

        ["Crew Scheduling", "fa-clipboard-list"],

        ["Attendance Records", "fa-calendar-check"],

        ["Certification", "fa-certificate"],

        ["Duty Hours & Rest", "fa-clock"],

        ["Standby Crew", "fa-user-clock"],

        ["Leave Requests", "fa-calendar-minus"],

        ["Crew Replacement", "fa-users-gear"]
    ];

    const staffMenu = [

        ["Leave Requests", "fa-calendar-minus"],

        ["Crew Sign In / Sign Off", "fa-calendar-check"]
    ];

    const menu = role === "Admin"
        ? adminMenu
        : staffMenu;

    const sidebar = $("sidebarMenu");

    sidebar.replaceChildren();


    // Generate navigation links

    menu.forEach(([label, icon]) => {

        const li = document.createElement("li");

        let item;

        // Enable Admin Dashboard navigation

        if (role === "Admin" && label === "Dashboard") {

            item = document.createElement("a");

            item.href = "admin_dashboard.html";

        } else {

            // Other modules remain disabled
            // until final group integration.

            item = document.createElement("span");

            item.className = "account-unavailable-link";

            item.title = "Pending group integration";
        }

        const symbol = document.createElement("i");

        symbol.className = "fa-solid " + icon;

        item.append(
            symbol,
            document.createTextNode(" " + label)
        );

        li.appendChild(item);

        sidebar.appendChild(li);
    });


    // Settings link

    const settingsItem = document.createElement("li");

    settingsItem.innerHTML = `
        <a href="settings.html" class="active">
            <i class="fa-solid fa-gear"></i>
            <span>Settings</span>
        </a>
    `;

    sidebar.appendChild(settingsItem);


    // Logout button

    const logoutItem = document.createElement("li");

    logoutItem.innerHTML = `
        <button type="button"
                class="account-logout-button"
                id="logoutButton">

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>Sign Out</span>

        </button>
    `;

    sidebar.appendChild(logoutItem);

    $("logoutButton").addEventListener(
        "click",
        logout
    );
}


// Display profile information

function displayProfile(user) {

    currentProfile = user;

    $("navbarName").textContent =
        user.Full_Name || "";

    $("profileName").textContent =
        user.Full_Name || "";

    $("profilePosition").textContent =
        user.Job_Position || "";

    $("employmentStatus").textContent =
        user.Employment_Status || "";

    $("staffID").textContent =
        user.Staff_ID;

    $("accountID").textContent =
        user.Account_ID;

    $("department").textContent =
        user.Department || "Not available";

    $("accountRole").textContent =
        user.Account_Role === "Admin"
        ? "Administrator"
        : "Railway Staff";

    $("username").textContent =
        user.Username || "";

    $("accountStatus").textContent =
        user.Account_Status;

    $("createdAt").textContent =
        user.Created_At || "Not available";

    $("lastLogin").textContent =
        user.Last_Login || "Not available";


    // Editable information

    $("fullName").value =
        user.Full_Name || "";

    $("email").value =
        user.Email || "";

    $("phone").value =
        user.Phone_Number || "";


    // Store original values for Cancel button

    $("fullName").defaultValue =
        $("fullName").value;

    $("email").defaultValue =
        $("email").value;

    $("phone").defaultValue =
        $("phone").value;


    // Generate initials avatar

    const initials = (user.Full_Name || "")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map(name => Array.from(name)[0] || "")
        .join("")
        .toUpperCase();

    $("profileAvatar").textContent =
        initials || "?";

    buildSidebar(user.Account_Role);
}


// Load profile from database

async function loadProfile() {

    const result = await apiRequest(
        "api/get-profile.php"
    );

    csrfToken = result.csrf_token;

    displayProfile(result.user);

    $("loadingMessage").hidden = true;

    $("accountPage").hidden = false;
}


// Update personal details

profileForm.addEventListener("submit", async event => {

    event.preventDefault();

    const button = profileForm.querySelector(
        'button[type="submit"]'
    );

    button.disabled = true;

    try {

        const result = await apiRequest(
            "api/update-profile.php",
            {
                full_name: $("fullName").value.trim(),

                email: $("email").value.trim(),

                phone: $("phone").value.trim()
            }
        );

        await loadProfile();

        showMessage(result.message, true);

    } catch (error) {

        showMessage(error.message);

    } finally {

        button.disabled = false;
    }
});


// Show / hide password

document.querySelectorAll("[data-target]")
    .forEach(button => {

        button.addEventListener("click", () => {

            const input = $(button.dataset.target);

            const hidden = input.type === "password";

            input.type = hidden ? "text" : "password";

            button.querySelector("i").className =
                hidden
                ? "fa-solid fa-eye-slash"
                : "fa-solid fa-eye";

            button.setAttribute(
                "aria-label",
                hidden ? "Hide password" : "Show password"
            );
        });
    });


// Password strength indicator

function updateStrength() {

    const password = $("newPassword").value;

    const bar = $("passwordStrengthBar");

    const text = $("passwordStrengthText");

    let score = 0;

    if (password.length >= 8) score++;

    if (password.length >= 12) score++;

    if (/[A-Z]/.test(password)) score++;

    if (/[a-z]/.test(password)) score++;

    if (/[0-9]/.test(password)) score++;

    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (!password) {

        bar.style.width = "0";

        text.textContent = "";

    } else if (score <= 3) {

        bar.style.width = "30%";

        bar.style.background = "#B64A4A";

        text.textContent = "Weak password";

        text.style.color = "#B64A4A";

    } else if (score <= 4) {

        bar.style.width = "60%";

        bar.style.background = "#D5A64A";

        text.textContent = "Medium password";

        text.style.color = "#A87820";

    } else {

        bar.style.width = "100%";

        bar.style.background = "#568B87";

        text.textContent = "Good password";

        text.style.color = "#347A53";
    }
}

$("newPassword").addEventListener(
    "input",
    updateStrength
);


// Change password

passwordForm.addEventListener("submit", async event => {

    event.preventDefault();

    const newPassword = $("newPassword").value;

    const confirmPassword = $("confirmPassword").value;

    if (
        newPassword.length < 8 ||
        new TextEncoder().encode(newPassword).length > 72 ||
        !/[A-Z]/.test(newPassword) ||
        !/[a-z]/.test(newPassword) ||
        !/[0-9]/.test(newPassword)
    ) {

        showMessage(
            "Password must contain 8–72 bytes, uppercase, lowercase and a number."
        );

        return;
    }

    if (newPassword !== confirmPassword) {

        showMessage("New passwords do not match.");

        return;
    }

    const button = passwordForm.querySelector(
        'button[type="submit"]'
    );

    button.disabled = true;

    try {

        const result = await apiRequest(
            "api/change-password.php",
            {
                current_password:
                    $("currentPassword").value,

                new_password: newPassword,

                confirm_password: confirmPassword
            }
        );

        csrfToken = result.csrf_token;

        passwordForm.reset();

        updateStrength();

        showMessage(result.message, true);

    } catch (error) {

        showMessage(error.message);

    } finally {

        button.disabled = false;
    }
});


passwordForm.addEventListener("reset", () => {

    setTimeout(updateStrength, 0);
});


// Logout

async function logout() {

    if (!confirm("Are you sure you want to sign out?")) {

        return;
    }

    try {

        const result = await apiRequest(
            "api/logout.php",
            {}
        );

        window.location.replace(result.redirect);

    } catch (error) {

        showMessage(error.message);
    }
}


// Initialise settings page

loadProfile().catch(error => {

    $("loadingMessage").textContent =
        error.message || "Unable to load profile.";
});


// Revalidate when restored from browser history

window.addEventListener("pageshow", event => {

    if (event.persisted) {

        $("accountPage").hidden = true;

        loadProfile().catch(error => {

            $("loadingMessage").hidden = false;

            $("loadingMessage").textContent =
                error.message;
        });
    }
});