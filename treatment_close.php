<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$treatment = get_treatment($id);
if (!$treatment) {
    set_flash('error', 'Treatment not found.');
    redirect('patients.php');
}

$balance = $treatment['total_cost'] - treatment_paid($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("treatment_close.php?id=$id");
    }

    $stmt = db()->prepare("UPDATE treatments SET status='Closed', closed_at=datetime('now'), updated_at=datetime('now') WHERE id=?");
    $stmt->execute([$id]);
    set_flash('success', 'Treatment closed.');
    redirect("patient.php?id=" . $treatment['patient_id']);
}
?>
<h1>Close Treatment</h1>
<div class="subtitle"><?= e($treatment['patient_name']) ?> — <?= e($treatment['name']) ?></div>

<?php if ($balance > 0): ?>
<div class="flash flash-warning">
    There is an outstanding balance of <strong><?= format_money($balance) ?></strong>.
    Are you sure you want to close this treatment?
</div>
<?php else: ?>
<div class="flash flash-success">This treatment is fully paid. Close it now?</div>
<?php endif; ?>

<div class="card">
    <div class="money-summary">
        <div class="money-item">
            <div class="label">Total Cost</div>
            <div class="value"><?= format_money($treatment['total_cost']) ?></div>
        </div>
        <div class="money-item">
            <div class="label">Paid</div>
            <div class="value text-success"><?= format_money(treatment_paid($id)) ?></div>
        </div>
        <div class="money-item">
            <div class="label">Balance</div>
            <div class="value <?= $balance > 0 ? 'text-danger' : '' ?>"><?= format_money($balance) ?></div>
        </div>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-actions">
            <a href="patient.php?id=<?= $treatment['patient_id'] ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Close Treatment</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
