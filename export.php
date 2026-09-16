<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();
require_auth();
require_owner();

$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$db = db();

$hasDateFilter = $from !== '' && $to !== '';

function date_cond(string $column, bool $hasFilter, string $from, string $to): string {
    return $hasFilter ? " AND $column BETWEEN '$from' AND '$to'" : '';
}

switch ($type) {
    case 'patients':
        $rows = $db->query("SELECT id, patient_id, name, mobile, age_or_dob, gender, basic_details, free_notes, created_at FROM patients ORDER BY name")->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('patients.csv', $data, ['ID', 'Patient ID', 'Name', 'Mobile', 'Age/DOB', 'Gender', 'Basic Details', 'Free Notes', 'Created At']);
        break;

    case 'treatments':
        $sql = "SELECT t.id, t.name, t.tooth_area, t.start_date, t.total_cost, t.status, t.closed_at,
                   t.treatment_notes, t.final_notes,
                   p.id AS patient_id, p.name AS patient_name, p.patient_id AS patient_code,
                   (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.treatment_id = t.id AND pay.deleted_at IS NULL) AS total_paid,
                   (t.total_cost - (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.treatment_id = t.id AND pay.deleted_at IS NULL)) AS balance,
                   (SELECT COALESCE(SUM(ex.amount), 0) FROM expenses ex WHERE ex.treatment_id = t.id AND ex.deleted_at IS NULL) AS total_expenses,
                   (t.total_cost - (SELECT COALESCE(SUM(ex.amount), 0) FROM expenses ex WHERE ex.treatment_id = t.id AND ex.deleted_at IS NULL)) AS treatment_margin
                FROM treatments t
                JOIN patients p ON t.patient_id = p.id
                WHERE 1=1";
        if ($hasDateFilter) {
            $sql .= " AND t.start_date BETWEEN '$from' AND '$to'";
        }
        $sql .= " ORDER BY t.start_date DESC";
        $rows = $db->query($sql)->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('treatments.csv', $data, ['Treatment ID', 'Treatment', 'Tooth/Area', 'Start Date', 'Total Cost', 'Status', 'Closed At', 'Treatment Notes', 'Final Notes', 'Patient ID', 'Patient Name', 'Patient Code', 'Total Paid', 'Balance', 'Total Expenses', 'Treatment Margin']);
        break;

    case 'payments':
        $sql = "SELECT p.id, p.payment_date, p.amount, p.payment_method, p.note,
                   p.treatment_id, t.name AS treatment_name,
                   pt.id AS patient_id, pt.name AS patient_name, pt.patient_id AS patient_code
                FROM payments p
                JOIN patients pt ON p.patient_id = pt.id
                LEFT JOIN treatments t ON p.treatment_id = t.id
                WHERE p.deleted_at IS NULL";
        if ($hasDateFilter) {
            $sql .= " AND p.payment_date BETWEEN '$from' AND '$to'";
        }
        $sql .= " ORDER BY p.payment_date DESC";
        $rows = $db->query($sql)->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('payments.csv', $data, ['Payment ID', 'Date', 'Amount', 'Method', 'Note', 'Treatment ID', 'Treatment', 'Patient ID', 'Patient Name', 'Patient Code']);
        break;

    case 'expenses':
        $sql = "SELECT e.id, e.expense_date, ec.name AS category, e.name_details, e.amount, e.description, e.notes,
                   e.patient_id, p.name AS patient_name, p.patient_id AS patient_code,
                   e.treatment_id, t.name AS treatment_name,
                   e.lab_id, lab.name AS lab_name,
                   e.consultant_id, con.name AS consultant_name
                FROM expenses e
                JOIN expense_categories ec ON e.category_id = ec.id
                LEFT JOIN patients p ON e.patient_id = p.id
                LEFT JOIN treatments t ON e.treatment_id = t.id
                LEFT JOIN labs lab ON e.lab_id = lab.id
                LEFT JOIN consultants con ON e.consultant_id = con.id
                WHERE e.deleted_at IS NULL";
        if ($hasDateFilter) {
            $sql .= " AND e.expense_date BETWEEN '$from' AND '$to'";
        }
        $sql .= " ORDER BY e.expense_date DESC";
        $rows = $db->query($sql)->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('expenses.csv', $data, ['Expense ID', 'Date', 'Category', 'Name/Details', 'Amount', 'Description', 'Notes', 'Patient ID', 'Patient', 'Patient Code', 'Treatment ID', 'Treatment', 'Lab ID', 'Lab Name', 'Consultant ID', 'Consultant Name']);
        break;

    case 'followups':
        $sql = "SELECT f.id, f.date, f.time, f.reason, f.notes, f.status,
                   p.id AS patient_id, p.name AS patient_name, p.patient_id AS patient_code,
                   t.id AS treatment_id, t.name AS treatment_name
                FROM followups f
                JOIN patients p ON f.patient_id = p.id
                LEFT JOIN treatments t ON f.treatment_id = t.id";
        if ($hasDateFilter) {
            $sql .= " WHERE f.date BETWEEN '$from' AND '$to'";
        }
        $sql .= " ORDER BY f.date DESC";
        $rows = $db->query($sql)->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        csv_output('followups.csv', $data, ['Followup ID', 'Date', 'Time', 'Reason', 'Notes', 'Status', 'Patient ID', 'Patient Name', 'Patient Code', 'Treatment ID', 'Treatment']);
        break;

    case 'full_json':
        $whereTreat = $hasDateFilter ? "WHERE t.start_date BETWEEN '$from' AND '$to'" : '';
        $treatments = $db->query("
            SELECT t.id, t.name, t.tooth_area, t.start_date, t.total_cost, t.status, t.closed_at,
                   t.treatment_notes, t.final_notes, t.created_at,
                   p.id AS patient_id, p.name AS patient_name, p.patient_id AS patient_code,
                   p.mobile AS patient_mobile, p.age_or_dob AS patient_age_or_dob, p.gender AS patient_gender
            FROM treatments t
            JOIN patients p ON t.patient_id = p.id
            $whereTreat
            ORDER BY t.start_date DESC
        ")->fetchAll();

        $result = [];
        foreach ($treatments as $t) {
            $payments = $db->prepare("SELECT id, payment_date, amount, payment_method, note
                                     FROM payments WHERE treatment_id = ? AND deleted_at IS NULL
                                     ORDER BY payment_date ASC");
            $payments->execute([$t['id']]);
            $paymentList = $payments->fetchAll();

            $expenses = $db->prepare("SELECT e.id, e.expense_date, ec.name AS category, e.name_details, e.amount,
                                        e.description, e.notes, e.lab_id, lab.name AS lab_name,
                                        e.consultant_id, con.name AS consultant_name
                                     FROM expenses e
                                     JOIN expense_categories ec ON e.category_id = ec.id
                                     LEFT JOIN labs lab ON e.lab_id = lab.id
                                     LEFT JOIN consultants con ON e.consultant_id = con.id
                                     WHERE e.treatment_id = ? AND e.deleted_at IS NULL
                                     ORDER BY e.expense_date ASC");
            $expenses->execute([$t['id']]);
            $expenseList = $expenses->fetchAll();

            $totalPaid = array_sum(array_column($paymentList, 'amount'));
            $totalExpenses = array_sum(array_column($expenseList, 'amount'));
            $balance = (float)$t['total_cost'] - $totalPaid;
            $margin = (float)$t['total_cost'] - $totalExpenses;
            $netCashFlow = $totalPaid - $totalExpenses;

            $result[] = [
                'treatment' => [
                    'id' => (int)$t['id'],
                    'name' => $t['name'],
                    'tooth_area' => $t['tooth_area'],
                    'start_date' => $t['start_date'],
                    'total_cost' => (float)$t['total_cost'],
                    'status' => $t['status'],
                    'closed_at' => $t['closed_at'],
                    'treatment_notes' => $t['treatment_notes'],
                    'final_notes' => $t['final_notes'],
                    'created_at' => $t['created_at'],
                ],
                'patient' => [
                    'id' => (int)$t['patient_id'],
                    'name' => $t['patient_name'],
                    'patient_code' => $t['patient_code'],
                    'mobile' => $t['patient_mobile'],
                    'age_or_dob' => $t['patient_age_or_dob'],
                    'gender' => $t['patient_gender'],
                ],
                'payments' => array_map(function($p) {
                    return [
                        'id' => (int)$p['id'],
                        'date' => $p['payment_date'],
                        'amount' => (float)$p['amount'],
                        'method' => $p['payment_method'],
                        'note' => $p['note'],
                    ];
                }, $paymentList),
                'expenses' => array_map(function($e) {
                    return [
                        'id' => (int)$e['id'],
                        'date' => $e['expense_date'],
                        'category' => $e['category'],
                        'name_details' => $e['name_details'],
                        'amount' => (float)$e['amount'],
                        'description' => $e['description'],
                        'notes' => $e['notes'],
                        'lab_id' => $e['lab_id'] ? (int)$e['lab_id'] : null,
                        'lab_name' => $e['lab_name'],
                        'consultant_id' => $e['consultant_id'] ? (int)$e['consultant_id'] : null,
                        'consultant_name' => $e['consultant_name'],
                    ];
                }, $expenseList),
                'totals' => [
                    'total_cost' => (float)$t['total_cost'],
                    'total_paid' => $totalPaid,
                    'balance' => $balance,
                    'total_expenses' => $totalExpenses,
                    'treatment_margin' => $margin,
                    'net_cash_flow' => $netCashFlow,
                ],
            ];
        }

        $filename = $hasDateFilter ? "full_export_{$from}_to_{$to}.json" : 'full_export.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode([
            'exported_at' => date('c'),
            'date_range' => $hasDateFilter ? ['from' => $from, 'to' => $to] : null,
            'treatment_count' => count($result),
            'treatments' => $result,
        ], JSON_PRETTY_PRINT);
        exit;

    default:
        set_flash('error', 'Invalid export type.');
        redirect('settings.php');
}
