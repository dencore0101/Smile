<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('backup_restore.php');
    }

    if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a backup file.';
    } else {
        $file = $_FILES['backup'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mime !== 'application/zip' && $mime !== 'application/x-zip-compressed') {
            $errors[] = 'Invalid file type. Please upload a SMILE backup ZIP file.';
        } else {
            $tmpName = $file['tmp_name'];
            $zip = new ZipArchive();
            if ($zip->open($tmpName) !== true) {
                $errors[] = 'Could not open backup file.';
            } else {
                // Verify it contains database.sqlite
                if ($zip->locateName('database.sqlite') === false) {
                    $errors[] = 'Invalid backup: missing database file.';
                } else {
                    // Backup current database
                    $backupDb = DB_PATH . '.pre_restore_' . date('Ymd_His');
                    if (file_exists(DB_PATH)) copy(DB_PATH, $backupDb);

                    // Extract database
                    $zip->extractTo(APP_ROOT . '/storage/restore_tmp');
                    $zip->close();

                    // Replace database
                    $extractedDb = APP_ROOT . '/storage/restore_tmp/database.sqlite';
                    if (file_exists($extractedDb)) {
                        copy($extractedDb, DB_PATH);
                        unlink($extractedDb);
                    }

                    // Restore photos
                    $extractedPhotos = APP_ROOT . '/storage/restore_tmp/photos';
                    if (is_dir($extractedPhotos)) {
                        // Clear existing photos
                        if (is_dir(PHOTO_DIR)) {
                            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PHOTO_DIR, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                            foreach ($iter as $f) {
                                if ($f->isDir()) rmdir($f->getRealPath());
                                else unlink($f->getRealPath());
                            }
                        } else {
                            mkdir(PHOTO_DIR, 0755, true);
                        }
                        // Copy restored photos
                        $restorer = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedPhotos, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($restorer as $f) {
                            $relPath = substr($f->getPathname(), strlen($extractedPhotos) + 1);
                            $dest = PHOTO_DIR . '/' . $relPath;
                            if ($f->isDir()) {
                                if (!is_dir($dest)) mkdir($dest, 0755, true);
                            } else {
                                copy($f->getPathname(), $dest);
                            }
                        }
                    }

                    // Clean up temp
                    function rrmdir($dir) {
                        if (!is_dir($dir)) return;
                        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                        foreach ($iter as $f) {
                            if ($f->isDir()) rmdir($f->getRealPath());
                            else unlink($f->getRealPath());
                        }
                        rmdir($dir);
                    }
                    rrmdir(APP_ROOT . '/storage/restore_tmp');

                    set_flash('success', 'Backup restored successfully. A pre-restore copy was saved.');
                    redirect('today.php');
                }
            }
        }
    }
}
?>
<h1>Restore Backup</h1>
<div class="subtitle">Restore from a previously downloaded backup file</div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="flash flash-warning">
    <strong>Warning:</strong> Restoring will overwrite all current data (database and photos).
    A copy of your current database will be saved before restore.
</div>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="backup">Select Backup File (.zip)</label>
            <input type="file" id="backup" name="backup" accept=".zip,application/zip" required>
        </div>
        <div class="form-actions">
            <a href="settings.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-danger" onclick="return confirm('This will overwrite ALL current data. Are you sure?')">Restore Backup</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
