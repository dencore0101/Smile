<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$db = db();
$stmt = $db->prepare("SELECT p.*, t.name AS treatment_name, pt.name AS patient_name, pt.id AS patient_id
                      FROM payments p
                      JOIN treatments t ON p.treatment_id = t.id
                      JOIN patients pt ON p.patient_id = pt.id
                      WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) {
    set_flash('error', 'Payment not found.');
    redirect('patients.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("payment_edit.php?id=$id");
    }

    $amount = (float)$_POST['amount'];
    $paymentDate = trim($_POST['payment_date']) ?: today_date();
    $paymentMethod = trim($_POST['payment_method']);
    $note = trim($_POST['note']);

    if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE payments SET amount=?, payment_date=?, payment_method=?, note=? WHERE id=?");
        $stmt->execute([$amount, $paymentDate, $paymentMethod, $note, $id]);
        set_flash('success', 'Payment updated.');
        redirect("patient.php?id=" . $payment['patient_id']);
    }
}
?>
<h1>Edit Payment</h1>
<div class="subtitle"><?= e($payment['patient_name']) ?> — <?= e($payment['treatment_name']) ?></div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="amount">Amount (<?= e(currency_symbol()) ?>) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required value="<?= e($payment['amount']) ?>">
            </div>
            <div class="form-group">
                <label for="payment_date">Payment Date</label>
                <input type="date" id="payment_date" name="payment_date" value="<?= e($payment['payment_date']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method">
                <option value="">—</option>
                <?php foreach (['Cash', 'UPI', 'Card', 'Bank transfer', 'Other'] as $m): ?>
                <option value="<?= $m ?>" <?= $payment['payment_method'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="note">Note</label>
            <input type="text" id="note" name="note" value="<?= e($payment['note']) ?>">
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $payment['patient_id'] ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
