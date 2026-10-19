<?php
require_once __DIR__ . '/../config.php';
session_start();
$_SESSION = [];
session_destroy();
header_remove('Set-Cookie');
setcookie(session_name(), '', time() - 86400, '/');
header('Location: /admin/');
exit;
