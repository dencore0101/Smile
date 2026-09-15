<?php
require_once __DIR__ . '/includes/header.php';

$treatmentId = (int)($_GET['treatment_id'] ?? $_POST['treatment_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);

$treatment = null;
$patient = null;

if ($treatmentId) {
    $treatment = get_treatment($treatmentId);
    if (!$treatment) { set_flash('error', 'Treatment not found.'); redirect('patients.php'); }
    $patientId = $treatment['patient_id'];
} elseif ($patientId) {
    $patient = get_patient($patientId);
    if (!$patient) { set_flash('error', 'Patient not found.'); redirect('patients.php'); }
}

// Get treatments for patient if we need to select one
$treatments = [];
if ($patientId && !$treatmentId) {
    $treatments = get_treatments_for_patient($patientId);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('payment_new.php');
    }

    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentDate = trim($_POST['payment_date'] ?? '') ?: today_date();
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($treatmentId === 0) $errors[] = 'Treatment is required.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';

    if (empty($errors)) {
        $treatment = get_treatment($treatmentId);
        if (!$treatment) { $errors[] = 'Treatment not found.'; }
        else {
            try {
                $db = db();
                $stmt = $db->prepare("INSERT INTO payments (treatment_id, patient_id, amount, payment_date, payment_method, note)
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$treatmentId, $treatment['patient_id'], $amount, $paymentDate, $paymentMethod, $note]);
                set_flash('success', 'Payment added.');
                redirect("patient.php?id=" . $treatment['patient_id']);
            } catch (Throwable $e) {
                log_error('Error', 'Add payment failed: ' . $e->getMessage(), __FILE__, __LINE__);
                $errors[] = is_debug() ? $e->getMessage() : 'Could not add payment.';
            }
        }
    }
}
?>
<h1>Add Payment</h1>
<?php if ($treatment): ?>
<div class="subtitle"><?= e($treatment['patient_name']) ?> — <?= e($treatment['name']) ?></div>
<?php elseif ($patient): ?>
<div class="subtitle">Patient: <?= e($patient['name']) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($treatmentId): ?>
            <input type="hidden" name="treatment_id" value="<?= $treatmentId ?>">
        <?php elseif ($patientId && !empty($treatments)): ?>
            <div class="form-group">
                <label for="treatment_id">Treatment *</label>
                <select id="treatment_id" name="treatment_id" required>
                    <option value="">Select treatment...</option>
                    <?php foreach ($treatments as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)($_POST['treatment_id'] ?? 0) === $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>) — <?= format_money($t['total_cost']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="amount">Amount (<?= e(currency_symbol()) ?>) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required value="<?= e($_POST['amount'] ?? '') ?>" autofocus>
            </div>
            <div class="form-group">
                <label for="payment_date">Payment Date</label>
                <input type="date" id="payment_date" name="payment_date" value="<?= e($_POST['payment_date'] ?? today_date()) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method">
                    <option value="">—</option>
                    <?php foreach (['Cash', 'UPI', 'Card', 'Bank transfer', 'Other'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($_POST['payment_method'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="note">Note</label>
            <input type="text" id="note" name="note" value="<?= e($_POST['note'] ?? '') ?>" placeholder="e.g. Advance, RCT sitting, Final payment">
        </div>
        <div class="form-actions">
            <a href="<?= $treatment ? 'patient.php?id=' . $treatment['patient_id'] : ($patient ? 'patient.php?id=' . $patient['id'] : 'patients.php') ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Payment</button>
        </div>
    </form>
</div>

<?php if ($treatmentId): 
    $paid = treatment_paid($treatmentId);
    $bal = $treatment['total_cost'] - $paid;
?>
<div class="card">
    <div class="money-summary">
        <div class="money-item">
            <div class="label">Total Cost</div>
            <div class="value"><?= format_money($treatment['total_cost']) ?></div>
        </div>
        <div class="money-item">
            <div class="label">Paid So Far</div>
            <div class="value text-success"><?= format_money($paid) ?></div>
        </div>
        <div class="money-item">
            <div class="label">Balance</div>
            <div class="value <?= $bal > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_money($bal) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
