<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('patients.php');
}
if (!csrf_check()) {
    set_flash('error', 'Invalid session. Please try again.');
    redirect('patients.php');
}

$id = (int)($_POST['patient_id'] ?? 0);
$action = $_POST['action'] ?? '';
$back = $_POST['back'] ?? '';
if (!preg_match('/^(patients\.php(\?[\w=&+%.-]*)?|patient\.php\?id=\d+)$/', $back)) {
    $back = 'patients.php';
}

$patient = get_patient($id);
if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect($back);
}

if ($action === 'complete') {
    db()->prepare("UPDATE patients SET status = 'Completed', completed_at = datetime('now') WHERE id = ?")->execute([$id]);
    set_flash('success', $patient['name'] . ' moved to Completed. All records have been kept.');
} elseif ($action === 'reopen') {
    db()->prepare("UPDATE patients SET status = 'Active', completed_at = NULL WHERE id = ?")->execute([$id]);
    set_flash('success', $patient['name'] . ' reopened and moved back to Active.');
} else {
    set_flash('error', 'Unknown action.');
}

redirect($back);
