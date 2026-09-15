<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();

$type = $_GET['type'] ?? '';
$db = db();

switch ($type) {
    case 'patients':
        $rows = $db->query("SELECT patient_id, name, mobile, age_or_dob, gender, basic_details, free_notes, created_at FROM patients ORDER BY name")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('patients.csv', $data, ['Patient ID', 'Name', 'Mobile', 'Age/DOB', 'Gender', 'Basic Details', 'Free Notes', 'Created At']);
        break;

    case 'treatments':
        $rows = $db->query("SELECT t.name, t.tooth_area, t.start_date, t.total_cost, t.status, t.closed_at,
                           p.name AS patient_name, p.patient_id AS patient_code,
                           t.treatment_notes, t.final_notes
                           FROM treatments t JOIN patients p ON t.patient_id = p.id
                           ORDER BY t.start_date DESC")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('treatments.csv', $data, ['Treatment', 'Tooth/Area', 'Start Date', 'Total Cost', 'Status', 'Closed At', 'Patient Name', 'Patient ID', 'Treatment Notes', 'Final Notes']);
        break;

    case 'payments':
        $rows = $db->query("SELECT p.payment_date, p.amount, p.payment_method, p.note,
                           pt.name AS patient_name, pt.patient_id AS patient_code, t.name AS treatment_name
                           FROM payments p
                           JOIN patients pt ON p.patient_id = pt.id
                           LEFT JOIN treatments t ON p.treatment_id = t.id
                           WHERE p.deleted_at IS NULL
                           ORDER BY p.payment_date DESC")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('payments.csv', $data, ['Date', 'Amount', 'Method', 'Note', 'Patient Name', 'Patient ID', 'Treatment']);
        break;

    case 'expenses':
        $rows = $db->query("SELECT e.expense_date, ec.name AS category, e.name_details, e.amount, e.description, e.notes,
                           p.name AS patient_name, t.name AS treatment_name
                           FROM expenses e
                           JOIN expense_categories ec ON e.category_id = ec.id
                           LEFT JOIN patients p ON e.patient_id = p.id
                           LEFT JOIN treatments t ON e.treatment_id = t.id
                           WHERE e.deleted_at IS NULL
                           ORDER BY e.expense_date DESC")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('expenses.csv', $data, ['Date', 'Category', 'Name/Details', 'Amount', 'Description', 'Notes', 'Patient', 'Treatment']);
        break;

    case 'followups':
        $rows = $db->query("SELECT f.date, f.time, f.reason, f.notes, f.status,
                           p.name AS patient_name, p.patient_id AS patient_code, t.name AS treatment_name
                           FROM followups f
                           JOIN patients p ON f.patient_id = p.id
                           LEFT JOIN treatments t ON f.treatment_id = t.id
                           ORDER BY f.date DESC")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('followups.csv', $data, ['Date', 'Time', 'Reason', 'Notes', 'Status', 'Patient Name', 'Patient ID', 'Treatment']);
        break;

    default:
        set_flash('error', 'Invalid export type.');
        redirect('settings.php');
}
