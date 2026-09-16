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

$catFilter = (int)($_GET['category'] ?? 0);
$search = trim($_GET['q'] ?? '');

$sql = "SELECT e.*, ec.name AS category_name, p.name AS patient_name, t.name AS treatment_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN patients p ON e.patient_id = p.id
        LEFT JOIN treatments t ON e.treatment_id = t.id
        WHERE e.deleted_at IS NULL
        AND e.expense_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($catFilter) {
    $sql .= " AND e.category_id = ?";
    $params[] = $catFilter;
}
if ($search) {
    $sql .= " AND (e.name_details LIKE ? OR e.description LIKE ? OR p.name LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Category totals
$catSql = "SELECT ec.name, COALESCE(SUM(e.amount), 0) AS total
           FROM expenses e
           JOIN expense_categories ec ON e.category_id = ec.id
           WHERE e.deleted_at IS NULL AND e.expense_date BETWEEN ? AND ?";
$catParams = [$startDate, $endDate];
if ($catFilter) { $catSql .= " AND e.category_id = ?"; $catParams[] = $catFilter; }
$catSql .= " GROUP BY ec.name ORDER BY total DESC";
$stmt = $db->prepare($catSql);
$stmt->execute($catParams);
$catTotals = $stmt->fetchAll();

$totalExpenses = array_sum(array_column($expenses, 'amount'));
$categories = get_expense_categories();

if (isset($_GET['export'])) {
    $rows = [];
    foreach ($expenses as $e) {
        $rows[] = [
            $e['expense_date'], $e['category_name'], $e['name_details'],
            $e['patient_name'] ?? '', $e['treatment_name'] ?? '',
            $e['amount'], $e['description'], $e['notes'],
        ];
    }
    csv_output("expenses_$startDate-$endDate.csv", $rows,
        ['Date', 'Category', 'Name/Details', 'Patient', 'Treatment', 'Amount', 'Description', 'Notes']);
}
?>
<h1>Expenses</h1>
<div class="subtitle"><?= e(date_range_label($preset === 'custom' ? 'custom' : $preset, $startDate, $endDate)) ?></div>

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

<div class="filter-bar">
    <form method="get" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="preset" value="<?= e($preset) ?>">
        <?php if ($preset === 'custom'): ?>
            <input type="hidden" name="start" value="<?= e($startDate) ?>">
            <input type="hidden" name="end" value="<?= e($endDate) ?>">
        <?php endif; ?>
        <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..." style="width:150px">
        <button type="submit" class="btn btn-sm btn-outline">Filter</button>
    </form>
    <a href="?preset=<?= e($preset) ?><?php if($preset==='custom'){echo '&start='.e($start).'&end='.e($end);} ?>&export=1" class="btn btn-sm btn-outline">Export CSV</a>
    <a href="expense_new.php" class="btn btn-sm btn-primary">+ Add Expense</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value negative"><?= format_money($totalExpenses) ?></div>
    </div>
    <?php foreach ($catTotals as $ct): ?>
    <div class="stat-card">
        <div class="stat-label"><?= e($ct['name']) ?></div>
        <div class="stat-value"><?= format_money($ct['total']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Name/Details</th>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th class="text-right">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                <tr><td colspan="7" class="text-center text-muted">No expenses found.</td></tr>
                <?php else: foreach ($expenses as $e): ?>
                <tr>
                    <td class="nowrap text-sm"><?= format_date($e['expense_date']) ?></td>
                    <td><span class="badge badge-active"><?= e($e['category_name']) ?></span></td>
                    <td class="text-sm"><?= e($e['name_details'] ?? '') ?></td>
                    <td class="text-sm"><?= e($e['patient_name'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= e($e['treatment_name'] ?? '—') ?></td>
                    <td class="text-right font-bold"><?= format_money($e['amount']) ?></td>
                    <td class="text-right nowrap">
                        <a href="expense_edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
