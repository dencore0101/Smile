<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT ph.*, p.name AS patient_name, p.id AS patient_id,
                      t.name AS treatment_name
                      FROM patient_photos ph
                      JOIN patients p ON ph.patient_id = p.id
                      LEFT JOIN treatments t ON ph.treatment_id = t.id
                      WHERE ph.id = ?");
$stmt->execute([$id]);
$photo = $stmt->fetch();
if (!$photo) {
    set_flash('error', 'Photo not found.');
    redirect('patients.php');
}

$treatments = get_treatments_for_patient($photo['patient_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("photo_edit.php?id=$id");
    }

    $note = trim($_POST['note'] ?? '');
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);

    $stmt = $db->prepare("UPDATE patient_photos SET note = ?, treatment_id = ? WHERE id = ?");
    $stmt->execute([$note, $treatmentId ?: null, $id]);
    set_flash('success', 'Photo updated.');
    redirect("patient.php?id=" . $photo['patient_id']);
}
?>
<h1>Edit Photo</h1>
<div class="subtitle">Patient: <?= e($photo['patient_name']) ?></div>

<div class="card">
    <img src="photo_view.php?id=<?= $id ?>" alt="Photo" style="max-width: 100%; border-radius: 8px; margin-bottom: 16px;">
    <form method="post">
        <?= csrf_field() ?>
        <?php if (!empty($treatments)): ?>
        <div class="form-group">
            <label for="treatment_id">Link to Treatment</label>
            <select id="treatment_id" name="treatment_id">
                <option value="">— General photo —</option>
                <?php foreach ($treatments as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $photo['treatment_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="note">Note</label>
            <input type="text" id="note" name="note" value="<?= e($photo['note']) ?>">
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $photo['patient_id'] ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
