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
$labs = db()->query("SELECT id, name FROM labs ORDER BY name")->fetchAll();
$allPatients = db()->query("SELECT id, name, patient_id FROM patients ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('lab_work_new.php');
    }

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $labId = (int)($_POST['lab_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $dateSent = trim($_POST['date_sent'] ?? '') ?: today_date();
    $expectedDelivery = trim($_POST['expected_delivery_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $actualArrival = trim($_POST['actual_arrival_date'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($patientId === 0) $errors[] = 'Patient is required.';
    if ($labId === 0) $errors[] = 'Lab is required.';
    if ($description === '') $errors[] = 'Work description is required.';
    if ($dateSent === '') $errors[] = 'Date sent is required.';

    if ($status === 'Arrived' && $actualArrival === '') {
        $actualArrival = today_date();
    }
    if ($status !== 'Arrived') {
        $actualArrival = '';
    }

    if (empty($errors)) {
        try {
            $db = db();
            $stmt = $db->prepare("INSERT INTO lab_work (patient_id, treatment_id, lab_id, description, date_sent, expected_delivery_date, status, actual_arrival_date, note)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, $treatmentId ?: null, $labId, $description,
                $dateSent, $expectedDelivery ?: null, $status,
                $actualArrival ?: null, $note
            ]);
            set_flash('success', 'Lab work added.');
            redirect('lab_work.php');
        } catch (Throwable $e) {
            log_error('Error', 'Add lab work failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? $e->getMessage() : 'Could not add lab work.';
        }
    }
}
?>
<h1>Add Lab Work</h1>
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
                    <?php foreach ($allPatients as $p): ?>
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
        <div class="form-group">
            <label for="lab_id">Lab *</label>
            <select id="lab_id" name="lab_id" required>
                <option value="">Select lab...</option>
                <?php foreach ($labs as $l): ?>
                <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="description">Work Description *</label>
            <input type="text" id="description" name="description" value="<?= e($_POST['description'] ?? '') ?>" placeholder="e.g. Crown 36 impression, Denture repair" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="date_sent">Date Sent *</label>
                <input type="date" id="date_sent" name="date_sent" value="<?= e($_POST['date_sent'] ?? today_date()) ?>" required>
            </div>
            <div class="form-group">
                <label for="expected_delivery_date">Expected Delivery</label>
                <input type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= e($_POST['expected_delivery_date'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['Pending', 'Arrived', 'Delayed'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'Pending') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="arrival_date_group" style="display:none;">
            <label for="actual_arrival_date">Actual Arrival Date</label>
            <input type="date" id="actual_arrival_date" name="actual_arrival_date" value="<?= e($_POST['actual_arrival_date'] ?? today_date()) ?>">
        </div>
        <div class="form-group">
            <label for="note">Note</label>
            <textarea id="note" name="note"><?= e($_POST['note'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <a href="<?= $patientId ? "patient.php?id=$patientId" : 'lab_work.php' ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Lab Work</button>
        </div>
    </form>
</div>

<script>
document.getElementById('status').addEventListener('change', function() {
    var group = document.getElementById('arrival_date_group');
    if (this.value === 'Arrived') {
        group.style.display = '';
    } else {
        group.style.display = 'none';
    }
});
var sel = document.getElementById('status');
if (sel.value === 'Arrived') document.getElementById('arrival_date_group').style.display = '';
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
