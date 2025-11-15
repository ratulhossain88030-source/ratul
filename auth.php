<?php 
session_start();

$usersFile = __DIR__ . '/../users.json';
$maxAccountsPerDevice = 1;

// Enhanced Device Fingerprinting
function getDeviceID() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'],
        $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        $_SERVER['REMOTE_ADDR'],
        gethostname(),
        $_SERVER['HTTP_ACCEPT'],
        $_SERVER['HTTP_ACCEPT_ENCODING'],
        $_SERVER['HTTP_ACCEPT_CHARSET']
    ];
    return hash('sha256', implode('|', $components));
}

// Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validation
    if (strlen($username) < 4 || strlen($username) > 20) {
        header("Location: ../index.php?error=username_length");
        exit;
    }

    if (strlen($password) < 6) {
        header("Location: ../index.php?error=password_length");
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        header("Location: ../index.php?error=username_invalid");
        exit;
    }

    // Check Device Limit
    $deviceID = getDeviceID();
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

    if (isset($users[$username])) {
        header("Location: ../index.php?error=username_exists");
        exit;
    }

    $deviceCount = 0;
    foreach ($users as $user) {
        if ($user['device'] === $deviceID) {
            $deviceCount++;
            if ($deviceCount >= $maxAccountsPerDevice) {
                header("Location: ../index.php?error=device");
                exit;
            }
        }
    }

    // Create User
    $users[$username] = [
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'device' => $deviceID,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR']
    ];

    if (!file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
        header("Location: ../index.php?error=registration_failed");
        exit;
    }
    
    // Create user directories
    if (!file_exists("../users/$username")) {
        if (!mkdir("../users/$username", 0755, true)) {
            header("Location: ../index.php?error=directory_creation_failed");
            exit;
        }
    }

    if (!file_exists("../users/$username/api")) {
        if (!mkdir("../users/$username/api", 0755, true)) {
            header("Location: ../index.php?error=directory_creation_failed");
            exit;
        }
    }

    $_SESSION['user'] = $username;
    header("Location: ../dashboard.php");
    exit;
}

// Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

    if (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
        header("Location: ../index.php?error=auth");
        exit;
    }

    if (file_exists("../users/$username/BLOCKED")) {
        header("Location: ../index.php?error=blocked");
        exit;
    }

    // Update last login info
    $users[$username]['last_login'] = date('Y-m-d H:i:s');
    $users[$username]['ip'] = $_SERVER['REMOTE_ADDR'];
    
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

    $_SESSION['user'] = $username;
    header("Location: ../dashboard.php");
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}