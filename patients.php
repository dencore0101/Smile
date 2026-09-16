<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['q'] ?? '');
$db = db();

if ($search !== '') {
    $stmt = $db->prepare("SELECT p.*,
                          (SELECT t.name FROM treatments t WHERE t.patient_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS last_treatment,
                          (
                            (SELECT COALESCE(SUM(t2.total_cost), 0) FROM treatments t2 WHERE t2.patient_id = p.id)
                            -
                            (SELECT COALESCE(SUM(pay.amount), 0)
                             FROM payments pay
                             JOIN treatments t2 ON t2.id = pay.treatment_id
                             WHERE t2.patient_id = p.id AND pay.deleted_at IS NULL)
                          ) AS balance
                          FROM patients p
                          WHERE p.name LIKE ? OR p.mobile LIKE ? OR p.patient_id LIKE ?
                          ORDER BY p.name ASC LIMIT 50");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
    $patients = $stmt->fetchAll();
} else {
    $stmt = $db->query("SELECT p.*,
                        (SELECT t.name FROM treatments t WHERE t.patient_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS last_treatment,
                        (
                          (SELECT COALESCE(SUM(t2.total_cost), 0) FROM treatments t2 WHERE t2.patient_id = p.id)
                          -
                          (SELECT COALESCE(SUM(pay.amount), 0)
                           FROM payments pay
                           JOIN treatments t2 ON t2.id = pay.treatment_id
                           WHERE t2.patient_id = p.id AND pay.deleted_at IS NULL)
                        ) AS balance
                        FROM patients p
                        ORDER BY p.name ASC LIMIT 100");
    $patients = $stmt->fetchAll();
}
?>
<h1>Patients</h1>
<div class="subtitle"><?= $search ? 'Search results' : 'All patients' ?></div>

<div class="quick-actions">
    <a href="patient_new.php" class="btn btn-primary">+ New Patient</a>
</div>

<div class="search-box">
    <form method="get" action="">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, mobile, or patient ID..." autofocus>
    </form>
</div>

<?php if (empty($patients)): ?>
    <div class="empty-state">
        <p><?= $search ? 'No patients found.' : 'No patients yet. Click "+ New Patient" to add one.' ?></p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table-clickable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Patient ID</th>
                        <th>Mobile</th>
                        <th>Last Treatment</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr onclick="window.location='patient.php?id=<?= $p['id'] ?>'">
                        <td class="font-semibold"><?= e($p['name']) ?></td>
                        <td class="font-mono text-sm"><?= e($p['patient_id']) ?></td>
                        <td><?= e($p['mobile']) ?></td>
                        <td class="text-sm text-muted"><?= e($p['last_treatment'] ?? '—') ?></td>
                        <td class="text-right <?= $p['balance'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_money($p['balance']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
