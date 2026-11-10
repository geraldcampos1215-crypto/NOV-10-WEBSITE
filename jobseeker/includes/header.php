<?php
$layout = $layout ?? 'public';
$self = basename($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= csrf_token() ?>">
<title><?= e($pageTitle ?? 'JobSeeker') ?> | JobSeeker</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">

<!-- Registration CSS -->
<link rel="stylesheet" href="<?= url('assets/css/register.css') ?>">

</head>
<body class="layout-<?= e($layout) ?>">
<?php
if ($layout === 'public') {
    include __DIR__ . '/navbar.php';
    if (!empty($_SESSION['flash'])) {
        echo '<div class="container pt-3">';
        show_flash();
        echo '</div>';
    }
} elseif ($layout === 'dash') {
    include __DIR__ . '/sidebar.php';
    show_flash();
}