<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

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

// Handle messages
$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
    'success' => $_SESSION['register_success'] ?? ''
];

// Determine which form to show based on URL parameter or session
$activeForm = 'login'; // default
if (isset($_GET['form']) && $_GET['form'] === 'register') {
    $activeForm = 'register';
} elseif (isset($_GET['form']) && $_GET['form'] === 'login') {
    $activeForm = 'login';
} elseif (isset($_SESSION['active_form'])) {
    $activeForm = $_SESSION['active_form'];
}

$timeoutMsg = isset($_GET['timeout']) ? 'Your session has expired. Please login again.' : '';

// Preserve form data
$loginEmail = $_SESSION['login_email'] ?? '';
$registerName = $_SESSION['register_name'] ?? '';
$registerEmail = $_SESSION['register_email'] ?? '';
$registerRole = $_SESSION['register_role'] ?? '';
$registerGrade = $_SESSION['register_grade'] ?? '';
$registerSection = $_SESSION['register_section'] ?? '';
$registerRollNo = $_SESSION['register_roll_no'] ?? '';
$registerDepartment = $_SESSION['register_department'] ?? '';
$registerSubjects = $_SESSION['register_subjects'] ?? '';

// Clear session messages
unset(
    $_SESSION['login_error'],
    $_SESSION['register_error'],
    $_SESSION['register_success'],
    $_SESSION['active_form'],
    $_SESSION['login_email'],
    $_SESSION['register_name'],
    $_SESSION['register_email'],
    $_SESSION['register_role'],
    $_SESSION['register_grade'],
    $_SESSION['register_section'],
    $_SESSION['register_roll_no'],
    $_SESSION['register_department'],
    $_SESSION['register_subjects']
);

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event Management System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .form-container {
            display: none !important;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .form-container.active {
            display: block !important;
            opacity: 1;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .back-to-home {
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-to-home a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .back-to-home a:hover {
            color: var(--primary-dark);
        }

        .user-menu-wrapper {
            position: relative;
        }

        .user-info {
            cursor: pointer;
            user-select: none;
        }

        .user-info::after {
            content: "Ã¢â€“Â¼";
            margin-left: 0.5rem;
            font-size: 0.75rem;
            opacity: 0.6;
            transition: var(--transition);
        }

        .user-menu-wrapper.active .user-info::after {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 0.75rem);
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 220px;
            display: none;
            z-index: 1000;
            overflow: hidden;
        }

        .user-menu-wrapper.active .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-light);
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: var(--bg-secondary);
            color: var(--primary);
        }

        .dropdown-menu a i {
            width: 1.25rem;
            text-align: center;
            opacity: 0.7;
        }

        .header-right .nav-links {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="form-box form-container <?= $activeForm === 'login' ? 'active' : '' ?>" id="login-container">
            <form action="login_register.php" method="post" autocomplete="on">
                <h2>Login</h2>

                <?php if ($timeoutMsg): ?>
                    <div class="message message-info">
                        <?= e($timeoutMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors['login']): ?>
                    <div class="message message-error">
                        <?= e($errors['login']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors['success']): ?>
                    <div class="message message-success">
                        <?= e($errors['success']) ?>
                    </div>
                <?php endif; ?>

                <?= CSRF::field() ?>

                <input type="email" name="email" placeholder="Email" value="<?= e($loginEmail) ?>" autocomplete="email"
                    required>

                <div class="password-toggle">
                    <input type="password" name="password" id="login-password" placeholder="Password"
                        autocomplete="current-password" required>
                    <button type="button" class="toggle-password" data-target="login-password" tabindex="-1">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <button type="submit" name="login">Login</button>

                <p style="text-align: center;">
                    Don't have an account?
                    <a href="?form=register" class="show-register"
                        style="color: var(--primary); text-decoration: underline; font-weight: 600;">Register here</a>
                </p>

                <div class="back-to-home">
                    <a href="landing.php">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </form>
        </div>

        <div class="form-box form-container <?= $activeForm === 'register' ? 'active' : '' ?>" id="register-container">
            <form action="login_register.php" method="post" autocomplete="on">
                <h2>Register</h2>

                <?php if ($errors['register']): ?>
                    <div class="message message-error">
                        <?= e($errors['register']) ?>
                    </div>
                <?php endif; ?>

                <?= CSRF::field() ?>

                <input type="text" name="name" placeholder="Full Name" value="<?= e($registerName) ?>"
                    autocomplete="name" required>

                <input type="email" name="email" placeholder="Email" value="<?= e($registerEmail) ?>"
                    autocomplete="email" required>

                <div class="password-toggle">
                    <input type="password" name="password" id="register-password" placeholder="Password"
                        autocomplete="new-password" minlength="4" required>
                    <button type="button" class="toggle-password" data-target="register-password" tabindex="-1">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <select name="role" id="role" required onchange="toggleRoleFields()">
                    <option value="">-- Select Role --</option>
                    <option value="student" <?= $registerRole === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="teacher" <?= $registerRole === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                </select>

                <div id="student-fields" style="display: none;">
                    <div
                        style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius); border-left: 4px solid var(--primary);">
                        <p style="margin: 0 0 1rem 0; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                            Student Information</p>
                        <input type="text" name="grade" id="student-grade" placeholder="Grade (e.g., 10th, 11th, 12th)"
                            value="<?= e($registerGrade ?? '') ?>" style="margin-bottom: 1rem;">
                        <input type="text" name="section" id="student-section" placeholder="Section (e.g., A, B, C)"
                            value="<?= e($registerSection ?? '') ?>" style="margin-bottom: 1rem;">
                        <input type="text" name="roll_no" id="student-rollno" placeholder="Roll Number (e.g., 101, 202)"
                            value="<?= e($registerRollNo ?? '') ?>" style="margin-bottom: 1rem;">
                    </div>
                </div>

                <div id="teacher-fields" style="display: none;">
                    <div
                        style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius); border-left: 4px solid var(--primary);">
                        <p style="margin: 0 0 1rem 0; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                            Teacher Information</p>
                        <input type="text" name="department" id="teacher-department"
                            placeholder="Department (e.g., Mathematics, Science, English)"
                            value="<?= e($registerDepartment ?? '') ?>" style="margin-bottom: 1rem;">
                        <textarea name="subjects" id="teacher-subjects"
                            placeholder="Subjects Assigned (e.g., Algebra, Geometry, Calculus)" rows="2"
                            style="margin-bottom: 1rem;"><?= e($registerSubjects ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" name="register">Register</button>

                <p style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Additional fields will appear after selecting your role
                </p>

                <p style="text-align: center; margin-top: 1rem;">
                    Already have an account?
                    <a href="?form=login" class="show-login"
                        style="color: var(--primary); text-decoration: underline; font-weight: 600;">Login here</a>
                </p>

                <div class="back-to-home">
                    <a href="landing.php">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Page-specific initialization
        document.addEventListener('DOMContentLoaded', function () {
            const loginContainer = document.getElementById('login-container');
            const registerContainer = document.getElementById('register-container');
            const showRegisterLinks = document.querySelectorAll('.show-register');
            const showLoginLinks = document.querySelectorAll('.show-login');
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');

            // Show register form
            showRegisterLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (loginContainer && registerContainer) {
                        loginContainer.classList.remove('active');
                        registerContainer.classList.add('active');
                        // Update URL without reload
                        window.history.pushState({}, '', '?form=register');
                    }
                });
            });

            // Show login form
            showLoginLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (loginContainer && registerContainer) {
                        registerContainer.classList.remove('active');
                        loginContainer.classList.add('active');
                        // Update URL without reload
                        window.history.pushState({}, '', '?form=login');
                    }
                });
            });

            // Toggle password visibility
            togglePasswordButtons.forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = button.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = button.querySelector('i');

                    if (input) {
                        if (input.type === 'password') {
                            input.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                        } else {
                            input.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                        }
                    }
                });
            });

            // Auto-hide success messages after 5 seconds
            setTimeout(function () {
                const successMessages = document.querySelectorAll('.message-success');
                successMessages.forEach(function (msg) {
                    msg.style.transition = 'opacity 0.5s';
                    msg.style.opacity = '0';
                    setTimeout(function () {
                        msg.style.display = 'none';
                    }, 500);
                });
            }, 5000);

            // Toggle role-specific fields
            toggleRoleFields();
        });

        function toggleRoleFields() {
            const roleSelect = document.getElementById('role');
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');

            if (roleSelect && studentFields && teacherFields) {
                const role = roleSelect.value;

                // Toggle student fields
                if (role === 'student') {
                    studentFields.style.display = 'block';
                    document.getElementById('student-grade').setAttribute('required', 'required');
                    document.getElementById('student-section').setAttribute('required', 'required');
                    document.getElementById('student-rollno').setAttribute('required', 'required');
                } else {
                    studentFields.style.display = 'none';
                    document.getElementById('student-grade').removeAttribute('required');
                    document.getElementById('student-section').removeAttribute('required');
                    document.getElementById('student-rollno').removeAttribute('required');
                }

                // Toggle teacher fields
                if (role === 'teacher') {
                    teacherFields.style.display = 'block';
                    document.getElementById('teacher-department').setAttribute('required', 'required');
                    document.getElementById('teacher-subjects').setAttribute('required', 'required');
                } else {
                    teacherFields.style.display = 'none';
                    document.getElementById('teacher-department').removeAttribute('required');
                    document.getElementById('teacher-subjects').removeAttribute('required');
                }
            }
        }
    </script>
</body>

</html>