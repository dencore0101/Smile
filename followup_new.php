<?php
require_once __DIR__ . '/includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$treatmentId = (int)($_GET['treatment_id'] ?? $_POST['treatment_id'] ?? 0);

$patient = null;
if ($treatmentId) {
    $treatment = get_treatment($treatmentId);
    if (!$treatment) { set_flash('error', 'Treatment not found.'); redirect('patients.php'); }
    $patientId = $treatment['patient_id'];
}
if ($patientId) {
    $patient = get_patient($patientId);
    if (!$patient) { set_flash('error', 'Patient not found.'); redirect('patients.php'); }
}

$treatments = $patientId ? get_treatments_for_patient($patientId) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('followup_new.php');
    }

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $date = trim($_POST['date'] ?? '') ?: today_date();
    $time = trim($_POST['time'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');

    if ($patientId === 0) $errors[] = 'Patient is required.';
    if ($date === '') $errors[] = 'Date is required.';

    if (empty($errors)) {
        try {
            $db = db();
            $stmt = $db->prepare("INSERT INTO followups (patient_id, treatment_id, date, time, reason, notes, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $treatmentId ?: null, $date, $time ?: null, $reason, $notes, $status]);
            set_flash('success', 'Follow-up added.');
            redirect("patient.php?id=$patientId");
        } catch (Throwable $e) {
            log_error('Error', 'Add followup failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? $e->getMessage() : 'Could not add follow-up.';
        }
    }
}
?>
<h1>Add Follow-up</h1>
<?php if ($patient): ?>
<div class="subtitle">Patient: <?= e($patient['name']) ?> (<?= e($patient['patient_id']) ?>)</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($patientId): ?>
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <?php else: ?>
            <div class="form-group">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" required>
                    <option value="">Select patient...</option>
                    <?php
                    $allPatients = db()->query("SELECT id, name, patient_id FROM patients ORDER BY name")->fetchAll();
                    foreach ($allPatients as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['patient_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if (!empty($treatments) && !$treatmentId): ?>
            <div class="form-group">
                <label for="treatment_id">Link to Treatment (optional)</label>
                <select id="treatment_id" name="treatment_id">
                    <option value="">— Not treatment-specific —</option>
                    <?php foreach ($treatments as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($treatmentId): ?>
            <input type="hidden" name="treatment_id" value="<?= $treatmentId ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="date">Date *</label>
                <input type="date" id="date" name="date" value="<?= e($_POST['date'] ?? today_date()) ?>" required>
            </div>
            <div class="form-group">
                <label for="time">Time (optional)</label>
                <input type="time" id="time" name="time" value="<?= e($_POST['time'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="reason">Reason</label>
            <input type="text" id="reason" name="reason" value="<?= e($_POST['reason'] ?? '') ?>" placeholder="e.g. Review, Crown fitting">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['Pending', 'Completed', 'Rescheduled', 'Cancelled', 'No-show'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'Pending') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <a href="<?= $patientId ? "patient.php?id=$patientId" : 'followups.php' ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Follow-up</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
