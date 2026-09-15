<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_installed()) {
    redirect('login.php');
}

$step = $_POST['step'] ?? 'check';
$errors = [];
$checks = [];

// Requirement checks
$checks['php'] = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks['pdo_sqlite'] = extension_loaded('pdo_sqlite');
$checks['json'] = extension_loaded('json');
// mbstring is optional — app works without it
$checks['mbstring'] = true;

// Folder permissions
$dirs = [APP_ROOT . '/storage', PHOTO_DIR, BACKUP_DIR, LOG_DIR];
foreach ($dirs as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
$checks['storage_writable'] = is_writable(APP_ROOT . '/storage');
$checks['photos_writable'] = is_writable(PHOTO_DIR);
$checks['backups_writable'] = is_writable(BACKUP_DIR);
$checks['logs_writable'] = is_writable(LOG_DIR);

$allPass = !in_array(false, $checks);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($step === 'install')) {
    if (!csrf_check()) {
        $errors[] = 'Invalid session token. Please try again.';
    }
    if (!$allPass) {
        $errors[] = 'Server requirements not met. Please fix the issues below.';
    }

    $clinicName = trim($_POST['clinic_name'] ?? '');
    $dentistName = trim($_POST['dentist_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $currency = trim($_POST['currency'] ?? '₹');

    if ($clinicName === '') $errors[] = 'Clinic name is required.';
    if ($username === '') $errors[] = 'Username is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($currency === '') $currency = '₹';

    if (empty($errors)) {
        try {
            // Create database
            $dsn = 'sqlite:' . DB_PATH;
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            db_install($pdo);

            // Default expense categories
            $cats = ['Lab', 'Consultant', 'Special Instrument'];
            foreach ($cats as $cat) {
                $stmt = $pdo->prepare('INSERT INTO expense_categories (name) VALUES (?)');
                $stmt->execute([$cat]);
            }

            // Default settings
            $settings = [
                'clinic_name' => $clinicName,
                'dentist_name' => $dentistName,
                'currency' => $currency,
                'debug_mode' => '0',
                'google_cal' => '0',
            ];
            $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }

            // Create user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);

            // Create config file
            $configContent = "<?php\n// SMILE local configuration\n// Generated: " . date('Y-m-d H:i:s') . "\n";
            file_put_contents(APP_ROOT . '/config.local.php', $configContent);

            set_flash('success', 'Installation complete. Please log in.');
            redirect('login.php');
        } catch (Throwable $e) {
            log_error('Error', 'Install failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? 'Installation error: ' . $e->getMessage() : 'Installation failed. Please check server requirements and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install SMILE</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="install-wrap">
    <div class="install-card">
        <div class="login-title">SMILE</div>
        <div class="login-subtitle">Dental Practice Management — Installation</div>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>System Requirements</h2>
        <table>
            <tbody>
                <tr>
                    <td>PHP 7.4+</td>
                    <td class="text-right">
                        <?php if ($checks['php']): ?>
                            <span class="text-success">OK (<?= PHP_VERSION ?>)</span>
                        <?php else: ?>
                            <span class="text-danger">Failed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>SQLite (PDO)</td>
                    <td class="text-right">
                        <?php if ($checks['pdo_sqlite']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Missing</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>JSON Extension</td>
                    <td class="text-right">
                        <?php if ($checks['json']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Missing</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>MBString Extension</td>
                    <td class="text-right">
                        <?php if ($checks['mbstring']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Missing</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Storage writable</td>
                    <td class="text-right">
                        <?php if ($checks['storage_writable']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Not writable</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Photos directory</td>
                    <td class="text-right">
                        <?php if ($checks['photos_writable']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Not writable</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Backups directory</td>
                    <td class="text-right">
                        <?php if ($checks['backups_writable']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Not writable</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Logs directory</td>
                    <td class="text-right">
                        <?php if ($checks['logs_writable']): ?>
                            <span class="text-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger">Not writable</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if ($allPass): ?>
        <h2 class="mt-4">Clinic Setup</h2>
        <form method="post">
            <input type="hidden" name="step" value="install">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="clinic_name">Clinic Name</label>
                <input type="text" id="clinic_name" name="clinic_name" required value="<?= e($_POST['clinic_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="dentist_name">Dentist Name (optional)</label>
                <input type="text" id="dentist_name" name="dentist_name" value="<?= e($_POST['dentist_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="currency">Currency Symbol</label>
                <input type="text" id="currency" name="currency" value="<?= e($_POST['currency'] ?? '₹') ?>" maxlength="5" required>
            </div>
            <div class="form-group">
                <label for="username">Login Username</label>
                <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Login Password (min 6 characters)</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg btn-block">Install SMILE</button>
            </div>
        </form>
        <?php else: ?>
        <div class="flash flash-error mt-4">
            Please fix the issues above before installing. Make sure the <code>storage/</code> and <code>logs/</code> directories are writable.
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
