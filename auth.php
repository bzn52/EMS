<?php
if (!defined('APP_INIT')) {
    die('Direct access not permitted');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

class Role {
    const STUDENT = 'student';
    const TEACHER = 'teacher';
    const ADMIN = 'admin';
    
    public static function all(): array {
        return [self::STUDENT, self::TEACHER, self::ADMIN];
    }
    
    public static function normalize(?string $role): ?string {
        if ($role === null) return null;
        $role = strtolower(trim((string)$role));
        if (in_array($role, self::all(), true)) {
            return $role;
        }
        return null;
    }
    
    public static function getTableName(string $role): ?string {
        $role = self::normalize($role);
        switch ($role) {
            case self::ADMIN: return 'admins';
            case self::TEACHER: return 'teachers';
            case self::STUDENT: return 'students';
            default: return null;
        }
    }
    
    public static function canCreateEvents(string $role): bool {
        return in_array($role, [self::TEACHER, self::ADMIN], true);
    }
    
    public static function canApproveEvents(string $role): bool {
        return $role === self::ADMIN;
    }
    
    public static function canEditEvent(string $role, string $eventCreatorType, int $eventCreatorId, int $currentUserId): bool {
        if ($role === self::ADMIN) return true;
        if ($role === self::TEACHER && $eventCreatorType === 'teacher') {
            return $eventCreatorId === $currentUserId;
        }
        return false;
    }
}

class Auth {
    
    public static function check(): bool {
        return !empty($_SESSION['user_id']) && 
               !empty($_SESSION['user_name']) && 
               !empty($_SESSION['role']);
    }
    
    public static function id(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
    
    public static function name(): ?string {
        return $_SESSION['user_name'] ?? null;
    }
    
    public static function email(): ?string {
        return $_SESSION['email'] ?? null;
    }
    
    public static function role(): ?string {
        return Role::normalize($_SESSION['role'] ?? null);
    }
    
    public static function hasRole($roles): bool {
        $userRole = self::role();
        if (!$userRole) return false;
        
        $allowed = is_array($roles) ? $roles : [$roles];
        $allowed = array_map([Role::class, 'normalize'], $allowed);
        
        return in_array($userRole, $allowed, true);
    }
    
    public static function requireLogin(string $redirect = 'index.php'): void {
        if (!self::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? null;
            header('Location: ' . $redirect);
            exit;
        }
    }
    
    public static function requireRole($roles, bool $showError = true): void {
        self::requireLogin();
        
        if (!self::hasRole($roles)) {
            if ($showError) {
                http_response_code(403);
                echo '<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page-wrapper">
        <main>
            <div class="container container-sm">
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon">🚫</div>
                        <h2 class="empty-state-title">Access Denied</h2>
                        <p class="empty-state-text">You do not have permission to view this page.</p>
                        <a href="' . self::getDashboardUrl() . '" class="btn btn-sm" style="margin-top: 1rem; width: auto; display: inline-block;">Go to Dashboard</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>';
            } else {
                header('Location: ' . self::getDashboardUrl());
            }
            exit;
        }
    }
    
    public static function getDashboardUrl(): string {
        $role = self::role();
        switch ($role) {
            case Role::ADMIN:
                return 'dashboard_admin.php';
            case Role::TEACHER:
                return 'dashboard_teacher.php';
            default:
                return 'dashboard_student.php';
        }
    }
    
    public static function login(array $user): void {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = Role::normalize($user['role']);
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        if (class_exists('RateLimit')) {
            RateLimit::reset('login');
        }
    }
    
    public static function logout(string $redirect = 'index.php'): void {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params['path'], 
                $params['domain'], 
                $params['secure'], 
                $params['httponly']
            );
        }
        
        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }
    
    public static function checkTimeout(int $maxInactivity = 1800): void {
        if (!self::check()) return;
        
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        
        if (time() - $lastActivity > $maxInactivity) {
            self::logout('index.php?timeout=1');
        }
        
        $_SESSION['last_activity'] = time();
    }
}

Auth::checkTimeout();