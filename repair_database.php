<?php
// ================================================================= 
// DATEI: repair_database.php
// Erstelle diese Datei im Projekt-Root und führe sie EINMAL aus
// =================================================================

echo "<h1>Datenbank-Reparatur für Schulden-Support</h1>";

try {
    // Verbindung zur Datenbank
    $db_path = __DIR__ . '/database/finance_tracker.db';

    if (!file_exists($db_path)) {
        die("❌ Datenbank nicht gefunden: $db_path");
    }

    echo "📂 Datenbank gefunden: $db_path<br>";

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Verbindung zur Datenbank hergestellt<br>";

    // Prüfe ob bereits repariert
    $stmt = $pdo->prepare("SELECT type FROM categories WHERE type IN ('debt_in', 'debt_out') LIMIT 1");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✅ Datenbank ist bereits repariert! Schulden-Kategorien gefunden.<br>";
        echo "<strong>Du kannst diese Datei jetzt löschen.</strong>";
        exit;
    }

    echo "🔧 Starte Reparatur...<br>";

    $pdo->beginTransaction();

    // 1. Backup erstellen
    echo "📋 Erstelle Backup...<br>";
    $pdo->exec("CREATE TABLE categories_safe_backup AS SELECT * FROM categories");

    // 2. Neue Tabelle erstellen
    echo "🆕 Erstelle neue Categories-Tabelle...<br>";
    $pdo->exec("
        CREATE TABLE categories_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('income','expense','debt_in','debt_out')),
            color TEXT,
            icon TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // 3. Daten kopieren
    echo "📦 Kopiere bestehende Daten...<br>";
    $pdo->exec("
        INSERT INTO categories_new (id, user_id, name, type, color, icon, created_at)
        SELECT id, user_id, name, type, color, icon, created_at 
        FROM categories
    ");

    // 4. Alte Tabelle löschen und neue umbenennen
    echo "🔄 Ersetze Tabelle...<br>";
    $pdo->exec("DROP TABLE categories");
    $pdo->exec("ALTER TABLE categories_new RENAME TO categories");

    // 5. Standard Schulden-Kategorien hinzufügen
    echo "➕ Füge Schulden-Kategorien hinzu...<br>";
    $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type, color, icon) VALUES (?, ?, ?, ?, ?)");

    $debt_categories = [
        [1, 'Firma → Privat', 'debt_out', '#fbbf24', '💸'],
        [1, 'Privat → Firma', 'debt_in', '#22c55e', '💰'],
        [1, 'Darlehen vergeben', 'debt_out', '#f97316', '🤝'],
        [1, 'Darlehen erhalten', 'debt_in', '#3b82f6', '🏦']
    ];

    foreach ($debt_categories as $cat) {
        $stmt->execute($cat);
    }

    // 6. Index wiederherstellen
    echo "📊 Erstelle Indizes...<br>";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_categories_type ON categories(type)");

    $pdo->commit();

    echo "<h2>✅ Reparatur erfolgreich abgeschlossen!</h2>";
    echo "<p><strong>Deine Daten:</strong></p>";

    // Zeige Zusammenfassung
    $stmt = $pdo->prepare("SELECT type, COUNT(*) as count FROM categories GROUP BY type");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo "<ul>";
    foreach ($results as $row) {
        $type_names = [
            'income' => 'Einnahmen',
            'expense' => 'Ausgaben',
            'debt_in' => 'Schulden Eingang',
            'debt_out' => 'Schulden Ausgang'
        ];
        $type_name = $type_names[$row['type']] ?? $row['type'];
        echo "<li>{$type_name}: {$row['count']} Kategorien</li>";
    }
    echo "</ul>";

    echo "<p>🗑️ Backup-Tabelle wurde erstellt (categories_safe_backup) - kann später gelöscht werden.</p>";
    echo "<p><strong>🎉 Du kannst jetzt Schulden-Kategorien erstellen!</strong></p>";
    echo "<p><em>Diese repair_database.php Datei kann jetzt gelöscht werden.</em></p>";
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "<h2>❌ Fehler bei der Reparatur:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Deine Daten sind sicher - das Backup wurde erstellt.</p>";
}
