<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT lw.*, p.name AS patient_name, p.id AS patient_id
                      FROM lab_work lw
                      JOIN patients p ON lw.patient_id = p.id
                      WHERE lw.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) {
    set_flash('error', 'Lab work entry not found.');
    redirect('lab_work.php');
}

$treatments = get_treatments_for_patient($item['patient_id']);
$labs = $db->query("SELECT id, name FROM labs ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("lab_work_edit.php?id=$id");
    }

    $labId = (int)($_POST['lab_id'] ?? 0);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $dateSent = trim($_POST['date_sent'] ?? '') ?: today_date();
    $expectedDelivery = trim($_POST['expected_delivery_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $actualArrival = trim($_POST['actual_arrival_date'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($labId === 0) {
        set_flash('error', 'Lab is required.');
        redirect("lab_work_edit.php?id=$id");
    }
    if ($description === '') {
        set_flash('error', 'Work description is required.');
        redirect("lab_work_edit.php?id=$id");
    }

    if ($status === 'Arrived' && $actualArrival === '') {
        $actualArrival = today_date();
    }
    if ($status !== 'Arrived') {
        $actualArrival = '';
    }

    $stmt = $db->prepare("UPDATE lab_work SET lab_id=?, treatment_id=?, description=?, date_sent=?, expected_delivery_date=?, status=?, actual_arrival_date=?, note=?, updated_at=datetime('now') WHERE id=?");
    $stmt->execute([
        $labId, $treatmentId ?: null, $description,
        $dateSent, $expectedDelivery ?: null, $status,
        $actualArrival ?: null, $note, $id
    ]);
    set_flash('success', 'Lab work updated.');
    redirect('lab_work.php');
}
?>
<h1>Edit Lab Work</h1>
<div class="subtitle">Patient: <?= e($item['patient_name']) ?></div>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if (!empty($treatments)): ?>
        <div class="form-group">
            <label for="treatment_id">Link to Treatment</label>
            <select id="treatment_id" name="treatment_id">
                <option value="">— Not treatment-specific —</option>
                <?php foreach ($treatments as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $item['treatment_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="lab_id">Lab *</label>
            <select id="lab_id" name="lab_id" required>
                <option value="">Select lab...</option>
                <?php foreach ($labs as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $item['lab_id'] == $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="description">Work Description *</label>
            <input type="text" id="description" name="description" value="<?= e($item['description']) ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="date_sent">Date Sent *</label>
                <input type="date" id="date_sent" name="date_sent" value="<?= e($item['date_sent']) ?>" required>
            </div>
            <div class="form-group">
                <label for="expected_delivery_date">Expected Delivery</label>
                <input type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= e($item['expected_delivery_date']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['Pending', 'Arrived', 'Delayed'] as $s): ?>
                <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="arrival_date_group" style="<?= $item['status'] === 'Arrived' ? '' : 'display:none;' ?>">
            <label for="actual_arrival_date">Actual Arrival Date</label>
            <input type="date" id="actual_arrival_date" name="actual_arrival_date" value="<?= e($item['actual_arrival_date'] ?? today_date()) ?>">
        </div>
        <div class="form-group">
            <label for="note">Note</label>
            <textarea id="note" name="note"><?= e($item['note']) ?></textarea>
        </div>
        <div class="form-actions">
            <a href="lab_work.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
