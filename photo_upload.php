<?php
require_once __DIR__ . '/includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$treatmentId = (int)($_GET['treatment_id'] ?? $_POST['treatment_id'] ?? 0);

$patient = get_patient($patientId);
if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('patients.php');
}

$treatments = get_treatments_for_patient($patientId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session.');
        redirect("photo_upload.php?patient_id=$patientId");
    }

    $treatmentId = (int)($_POST['treatment_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a photo to upload.';
    } else {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowed)) {
            $errors[] = 'Only JPG, JPEG, PNG, and WEBP files are allowed.';
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $errors[] = 'File too large. Maximum 20 MB.';
        } else {
            $ext = match($mimeType) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            $filename = $patientId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $patientDir = PHOTO_DIR . '/' . $patientId;
            if (!is_dir($patientDir)) {
                mkdir($patientDir, 0755, true);
            }
            $destPath = $patientDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $relPath = $patientId . '/' . $filename;
                $db = db();
                $stmt = $db->prepare("INSERT INTO patient_photos (patient_id, treatment_id, file_path, note) VALUES (?, ?, ?, ?)");
                $stmt->execute([$patientId, $treatmentId ?: null, $relPath, $note]);
                set_flash('success', 'Photo uploaded.');
                redirect("patient.php?id=$patientId");
            } else {
                $errors[] = 'Could not save the uploaded file.';
            }
        }
    }
}
?>
<h1>Upload Photo</h1>
<div class="subtitle">Patient: <?= e($patient['name']) ?> (<?= e($patient['patient_id']) ?>)</div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="patient_id" value="<?= $patientId ?>">
        <?php if ($treatmentId): ?>
            <input type="hidden" name="treatment_id" value="<?= $treatmentId ?>">
        <?php elseif (!empty($treatments)): ?>
            <div class="form-group">
                <label for="treatment_id">Link to Treatment (optional)</label>
                <select id="treatment_id" name="treatment_id">
                    <option value="">— General photo —</option>
                    <?php foreach ($treatments as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['tooth_area'] ?: '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="photo">Select Photo</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <div class="form-group">
            <label for="note">Note (optional)</label>
            <input type="text" id="note" name="note" placeholder="e.g. Pre-op, Post-op">
        </div>
        <div class="form-actions">
            <a href="patient.php?id=<?= $patientId ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Upload</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
