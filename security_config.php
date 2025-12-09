<?php
// Prevent direct access
if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");

// CSRF Token Management
class CSRF {
    public static function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function field(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
}

// Rate limiting helper
class RateLimit {
    private static function getKey(string $action): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        return "rate_limit_{$action}_{$ip}_{$userId}";
    }
    
    public static function check(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = self::getKey($action);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'reset' => time() + $windowSeconds];
        }
        
        $data = &$_SESSION[$key];
        
        // Reset if window expired
        if (time() > $data['reset']) {
            $data = ['count' => 0, 'reset' => time() + $windowSeconds];
        }
        
        // Check limit
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        $data['count']++;
        return true;
    }
    
    public static function reset(string $action): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $key = self::getKey($action);
        unset($_SESSION[$key]);
    }
}

// Input sanitization helper
class Input {
    public static function clean(string $input): string {
        return trim($input);
    }
    
    public static function int($value): int {
        return (int) $value;
    }
    
    public static function email(?string $email): ?string {
        if (!$email) return null;
        $email = self::clean($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
    }
    
    public static function text(?string $text, int $maxLength = 5000): string {
        if (!$text) return '';
        $text = self::clean($text);
        return mb_substr($text, 0, $maxLength);
    }
}

// File upload security
class FileUpload {
    private const MAX_SIZE = 5242880; // 5MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    public static function validate(array $file): array {
        $errors = [];
        
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return $errors;
        }
        
        // Check size
        if ($file['size'] > self::MAX_SIZE) {
            $errors[] = 'File too large (max 5MB)';
        }
        
        // Check mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            $errors[] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed';
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = 'Invalid file extension';
        }
        
        return $errors;
    }
    
    public static function generateSecureName(string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    }
}