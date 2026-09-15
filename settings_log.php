<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

$logContent = file_exists(LOG_FILE) ? file_get_contents(LOG_FILE) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Log — SMILE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <h1>Debug Log</h1>
    <div class="subtitle">Full application error log</div>
    <a href="settings.php" class="btn btn-outline mb-4">Back to Settings</a>
    <?php if (empty($logContent)): ?>
        <div class="empty-state"><p>Log is empty.</p></div>
    <?php else: ?>
        <pre class="card font-mono text-sm" style="white-space:pre-wrap; max-height:80vh; overflow:auto;"><?= e($logContent) ?></pre>
    <?php endif; ?>
</div>
</body>
</html>
