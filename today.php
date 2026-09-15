<?php
require_once __DIR__ . '/includes/header.php';

$today = today_date();
$db = db();

// Today's follow-ups
$stmt = $db->prepare("SELECT f.*, p.name AS patient_name, p.patient_id AS patient_code,
                      t.name AS treatment_name
                      FROM followups f
                      JOIN patients p ON f.patient_id = p.id
                      LEFT JOIN treatments t ON f.treatment_id = t.id
                      WHERE f.date = ? AND f.status NOT IN ('Cancelled', 'Completed', 'No-show')
                      ORDER BY f.time ASC, p.name ASC");
$stmt->execute([$today]);
$today_followups = $stmt->fetchAll();

// Today's collection
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments
                      WHERE payment_date = ? AND deleted_at IS NULL");
$stmt->execute([$today]);
$today_collection = (float)$stmt->fetchColumn();

// Today's expenses
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses
                      WHERE expense_date = ? AND deleted_at IS NULL");
$stmt->execute([$today]);
$today_expenses = (float)$stmt->fetchColumn();

$today_net = $today_collection - $today_expenses;

// Today's expenses detail
$stmt = $db->prepare("SELECT e.*, ec.name AS category_name, p.name AS patient_name
                      FROM expenses e
                      JOIN expense_categories ec ON e.category_id = ec.id
                      LEFT JOIN patients p ON e.patient_id = p.id
                      WHERE e.expense_date = ? AND e.deleted_at IS NULL
                      ORDER BY e.created_at DESC");
$stmt->execute([$today]);
$today_expense_list = $stmt->fetchAll();

// Today's payments detail
$stmt = $db->prepare("SELECT p.*, pt.name AS patient_name, pt.patient_id AS patient_code,
                      t.name AS treatment_name
                      FROM payments p
                      JOIN patients pt ON p.patient_id = pt.id
                      LEFT JOIN treatments t ON p.treatment_id = t.id
                      WHERE p.payment_date = ? AND p.deleted_at IS NULL
                      ORDER BY p.created_at DESC");
$stmt->execute([$today]);
$today_payments = $stmt->fetchAll();
?>
<h1>SMILE</h1>
<div class="subtitle"><?= e(today_date_long()) ?></div>

<div class="quick-actions">
    <a href="patient_new.php" class="btn btn-primary">+ New Patient</a>
    <a href="patients.php" class="btn btn-outline">Search Patient</a>
    <a href="payment_new.php" class="btn btn-outline">+ Add Payment</a>
    <a href="expense_new.php" class="btn btn-outline">+ Add Expense</a>
    <a href="followup_new.php" class="btn btn-outline">+ Add Follow-up</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Today's Collection</div>
        <div class="stat-value positive"><?= format_money($today_collection) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Today's Expenses</div>
        <div class="stat-value negative"><?= format_money($today_expenses) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Today's Net</div>
        <div class="stat-value <?= $today_net >= 0 ? 'positive' : 'negative' ?>"><?= format_money($today_net) ?></div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <div class="section-title">Today's Follow-ups</div>
        <a href="followups.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="section-body">
        <?php if (empty($today_followups)): ?>
            <div class="empty-state"><p>No follow-ups scheduled for today.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Treatment / Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_followups as $f): ?>
                        <tr class="table-clickable" onclick="window.location='patient.php?id=<?= $f['patient_id'] ?>'">
                            <td><?= e($f['time'] ? date('g:i A', strtotime($f['time'])) : '—') ?></td>
                            <td><?= e($f['patient_name']) ?></td>
                            <td><?= e($f['treatment_name'] ?: $f['reason']) ?></td>
                            <td><span class="badge badge-<?= strtolower($f['status']) ?>"><?= e($f['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <div class="section-header"><div class="section-title">Today's Collection</div></div>
    <div class="section-body">
        <?php if (empty($today_payments)): ?>
            <div class="empty-state"><p>No payments recorded today.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Treatment</th>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_payments as $p): ?>
                        <tr class="table-clickable" onclick="window.location='patient.php?id=<?= $p['patient_id'] ?>'">
                            <td><?= e($p['patient_name']) ?></td>
                            <td><?= e($p['treatment_name'] ?? '—') ?></td>
                            <td><?= e($p['payment_method'] ?? '—') ?></td>
                            <td class="text-right font-bold"><?= format_money($p['amount']) ?></td>
                            <td class="text-sm text-muted"><?= e($p['note'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <div class="section-header"><div class="section-title">Today's Expenses</div></div>
    <div class="section-body">
        <?php if (empty($today_expense_list)): ?>
            <div class="empty-state"><p>No expenses recorded today.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Name/Details</th>
                            <th>Patient</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_expense_list as $e): ?>
                        <tr>
                            <td><span class="badge badge-active"><?= e($e['category_name']) ?></span></td>
                            <td><?= e($e['name_details'] ?? '') ?></td>
                            <td><?= e($e['patient_name'] ?? '—') ?></td>
                            <td class="text-right font-bold"><?= format_money($e['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
