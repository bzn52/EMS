<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

/* ----------------------
   Registration Handler
   ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['register_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    // Rate limiting
    if (!RateLimit::check('register', 5, 3600)) {
        $_SESSION['register_error'] = 'Too many registration attempts. Please try again later.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    // Collect and sanitize input
    $name = Input::text($_POST['name'] ?? '', 100);
    $email = Input::email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Plain text password
    $role = $_POST['role'] ?? ''; // Get raw role value
    
    // Validate and normalize role
    $role = trim(strtolower($role));
    if (!in_array($role, ['student', 'teacher'], true)) {
        $role = '';
    }
    
    $errors = [];
    
    // Validation
    if (strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long.';
    }
    
    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (strlen($password) < 4) {
        $errors[] = 'Password must be at least 4 characters long.';
    }
    
    if (empty($role)) {
        $errors[] = 'Please select a valid role.';
    }
    
    // Check email uniqueness
    if (empty($errors) && $email) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        }
        $stmt->close();
    }
    
    // Handle errors
    if (!empty($errors)) {
        $_SESSION['register_error'] = implode(' ', $errors);
        $_SESSION['active_form'] = 'register';
        $_SESSION['register_name'] = $name;
        $_SESSION['register_email'] = $email;
        $_SESSION['register_role'] = $role;
        header('Location: index.php');
        exit;
    }
    
    // Create user - Students auto-approved, Teachers need admin approval
    $approved = ($role === 'student') ? 1 : 0;
    $emailVerified = 1; // Auto-verify (no email system)
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, approved, email_verified, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssssii', $name, $email, $password, $role, $approved, $emailVerified);
    
    if ($stmt->execute()) {
        RateLimit::reset('register');
        $stmt->close();
        
        if ($role === 'teacher') {
            $_SESSION['register_success'] = 'Registration successful! Your teacher account is pending admin approval.';
        } else {
            $_SESSION['register_success'] = 'Registration successful! You can now login.';
        }
        
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    } else {
        error_log("Registration failed: " . $stmt->error);
        $_SESSION['register_error'] = 'Registration failed. Please try again.';
        $_SESSION['active_form'] = 'register';
        $stmt->close();
        header('Location: index.php');
        exit;
    }
}

/* ----------------------
   Login Handler
   ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['login_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
    // Rate limiting
    if (!RateLimit::check('login', 5, 900)) {
        $_SESSION['login_error'] = 'Too many login attempts. Please try again in 15 minutes.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
    $email = Input::email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || strlen($password) === 0) {
        $_SESSION['login_error'] = 'Email and password are required.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Fetch user
    $stmt = $conn->prepare("SELECT id, name, email, password, role, approved FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Debug: Check if user exists
    if (!$user) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Verify password (plain text comparison - trim to remove whitespace)
    $storedPassword = trim($user['password']);
    $enteredPassword = trim($password);
    
    if ($storedPassword !== $enteredPassword) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Normalize and validate role from database
    $userRole = trim(strtolower($user['role']));
    if (!in_array($userRole, ['student', 'teacher', 'admin'], true)) {
        $_SESSION['login_error'] = 'Invalid user role. Contact administrator.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
    // Check if account is approved (for teachers)
    if ($userRole === 'teacher' && (int)$user['approved'] !== 1) {
        $_SESSION['login_error'] = 'Your teacher account is pending admin approval.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Login successful - use normalized role
    $user['role'] = $userRole;
    Auth::login($user);
    
    // Redirect to intended URL or dashboard
    $redirect = $_SESSION['intended_url'] ?? Auth::getDashboardUrl();
    unset($_SESSION['intended_url']);
    
    header('Location: ' . $redirect);
    exit;
}

// Invalid request
header('Location: index.php');
exit;