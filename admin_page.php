<?php
// Redirect to admin dashboard
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

header('Location: dashboard_admin.php');
exit;