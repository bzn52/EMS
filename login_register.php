<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

/* Registration Handler */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['register_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    if (!RateLimit::check('register', 5, 3600)) {
        $_SESSION['register_error'] = 'Too many registration attempts. Please try again later.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    $name = Input::text($_POST['name'] ?? '', 100);
    $email = Input::email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim(strtolower($_POST['role'] ?? ''));
    
    if (!in_array($role, ['student', 'teacher'], true)) {
        $role = '';
    }
    
    $errors = [];
    
    //register requirement
    if (strlen($name) < 2) $errors[] = 'Name must be at least 2 characters long.';
    if (!$email) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one number.';
    if (empty($role)) $errors[] = 'Please select a valid role.';
    
    // Check email uniqueness across all tables
    if (empty($errors) && $email) {
        $tables = ['admins', 'teachers', 'students'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'This email is already registered.';
                $stmt->close();
                break;
            }
            $stmt->close();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['register_error'] = implode(' ', $errors);
        $_SESSION['active_form'] = 'register';
        $_SESSION['register_name'] = $name;
        $_SESSION['register_email'] = $email;
        $_SESSION['register_role'] = $role;
        header('Location: index.php');
        exit;
    }
    
    // Insert into appropriate table
    if ($role === 'student') {
        $stmt = $conn->prepare("INSERT INTO students (name, email, password, created_at) 
                               VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('sss', $name, $email, $password);
    } else { // teacher
        $stmt = $conn->prepare("INSERT INTO teachers (name, email, password, approved, created_at) 
                               VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param('sss', $name, $email, $password);
    }
    
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

/* Login Handler */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['login_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
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
    
    // Try to find user in all tables
    $user = null;
    $userRole = null;
    
    // Check admins
    $stmt = $conn->prepare("SELECT id, name, email, password, 1 as approved FROM admins WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $userRole = 'admin';
    } else {
        // Check teachers
        $stmt = $conn->prepare("SELECT id, name, email, password, approved FROM teachers WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $userRole = 'teacher';
        } else {
            // Check students
            $stmt = $conn->prepare("SELECT id, name, email, password, 1 as approved FROM students WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                $userRole = 'student';
            }
        }
    }
    
    if (!$user) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Verify password
    if (trim($user['password']) !== trim($password)) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Check teacher approval
    if ($userRole === 'teacher' && (int)$user['approved'] !== 1) {
        $_SESSION['login_error'] = 'Your teacher account is pending admin approval.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Login successful
    $user['role'] = $userRole;
    Auth::login($user);
    
    $redirect = $_SESSION['intended_url'] ?? Auth::getDashboardUrl();
    unset($_SESSION['intended_url']);
    
    header('Location: ' . $redirect);
    exit;
}

header('Location: index.php');
exit;