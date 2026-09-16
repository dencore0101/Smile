<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$db = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('consultants.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            set_flash('error', 'Consultant name is required.');
        } else {
            $stmt = $db->prepare("INSERT INTO consultants (name, contact, notes) VALUES (?, ?, ?)");
            $stmt->execute([$name, $contact, $notes]);
            set_flash('success', 'Consultant added.');
        }
        redirect('consultants.php');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            set_flash('error', 'Consultant name is required.');
        } else {
            $stmt = $db->prepare("UPDATE consultants SET name=?, contact=?, notes=? WHERE id=?");
            $stmt->execute([$name, $contact, $notes, $id]);
            set_flash('success', 'Consultant updated.');
        }
        redirect('consultants.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT COUNT(*) FROM expenses WHERE consultant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            set_flash('error', 'Cannot delete: this consultant is linked to existing expenses.');
        } else {
            $stmt = $db->prepare("DELETE FROM consultants WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Consultant deleted.');
        }
        redirect('consultants.php');
    }
}

$consultants = get_consultants();
?>
<h1>Consultants</h1>
<div class="subtitle">Manage consultant master list</div>

<div class="card mb-4">
    <h3>Add New Consultant</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Consultant Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Dr XYZ">
            </div>
            <div class="form-group">
                <label for="contact">Contact (optional)</label>
                <input type="text" id="contact" name="contact" placeholder="Phone, specialty...">
            </div>
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <input type="text" id="notes" name="notes" placeholder="Specialty, referral notes...">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Consultant</button>
        </div>
    </form>
</div>

<?php if (empty($consultants)): ?>
    <div class="empty-state"><p>No consultants added yet.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultants as $con): ?>
                <tr>
                    <td class="font-semibold"><?= e($con['name']) ?></td>
                    <td class="text-sm"><?= e($con['contact'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= e($con['notes'] ?? '—') ?></td>
                    <td class="text-right nowrap">
                        <button type="button" class="btn btn-sm btn-outline" onclick="openModal('editCon<?= $con['id'] ?>')">Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this consultant?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $con['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($consultants as $con): ?>
<div class="modal-overlay" id="editCon<?= $con['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Consultant</div>
            <button class="modal-close" onclick="closeModal('editCon<?= $con['id'] ?>')">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $con['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Consultant Name</label>
                    <input type="text" name="name" value="<?= e($con['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" value="<?= e($con['contact']) ?>">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" value="<?= e($con['notes']) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCon<?= $con['id'] ?>')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
