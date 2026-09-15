<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT f.*, p.name AS patient_name, p.id AS patient_id
                      FROM followups f
                      JOIN patients p ON f.patient_id = p.id
                      WHERE f.id = ?");
$stmt->execute([$id]);
$followup = $stmt->fetch();
if (!$followup) {
    set_flash('error', 'Follow-up not found.');
    redirect('followups.php');
}

$treatments = get_treatments_for_patient($followup['patient_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("followup_edit.php?id=$id");
    }

    $date = trim($_POST['date']) ?: today_date();
    $time = trim($_POST['time']);
    $reason = trim($_POST['reason']);
    $notes = trim($_POST['notes']);
    $status = trim($_POST['status']);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);

    $stmt = $db->prepare("UPDATE followups SET date=?, time=?, reason=?, notes=?, status=?, treatment_id=? WHERE id=?");
    $stmt->execute([$date, $time ?: null, $reason, $notes, $status, $treatmentId ?: null, $id]);
    set_flash('success', 'Follow-up updated.');
    redirect("patient.php?id=" . $followup['patient_id']);
}
?>
<h1>Edit Follow-up</h1>
<div class="subtitle">Patient: <?= e($followup['patient_name']) ?></div>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if (!empty($treatments)): ?>
        <div class="form-group">
            <label for="treatment_id">Link to Treatment</label>
            <select id="treatment_id" name="treatment_id">
                <option value="">— Not treatment-specific —</option>
                <?php foreach ($treatments as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $followup['treatment_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= e($followup['date']) ?>" required>
            </div>
            <div class="form-group">
                <label for="time">Time</label>
                <input type="time" id="time" name="time" value="<?= e($followup['time']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="reason">Reason</label>
            <input type="text" id="reason" name="reason" value="<?= e($followup['reason']) ?>">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"><?= e($followup['notes']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['Pending', 'Completed', 'Rescheduled', 'Cancelled', 'No-show'] as $s): ?>
                <option value="<?= $s ?>" <?= $followup['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $followup['patient_id'] ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
