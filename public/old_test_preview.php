<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'demo@example.local';
$_SESSION['user_role'] = 'organizer';
$_SESSION['user'] = ['id' => 1, 'email' => 'demo@example.local', 'role' => 'organizer'];
$_GET['draft_id'] = '58';
require __DIR__ . '/index.php';
