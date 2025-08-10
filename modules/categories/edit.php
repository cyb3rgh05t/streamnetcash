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

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
$stmt->execute([$category_id, $user_id]);
$category = $stmt->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategorie nicht gefunden oder keine Berechtigung.';
    header('Location: index.php');
    exit;
}

// Statistiken zur Kategorie laden
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as transaction_count,
        COALESCE(SUM(amount), 0) as total_amount,
        MAX(transaction_date) as last_used
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
                WHERE id = ? AND user_id = ?
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
    <title>Kategorie bearbeiten - Finance Tracker</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 30px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: var(--clr-primary-a20);
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .current-info {
            background-color: var(--clr-surface-tonal-a10);
            border-left: 4px solid var(--clr-primary-a0);
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .current-info h4 {
            color: var(--clr-primary-a20);
            margin-bottom: 15px;
            font-size: 16px;
        }

        .current-category {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .current-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--clr-light-a0);
        }

        .current-details h5 {
            color: var(--clr-light-a0);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .current-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .current-type.income {
            background-color: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }

        .current-type.expense {
            background-color: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            font-size: 13px;
        }

        .stat-item {
            color: var(--clr-surface-a50);
        }

        .stat-value {
            color: var(--clr-light-a0);
            font-weight: 500;
        }

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background-color: var(--clr-surface-a20);
            border-radius: 8px;
            border: 1px solid var(--clr-surface-a30);
        }

        .icon-option {
            position: relative;
        }

        .icon-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .icon-label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background-color: var(--clr-surface-a30);
            border: 2px solid var(--clr-surface-a30);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 20px;
        }

        .icon-radio:checked+.icon-label {
            border-color: var(--clr-primary-a0);
            background-color: var(--clr-primary-a0);
            transform: scale(1.1);
        }

        .color-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 8px;
        }

        .color-option {
            position: relative;
        }

        .color-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .color-label {
            display: block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .color-radio:checked+.color-label {
            border-color: var(--clr-light-a0);
            transform: scale(1.2);
            box-shadow: 0 0 0 2px var(--clr-surface-a0);
        }

        .preview-section {
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .preview-title {
            color: var(--clr-primary-a20);
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
        }

        .category-preview {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background-color: var(--clr-surface-a10);
            border-radius: 25px;
            border: 1px solid var(--clr-surface-a20);
        }

        .preview-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--clr-light-a0);
        }

        .preview-name {
            font-weight: 500;
            color: var(--clr-light-a0);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .btn-cancel {
            background-color: var(--clr-surface-a30);
            color: var(--clr-light-a0);
        }

        .btn-cancel:hover {
            background-color: var(--clr-surface-a40);
        }

        .btn-delete {
            background-color: #f87171;
            color: var(--clr-light-a0);
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }

        .btn-delete:disabled {
            background-color: var(--clr-surface-a40);
            cursor: not-allowed;
            opacity: 0.5;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .warning-box {
            background-color: rgba(251, 191, 36, 0.1);
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 12px;
            margin-top: 15px;
            font-size: 13px;
            color: #fcd34d;
        }

        @media (max-width: 768px) {
            .form-container {
                margin: 0;
            }

            .form-card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .icon-selector {
                grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div style="padding: 20px; border-bottom: 1px solid var(--clr-surface-a20); margin-bottom: 20px;">
                <h2 style="color: var(--clr-primary-a20);">💰 Finance Tracker</h2>
                <p style="color: var(--clr-surface-a50); font-size: 14px;">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php">📊 Dashboard</a></li>
                    <li><a href="../expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="index.php" class="active">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../logout.php">🚪 Logout</a>
                    </li>
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