<?php
require_once __DIR__ . '/includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$treatmentId = (int)($_GET['treatment_id'] ?? $_POST['treatment_id'] ?? 0);

$patient = null;
$treatment = null;

if ($treatmentId) {
    $treatment = get_treatment($treatmentId);
    if (!$treatment) { set_flash('error', 'Treatment not found.'); redirect('patients.php'); }
    $patientId = $treatment['patient_id'];
}
if ($patientId) {
    $patient = get_patient($patientId);
    if (!$patient) { set_flash('error', 'Patient not found.'); redirect('patients.php'); }
}

$categories = get_expense_categories();
$treatments = $patientId ? get_treatments_for_patient($patientId) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('expense_new.php');
    }

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $nameDetails = trim($_POST['name_details'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $expenseDate = trim($_POST['expense_date'] ?? '') ?: today_date();
    $notes = trim($_POST['notes'] ?? '');

    if ($categoryId === 0) $errors[] = 'Category is required.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';

    if (empty($errors)) {
        try {
            $db = db();
            $stmt = $db->prepare("INSERT INTO expenses (patient_id, treatment_id, category_id, name_details, description, amount, expense_date, notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId ?: null,
                $treatmentId ?: null,
                $categoryId,
                $nameDetails,
                $description,
                $amount,
                $expenseDate,
                $notes,
            ]);
            set_flash('success', 'Expense added.');
            if ($patientId) {
                redirect("patient.php?id=$patientId");
            } else {
                redirect("expenses.php");
            }
        } catch (Throwable $e) {
            log_error('Error', 'Add expense failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? $e->getMessage() : 'Could not add expense.';
        }
    }
}
?>
<h1>Add Expense</h1>
<?php if ($patient): ?>
<div class="subtitle">Patient: <?= e($patient['name']) ?> (<?= e($patient['patient_id']) ?>)</div>
<?php else: ?>
<div class="subtitle">General clinic expense (not linked to a patient)</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($patientId && !$treatmentId): ?>
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <?php if (!empty($treatments)): ?>
            <div class="form-group">
                <label for="treatment_id">Link to Treatment (optional)</label>
                <select id="treatment_id" name="treatment_id">
                    <option value="">— Not treatment-specific —</option>
                    <?php foreach ($treatments as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        <?php elseif ($treatmentId): ?>
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <input type="hidden" name="treatment_id" value="<?= $treatmentId ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)($_POST['category_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount (<?= e(currency_symbol()) ?>) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required value="<?= e($_POST['amount'] ?? '') ?>" autofocus>
            </div>
        </div>
        <div class="form-group">
            <label for="name_details">Name / Details</label>
            <input type="text" id="name_details" name="name_details" value="<?= e($_POST['name_details'] ?? '') ?>" placeholder="e.g. ABC Dental Lab, Dr XYZ">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="expense_date">Date</label>
                <input type="date" id="expense_date" name="expense_date" value="<?= e($_POST['expense_date'] ?? today_date()) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <input type="text" id="description" name="description" value="<?= e($_POST['description'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <a href="<?= $patientId ? "patient.php?id=$patientId" : 'expenses.php' ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Expense</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
