<?php

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!is_installed()) {
        throw new RuntimeException('SMILE is not installed yet.');
    }

    $dsn = 'sqlite:' . DB_PATH;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    db_migrate($pdo);

    return $pdo;
}

function db_install(PDO $pdo): void {
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        mobile TEXT NOT NULL,
        age_or_dob TEXT,
        gender TEXT,
        basic_details TEXT,
        free_notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_mobile ON patients(mobile)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_name ON patients(name)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS treatments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        tooth_area TEXT,
        start_date TEXT,
        total_cost REAL NOT NULL DEFAULT 0,
        treatment_notes TEXT,
        final_notes TEXT,
        status TEXT NOT NULL DEFAULT 'Active',
        closed_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_treatments_patient ON treatments(patient_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_treatments_status ON treatments(status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        treatment_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        payment_date TEXT NOT NULL,
        payment_method TEXT,
        note TEXT,
        deleted_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_treatment ON payments(treatment_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_date ON payments(payment_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_deleted ON payments(deleted_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        treatment_id INTEGER,
        file_path TEXT NOT NULL,
        note TEXT,
        uploaded_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE SET NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_patient ON patient_photos(patient_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS followups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        treatment_id INTEGER,
        date TEXT NOT NULL,
        time TEXT,
        reason TEXT,
        notes TEXT,
        status TEXT NOT NULL DEFAULT 'Pending',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE SET NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_followups_date ON followups(date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_followups_status ON followups(status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER,
        treatment_id INTEGER,
        category_id INTEGER NOT NULL,
        name_details TEXT,
        description TEXT,
        amount REAL NOT NULL,
        expense_date TEXT NOT NULL,
        notes TEXT,
        deleted_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
        FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE SET NULL,
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses(expense_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_deleted ON expenses(deleted_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'owner',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS labs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS consultants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
}

function db_migrate(PDO $pdo): void {
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Add role column to users if missing
    $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $hasRole = false;
    foreach ($cols as $c) { if ($c['name'] === 'role') { $hasRole = true; break; } }
    if (!$hasRole) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'owner'");
    }

    // Create labs table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS labs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Create consultants table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact TEXT,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Add lab_id column to expenses if missing
    $cols = $pdo->query('PRAGMA table_info(expenses)')->fetchAll(PDO::FETCH_ASSOC);
    $hasLabId = false;
    foreach ($cols as $c) { if ($c['name'] === 'lab_id') { $hasLabId = true; break; } }
    if (!$hasLabId) {
        $pdo->exec('ALTER TABLE expenses ADD COLUMN lab_id INTEGER REFERENCES labs(id) ON DELETE SET NULL');
    }

    // Add consultant_id column to expenses if missing
    $hasConsultantId = false;
    foreach ($cols as $c) { if ($c['name'] === 'consultant_id') { $hasConsultantId = true; break; } }
    if (!$hasConsultantId) {
        $pdo->exec('ALTER TABLE expenses ADD COLUMN consultant_id INTEGER REFERENCES consultants(id) ON DELETE SET NULL');
    }

    // Create lab_work table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_work (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
        treatment_id INTEGER REFERENCES treatments(id) ON DELETE SET NULL,
        lab_id INTEGER NOT NULL REFERENCES labs(id) ON DELETE RESTRICT,
        description TEXT NOT NULL,
        date_sent TEXT NOT NULL,
        expected_delivery_date TEXT,
        status TEXT NOT NULL DEFAULT 'Pending',
        actual_arrival_date TEXT,
        note TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
}
