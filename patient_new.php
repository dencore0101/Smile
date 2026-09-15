<?php
require_once __DIR__ . '/includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        set_flash('error', 'Invalid session. Please try again.');
        redirect('patient_new.php');
    }

    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $ageOrDob = trim($_POST['age_or_dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $basicDetails = trim($_POST['basic_details'] ?? '');
    $freeNotes = trim($_POST['free_notes'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($mobile === '') $errors[] = 'Mobile number is required.';

    if (empty($errors)) {
        try {
            $db = db();
            $patientId = generate_patient_id($db, $mobile);
            $stmt = $db->prepare("INSERT INTO patients (patient_id, name, mobile, age_or_dob, gender, basic_details, free_notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $name, $mobile, $ageOrDob, $gender, $basicDetails, $freeNotes]);
            $id = (int)$db->lastInsertId();
            set_flash('success', "Patient created. ID: $patientId");
            redirect("patient.php?id=$id");
        } catch (Throwable $e) {
            log_error('Error', 'Create patient failed: ' . $e->getMessage(), __FILE__, __LINE__);
            $errors[] = is_debug() ? $e->getMessage() : 'Could not create patient. Please try again.';
        }
    }
}
?>
<h1>New Patient</h1>
<div class="subtitle">Add a new patient record</div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" autofocus>
            </div>
            <div class="form-group">
                <label for="mobile">Mobile Number *</label>
                <input type="tel" id="mobile" name="mobile" required value="<?= e($_POST['mobile'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="age_or_dob">Age / Date of Birth</label>
                <input type="text" id="age_or_dob" name="age_or_dob" value="<?= e($_POST['age_or_dob'] ?? '') ?>" placeholder="e.g. 35 or 1990-05-15">
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">—</option>
                    <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="basic_details">Basic Details</label>
            <textarea id="basic_details" name="basic_details" placeholder="Address, occupation, medical conditions, allergies..."><?= e($_POST['basic_details'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="free_notes">Free Notes</label>
            <textarea id="free_notes" name="free_notes" rows="4" placeholder="Write anything for yourself..."><?= e($_POST['free_notes'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <a href="patients.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Patient</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
