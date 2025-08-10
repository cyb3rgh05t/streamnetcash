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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );
        SQL;

        $pdo->beginTransaction();
        try {
            $pdo->exec($schemaSql);
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            die('Database schema initialization failed: ' . $t->getMessage());
        }
    }

    /**
     * Ensures a basic category set exists for a given user.
     * Call this after creating a new user account.
     *
     * @param int $user_id
     */
    public function ensureDefaultCategories(int $user_id): void
    {
        $pdo = $this->getConnection();

        // If the user already has categories, do nothing
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM categories WHERE user_id = ?');
        $check->execute([$user_id]);
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
            INSERT INTO users (username, email, password_hash)
            VALUES (?, ?, ?)
        ');

        $stmt->execute([$username, $email, $password_hash]);
        $user_id = (int)$pdo->lastInsertId();

        // Create default categories for new user
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
            SELECT id, username, email, password_hash 
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
}
