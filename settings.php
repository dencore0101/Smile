<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('settings.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'clinic') {
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)
                              ON CONFLICT(key) DO UPDATE SET value = excluded.value");
        $stmt->execute(['clinic_name', trim($_POST['clinic_name'])]);
        $stmt->execute(['dentist_name', trim($_POST['dentist_name'])]);
        $stmt->execute(['clinic_contact', trim($_POST['clinic_contact'])]);
        $stmt->execute(['clinic_address', trim($_POST['clinic_address'])]);
        $stmt->execute(['currency', trim($_POST['currency'])]);
        set_flash('success', 'Clinic settings updated.');
        redirect('settings.php');
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } elseif (!change_password($current, $new)) {
            set_flash('error', 'Current password is incorrect.');
        } else {
            set_flash('success', 'Password changed.');
        }
        redirect('settings.php');
    }

    if ($action === 'assistant_password') {
        $new = $_POST['assistant_password'] ?? '';
        if (strlen($new) < 6) {
            set_flash('error', 'Assistant password must be at least 6 characters.');
        } elseif (!set_assistant_password($new)) {
            set_flash('error', 'Could not set assistant password.');
        } else {
            set_flash('success', 'Assistant password set. Username: assistant');
        }
        redirect('settings.php');
    }

    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name === '') {
            set_flash('error', 'Category name is required.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO expense_categories (name) VALUES (?)");
                $stmt->execute([$name]);
                set_flash('success', 'Category added.');
            } catch (Throwable $e) {
                set_flash('error', 'Category already exists.');
            }
        }
        redirect('settings.php');
    }

    if ($action === 'edit_category') {
        $catId = (int)$_POST['cat_id'];
        $name = trim($_POST['cat_name'] ?? '');
        if ($name === '') {
            set_flash('error', 'Category name is required.');
        } else {
            try {
                $stmt = $db->prepare("UPDATE expense_categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $catId]);
                set_flash('success', 'Category updated.');
            } catch (Throwable $e) {
                set_flash('error', 'Category name already exists.');
            }
        }
        redirect('settings.php');
    }

    if ($action === 'delete_category') {
        $catId = (int)$_POST['cat_id'];
        $stmt = $db->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ? AND deleted_at IS NULL");
        $stmt->execute([$catId]);
        if ((int)$stmt->fetchColumn() > 0) {
            set_flash('error', 'Cannot delete: expenses exist in this category. Remove or reassign expenses first.');
        } else {
            $stmt = $db->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$catId]);
            set_flash('success', 'Category deleted.');
        }
        redirect('settings.php');
    }

    if ($action === 'debug') {
        $mode = $_POST['debug_mode'] ?? '0';
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)
                              ON CONFLICT(key) DO UPDATE SET value = excluded.value");
        $stmt->execute(['debug_mode', $mode]);
        set_flash('success', 'Debug mode updated.');
        redirect('settings.php');
    }

    if ($action === 'clear_log') {
        @file_put_contents(LOG_FILE, '');
        set_flash('success', 'Debug log cleared.');
        redirect('settings.php');
    }
}

$categories = get_expense_categories();
$labs = get_labs();
$consultants = get_consultants();
$debugMode = setting('debug_mode', '0') === '1';
$logContent = file_exists(LOG_FILE) ? file_get_contents(LOG_FILE) : '';
$logSize = file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0;

// Check if assistant account exists
$hasAssistant = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'assistant'")->fetchColumn() > 0;

// System health
$phpVersion = PHP_VERSION;
$dbSize = file_exists(DB_PATH) ? filesize(DB_PATH) : 0;
$photoSize = 0;
if (is_dir(PHOTO_DIR)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PHOTO_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) $photoSize += $file->getSize();
}
$backupCount = 0;
if (is_dir(BACKUP_DIR)) {
    $backupCount = count(glob(BACKUP_DIR . '/*.zip'));
}
$diskFree = @disk_free_space(APP_ROOT);
?>
<h1>Settings</h1>
<div class="subtitle">Manage your clinic configuration</div>

<!-- Clinic Settings -->
<div class="section">
    <div class="section-header"><div class="section-title">Clinic Details</div></div>
    <div class="section-body">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clinic">
            <div class="form-row">
                <div class="form-group">
                    <label for="clinic_name">Clinic Name</label>
                    <input type="text" id="clinic_name" name="clinic_name" value="<?= e(setting('clinic_name')) ?>">
                </div>
                <div class="form-group">
                    <label for="dentist_name">Dentist Name</label>
                    <input type="text" id="dentist_name" name="dentist_name" value="<?= e(setting('dentist_name')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="clinic_contact">Clinic Contact</label>
                    <input type="text" id="clinic_contact" name="clinic_contact" value="<?= e(setting('clinic_contact')) ?>">
                </div>
                <div class="form-group">
                    <label for="currency">Currency Symbol</label>
                    <input type="text" id="currency" name="currency" value="<?= e(setting('currency', '₹')) ?>" maxlength="5">
                </div>
            </div>
            <div class="form-group">
                <label for="clinic_address">Clinic Address</label>
                <textarea id="clinic_address" name="clinic_address"><?= e(setting('clinic_address')) ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Password -->
<div class="section">
    <div class="section-header"><div class="section-title">Change Owner Password</div></div>
    <div class="section-body">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password (min 6 characters)</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Assistant Account -->
<div class="section">
    <div class="section-header"><div class="section-title">Assistant Account</div></div>
    <div class="section-body">
        <p class="text-sm text-muted mb-4">
            The assistant account can search patients, create/edit patients, add photos, manage follow-ups, and add/edit payments.
            The assistant cannot see expenses, profit, reports, or financial exports.
            <?php if ($hasAssistant): ?>
            <br><br><strong>Assistant username:</strong> assistant
            <?php endif; ?>
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assistant_password">
            <div class="form-group">
                <label for="assistant_password"><?= $hasAssistant ? 'Set New Assistant Password' : 'Create Assistant Password' ?> (min 6 characters)</label>
                <input type="password" id="assistant_password" name="assistant_password" required minlength="6">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $hasAssistant ? 'Update Assistant Password' : 'Create Assistant Account' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Expense Categories -->
<div class="section">
    <div class="section-header"><div class="section-title">Expense Categories</div></div>
    <div class="section-body">
        <div class="table-wrap mb-4">
            <table>
                <thead>
                    <tr><th>Name</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td class="font-semibold"><?= e($c['name']) ?></td>
                        <td class="text-right nowrap">
                            <button type="button" class="btn btn-sm btn-outline" onclick="openModal('editCat<?= $c['id'] ?>')">Edit</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" style="display:flex; gap:8px; align-items:end;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            <div class="form-group" style="flex:1">
                <label for="cat_name">New Category Name</label>
                <input type="text" id="cat_name" name="cat_name" placeholder="e.g. Materials, Referral">
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
    </div>
</div>

<!-- Lab & Consultant Master Lists -->
<div class="section">
    <div class="section-header"><div class="section-title">Labs & Consultants</div></div>
    <div class="section-body">
        <p class="text-sm text-muted mb-4">Manage your lab and consultant master lists used when adding expenses.</p>
        <div class="flex gap-2 flex-wrap">
            <a href="labs.php" class="btn btn-outline">Manage Labs (<?= count($labs) ?>)</a>
            <a href="consultants.php" class="btn btn-outline">Manage Consultants (<?= count($consultants) ?>)</a>
        </div>
    </div>
</div>

<!-- Backup -->
<div class="section">
    <div class="section-header"><div class="section-title">Backup & Restore</div></div>
    <div class="section-body">
        <p class="text-sm text-muted mb-4">Download a backup of your database and all patient photos. Restore will overwrite all current data.</p>
        <div class="flex gap-2 flex-wrap">
            <a href="backup_download.php" class="btn btn-primary">Download Backup</a>
            <a href="backup_restore.php" class="btn btn-outline">Restore Backup</a>
        </div>
        <?php if ($backupCount > 0): ?>
        <p class="text-sm text-muted mt-4"><?= $backupCount ?> backup file(s) on server.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Debug -->
<div class="section">
    <div class="section-header"><div class="section-title">Debug & System Health</div></div>
    <div class="section-body">
        <div class="mb-4">
            <a href="debug.php" class="btn btn-primary">Open Debug Page</a>
        </div>
        <form method="post" style="display:flex; gap:8px; align-items:center; margin-bottom:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="debug">
            <label>Debug Mode:</label>
            <select name="debug_mode">
                <option value="0" <?= !$debugMode ? 'selected' : '' ?>>OFF</option>
                <option value="1" <?= $debugMode ? 'selected' : '' ?>>ON</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Save</button>
        </form>

        <h3>System Health</h3>
        <div class="table-wrap mb-4">
            <table>
                <tbody>
                    <tr><td>PHP Version</td><td class="text-right"><?= e($phpVersion) ?></td></tr>
                    <tr><td>Database Status</td><td class="text-right"><span class="text-success">OK (<?= round($dbSize / 1024) ?> KB)</span></td></tr>
                    <tr><td>Photos Storage</td><td class="text-right"><?= round($photoSize / 1024 / 1024, 1) ?> MB</td></tr>
                    <tr><td>Backups</td><td class="text-right"><?= $backupCount ?> file(s)</td></tr>
                    <tr><td>Disk Free</td><td class="text-right"><?= $diskFree ? round($diskFree / 1024 / 1024, 0) . ' MB' : '—' ?></td></tr>
                    <tr><td>Upload Directory</td><td class="text-right"><?= is_writable(PHOTO_DIR) ? '<span class="text-success">Writable</span>' : '<span class="text-danger">Not writable</span>' ?></td></tr>
                </tbody>
            </table>
        </div>

        <h3>Debug Log <?= $logSize > 0 ? '(' . round($logSize / 1024) . ' KB)' : '' ?></h3>
        <?php if ($logSize > 0): ?>
        <div class="flex gap-2 mb-4">
            <a href="settings_log.php" target="_blank" class="btn btn-sm btn-outline">View Full Log</a>
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_log">
                <button type="submit" class="btn btn-sm btn-danger">Clear Log</button>
            </form>
        </div>
        <pre class="card font-mono text-sm" style="max-height:300px; overflow:auto; white-space:pre-wrap;"><?= e(substr($logContent, -5000)) ?></pre>
        <?php else: ?>
        <p class="text-muted text-sm">Log is empty.</p>
        <?php endif; ?>
    </div>
</div>

<!-- CSV Exports -->
<div class="section">
    <div class="section-header"><div class="section-title">Data Export</div></div>
    <div class="section-body">
        <p class="text-sm text-muted mb-4">Export data as CSV spreadsheets or as a combined JSON file (for LLM analysis). Optional date range filters all exports.</p>
        <form method="get" action="export.php" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-bottom:16px;">
            <div class="form-group" style="margin:0">
                <label>From Date (optional)</label>
                <input type="date" name="from" value="" style="width:auto">
            </div>
            <div class="form-group" style="margin:0">
                <label>To Date (optional)</label>
                <input type="date" name="to" value="" style="width:auto">
            </div>
            <div class="form-group" style="margin:0">
                <label>Export Type</label>
                <select name="type" style="width:auto">
                    <option value="full_json">Combined JSON (all data, nested)</option>
                    <option value="patients">Patients CSV</option>
                    <option value="treatments">Treatments CSV (with derived fields)</option>
                    <option value="payments">Payments CSV</option>
                    <option value="expenses">Expenses CSV (with lab/consultant)</option>
                    <option value="followups">Follow-ups CSV</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Download</button>
        </form>
        <div class="flex gap-2 flex-wrap">
            <a href="export.php?type=full_json" class="btn btn-sm btn-primary">Combined JSON (all-time)</a>
            <a href="export.php?type=patients" class="btn btn-sm btn-outline">Patients CSV</a>
            <a href="export.php?type=treatments" class="btn btn-sm btn-outline">Treatments CSV</a>
            <a href="export.php?type=payments" class="btn btn-sm btn-outline">Payments CSV</a>
            <a href="export.php?type=expenses" class="btn btn-sm btn-outline">Expenses CSV</a>
            <a href="export.php?type=followups" class="btn btn-sm btn-outline">Follow-ups CSV</a>
        </div>
    </div>
</div>

<!-- Edit Category Modals -->
<?php foreach ($categories as $c): ?>
<div class="modal-overlay" id="editCat<?= $c['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Category</div>
            <button class="modal-close" onclick="closeModal('editCat<?= $c['id'] ?>')">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="cat_name" value="<?= e($c['name']) ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCat<?= $c['id'] ?>')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
