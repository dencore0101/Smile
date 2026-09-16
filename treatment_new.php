<?php
require_once __DIR__ . '/includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$patient = get_patient($patientId);
if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('patients.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session. Please try again.');
        redirect("treatment_new.php?patient_id=$patientId");
    }

    $name = trim($_POST['name'] ?? '');
    $toothArea = trim($_POST['tooth_area'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '') ?: today_date();
    $totalCost = (float)($_POST['total_cost'] ?? 0);
    $treatmentNotes = trim($_POST['treatment_notes'] ?? '');
    $finalNotes = trim($_POST['final_notes'] ?? '');

    if ($name === '') $errors[] = 'Treatment name is required.';

    if (empty($errors)) {
        try {
            $db = db();
            $stmt = $db->prepare("INSERT INTO treatments (patient_id, name, tooth_area, start_date, total_cost, treatment_notes, final_notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $name, $toothArea, $startDate, $totalCost, $treatmentNotes, $finalNotes]);
            set_flash('success', 'Treatment created.');
            redirect("patient.php?id=$patientId");
        } catch (Throwable $e) {
            log_error('Error', 'Create treatment failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? $e->getMessage() : 'Could not create treatment.';
        }
    }
}
?>
<h1>New Treatment</h1>
<div class="subtitle">Patient: <?= e($patient['name']) ?> (<?= e($patient['patient_id']) ?>)</div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <div class="form-group">
            <label for="name">Treatment Name *</label>
            <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" autofocus placeholder="e.g. RCT 36, Crown 36, Filling 46">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tooth_area">Tooth / Area</label>
                <input type="text" id="tooth_area" name="tooth_area" value="<?= e($_POST['tooth_area'] ?? '') ?>" placeholder="e.g. 36, Upper Right">
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= e($_POST['start_date'] ?? today_date()) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="total_cost">Total Cost (<?= e(currency_symbol()) ?>)</label>
            <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= e($_POST['total_cost'] ?? '0') ?>">
        </div>
        <div class="form-group">
            <label for="treatment_notes">Treatment Notes</label>
            <textarea id="treatment_notes" name="treatment_notes" placeholder="Ongoing notes..."><?= e($_POST['treatment_notes'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="final_notes">Final Notes</label>
            <textarea id="final_notes" name="final_notes" placeholder="Final summary (can be filled later)..."><?= e($_POST['final_notes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $patientId ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Treatment</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
