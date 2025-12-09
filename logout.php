<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

Auth::logout('index.php');