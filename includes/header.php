<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

init_session();
require_auth();

$page = basename($_SERVER['PHP_SELF'], '.php');
$nav_items = [
    'today' => 'Today',
    'patients' => 'Patients',
    'followups' => 'Follow-ups',
    'expenses' => 'Expenses',
    'reports' => 'Reports',
    'settings' => 'Settings',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(clinic_name()) ?> — SMILE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="header">
    <div class="header-inner">
        <div class="header-brand"><a href="today.php"><?= e(clinic_name()) ?></a></div>
        <div class="header-user">
            <span><?= e(current_username()) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>
<div class="nav">
    <div class="nav-inner">
        <?php foreach ($nav_items as $key => $label): ?>
            <a href="<?= $key ?>.php" class="<?= $page === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div class="container">
<?php
$flashes = flash();
foreach ($flashes as $f):
?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
