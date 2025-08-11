<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

$category_id = $_GET['id'] ?? '';

// Kategorie laden und Berechtigung prüfen
if (empty($category_id)) {
    $_SESSION['error'] = 'Keine Kategorie angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id, $user_id]);
$category = $stmt->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategorie nicht gefunden oder keine Berechtigung.';
    header('Location: index.php');
    exit;
}

// Statistiken zur Kategorie laden (FIXED: date statt transaction_date)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as transaction_count,
        COALESCE(SUM(amount), 0) as total_amount,
        MAX(date) as last_used
    FROM transactions 
    WHERE category_id = ? AND user_id = ?
");
$stmt->execute([$category_id, $user_id]);
$stats = $stmt->fetch();

// Vordefinierte Icons und Farben
$predefined_icons = [
    '💰',
    '💼',
    '💻',
    '📈',
    '🏆',
    '🎁',
    '🏠',
    '🚗',
    '🛒',
    '🍕',
    '⛽',
    '💊',
    '👕',
    '📱',
    '🎬',
    '🎮',
    '📚',
    '✈️',
    '🏥',
    '💡',
    '🔧',
    '🧽',
    '🎓',
    '🐕',
    '🌟',
    '💳',
    '📊',
    '🎯',
    '🍔',
    '☕'
];

$predefined_colors = [
    '#e6a309',
    '#ebad36',
    '#f0b753',
    '#f4c16c',
    '#f8cb85',
    '#fbd59d',
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#06b6d4',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#f43f5e',
    '#84cc16',
    '#10b981',
    '#0ea5e9'
];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $icon = $_POST['icon'] ?? '';
    $color = $_POST['color'] ?? '';

    $errors = [];

    // Validierung
    if (empty($name)) {
        $errors[] = 'Name ist erforderlich.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Name muss mindestens 2 Zeichen lang sein.';
    } elseif (strlen($name) > 50) {
        $errors[] = 'Name darf maximal 50 Zeichen lang sein.';
    }

    if (empty($icon)) {
        $errors[] = 'Bitte wähle ein Icon aus.';
    }

    if (empty($color)) {
        $errors[] = 'Bitte wähle eine Farbe aus.';
    } elseif (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $errors[] = 'Ungültiger Farbcode-Format.';
    }

    // Prüfe ob Name bereits existiert (für diesen Benutzer und Typ, außer bei der aktuellen Kategorie)
    if (!empty($name)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ? AND id != ?");
        $stmt->execute([$user_id, $name, $category['type'], $category_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Eine andere Kategorie mit diesem Namen existiert bereits für diesen Typ.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, icon = ?, color = ?
                WHERE id = ?
            ");

            $stmt->execute([$name, $icon, $color, $category_id, $user_id]);

            $_SESSION['success'] = 'Kategorie "' . htmlspecialchars($name) . '" erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Update category data with form values on error
    $category['name'] = $name;
    $category['icon'] = $icon;
    $category['color'] = $color;
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie bearbeiten - StreamNet Finance</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/categories.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="StreamNet Finance Logo" class="sidebar-logo-image">
                    <h2 class="sidebar-logo-text">StreamNet Finance</h2>
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php">📊 Dashboard</a></li>
                    <li><a href="../expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../recurring/index.php">🔄 Wiederkehrend</a></li>
                    <li><a href="index.php" class="active">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../settings.php">⚙️ Einstellungen</a>
                    </li>
                    <li><a href="../../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">✏️ Kategorie bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details deiner Kategorie</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2>✏️ Kategorie bearbeiten</h2>
                        <p>Ändere Name, Icon und Farbe deiner Kategorie</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle Kategorie</h4>
                        <div class="current-category">
                            <div class="current-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                <?= htmlspecialchars($category['icon']) ?>
                            </div>
                            <div class="current-details">
                                <h5><?= htmlspecialchars($category['name']) ?></h5>
                                <span class="current-type <?= $category['type'] ?>">
                                    <?= $category['type'] === 'income' ? '💰 Einnahme' : '💸 Ausgabe' ?>
                                </span>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <strong>Transaktionen:</strong><br>
                                <span class="stat-value"><?= $stats['transaction_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Gesamtbetrag:</strong><br>
                                <span class="stat-value">€<?= number_format($stats['total_amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Erstellt:</strong><br>
                                <span class="stat-value"><?= date('d.m.Y', strtotime($category['created_at'])) ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Zuletzt verwendet:</strong><br>
                                <span class="stat-value">
                                    <?= $stats['last_used'] ? date('d.m.Y', strtotime($stats['last_used'])) : 'Nie' ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($stats['transaction_count'] > 0): ?>
                            <div class="warning-box">
                                ⚠️ <strong>Hinweis:</strong> Diese Kategorie wird in <?= $stats['transaction_count'] ?> Transaktionen verwendet.
                                Änderungen wirken sich auf alle bestehenden Einträge aus.
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="name">Name der Kategorie *</label>
                            <input type="text" id="name" name="name"
                                class="form-input"
                                value="<?= htmlspecialchars($category['name']) ?>"
                                placeholder="z.B. Lebensmittel, Gehalt, Miete..."
                                maxlength="50" required
                                oninput="updatePreview()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon auswählen *</label>
                            <div class="icon-selector">
                                <?php foreach ($predefined_icons as $icon): ?>
                                    <div class="icon-option">
                                        <input type="radio" id="icon_<?= urlencode($icon) ?>" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                            class="icon-radio" <?= $category['icon'] === $icon ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="icon_<?= urlencode($icon) ?>" class="icon-label">
                                            <?= htmlspecialchars($icon) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Farbe auswählen *</label>
                            <div class="color-selector">
                                <?php foreach ($predefined_colors as $color): ?>
                                    <div class="color-option">
                                        <input type="radio" id="color_<?= substr($color, 1) ?>" name="color" value="<?= $color ?>"
                                            class="color-radio" <?= $category['color'] === $color ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="color_<?= substr($color, 1) ?>" class="color-label"
                                            style="background-color: <?= $color ?>;"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preview-section">
                            <div class="preview-title">🔍 Neue Vorschau</div>
                            <div class="category-preview">
                                <div class="preview-icon" id="previewIcon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                    <?= htmlspecialchars($category['icon']) ?>
                                </div>
                                <div class="preview-name" id="previewName">
                                    <?= htmlspecialchars($category['name']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <?php if ($stats['transaction_count'] == 0): ?>
                                <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Kategorie wirklich löschen?')">🗑️ Löschen</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden">
                                    🔒 Wird verwendet
                                </button>
                            <?php endif; ?>
                            <button type="submit" class="btn">💾 Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updatePreview() {
            const name = document.getElementById('name').value || '<?= htmlspecialchars($category['name']) ?>';
            const selectedIcon = document.querySelector('input[name="icon"]:checked');
            const selectedColor = document.querySelector('input[name="color"]:checked');

            document.getElementById('previewName').textContent = name;

            if (selectedIcon) {
                document.getElementById('previewIcon').textContent = selectedIcon.value;
            }

            if (selectedColor) {
                document.getElementById('previewIcon').style.backgroundColor = selectedColor.value;
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
    </script>
</body>

</html>