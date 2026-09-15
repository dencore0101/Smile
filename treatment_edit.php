<?php
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session. Please try again.');
        redirect('patients.php');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $treatment = get_treatment($id);
    if (!$treatment) {
        set_flash('error', 'Treatment not found.');
        redirect('patients.php');
    }
    $patientId = $treatment['patient_id'];

    if ($action === 'edit_treatment_notes') {
        $stmt = db()->prepare("UPDATE treatments SET treatment_notes=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([trim($_POST['treatment_notes']), $id]);
        set_flash('success', 'Treatment notes updated.');
    } elseif ($action === 'edit_final_notes') {
        $stmt = db()->prepare("UPDATE treatments SET final_notes=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([trim($_POST['final_notes']), $id]);
        set_flash('success', 'Final notes updated.');
    } elseif ($action === 'edit_treatment') {
        $name = trim($_POST['name']);
        $toothArea = trim($_POST['tooth_area']);
        $startDate = trim($_POST['start_date']) ?: today_date();
        $totalCost = (float)$_POST['total_cost'];
        $stmt = db()->prepare("UPDATE treatments SET name=?, tooth_area=?, start_date=?, total_cost=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([$name, $toothArea, $startDate, $totalCost, $id]);
        set_flash('success', 'Treatment updated.');
    }

    redirect("patient.php?id=$patientId");
}

$id = (int)($_GET['id'] ?? 0);
$treatment = get_treatment($id);
if (!$treatment) {
    set_flash('error', 'Treatment not found.');
    redirect('patients.php');
}
$patientId = $treatment['patient_id'];
?>
<h1>Edit Treatment</h1>
<div class="subtitle">Patient: <?= e($treatment['patient_name']) ?> (<?= e($treatment['patient_id']) ?>)</div>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_treatment">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label for="name">Treatment Name *</label>
            <input type="text" id="name" name="name" value="<?= e($treatment['name']) ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="tooth_area">Tooth / Area</label>
                <input type="text" id="tooth_area" name="tooth_area" value="<?= e($treatment['tooth_area']) ?>">
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= e($treatment['start_date']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="total_cost">Total Cost (<?= e(currency_symbol()) ?>)</label>
            <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" value="<?= e($treatment['total_cost']) ?>">
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $patientId ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
