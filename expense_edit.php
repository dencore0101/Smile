<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT e.*, p.name AS patient_name, p.id AS patient_id
                      FROM expenses e
                      LEFT JOIN patients p ON e.patient_id = p.id
                      WHERE e.id = ? AND e.deleted_at IS NULL");
$stmt->execute([$id]);
$expense = $stmt->fetch();
if (!$expense) {
    set_flash('error', 'Expense not found.');
    redirect('patients.php');
}

$categories = get_expense_categories();
$treatments = $expense['patient_id'] ? get_treatments_for_patient($expense['patient_id']) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("expense_edit.php?id=$id");
    }

    $categoryId = (int)$_POST['category_id'];
    $nameDetails = trim($_POST['name_details']);
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expenseDate = trim($_POST['expense_date']) ?: today_date();
    $notes = trim($_POST['notes']);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);

    if ($categoryId === 0) $errors[] = 'Category is required.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE expenses SET category_id=?, name_details=?, description=?, amount=?, expense_date=?, notes=?, treatment_id=? WHERE id=?");
        $stmt->execute([$categoryId, $nameDetails, $description, $amount, $expenseDate, $notes, $treatmentId ?: null, $id]);
        set_flash('success', 'Expense updated.');
        redirect($expense['patient_id'] ? "patient.php?id=" . $expense['patient_id'] : "expenses.php");
    }
}
?>
<h1>Edit Expense</h1>
<?php if ($expense['patient_name']): ?>
<div class="subtitle">Patient: <?= e($expense['patient_name']) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if (!empty($treatments)): ?>
        <div class="form-group">
            <label for="treatment_id">Link to Treatment</label>
            <select id="treatment_id" name="treatment_id">
                <option value="">— Not treatment-specific —</option>
                <?php foreach ($treatments as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $expense['treatment_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $expense['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount (<?= e(currency_symbol()) ?>) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required value="<?= e($expense['amount']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="name_details">Name / Details</label>
            <input type="text" id="name_details" name="name_details" value="<?= e($expense['name_details']) ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="expense_date">Date</label>
                <input type="date" id="expense_date" name="expense_date" value="<?= e($expense['expense_date']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" value="<?= e($expense['description']) ?>">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"><?= e($expense['notes']) ?></textarea>
        </div>
        <div class="form-actions">
            <a href="<?= $expense['patient_id'] ? 'patient.php?id=' . $expense['patient_id'] : 'expenses.php' ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
