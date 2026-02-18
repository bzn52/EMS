<?php
// Main Router - Entry Point
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!defined('APP_INIT')) {
  define('APP_INIT', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Check if user is already logged in
if (Auth::check()) {
  // Redirect to appropriate dashboard
  header('Location: ' . Auth::getDashboardUrl());
  exit;
}

// Not logged in - show landing page
header('Location: landing.php');
exit;