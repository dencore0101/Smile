<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? ($search !== '' ? 'all' : 'active');
if (!in_array($statusFilter, ['active', 'completed', 'all'], true)) {
    $statusFilter = 'active';
}

$sortOptions = [
    'recent' => ['Recently updated', 'p.last_activity_at DESC'],
    'added' => ['Recently added', 'p.created_at DESC'],
    'oldest' => ['Oldest first', 'p.created_at ASC'],
    'name' => ['Name A–Z', 'p.name COLLATE NOCASE ASC'],
    'followup' => ['Upcoming follow-up', 'next_followup IS NULL, next_followup ASC, p.name COLLATE NOCASE ASC'],
];
$sortKey = $_GET['sort'] ?? 'recent';
if (!isset($sortOptions[$sortKey])) {
    $sortKey = 'recent';
}
$viewMode = ($_GET['view'] ?? 'list') === 'table' ? 'table' : 'list';

$where = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter === 'completed' ? 'Completed' : 'Active';
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.mobile LIKE ? OR p.patient_id LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT p.*,
    (SELECT t.name FROM treatments t WHERE t.patient_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS last_treatment,
    (SELECT COUNT(*) FROM treatments t WHERE t.patient_id = p.id AND t.status <> 'Closed') AS open_treatments,
    (
        (SELECT COALESCE(SUM(t2.total_cost), 0) FROM treatments t2 WHERE t2.patient_id = p.id)
        -
        (SELECT COALESCE(SUM(pay.amount), 0)
         FROM payments pay
         JOIN treatments t3 ON t3.id = pay.treatment_id
         WHERE t3.patient_id = p.id AND pay.deleted_at IS NULL)
    ) AS balance,
    (SELECT COUNT(*) FROM followups f WHERE f.patient_id = p.id AND f.status = 'Pending') AS pending_followups,
    (SELECT MIN(f.date) FROM followups f WHERE f.patient_id = p.id AND f.status = 'Pending' AND f.date >= date('now')) AS next_followup
    FROM patients p
    $whereSql
    ORDER BY {$sortOptions[$sortKey][1]}");
$stmt->execute($params);
$patients = $stmt->fetchAll();

$groups = ['Active' => [], 'Completed' => []];
foreach ($patients as $p) {
    $groups[$p['status']][] = $p;
}

$listBack = 'patients.php?' . http_build_query(['q' => $search, 'status' => $statusFilter, 'sort' => $sortKey, 'view' => $viewMode]);
?>
<h1>Patients</h1>

<div class="quick-actions">
    <a href="patient_new.php" class="btn btn-primary">+ New Patient</a>
</div>

<form method="get" action="patients.php" class="patient-toolbar">
    <div class="search-box">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, mobile, or patient ID..." autofocus>
    </div>
    <select name="status" aria-label="Filter by status" onchange="this.form.submit()">
        <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'all' => 'All patients'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort" aria-label="Sort patients" onchange="this.form.submit()">
        <?php foreach ($sortOptions as $val => $opt): ?>
        <option value="<?= $val ?>" <?= $sortKey === $val ? 'selected' : '' ?>><?= e($opt[0]) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-outline btn-sm">Apply</button></noscript>
</form>

<div class="view-toggle">
    <a href="?<?= e(http_build_query(['q' => $search, 'status' => $statusFilter, 'sort' => $sortKey, 'view' => 'list'])) ?>" class="tab <?= $viewMode === 'list' ? 'active' : '' ?>">List</a>
    <a href="?<?= e(http_build_query(['q' => $search, 'status' => $statusFilter, 'sort' => $sortKey, 'view' => 'table'])) ?>" class="tab <?= $viewMode === 'table' ? 'active' : '' ?>">Table</a>
</div>

<?php if ($viewMode === 'table' && !empty($patients)): ?>
<div class="table-wrap">
    <table class="table-clickable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Patient ID</th>
                <th>Mobile</th>
                <th>Last treatment</th>
                <th class="text-right">Open treatments</th>
                <th class="text-right">Balance</th>
                <th>Next follow-up</th>
                <th>Last activity</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $p): ?>
            <tr onclick="window.location='patient.php?id=<?= (int)$p['id'] ?>'">
                <td><?= e($p['name']) ?></td>
                <td class="font-mono text-sm"><?= e($p['patient_id']) ?></td>
                <td class="text-sm"><?= e($p['mobile']) ?></td>
                <td class="text-sm"><?= e($p['last_treatment'] ?? '—') ?></td>
                <td class="text-right"><?= (int)$p['open_treatments'] ?></td>
                <td class="text-right <?= $p['balance'] > 0 ? 'text-danger' : '' ?>"><?= format_money($p['balance']) ?></td>
                <td class="nowrap"><?= $p['next_followup'] ? e(format_date($p['next_followup'])) : '—' ?></td>
                <td class="nowrap text-sm"><?= $p['last_activity_at'] ? e(format_date(substr($p['last_activity_at'], 0, 10))) : '—' ?></td>
                <td><?= e($p['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($viewMode === 'list'): ?>

<?php if (empty($patients)): ?>
    <div class="empty-state">
        <p><?= $search !== '' ? 'No patients match your search.' : ($statusFilter === 'completed' ? 'No completed patients.' : 'No active patients. Click "+ New Patient" to add one.') ?></p>
    </div>
<?php endif; ?>

<?php foreach ($groups as $groupName => $rows): ?>
    <?php if (empty($rows)) continue; ?>
    <section class="task-section">
        <div class="task-section-title"><?= strtoupper($groupName) ?> <span class="task-count"><?= count($rows) ?></span></div>
        <div class="task-list">
            <?php foreach ($rows as $p):
                $isCompleted = $p['status'] === 'Completed';
                $wa = normalize_whatsapp_number($p['mobile']);
                $summary = ['open_treatments' => (int)$p['open_treatments'], 'balance' => (float)$p['balance']];
            ?>
            <div class="task-row<?= $isCompleted ? ' is-completed' : '' ?>">
                <?php if ($isCompleted): ?>
                    <form method="post" action="patient_status.php" class="task-action">
                        <?= csrf_field() ?>
                        <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="reopen">
                        <input type="hidden" name="back" value="<?= e($listBack) ?>">
                        <button type="submit" class="btn btn-outline btn-sm">Reopen</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="patient_status.php" class="task-action"
                          onsubmit="return confirm(this.dataset.confirm)"
                          data-confirm="<?= e(patient_complete_message($p, $summary)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="back" value="<?= e($listBack) ?>">
                        <button type="submit" class="task-check" title="Mark as completed" aria-label="Mark <?= e($p['name']) ?> as completed"></button>
                    </form>
                <?php endif; ?>

                <a class="task-main" href="patient.php?id=<?= $p['id'] ?>">
                    <div class="task-title">
                        <span><?= e($p['name']) ?></span>
                        <span class="task-code font-mono"><?= e($p['patient_id']) ?></span>
                        <?php if ((int)$p['pending_followups'] > 0): ?>
                            <span class="task-badge">Follow-up pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="task-sub">
                        <?= e($p['mobile']) ?>
                        &middot; <?= e($p['last_treatment'] ?? 'No treatment yet') ?>
                        &middot; Last activity <?= $p['last_activity_at'] ? e(format_date(substr($p['last_activity_at'], 0, 10))) : '—' ?>
                        <?php if ($p['next_followup']): ?>
                            &middot; Next follow-up <?= e(format_date($p['next_followup'])) ?>
                        <?php endif; ?>
                    </div>
                </a>

                <div class="task-balance <?= $p['balance'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_money($p['balance']) ?></div>

                <details class="task-menu">
                    <summary>More</summary>
                    <div class="task-menu-panel">
                        <a href="patient.php?id=<?= $p['id'] ?>">Open profile</a>
                        <a href="followup_new.php?patient_id=<?= $p['id'] ?>">Add follow-up</a>
                        <a href="treatment_new.php?patient_id=<?= $p['id'] ?>">New treatment</a>
                        <?php if ($wa): ?>
                            <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener">WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
