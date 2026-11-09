<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('followups.php');
}
if (!csrf_check()) {
    set_flash('error', 'Invalid session. Please try again.');
    redirect('followups.php');
}

$id = (int)($_POST['id'] ?? 0);
$back = $_POST['back'] ?? '';
if (!preg_match('/^(followups\.php(\?[\w=&+%.-]*)?|patient\.php\?id=\d+)$/', $back)) {
    $back = 'followups.php';
}

$db = db();
$stmt = $db->prepare("SELECT id FROM followups WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    set_flash('error', 'Follow-up not found.');
    redirect($back);
}

$db->prepare("DELETE FROM followups WHERE id = ?")->execute([$id]);
set_flash('success', 'Follow-up deleted.');
redirect($back);
