<?php

function generate_patient_id(PDO $db, string $mobile): string {
    $mobile = preg_replace('/\D/', '', $mobile);
    $base = 'P-' . $mobile;
    $stmt = $db->prepare("SELECT patient_id FROM patients WHERE patient_id = ? OR patient_id LIKE ? ORDER BY patient_id");
    $stmt->execute([$base, $base . '-%']);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($existing) === 0) return $base;
    $max = 0;
    foreach ($existing as $pid) {
        if ($pid === $base) { $max = max($max, 0); continue; }
        if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)$/', $pid, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $base . '-' . str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
}

function get_patient(int $id): ?array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_patient_by_id(string $patientId): ?array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM patients WHERE patient_id = ?');
    $stmt->execute([$patientId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_treatment(int $id): ?array {
    $db = db();
    $stmt = $db->prepare('SELECT t.*, p.name AS patient_name, p.patient_id AS patient_code
                         FROM treatments t JOIN patients p ON t.patient_id = p.id WHERE t.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function treatment_paid(int $treatmentId): float {
    $db = db();
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE treatment_id = ? AND deleted_at IS NULL");
    $stmt->execute([$treatmentId]);
    return (float)$stmt->fetchColumn();
}

function treatment_expenses(int $treatmentId): float {
    $db = db();
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE treatment_id = ? AND deleted_at IS NULL");
    $stmt->execute([$treatmentId]);
    return (float)$stmt->fetchColumn();
}

function patient_total_paid(int $patientId): float {
    $db = db();
    $stmt = $db->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                          JOIN treatments t ON p.treatment_id = t.id
                          WHERE p.patient_id = ? AND p.deleted_at IS NULL");
    $stmt->execute([$patientId]);
    return (float)$stmt->fetchColumn();
}

function patient_total_cost(int $patientId): float {
    $db = db();
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM treatments WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    return (float)$stmt->fetchColumn();
}

function patient_total_expenses(int $patientId): float {
    $db = db();
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE patient_id = ? AND deleted_at IS NULL");
    $stmt->execute([$patientId]);
    return (float)$stmt->fetchColumn();
}

function get_expense_categories(): array {
    $db = db();
    return $db->query('SELECT * FROM expense_categories ORDER BY name')->fetchAll();
}

function get_treatments_for_patient(int $patientId): array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM treatments WHERE patient_id = ? ORDER BY created_at DESC');
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

function get_payments_for_treatment(int $treatmentId): array {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM payments WHERE treatment_id = ? AND deleted_at IS NULL ORDER BY payment_date ASC, created_at ASC");
    $stmt->execute([$treatmentId]);
    return $stmt->fetchAll();
}

function get_photos_for_patient(int $patientId): array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM patient_photos WHERE patient_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

function get_photos_for_treatment(int $treatmentId): array {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM patient_photos WHERE treatment_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$treatmentId]);
    return $stmt->fetchAll();
}

function get_expenses_for_treatment(int $treatmentId): array {
    $db = db();
    $stmt = $db->prepare("SELECT e.*, ec.name AS category_name FROM expenses e
                          JOIN expense_categories ec ON e.category_id = ec.id
                          WHERE e.treatment_id = ? AND e.deleted_at IS NULL
                          ORDER BY e.expense_date DESC");
    $stmt->execute([$treatmentId]);
    return $stmt->fetchAll();
}

function get_expenses_for_patient(int $patientId): array {
    $db = db();
    $stmt = $db->prepare("SELECT e.*, ec.name AS category_name, t.name AS treatment_name
                          FROM expenses e
                          JOIN expense_categories ec ON e.category_id = ec.id
                          LEFT JOIN treatments t ON e.treatment_id = t.id
                          WHERE e.patient_id = ? AND e.deleted_at IS NULL
                          ORDER BY e.expense_date DESC");
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

function get_followups_for_patient(int $patientId): array {
    $db = db();
    $stmt = $db->prepare("SELECT f.*, t.name AS treatment_name FROM followups f
                          LEFT JOIN treatments t ON f.treatment_id = t.id
                          WHERE f.patient_id = ? ORDER BY f.date DESC, f.time ASC");
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

function get_treatment_name(int $id): string {
    $t = get_treatment($id);
    return $t ? $t['name'] : '';
}

// Date range helpers
function date_range(string $preset): array {
    $today = today_date();
    switch ($preset) {
        case 'today':
            return [$today, $today];
        case 'week':
            $dow = (int)date('N'); // 1=Mon
            $start = date('Y-m-d', strtotime("-" . ($dow - 1) . " days"));
            $end = date('Y-m-d', strtotime("+" . (7 - $dow) . " days"));
            return [$start, $end];
        case 'month':
            return [date('Y-m-01'), date('Y-m-t')];
        case 'year':
            return [date('Y-01-01'), date('Y-12-31')];
        default:
            return [$today, $today];
    }
}

function date_range_label(string $preset, string $start = '', string $end = ''): string {
    switch ($preset) {
        case 'today':
            return today_date_long();
        case 'week':
            [$s, $e] = date_range('week');
            return format_date($s) . ' – ' . format_date($e);
        case 'month':
            return date('F Y');
        case 'year':
            return date('Y');
        case 'custom':
            return format_date($start) . ' – ' . format_date($end);
        default:
            return '';
    }
}

function csv_output(string $filename, array $rows, array $headers): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function google_cal_link(array $fu): string {
    $title = $fu['patient_name'] ?? 'Follow-up';
    if (!empty($fu['treatment_name'])) $title .= ' — ' . $fu['treatment_name'];
    elseif (!empty($fu['reason'])) $title .= ' — ' . $fu['reason'];

    $dt = $fu['date'];
    $time = $fu['time'] ?? '';
    $details = $fu['notes'] ?? '';

    if ($time) {
        $start = date('Ymd\THis', strtotime("$dt $time"));
        $end = date('Ymd\THis', strtotime("$dt $time +30 minutes"));
    } else {
        $start = date('Ymd', strtotime($dt));
        $end = date('Ymd', strtotime($dt . ' +1 day'));
    }

    $params = http_build_query([
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $start . '/' . $end,
        'details' => $details,
    ]);
    return 'https://calendar.google.com/calendar/render?' . $params;
}
