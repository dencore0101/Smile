<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();
require_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    set_flash('error', 'Invalid request.');
    redirect('patients.php');
}

$id = (int)($_POST['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT patient_id FROM expenses WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    set_flash('error', 'Expense not found.');
    redirect('patients.php');
}

// Soft delete
$stmt = $db->prepare("UPDATE expenses SET deleted_at = datetime('now') WHERE id = ?");
$stmt->execute([$id]);
set_flash('success', 'Expense deleted.');
redirect($row['patient_id'] ? "patient.php?id=" . $row['patient_id'] : "expenses.php");
