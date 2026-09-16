<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$db = db();
$testWriteResult = null;
$testWriteMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_write') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('debug.php');
    }
    try {
        $testVal = 'test_' . uniqid();
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute(['__test_write__', $testVal]);

        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(['__test_write__']);
        $row = $stmt->fetch();

        if ($row && $row['value'] === $testVal) {
            $stmt = $db->prepare("DELETE FROM settings WHERE key = ?");
            $stmt->execute(['__test_write__']);
            $testWriteResult = 'pass';
            $testWriteMessage = 'Database write test passed: insert, read, and delete all succeeded.';
        } else {
            $testWriteResult = 'fail';
            $testWriteMessage = 'Database write test failed: could not read back the test row.';
        }
    } catch (Throwable $e) {
        $testWriteResult = 'fail';
        $testWriteMessage = is_debug() ? $e->getMessage() : 'Database write test failed.';
        log_error('Error', 'Debug test-write failed: ' . $e->getMessage(), __FILE__, __LINE__);
    }
}

// Environment checks
$checks = [];

$checks['database'] = [
    'label' => 'SQLite Database',
    'pass' => file_exists(DB_PATH) && is_writable(DB_PATH),
    'detail' => file_exists(DB_PATH)
        ? (is_writable(DB_PATH) ? 'Present and writable' : 'Present but NOT writable')
        : 'Database file not found',
];

$checks['photos'] = [
    'label' => 'Photo Storage Directory',
    'pass' => is_dir(PHOTO_DIR) && is_writable(PHOTO_DIR),
    'detail' => is_dir(PHOTO_DIR)
        ? (is_writable(PHOTO_DIR) ? 'Present and writable' : 'Present but NOT writable')
        : 'Directory not found',
];

$checks['logs'] = [
    'label' => 'Log Directory',
    'pass' => is_dir(LOG_DIR) && is_writable(LOG_DIR),
    'detail' => is_dir(LOG_DIR)
        ? (is_writable(LOG_DIR) ? 'Present and writable' : 'Present but NOT writable')
        : 'Directory not found',
];

$checks['backups'] = [
    'label' => 'Backup Directory',
    'pass' => is_dir(BACKUP_DIR) && is_writable(BACKUP_DIR),
    'detail' => is_dir(BACKUP_DIR)
        ? (is_writable(BACKUP_DIR) ? 'Present and writable' : 'Present but NOT writable')
        : 'Directory not found',
];

$checks['php'] = [
    'label' => 'PHP Version',
    'pass' => version_compare(PHP_VERSION, '8.0', '>='),
    'detail' => PHP_VERSION,
];

$checks['session'] = [
    'label' => 'Current Session',
    'pass' => true,
    'detail' => 'User: ' . current_username() . ' | Role: ' . ucfirst(current_role()),
];

$checks['sqlite_ext'] = [
    'label' => 'SQLite Extension',
    'pass' => extension_loaded('pdo_sqlite'),
    'detail' => extension_loaded('pdo_sqlite') ? 'Loaded' : 'NOT loaded',
];

// Recent log entries
$logLines = [];
if (file_exists(LOG_FILE)) {
    $content = file_get_contents(LOG_FILE);
    if ($content) {
        $lines = explode("\n", trim($content));
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        $logLines = array_slice(array_reverse($lines), 0, 100);
    }
}

// Failed login attempts from log
$failedLogins = [];
foreach ($logLines as $line) {
    if (strpos($line, 'Failed login') !== false) {
        $failedLogins[] = $line;
    }
}
?>
<h1>Debug</h1>
<div class="subtitle">System diagnostics and recent errors</div>

<?php if ($testWriteResult): ?>
    <div class="flash flash-<?= $testWriteResult === 'pass' ? 'success' : 'error' ?>"><?= e($testWriteMessage) ?></div>
<?php endif; ?>

<!-- Environment Checks -->
<div class="section">
    <div class="section-header"><div class="section-title">Environment Checks</div></div>
    <div class="section-body">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check): ?>
                    <tr>
                        <td class="font-semibold"><?= e($check['label']) ?></td>
                        <td>
                            <?php if ($check['pass']): ?>
                                <span class="badge badge-active">PASS</span>
                            <?php else: ?>
                                <span class="badge badge-overdue">FAIL</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted"><?= e($check['detail']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Test Write -->
<div class="section">
    <div class="section-header"><div class="section-title">Database Write Test</div></div>
    <div class="section-body">
        <p class="text-sm text-muted mb-4">Tests that the database is writable by inserting a temporary row, reading it back, then deleting it. This catches silent permission issues.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_write">
            <button type="submit" class="btn btn-primary">Run Test Write</button>
        </form>
    </div>
</div>

<!-- Failed Login Attempts -->
<div class="section">
    <div class="section-header"><div class="section-title">Recent Failed Login Attempts (<?= count($failedLogins) ?>)</div></div>
    <div class="section-body">
        <?php if (empty($failedLogins)): ?>
            <p class="text-muted text-sm">No failed login attempts logged.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Log Entry</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($failedLogins, 0, 20) as $log): ?>
                        <tr>
                            <td class="text-sm font-mono"><?= e($log) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Application Errors -->
<div class="section">
    <div class="section-header"><div class="section-title">Recent Application Log (last 100 lines)</div></div>
    <div class="section-body">
        <?php if (empty($logLines)): ?>
            <p class="text-muted text-sm">Log is empty. No errors recorded.</p>
        <?php else: ?>
            <pre class="card font-mono text-sm" style="max-height:500px; overflow:auto; white-space:pre-wrap; padding:12px;"><?php
            foreach ($logLines as $line) {
                echo e($line) . "\n";
            }
            ?></pre>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
