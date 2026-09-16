<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$db = db();
$preset = $_GET['preset'] ?? 'month';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if ($preset === 'custom' && $start && $end) {
    $startDate = $start;
    $endDate = $end;
} else {
    [$startDate, $endDate] = date_range($preset);
}

$label = date_range_label($preset === 'custom' ? 'custom' : $preset, $startDate, $endDate);

// Treatment value (total cost of treatments started in period)
$stmt = $db->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM treatments WHERE start_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$treatmentValue = (float)$stmt->fetchColumn();

// Money collected (payments in period)
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date BETWEEN ? AND ? AND deleted_at IS NULL");
$stmt->execute([$startDate, $endDate]);
$collected = (float)$stmt->fetchColumn();

// Outstanding (total balance across all treatments up to end date)
$stmt = $db->prepare("SELECT t.total_cost, COALESCE(SUM(p.amount), 0) AS paid
                     FROM treatments t
                     LEFT JOIN payments p ON p.treatment_id = t.id AND p.deleted_at IS NULL AND p.payment_date <= ?
                     GROUP BY t.id");
$stmt->execute([$endDate]);
$outstanding = 0;
foreach ($stmt->fetchAll() as $row) {
    $outstanding += (float)$row['total_cost'] - (float)$row['paid'];
}

// Patient-linked expenses
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? AND deleted_at IS NULL AND patient_id IS NOT NULL");
$stmt->execute([$startDate, $endDate]);
$patientExpenses = (float)$stmt->fetchColumn();

// General (non-patient) expenses
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? AND deleted_at IS NULL AND patient_id IS NULL");
$stmt->execute([$startDate, $endDate]);
$generalExpenses = (float)$stmt->fetchColumn();

$totalExpenses = $patientExpenses + $generalExpenses;
$netCashFlow = $collected - $totalExpenses;

// Expense breakdown by category
$stmt = $db->prepare("SELECT ec.name, COALESCE(SUM(e.amount), 0) AS total, COUNT(e.id) AS cnt
                      FROM expenses e
                      JOIN expense_categories ec ON e.category_id = ec.id
                      WHERE e.expense_date BETWEEN ? AND ? AND e.deleted_at IS NULL
                      GROUP BY ec.name ORDER BY total DESC");
$stmt->execute([$startDate, $endDate]);
$catBreakdown = $stmt->fetchAll();

// Payments detail
$stmt = $db->prepare("SELECT p.*, pt.name AS patient_name, t.name AS treatment_name
                      FROM payments p
                      JOIN patients pt ON p.patient_id = pt.id
                      LEFT JOIN treatments t ON p.treatment_id = t.id
                      WHERE p.payment_date BETWEEN ? AND ? AND p.deleted_at IS NULL
                      ORDER BY p.payment_date DESC, p.created_at DESC");
$stmt->execute([$startDate, $endDate]);
$payments = $stmt->fetchAll();

// Treatments in period — FIXED: use correlated subqueries instead of JOIN+GROUP BY to avoid double-counting
$stmt = $db->prepare("SELECT t.*, p.name AS patient_name,
                      (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.treatment_id = t.id AND pay.deleted_at IS NULL) AS paid,
                      (SELECT COALESCE(SUM(ex.amount), 0) FROM expenses ex WHERE ex.treatment_id = t.id AND ex.deleted_at IS NULL) AS expenses
                      FROM treatments t
                      JOIN patients p ON t.patient_id = p.id
                      WHERE t.start_date BETWEEN ? AND ?
                      ORDER BY t.start_date DESC");
$stmt->execute([$startDate, $endDate]);
$treatments = $stmt->fetchAll();

// CSV export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    if ($type === 'payments') {
        $rows = [];
        foreach ($payments as $p) {
            $rows[] = [$p['payment_date'], $p['patient_name'], $p['treatment_name'], $p['amount'], $p['payment_method'], $p['note']];
        }
        csv_output("payments_$startDate-$endDate.csv", $rows, ['Date', 'Patient', 'Treatment', 'Amount', 'Method', 'Note']);
    } elseif ($type === 'summary') {
        $rows = [
            ['Treatment Value', $treatmentValue],
            ['Money Collected', $collected],
            ['Outstanding', $outstanding],
            ['Patient-linked Expenses', $patientExpenses],
            ['General Expenses', $generalExpenses],
            ['Total Expenses', $totalExpenses],
            ['Net Cash Flow', $netCashFlow],
        ];
        csv_output("report_summary_$startDate-$endDate.csv", $rows, ['Metric', 'Amount']);
    }
}
?>
<h1>Reports</h1>
<div class="subtitle"><?= e($label) ?></div>

<div class="filter-bar">
    <a href="?preset=today" class="btn btn-sm <?= $preset==='today'?'btn-primary':'btn-outline' ?>">Today</a>
    <a href="?preset=week" class="btn btn-sm <?= $preset==='week'?'btn-primary':'btn-outline' ?>">This Week</a>
    <a href="?preset=month" class="btn btn-sm <?= $preset==='month'?'btn-primary':'btn-outline' ?>">This Month</a>
    <a href="?preset=year" class="btn btn-sm <?= $preset==='year'?'btn-primary':'btn-outline' ?>">This Year</a>
    <form method="get" style="display:flex; gap:4px; align-items:center;">
        <input type="hidden" name="preset" value="custom">
        <input type="date" name="start" value="<?= e($start) ?>" style="width:auto">
        <input type="date" name="end" value="<?= e($end) ?>" style="width:auto">
        <button type="submit" class="btn btn-sm btn-outline">Custom</button>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Treatment Value</div>
        <div class="stat-value"><?= format_money($treatmentValue) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Money Collected</div>
        <div class="stat-value positive"><?= format_money($collected) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value <?= $outstanding > 0 ? 'negative' : '' ?>"><?= format_money($outstanding) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Patient Expenses</div>
        <div class="stat-value negative"><?= format_money($patientExpenses) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">General Expenses</div>
        <div class="stat-value negative"><?= format_money($generalExpenses) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value negative"><?= format_money($totalExpenses) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Net Cash Flow</div>
        <div class="stat-value <?= $netCashFlow >= 0 ? 'positive' : 'negative' ?>"><?= format_money($netCashFlow) ?></div>
    </div>
</div>

<div class="filter-bar">
    <a href="?preset=<?= e($preset) ?><?php if($preset==='custom'){echo '&start='.e($start).'&end='.e($end);} ?>&export=summary" class="btn btn-sm btn-outline">Export Summary CSV</a>
    <a href="?preset=<?= e($preset) ?><?php if($preset==='custom'){echo '&start='.e($start).'&end='.e($end);} ?>&export=payments" class="btn btn-sm btn-outline">Export Payments CSV</a>
</div>

<!-- Expense breakdown -->
<div class="section">
    <div class="section-header"><div class="section-title">Expense Breakdown by Category</div></div>
    <div class="section-body">
        <?php if (empty($catBreakdown)): ?>
            <p class="text-muted text-sm">No expenses in this period.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catBreakdown as $c): ?>
                    <tr>
                        <td><span class="badge badge-active"><?= e($c['name']) ?></span></td>
                        <td class="text-right text-sm"><?= $c['cnt'] ?></td>
                        <td class="text-right font-bold"><?= format_money($c['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 2px solid var(--gray-200)">
                        <td class="font-bold">Total</td>
                        <td></td>
                        <td class="text-right font-bold"><?= format_money($totalExpenses) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Treatments in period -->
<div class="section">
    <div class="section-header"><div class="section-title">Treatments Started (<?= count($treatments) ?>)</div></div>
    <div class="section-body">
        <?php if (empty($treatments)): ?>
            <p class="text-muted text-sm">No treatments started in this period.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Treatment</th>
                        <th>Start Date</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Expenses</th>
                        <th class="text-right">Treatment Margin</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treatments as $t):
                        $bal = $t['total_cost'] - $t['paid'];
                        $margin = $t['total_cost'] - $t['expenses'];
                    ?>
                    <tr class="table-clickable" onclick="window.location='patient.php?id=<?= $t['patient_id'] ?>'">
                        <td class="font-semibold text-sm"><?= e($t['patient_name']) ?></td>
                        <td class="text-sm"><?= e($t['name']) ?></td>
                        <td class="text-sm nowrap"><?= format_date($t['start_date']) ?></td>
                        <td class="text-right font-bold"><?= format_money($t['total_cost']) ?></td>
                        <td class="text-right text-success"><?= format_money($t['paid']) ?></td>
                        <td class="text-right text-danger"><?= format_money($t['expenses']) ?></td>
                        <td class="text-right font-bold"><?= format_money($margin) ?></td>
                        <td><span class="badge badge-<?= strtolower($t['status']) ?>"><?= e($t['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payments in period -->
<div class="section">
    <div class="section-header"><div class="section-title">Payments Collected (<?= count($payments) ?>)</div></div>
    <div class="section-body">
        <?php if (empty($payments)): ?>
            <p class="text-muted text-sm">No payments collected in this period.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Treatment</th>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr class="table-clickable" onclick="window.location='patient.php?id=<?= $p['patient_id'] ?>'">
                        <td class="nowrap text-sm"><?= format_date($p['payment_date']) ?></td>
                        <td class="font-semibold text-sm"><?= e($p['patient_name']) ?></td>
                        <td class="text-sm text-muted"><?= e($p['treatment_name'] ?? '—') ?></td>
                        <td class="text-sm"><?= e($p['payment_method'] ?? '—') ?></td>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
