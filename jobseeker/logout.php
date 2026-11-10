<?php
require_once __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['uid'])) log_activity('User logout', '');
session_unset(); session_destroy();
session_start(); session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect('login.php');
