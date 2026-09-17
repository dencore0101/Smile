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
                        <th></th>
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
                        <td onclick="event.stopPropagation()">
                            <?php $wa = normalize_whatsapp_number($p['mobile']); ?>
                            <?php if ($wa): ?>
                                <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline" title="WhatsApp" style="padding:4px 8px; line-height:1;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
