<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

$id = (int)($_GET['id'] ?? 0);
$full = isset($_GET['full']);

$db = db();
$stmt = $db->prepare("SELECT * FROM patient_photos WHERE id = ?");
$stmt->execute([$id]);
$photo = $stmt->fetch();
if (!$photo) {
    http_response_code(404);
    echo 'Photo not found.';
    exit;
}

$filePath = PHOTO_DIR . '/' . $photo['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filePath);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
exit;
