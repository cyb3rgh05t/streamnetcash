<?php

declare(strict_types=1);

class Database
{
    /** @var string */
    private $db_file;

    /** @var ?PDO */
    private $connection = null;

    /**
     * @param string|null $dbPath Optional custom absolute/relative path to the SQLite file.
     */
    public function __construct(?string $dbPath = null)
    {
        // Default location: <project-root>/database/finance_tracker.db
        $this->db_file = $dbPath ?? (__DIR__ . '/../database/finance_tracker.db');
        $this->initializeDatabase();
    }

    /**
     * Returns a shared PDO connection (singleton-like per instance).
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            try {
                // Ensure directory exists
                $dbDir = dirname($this->db_file);
                if (!is_dir($dbDir)) {
                    if (!mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
                        throw new RuntimeException('Failed to create database directory: ' . $dbDir);
                    }
                }

                $this->connection = new PDO('sqlite:' . $this->db_file);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // Important for SQLite: enforce foreign keys
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $e) {
                // Fail fast with a clear message in development; adapt to logging for production
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * Creates schema if missing and performs one-time setup.
     * Safe to call multiple times.
     */
    private function initializeDatabase(): void
    {
        $pdo = $this->getConnection();

        // Create tables if they don't exist yet
        $schemaSql = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            starting_balance REAL DEFAULT 0.00,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('income','expense')),
            color TEXT,
            icon TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER,
            amount REAL NOT NULL,
            note TEXT,
            date TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            recurring_transaction_id INTEGER DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transactions(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS recurring_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            note TEXT,
            frequency TEXT NOT NULL CHECK (frequency IN ('daily','weekly','monthly','yearly')),
            start_date TEXT NOT NULL,
            end_date TEXT,
            next_due_date TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        );
        SQL;

        $pdo->beginTransaction();
        try {
            $pdo->exec($schemaSql);
            $this->migrateExistingUsers();
            $this->migrateRecurringTransactions();
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            die('Database schema initialization failed: ' . $t->getMessage());
        }
    }

    /**
     * Migration für bestehende Benutzer ohne starting_balance Spalte
     */
    private function migrateExistingUsers(): void
    {
        $pdo = $this->getConnection();

        try {
            // Prüfe ob starting_balance Spalte bereits existiert
            $stmt = $pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $hasStartingBalance = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'starting_balance') {
                    $hasStartingBalance = true;
                    break;
                }
            }

            // Füge Spalte hinzu wenn sie nicht existiert
            if (!$hasStartingBalance) {
                $pdo->exec("ALTER TABLE users ADD COLUMN starting_balance REAL DEFAULT 0.00");
            }
        } catch (PDOException $e) {
            // Spalte existiert bereits oder anderer Fehler - das ist okay
            error_log("Migration info: " . $e->getMessage());
        }
    }

    /**
     * Migration für wiederkehrende Transaktionen
     */
    private function migrateRecurringTransactions(): void
    {
        $pdo = $this->getConnection();

        try {
            // Prüfe ob recurring_transaction_id Spalte bereits existiert
            $stmt = $pdo->prepare("PRAGMA table_info(transactions)");
            $stmt->execute();
            $columns = $stmt->fetchAll();

            $hasRecurringId = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'recurring_transaction_id') {
                    $hasRecurringId = true;
                    break;
                }
            }

            // Füge Spalte hinzu wenn sie nicht existiert
            if (!$hasRecurringId) {
                $pdo->exec("ALTER TABLE transactions ADD COLUMN recurring_transaction_id INTEGER DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // Spalte existiert bereits oder anderer Fehler - das ist okay
            error_log("Recurring migration info: " . $e->getMessage());
        }
    }

    /**
     * Ensures a basic category set exists for a given user.
     * Call this after creating a new user account.
     * FIXED: Prüft jetzt ob überhaupt Kategorien existieren, nicht user-spezifisch
     *
     * @param int $user_id
     */
    public function ensureDefaultCategories(int $user_id): void
    {
        $pdo = $this->getConnection();

        // FIXED: If ANY categories exist, do nothing (shared categories)
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM categories');
        $check->execute();
        $row = $check->fetch();
        if ($row && (int)$row['cnt'] > 0) {
            return;
        }

        $default_categories = [
            ['name' => 'Gehalt',      'type' => 'income',  'color' => '#4ade80', 'icon' => '💼'],
            ['name' => 'Freelance',   'type' => 'income',  'color' => '#22c55e', 'icon' => '💻'],
            ['name' => 'Lebensmittel', 'type' => 'expense', 'color' => '#f97316', 'icon' => '🛒'],
            ['name' => 'Miete',       'type' => 'expense', 'color' => '#9333ea', 'icon' => '🏠'],
            ['name' => 'Transport',   'type' => 'expense', 'color' => '#78716c', 'icon' => '🚗'],
            ['name' => 'Freizeit',    'type' => 'expense', 'color' => '#ec4899', 'icon' => '🎬']
        ];

        $stmt = $pdo->prepare('
            INSERT INTO categories (user_id, name, type, color, icon)
            VALUES (?, ?, ?, ?, ?)
        ');

        $pdo->beginTransaction();
        try {
            foreach ($default_categories as $c) {
                $stmt->execute([$user_id, $c['name'], $c['type'], $c['color'], $c['icon']]);
            }
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            die('Seeding default categories failed: ' . $t->getMessage());
        }
    }

    /**
     * Creates a new user account with hashed password
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @return int User ID
     */
    public function createUser(string $username, string $email, string $password): int
    {
        $pdo = $this->getConnection();

        // Check if user already exists
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            throw new RuntimeException('User already exists');
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, starting_balance)
            VALUES (?, ?, ?, 0.00)
        ');

        $stmt->execute([$username, $email, $password_hash]);
        $user_id = (int)$pdo->lastInsertId();

        // Create default categories for new user (only if no categories exist)
        $this->ensureDefaultCategories($user_id);

        return $user_id;
    }

    /**
     * Authenticate user by username/email and password
     *
     * @param string $identifier Username or email
     * @param string $password
     * @return array|null User data or null if authentication failed
     */
    public function authenticateUser(string $identifier, string $password): ?array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('
            SELECT id, username, email, password_hash, starting_balance
            FROM users 
            WHERE username = ? OR email = ?
        ');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Remove password_hash from return data
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Update user's starting balance
     * FIXED: Verwendet gemeinsames Startkapital (erste User)
     *
     * @param int $user_id
     * @param float $starting_balance
     * @return bool Success
     */
    public function updateStartingBalance(int $user_id, float $starting_balance): bool
    {
        $pdo = $this->getConnection();

        // FIXED: Immer den ersten User updaten (gemeinsames Startkapital)
        $stmt = $pdo->prepare('
            UPDATE users 
            SET starting_balance = ? 
            WHERE id = (SELECT MIN(id) FROM users)
        ');

        return $stmt->execute([$starting_balance]);
    }

    /**
     * Get user's starting balance
     * FIXED: Verwendet gemeinsames Startkapital (erste User)
     *
     * @param int $user_id
     * @return float Starting balance
     */
    public function getStartingBalance(int $user_id): float
    {
        $pdo = $this->getConnection();

        // FIXED: Immer das Startkapital vom ersten User verwenden (gemeinsam)
        $stmt = $pdo->prepare('SELECT starting_balance FROM users ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $result = $stmt->fetchColumn();

        return $result !== false ? (float)$result : 0.00;
    }

    /**
     * Process due recurring transactions
     * FIXED: Verarbeitet alle fälligen Transaktionen, nicht user-spezifisch
     *
     * @param int|null $user_id Process only for specific user (optional, deprecated for shared usage)
     * @return int Number of transactions created
     */
    public function processDueRecurringTransactions(?int $user_id = null): int
    {
        $pdo = $this->getConnection();
        $today = date('Y-m-d');
        $transactions_created = 0;

        // FIXED: Get all due recurring transactions (shared across all users)
        $sql = "
            SELECT rt.*, c.type as transaction_type
            FROM recurring_transactions rt
            JOIN categories c ON rt.category_id = c.id
            WHERE rt.is_active = 1 
            AND rt.next_due_date <= ?
            AND (rt.end_date IS NULL OR rt.end_date >= ?)
        ";

        $params = [$today, $today];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $due_recurring = $stmt->fetchAll();

        $pdo->beginTransaction();
        try {
            foreach ($due_recurring as $recurring) {
                // Create transaction
                $create_stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, category_id, amount, note, date, recurring_transaction_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
                ");

                $note = $recurring['note'] . ' (Wiederkehrend)';

                $create_stmt->execute([
                    $recurring['user_id'],
                    $recurring['category_id'],
                    $recurring['amount'],
                    $note,
                    $recurring['next_due_date'],
                    $recurring['id']
                ]);

                // Calculate next due date
                $next_due = $this->calculateNextDueDate($recurring['next_due_date'], $recurring['frequency']);

                // Update recurring transaction
                $update_stmt = $pdo->prepare("
                    UPDATE recurring_transactions 
                    SET next_due_date = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$next_due, $recurring['id']]);

                $transactions_created++;
            }

            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }

        return $transactions_created;
    }

    /**
     * Calculate next due date based on frequency
     *
     * @param string $current_date
     * @param string $frequency
     * @return string Next due date
     */
    private function calculateNextDueDate(string $current_date, string $frequency): string
    {
        $date = new DateTime($current_date);

        switch ($frequency) {
            case 'daily':
                $date->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $date->add(new DateInterval('P7D'));
                break;
            case 'monthly':
                $date->add(new DateInterval('P1M'));
                break;
            case 'yearly':
                $date->add(new DateInterval('P1Y'));
                break;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Get recurring transactions statistics for user
     * FIXED: Zeigt alle wiederkehrenden Transaktionen (shared)
     *
     * @param int $user_id (deprecated for shared usage)
     * @return array Statistics
     */
    public function getRecurringStats(int $user_id): array
    {
        $pdo = $this->getConnection();

        // FIXED: Get stats for all recurring transactions (shared)
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN is_active = 1 AND next_due_date <= ? THEN 1 END) as due_soon,
                COUNT(CASE WHEN is_active = 1 AND next_due_date < ? THEN 1 END) as overdue
            FROM recurring_transactions
        ");

        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+7 days'));

        $stmt->execute([$soon, $today]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Get due recurring transactions
     * FIXED: Zeigt alle fälligen wiederkehrenden Transaktionen (shared)
     *
     * @param int $user_id (deprecated for shared usage)
     * @param int $days_ahead How many days ahead to check (default 3)
     * @return array Recurring transactions
     */
    public function getDueRecurringTransactions(int $user_id, int $days_ahead = 3): array
    {
        $pdo = $this->getConnection();

        // FIXED: Get all due recurring transactions (shared)
        $stmt = $pdo->prepare("
            SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
            FROM recurring_transactions rt
            JOIN categories c ON rt.category_id = c.id
            WHERE rt.is_active = 1 AND rt.next_due_date <= ?
            ORDER BY rt.next_due_date ASC
        ");

        $due_date = date('Y-m-d', strtotime("+$days_ahead days"));
        $stmt->execute([$due_date]);

        return $stmt->fetchAll();
    }
}
