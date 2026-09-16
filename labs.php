<?php
require_once __DIR__ . '/includes/header.php';

require_owner();

$db = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect('labs.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            set_flash('error', 'Lab name is required.');
        } else {
            $stmt = $db->prepare("INSERT INTO labs (name, contact, notes) VALUES (?, ?, ?)");
            $stmt->execute([$name, $contact, $notes]);
            set_flash('success', 'Lab added.');
        }
        redirect('labs.php');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($name === '') {
            set_flash('error', 'Lab name is required.');
        } else {
            $stmt = $db->prepare("UPDATE labs SET name=?, contact=?, notes=? WHERE id=?");
            $stmt->execute([$name, $contact, $notes, $id]);
            set_flash('success', 'Lab updated.');
        }
        redirect('labs.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Check if lab is used in expenses
        $stmt = $db->prepare("SELECT COUNT(*) FROM expenses WHERE lab_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            set_flash('error', 'Cannot delete: this lab is linked to existing expenses.');
        } else {
            $stmt = $db->prepare("DELETE FROM labs WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Lab deleted.');
        }
        redirect('labs.php');
    }
}

$labs = get_labs();
?>
<h1>Labs</h1>
<div class="subtitle">Manage dental laboratory master list</div>

<div class="card mb-4">
    <h3>Add New Lab</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Lab Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. ABC Dental Lab">
            </div>
            <div class="form-group">
                <label for="contact">Contact (optional)</label>
                <input type="text" id="contact" name="contact" placeholder="Phone, address...">
            </div>
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <input type="text" id="notes" name="notes" placeholder="Specialty, turnaround time...">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Lab</button>
        </div>
    </form>
</div>

<?php if (empty($labs)): ?>
    <div class="empty-state"><p>No labs added yet.</p></div>
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
                <?php foreach ($labs as $lab): ?>
                <tr>
                    <td class="font-semibold"><?= e($lab['name']) ?></td>
                    <td class="text-sm"><?= e($lab['contact'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= e($lab['notes'] ?? '—') ?></td>
                    <td class="text-right nowrap">
                        <button type="button" class="btn btn-sm btn-outline" onclick="openModal('editLab<?= $lab['id'] ?>')">Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this lab?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $lab['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($labs as $lab): ?>
<div class="modal-overlay" id="editLab<?= $lab['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Lab</div>
            <button class="modal-close" onclick="closeModal('editLab<?= $lab['id'] ?>')">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $lab['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Lab Name</label>
                    <input type="text" name="name" value="<?= e($lab['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" value="<?= e($lab['contact']) ?>">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" value="<?= e($lab['notes']) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editLab<?= $lab['id'] ?>')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
