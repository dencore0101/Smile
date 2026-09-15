<?php
require_once __DIR__ . '/includes/header.php';

$view = $_GET['view'] ?? 'today';
$today = today_date();
$db = db();

switch ($view) {
    case 'upcoming':
        $stmt = $db->prepare("SELECT f.*, p.name AS patient_name, p.patient_id AS patient_code, t.name AS treatment_name
                              FROM followups f
                              JOIN patients p ON f.patient_id = p.id
                              LEFT JOIN treatments t ON f.treatment_id = t.id
                              WHERE f.date > ? AND f.status = 'Pending'
                              ORDER BY f.date ASC, f.time ASC");
        $stmt->execute([$today]);
        $title = 'Upcoming Follow-ups';
        break;
    case 'overdue':
        $stmt = $db->prepare("SELECT f.*, p.name AS patient_name, p.patient_id AS patient_code, t.name AS treatment_name
                              FROM followups f
                              JOIN patients p ON f.patient_id = p.id
                              LEFT JOIN treatments t ON f.treatment_id = t.id
                              WHERE f.date < ? AND f.status = 'Pending'
                              ORDER BY f.date DESC, f.time DESC");
        $stmt->execute([$today]);
        $title = 'Overdue Follow-ups';
        break;
    case 'all':
        $stmt = $db->query("SELECT f.*, p.name AS patient_name, p.patient_id AS patient_code, t.name AS treatment_name
                            FROM followups f
                            JOIN patients p ON f.patient_id = p.id
                            LEFT JOIN treatments t ON f.treatment_id = t.id
                            ORDER BY f.date DESC, f.time DESC");
        $title = 'All Follow-ups';
        break;
    default:
        $stmt = $db->prepare("SELECT f.*, p.name AS patient_name, p.patient_id AS patient_code, t.name AS treatment_name
                              FROM followups f
                              JOIN patients p ON f.patient_id = p.id
                              LEFT JOIN treatments t ON f.treatment_id = t.id
                              WHERE f.date = ? AND f.status NOT IN ('Cancelled', 'Completed', 'No-show')
                              ORDER BY f.time ASC, p.name ASC");
        $stmt->execute([$today]);
        $title = "Today's Follow-ups";
        break;
}
$followups = $stmt->fetchAll();
?>
<h1>Follow-ups</h1>
<div class="subtitle"><?= e($title) ?></div>

<div class="quick-actions">
    <a href="followup_new.php" class="btn btn-primary">+ Add Follow-up</a>
</div>

<div class="tabs">
    <a href="?view=today" class="tab <?= $view === 'today' ? 'active' : '' ?>">Today</a>
    <a href="?view=upcoming" class="tab <?= $view === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
    <a href="?view=overdue" class="tab <?= $view === 'overdue' ? 'active' : '' ?>">Overdue</a>
    <a href="?view=all" class="tab <?= $view === 'all' ? 'active' : '' ?>">All</a>
</div>

<?php if (empty($followups)): ?>
    <div class="empty-state"><p>No follow-ups found.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table class="table-clickable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($followups as $f):
                    $isOverdue = $f['date'] < $today && $f['status'] === 'Pending';
                ?>
                <tr onclick="window.location='patient.php?id=<?= $f['patient_id'] ?>'">
                    <td class="nowrap <?= $isOverdue ? 'text-danger font-bold' : '' ?>"><?= format_date($f['date']) ?></td>
                    <td class="text-sm"><?= e($f['time'] ? date('g:i A', strtotime($f['time'])) : '—') ?></td>
                    <td class="font-semibold"><?= e($f['patient_name']) ?></td>
                    <td class="text-sm text-muted"><?= e($f['treatment_name'] ?? '—') ?></td>
                    <td class="text-sm"><?= e($f['reason'] ?? '') ?></td>
                    <td>
                        <?php if ($isOverdue): ?>
                            <span class="badge badge-overdue">Overdue</span>
                        <?php else: ?>
                            <span class="badge badge-<?= strtolower($f['status']) ?>"><?= e($f['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td onclick="event.stopPropagation()"><a href="followup_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
