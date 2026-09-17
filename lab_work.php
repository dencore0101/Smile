<?php
require_once __DIR__ . '/includes/header.php';

$view = $_GET['view'] ?? 'today';
$today = today_date();
$db = db();

switch ($view) {
    case 'pending':
        $stmt = $db->prepare("SELECT lw.*, p.name AS patient_name, p.patient_id AS patient_code,
                              t.name AS treatment_name, l.name AS lab_name
                              FROM lab_work lw
                              JOIN patients p ON lw.patient_id = p.id
                              LEFT JOIN treatments t ON lw.treatment_id = t.id
                              JOIN labs l ON lw.lab_id = l.id
                              WHERE lw.status = 'Pending' AND lw.expected_delivery_date >= ?
                              ORDER BY lw.expected_delivery_date ASC");
        $stmt->execute([$today]);
        $title = 'Pending Lab Work';
        break;
    case 'overdue':
        $stmt = $db->prepare("SELECT lw.*, p.name AS patient_name, p.patient_id AS patient_code,
                              t.name AS treatment_name, l.name AS lab_name
                              FROM lab_work lw
                              JOIN patients p ON lw.patient_id = p.id
                              LEFT JOIN treatments t ON lw.treatment_id = t.id
                              JOIN labs l ON lw.lab_id = l.id
                              WHERE lw.status = 'Pending' AND lw.expected_delivery_date < ?
                              ORDER BY lw.expected_delivery_date ASC");
        $stmt->execute([$today]);
        $title = 'Overdue Lab Work';
        break;
    case 'arrived':
        $stmt = $db->query("SELECT lw.*, p.name AS patient_name, p.patient_id AS patient_code,
                           t.name AS treatment_name, l.name AS lab_name
                           FROM lab_work lw
                           JOIN patients p ON lw.patient_id = p.id
                           LEFT JOIN treatments t ON lw.treatment_id = t.id
                           JOIN labs l ON lw.lab_id = l.id
                           WHERE lw.status = 'Arrived'
                           ORDER BY lw.actual_arrival_date DESC");
        $title = 'Arrived Lab Work';
        break;
    case 'all':
        $stmt = $db->query("SELECT lw.*, p.name AS patient_name, p.patient_id AS patient_code,
                           t.name AS treatment_name, l.name AS lab_name
                           FROM lab_work lw
                           JOIN patients p ON lw.patient_id = p.id
                           LEFT JOIN treatments t ON lw.treatment_id = t.id
                           JOIN labs l ON lw.lab_id = l.id
                           ORDER BY lw.date_sent DESC");
        $title = 'All Lab Work';
        break;
    default:
        $stmt = $db->prepare("SELECT lw.*, p.name AS patient_name, p.patient_id AS patient_code,
                              t.name AS treatment_name, l.name AS lab_name
                              FROM lab_work lw
                              JOIN patients p ON lw.patient_id = p.id
                              LEFT JOIN treatments t ON lw.treatment_id = t.id
                              JOIN labs l ON lw.lab_id = l.id
                              WHERE lw.date_sent = ? OR lw.expected_delivery_date = ?
                              ORDER BY lw.status ASC, lw.expected_delivery_date ASC");
        $stmt->execute([$today, $today]);
        $title = "Today's Lab Work";
        break;
}
$items = $stmt->fetchAll();
?>
<h1>Lab Work</h1>
<div class="subtitle"><?= e($title) ?></div>

<div class="quick-actions">
    <a href="lab_work_new.php" class="btn btn-primary">+ Add Lab Work</a>
</div>

<div class="tabs">
    <a href="?view=today" class="tab <?= $view === 'today' ? 'active' : '' ?>">Today</a>
    <a href="?view=pending" class="tab <?= $view === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?view=overdue" class="tab <?= $view === 'overdue' ? 'active' : '' ?>">Overdue</a>
    <a href="?view=arrived" class="tab <?= $view === 'arrived' ? 'active' : '' ?>">Arrived</a>
    <a href="?view=all" class="tab <?= $view === 'all' ? 'active' : '' ?>">All</a>
</div>

<?php if (empty($items)): ?>
    <div class="empty-state"><p>No lab work entries found.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table class="table-clickable">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Lab</th>
                    <th>Work</th>
                    <th>Sent</th>
                    <th>Expected</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $lw):
                    $isOverdue = $lw['status'] === 'Pending'
                        && $lw['expected_delivery_date']
                        && $lw['expected_delivery_date'] < $today;
                ?>
                <tr onclick="window.location='patient.php?id=<?= $lw['patient_id'] ?>'">
                    <td class="font-semibold"><?= e($lw['patient_name']) ?></td>
                    <td class="text-sm text-muted"><?= e($lw['treatment_name'] ?? '—') ?></td>
                    <td class="text-sm"><?= e($lw['lab_name']) ?></td>
                    <td class="text-sm"><?= e($lw['description']) ?></td>
                    <td class="nowrap text-sm"><?= format_date($lw['date_sent']) ?></td>
                    <td class="nowrap text-sm <?= $isOverdue ? 'text-danger font-bold' : '' ?>"><?= e($lw['expected_delivery_date'] ? format_date($lw['expected_delivery_date']) : '—') ?></td>
                    <td>
                        <?php if ($isOverdue): ?>
                            <span class="badge badge-overdue">Overdue</span>
                        <?php else: ?>
                            <span class="badge badge-<?= strtolower($lw['status']) === 'pending' ? 'pending' : (strtolower($lw['status']) === 'arrived' ? 'active' : 'overdue') ?>"><?= e($lw['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td onclick="event.stopPropagation()"><a href="lab_work_edit.php?id=<?= $lw['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
