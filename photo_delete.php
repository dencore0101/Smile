<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    set_flash('error', 'Invalid request.');
    redirect('patients.php');
}

$id = (int)($_POST['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT * FROM patient_photos WHERE id = ?");
$stmt->execute([$id]);
$photo = $stmt->fetch();
if (!$photo) {
    set_flash('error', 'Photo not found.');
    redirect('patients.php');
}

// Delete file
$filePath = PHOTO_DIR . '/' . $photo['file_path'];
if (file_exists($filePath)) {
    unlink($filePath);
}

// Delete record
$stmt = $db->prepare("DELETE FROM patient_photos WHERE id = ?");
$stmt->execute([$id]);
set_flash('success', 'Photo deleted.');
redirect("patient.php?id=" . $photo['patient_id']);
