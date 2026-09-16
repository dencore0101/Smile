<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();
require_owner();

if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);

$timestamp = date('Y-m-d_His');
$backupFile = BACKUP_DIR . '/smile_backup_' . $timestamp . '.zip';

$zip = new ZipArchive();
if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    set_flash('error', 'Could not create backup file.');
    redirect('settings.php');
}

// Add database
$zip->addFile(DB_PATH, 'database.sqlite');

// Add photos
if (is_dir(PHOTO_DIR)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PHOTO_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $relPath = 'photos/' . substr($file->getPathname(), strlen(PHOTO_DIR) + 1);
            $zip->addFile($file->getPathname(), $relPath);
        }
    }
}

// Add config
if (file_exists(APP_ROOT . '/config.local.php')) {
    $zip->addFile(APP_ROOT . '/config.local.php', 'config.local.php');
}

$zip->close();

// Download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="smile_backup_' . $timestamp . '.zip"');
header('Content-Length: ' . filesize($backupFile));
readfile($backupFile);
exit;
