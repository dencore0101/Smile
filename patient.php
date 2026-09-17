<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$patient = get_patient($id);
if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('patients.php');
}

$db = db();
$treatments = get_treatments_for_patient($id);
$photos = get_photos_for_patient($id);
$followups = get_followups_for_patient($id);

$totalCost = patient_total_cost($id);
$totalPaid = patient_total_paid($id);
$balance = $totalCost - $totalPaid;

$isOwner = is_owner();

// Only fetch expense data for owner
if ($isOwner) {
    $patientExpenses = get_expenses_for_patient($id);
    $totalExpenses = patient_total_expenses($id);
} else {
    $patientExpenses = [];
    $totalExpenses = 0;
}

// Handle inline edit of basic info / free notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session. Please try again.');
        redirect("patient.php?id=$id");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'edit_basic') {
        $stmt = $db->prepare("UPDATE patients SET name=?, mobile=?, age_or_dob=?, gender=?, basic_details=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([
            trim($_POST['name']), trim($_POST['mobile']), trim($_POST['age_or_dob']),
            trim($_POST['gender']), trim($_POST['basic_details']), $id
        ]);
        set_flash('success', 'Patient details updated.');
        redirect("patient.php?id=$id");
    }

    if ($action === 'edit_notes') {
        $stmt = $db->prepare("UPDATE patients SET free_notes=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([trim($_POST['free_notes']), $id]);
        set_flash('success', 'Notes updated.');
        redirect("patient.php?id=$id");
    }
}
?>
<div class="patient-header">
    <div class="patient-name"><?= e($patient['name']) ?></div>
    <div class="patient-meta">
        <span class="font-mono"><?= e($patient['patient_id']) ?></span>
        <span><?= e($patient['mobile']) ?></span>
        <?php $wa = normalize_whatsapp_number($patient['mobile']); ?>
        <?php if ($wa): ?>
            <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline" title="WhatsApp" style="padding:2px 6px; line-height:1; vertical-align:middle;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        <?php endif; ?>
        <?php if ($patient['age_or_dob']): ?><span><?= e($patient['age_or_dob']) ?></span><?php endif; ?>
        <?php if ($patient['gender']): ?><span><?= e($patient['gender']) ?></span><?php endif; ?>
    </div>
    <div class="mt-2 flex gap-2 flex-wrap">
        <a href="treatment_new.php?patient_id=<?= $id ?>" class="btn btn-primary btn-sm">+ New Treatment</a>
        <a href="photo_upload.php?patient_id=<?= $id ?>" class="btn btn-outline btn-sm">+ Add Photo</a>
        <a href="followup_new.php?patient_id=<?= $id ?>" class="btn btn-outline btn-sm">+ Add Follow-up</a>
        <?php if ($isOwner): ?>
        <a href="expense_new.php?patient_id=<?= $id ?>" class="btn btn-outline btn-sm">+ Add Expense</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="openModal('editBasicModal')">Edit Details</button>
    </div>
</div>

<!-- Money summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Treatment Cost</div>
        <div class="stat-value"><?= format_money($totalCost) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Paid</div>
        <div class="stat-value positive"><?= format_money($totalPaid) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Balance</div>
        <div class="stat-value <?= $balance > 0 ? 'negative' : 'positive' ?>"><?= format_money($balance) ?></div>
    </div>
    <?php if ($isOwner): ?>
    <div class="stat-card">
        <div class="stat-label">Patient Expenses</div>
        <div class="stat-value negative"><?= format_money($totalExpenses) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Basic Details -->
<div class="section">
    <div class="section-header">
        <div class="section-title">Basic Details</div>
        <button type="button" class="btn btn-sm btn-outline" onclick="openModal('editBasicModal')">Edit</button>
    </div>
    <div class="section-body">
        <?php if ($patient['basic_details']): ?>
            <p class="text-sm"><?= nl2br(e($patient['basic_details'])) ?></p>
        <?php else: ?>
            <p class="text-muted text-sm">No basic details recorded.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Free Notes -->
<div class="section">
    <div class="section-header">
        <div class="section-title">Free Notes</div>
        <button type="button" class="btn btn-sm btn-outline" onclick="openModal('editNotesModal')">Edit</button>
    </div>
    <div class="section-body">
        <?php if ($patient['free_notes']): ?>
            <p class="text-sm"><?= nl2br(e($patient['free_notes'])) ?></p>
        <?php else: ?>
            <p class="text-muted text-sm">No notes yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Photos -->
<div class="section">
    <div class="section-header">
        <div class="section-title">Photos (<?= count($photos) ?>)</div>
        <a href="photo_upload.php?patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add Photo</a>
    </div>
    <div class="section-body">
        <?php if (empty($photos)): ?>
            <p class="text-muted text-sm">No photos uploaded.</p>
        <?php else: ?>
            <div class="photo-grid">
                <?php foreach ($photos as $photo): ?>
                <div class="photo-item">
                    <img src="photo_view.php?id=<?= $photo['id'] ?>" alt="Patient photo" onclick="window.open('photo_view.php?id=<?= $photo['id'] ?>&full=1', '_blank')">
                    <div class="photo-info">
                        <div class="photo-note"><?= e($photo['note'] ?? '') ?></div>
                        <div class="photo-date"><?= format_date($photo['uploaded_at']) ?></div>
                    </div>
                    <div class="photo-actions">
                        <a href="photo_edit.php?id=<?= $photo['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <form method="post" action="photo_delete.php" style="display:inline" onsubmit="return confirm('Delete this photo?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Treatments -->
<div class="section">
    <div class="section-header">
        <div class="section-title">Treatments (<?= count($treatments) ?>)</div>
        <a href="treatment_new.php?patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ New Treatment</a>
    </div>
    <div class="section-body">
        <?php if (empty($treatments)): ?>
            <p class="text-muted text-sm">No treatments yet.</p>
        <?php else: ?>
            <?php foreach ($treatments as $t):
                $paid = treatment_paid($t['id']);
                $bal = $t['total_cost'] - $paid;
                $tPhotos = get_photos_for_treatment($t['id']);
                $tPayments = get_payments_for_treatment($t['id']);
                $tFollowups = $db->query("SELECT * FROM followups WHERE treatment_id = {$t['id']} ORDER BY date DESC")->fetchAll();
                // Only fetch expense data for owner
                if ($isOwner) {
                    $exp = treatment_expenses($t['id']);
                    $margin = $t['total_cost'] - $exp;
                    $tExpenses = get_expenses_for_treatment($t['id']);
                } else {
                    $exp = 0;
                    $margin = 0;
                    $tExpenses = [];
                }
            ?>
            <div class="treatment-card">
                <div class="treatment-card-header" onclick="this.parentElement.querySelector('.treatment-card-body').classList.toggle('hidden')">
                    <div>
                        <span class="treatment-card-title"><?= e($t['name']) ?></span>
                        <?php if ($t['tooth_area']): ?><span class="text-muted text-sm"> — <?= e($t['tooth_area']) ?></span><?php endif; ?>
                        <span class="badge badge-<?= strtolower($t['status']) ?> ml-2"><?= e($t['status']) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm text-muted">Cost: </span>
                        <span class="font-bold"><?= format_money($t['total_cost']) ?></span>
                        <span class="text-sm text-muted"> | Paid: </span>
                        <span class="font-bold text-success"><?= format_money($paid) ?></span>
                        <span class="text-sm text-muted"> | Bal: </span>
                        <span class="font-bold <?= $bal > 0 ? 'text-danger' : 'text-muted' ?>"><?= format_money($bal) ?></span>
                    </div>
                </div>
                <div class="treatment-card-body">
                    <!-- Treatment meta -->
                    <div class="flex-wrap gap-4 mb-2 text-sm">
                        <?php if ($t['start_date']): ?><span><strong>Start:</strong> <?= format_date($t['start_date']) ?></span><?php endif; ?>
                        <?php if ($t['closed_at']): ?><span><strong>Closed:</strong> <?= format_date($t['closed_at']) ?></span><?php endif; ?>
                    </div>

                    <div class="flex gap-2 flex-wrap mb-4">
                        <a href="treatment_edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline">Edit Treatment</a>
                        <a href="payment_new.php?treatment_id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">+ Add Payment</a>
                        <?php if ($isOwner): ?>
                        <a href="expense_new.php?treatment_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline">+ Add Expense</a>
                        <?php endif; ?>
                        <a href="photo_upload.php?treatment_id=<?= $t['id'] ?>&patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add Photo</a>
                        <a href="followup_new.php?treatment_id=<?= $t['id'] ?>&patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add Follow-up</a>
                        <?php if ($t['status'] === 'Active'): ?>
                        <a href="treatment_close.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline" onclick="return confirm('<?= $bal > 0 ? "There is an outstanding balance of " . format_money($bal) . ". " : "" ?>Close this treatment?')">Close Treatment</a>
                        <?php endif; ?>
                    </div>

                    <!-- Profit summary -->
                    <div class="money-summary">
                        <div class="money-item">
                            <div class="label">Total Cost</div>
                            <div class="value"><?= format_money($t['total_cost']) ?></div>
                        </div>
                        <div class="money-item">
                            <div class="label">Paid</div>
                            <div class="value text-success"><?= format_money($paid) ?></div>
                        </div>
                        <div class="money-item">
                            <div class="label">Balance</div>
                            <div class="value <?= $bal > 0 ? 'text-danger' : '' ?>"><?= format_money($bal) ?></div>
                        </div>
                        <?php if ($isOwner): ?>
                        <div class="money-item">
                            <div class="label">Expenses</div>
                            <div class="value text-danger"><?= format_money($exp) ?></div>
                        </div>
                        <div class="money-item">
                            <div class="label">Treatment Margin</div>
                            <div class="value"><?= format_money($margin) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payments -->
                    <h3>Payments (<?= count($tPayments) ?>)</h3>
                    <?php if (!empty($tPayments)): ?>
                    <div class="table-wrap mb-4">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th class="text-right">Amount</th>
                                    <th>Note</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tPayments as $p): ?>
                                <tr>
                                    <td class="nowrap"><?= format_date($p['payment_date']) ?></td>
                                    <td class="text-sm"><?= e($p['payment_method'] ?? '—') ?></td>
                                    <td class="text-right font-bold"><?= format_money($p['amount']) ?></td>
                                    <td class="text-sm text-muted"><?= e($p['note'] ?? '') ?></td>
                                    <td class="text-right nowrap">
                                        <a href="payment_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                        <form method="post" action="payment_delete.php" style="display:inline" onsubmit="return confirm('Delete this payment? This cannot be undone.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-sm mb-4">No payments yet.</p>
                    <?php endif; ?>

                    <?php if ($isOwner): ?>
                    <!-- Expenses (Owner only) -->
                    <h3>Expenses (<?= count($tExpenses) ?>)</h3>
                    <?php if (!empty($tExpenses)): ?>
                    <div class="table-wrap mb-4">
                        <table>
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Name/Details</th>
                                    <th>Date</th>
                                    <th class="text-right">Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tExpenses as $ex): ?>
                                <tr>
                                    <td><span class="badge badge-active"><?= e($ex['category_name']) ?></span></td>
                                    <td class="text-sm"><?= e($ex['name_details'] ?? '') ?></td>
                                    <td class="nowrap text-sm"><?= format_date($ex['expense_date']) ?></td>
                                    <td class="text-right font-bold"><?= format_money($ex['amount']) ?></td>
                                    <td class="text-right nowrap">
                                        <a href="expense_edit.php?id=<?= $ex['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                        <form method="post" action="expense_delete.php" style="display:inline" onsubmit="return confirm('Delete this expense?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-sm mb-4">No expenses for this treatment.</p>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Treatment notes -->
                    <h3>Treatment Notes</h3>
                    <div class="card mb-4">
                        <div class="flex-between mb-2">
                            <span class="text-sm text-muted">Ongoing notes</span>
                            <button class="btn btn-sm btn-outline" onclick="openModal('editTreatmentNotes<?= $t['id'] ?>')">Edit</button>
                        </div>
                        <p class="text-sm"><?= nl2br(e($t['treatment_notes'] ?? '')) ?: '<span class="text-muted">No notes yet.</span>' ?></p>
                    </div>

                    <h3>Final Notes</h3>
                    <div class="card mb-4">
                        <div class="flex-between mb-2">
                            <span class="text-sm text-muted">Final summary</span>
                            <button class="btn btn-sm btn-outline" onclick="openModal('editFinalNotes<?= $t['id'] ?>')">Edit</button>
                        </div>
                        <p class="text-sm"><?= nl2br(e($t['final_notes'] ?? '')) ?: '<span class="text-muted">No final notes yet.</span>' ?></p>
                    </div>

                    <!-- Treatment photos -->
                    <?php if (!empty($tPhotos)): ?>
                    <h3>Treatment Photos</h3>
                    <div class="photo-grid mb-4">
                        <?php foreach ($tPhotos as $photo): ?>
                        <div class="photo-item">
                            <img src="photo_view.php?id=<?= $photo['id'] ?>" alt="Treatment photo" onclick="window.open('photo_view.php?id=<?= $photo['id'] ?>&full=1', '_blank')">
                            <div class="photo-info">
                                <div class="photo-note"><?= e($photo['note'] ?? '') ?></div>
                                <div class="photo-date"><?= format_date($photo['uploaded_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Follow-ups for this treatment -->
                    <?php if (!empty($tFollowups)): ?>
                    <h3>Follow-ups</h3>
                    <div class="table-wrap mb-4">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tFollowups as $f): ?>
                                <tr>
                                    <td class="nowrap"><?= format_date($f['date']) ?></td>
                                    <td class="text-sm"><?= e($f['time'] ? date('g:i A', strtotime($f['time'])) : '—') ?></td>
                                    <td class="text-sm"><?= e($f['reason'] ?? '') ?></td>
                                    <td><span class="badge badge-<?= strtolower($f['status']) ?>"><?= e($f['status']) ?></span></td>
                                    <td><a href="followup_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modals for treatment notes -->
            <div class="modal-overlay" id="editTreatmentNotes<?= $t['id'] ?>">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title">Edit Treatment Notes</div>
                        <button class="modal-close" onclick="closeModal('editTreatmentNotes<?= $t['id'] ?>')">&times;</button>
                    </div>
                    <form method="post" action="treatment_edit.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_treatment_notes">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Treatment Notes</label>
                                <textarea name="treatment_notes" rows="6"><?= e($t['treatment_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('editTreatmentNotes<?= $t['id'] ?>')">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-overlay" id="editFinalNotes<?= $t['id'] ?>">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-title">Edit Final Notes</div>
                        <button class="modal-close" onclick="closeModal('editFinalNotes<?= $t['id'] ?>')">&times;</button>
                    </div>
                    <form method="post" action="treatment_edit.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_final_notes">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Final Notes</label>
                                <textarea name="final_notes" rows="6"><?= e($t['final_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('editFinalNotes<?= $t['id'] ?>')">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($isOwner): ?>
<!-- Patient-level Expenses (Owner only) -->
<div class="section">
    <div class="section-header">
        <div class="section-title">All Patient Expenses (<?= count($patientExpenses) ?>)</div>
        <a href="expense_new.php?patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add Expense</a>
    </div>
    <div class="section-body">
        <?php if (empty($patientExpenses)): ?>
            <p class="text-muted text-sm">No expenses recorded for this patient.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Name/Details</th>
                            <th>Treatment</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patientExpenses as $ex): ?>
                        <tr>
                            <td><span class="badge badge-active"><?= e($ex['category_name']) ?></span></td>
                            <td class="text-sm"><?= e($ex['name_details'] ?? '') ?></td>
                            <td class="text-sm text-muted"><?= e($ex['treatment_name'] ?? '—') ?></td>
                            <td class="nowrap text-sm"><?= format_date($ex['expense_date']) ?></td>
                            <td class="text-right font-bold"><?= format_money($ex['amount']) ?></td>
                            <td class="text-right nowrap">
                                <a href="expense_edit.php?id=<?= $ex['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                <form method="post" action="expense_delete.php" style="display:inline" onsubmit="return confirm('Delete this expense?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Follow-ups -->
<div class="section">
    <div class="section-header">
        <div class="section-title">Follow-ups (<?= count($followups) ?>)</div>
        <a href="followup_new.php?patient_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add Follow-up</a>
    </div>
    <div class="section-body">
        <?php if (empty($followups)): ?>
            <p class="text-muted text-sm">No follow-ups recorded.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Treatment</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups as $f): ?>
                        <tr>
                            <td class="nowrap"><?= format_date($f['date']) ?></td>
                            <td class="text-sm"><?= e($f['time'] ? date('g:i A', strtotime($f['time'])) : '—') ?></td>
                            <td class="text-sm text-muted"><?= e($f['treatment_name'] ?? '—') ?></td>
                            <td class="text-sm"><?= e($f['reason'] ?? '') ?></td>
                            <td><span class="badge badge-<?= strtolower($f['status']) ?>"><?= e($f['status']) ?></span></td>
                            <td><a href="followup_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Basic Modal -->
<div class="modal-overlay" id="editBasicModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Patient Details</div>
            <button class="modal-close" onclick="closeModal('editBasicModal')">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_basic">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= e($patient['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="tel" name="mobile" value="<?= e($patient['mobile']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Age / DOB</label>
                        <input type="text" name="age_or_dob" value="<?= e($patient['age_or_dob']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">—</option>
                            <option value="Male" <?= $patient['gender']==='Male'?'selected':'' ?>>Male</option>
                            <option value="Female" <?= $patient['gender']==='Female'?'selected':'' ?>>Female</option>
                            <option value="Other" <?= $patient['gender']==='Other'?'selected':'' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Basic Details</label>
                    <textarea name="basic_details" rows="4"><?= e($patient['basic_details']) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editBasicModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Notes Modal -->
<div class="modal-overlay" id="editNotesModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Free Notes</div>
            <button class="modal-close" onclick="closeModal('editNotesModal')">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_notes">
            <div class="modal-body">
                <div class="form-group">
                    <label>Free Notes</label>
                    <textarea name="free_notes" rows="8" autofocus><?= e($patient['free_notes']) ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editNotesModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
