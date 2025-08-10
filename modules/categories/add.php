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

// Form-Token generieren für Doppel-Submit Schutz
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

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

$errors = [];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token-Validierung (Schutz vor Doppel-Submit)
    $token = $_POST['form_token'] ?? '';
    if (!hash_equals($_SESSION['form_token'], $token)) {
        $errors[] = 'Ungültiges Formular. Bitte versuche es erneut.';
    } else {
        // Token verbrauchen (einmalige Verwendung)
        unset($_SESSION['form_token']);

        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $icon = $_POST['icon'] ?? '';
        $color = $_POST['color'] ?? '';

        // Validierung
        if (empty($name)) {
            $errors[] = 'Name ist erforderlich.';
        } elseif (strlen($name) < 2) {
            $errors[] = 'Name muss mindestens 2 Zeichen lang sein.';
        } elseif (strlen($name) > 50) {
            $errors[] = 'Name darf maximal 50 Zeichen lang sein.';
        }

        if (empty($type) || !in_array($type, ['income', 'expense'])) {
            $errors[] = 'Bitte wähle einen gültigen Typ aus.';
        }

        if (empty($icon)) {
            $errors[] = 'Bitte wähle ein Icon aus.';
        }

        if (empty($color)) {
            $errors[] = 'Bitte wähle eine Farbe aus.';
        } elseif (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $errors[] = 'Ungültiger Farbcode-Format.';
        }

        // Prüfe ob Name bereits existiert (für diesen Benutzer und Typ)
        if (!empty($name) && !empty($type)) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ?");
            $stmt->execute([$user_id, $name, $type]);
            if ($stmt->fetch()) {
                $errors[] = 'Eine Kategorie mit diesem Namen existiert bereits für diesen Typ.';
            }
        }

        if (empty($errors)) {
            try {
                // Prüfe nochmals ob Kategorie bereits existiert (Race Condition Schutz)
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ?");
                $stmt->execute([$user_id, $name, $type]);
                if ($stmt->fetch()) {
                    $errors[] = 'Kategorie "' . htmlspecialchars($name) . '" existiert bereits.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO categories (user_id, name, type, icon, color, created_at)
                        VALUES (?, ?, ?, ?, ?, datetime('now'))
                    ");

                    $stmt->execute([$user_id, $name, $type, $icon, $color]);

                    // Erfolg-Nachricht setzen und sofort weiterleiten (verhindert Doppel-Submit)
                    $_SESSION['success'] = 'Kategorie "' . htmlspecialchars($name) . '" erfolgreich erstellt!';

                    // JavaScript-basierte Weiterleitung + HTTP Header für Sicherheit
                    echo '<script>window.location.replace("index.php");</script>';
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
            }
        }
    }
}

// Neues Token für nächste Form generieren
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Standardwerte für Formular
$form_data = [
    'name' => $_POST['name'] ?? '',
    'type' => $_POST['type'] ?? ($_GET['type'] ?? ''),
    'icon' => $_POST['icon'] ?? '📁',
    'color' => $_POST['color'] ?? '#e6a309'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Kategorie - Finance Tracker</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-grid .form-group:first-child {
            grid-column: 1 / -1;
        }

        .type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .type-option {
            position: relative;
        }

        .type-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .type-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background-color: var(--clr-surface-a20);
            border: 2px solid var(--clr-surface-a20);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .type-radio:checked+.type-label {
            border-color: var(--clr-primary-a0);
            background-color: var(--clr-surface-tonal-a10);
        }

        .type-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .type-name {
            font-weight: 500;
            color: var(--clr-light-a0);
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

        @media (max-width: 768px) {
            .form-container {
                margin: 0;
            }

            .form-card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .icon-selector {
                grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">🏷️ Neue Kategorie</h1>
                    <p style="color: var(--clr-surface-a50);">Erstelle eine neue Kategorie für deine Transaktionen</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2>🏷️ Kategorie erstellen</h2>
                        <p>Definiere eine neue Kategorie mit Name, Typ, Icon und Farbe</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="categoryForm" onsubmit="return submitForm()">
                        <!-- CSRF-Token für Doppel-Submit Schutz -->
                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token']) ?>">

                        <div class="form-group">
                            <label class="form-label" for="name">Name der Kategorie *</label>
                            <input type="text" id="name" name="name"
                                class="form-input"
                                value="<?= htmlspecialchars($form_data['name']) ?>"
                                placeholder="z.B. Lebensmittel, Gehalt, Miete..."
                                maxlength="50" required
                                oninput="updatePreview()">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Typ *</label>
                                <div class="type-selector">
                                    <div class="type-option">
                                        <input type="radio" id="type_income" name="type" value="income"
                                            class="type-radio" <?= $form_data['type'] === 'income' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_income" class="type-label">
                                            <div class="type-icon">💰</div>
                                            <div class="type-name">Einnahme</div>
                                        </label>
                                    </div>
                                    <div class="type-option">
                                        <input type="radio" id="type_expense" name="type" value="expense"
                                            class="type-radio" <?= $form_data['type'] === 'expense' ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="type_expense" class="type-label">
                                            <div class="type-icon">💸</div>
                                            <div class="type-name">Ausgabe</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon auswählen *</label>
                            <div class="icon-selector">
                                <?php foreach ($predefined_icons as $icon): ?>
                                    <div class="icon-option">
                                        <input type="radio" id="icon_<?= urlencode($icon) ?>" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                            class="icon-radio" <?= $form_data['icon'] === $icon ? 'checked' : '' ?>
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
                                            class="color-radio" <?= $form_data['color'] === $color ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="color_<?= substr($color, 1) ?>" class="color-label"
                                            style="background-color: <?= $color ?>;"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preview-section">
                            <div class="preview-title">🔍 Vorschau</div>
                            <div class="category-preview">
                                <div class="preview-icon" id="previewIcon" style="background-color: <?= htmlspecialchars($form_data['color']) ?>;">
                                    <?= htmlspecialchars($form_data['icon']) ?>
                                </div>
                                <div class="preview-name" id="previewName">
                                    <?= htmlspecialchars($form_data['name']) ?: 'Kategorie-Name' ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <button type="submit" class="btn" id="submitBtn">💾 Kategorie erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        let formSubmitted = false;

        function submitForm() {
            if (formSubmitted) {
                return false; // Verhindert Doppel-Submit
            }

            // Formular validieren
            const name = document.getElementById('name').value.trim();
            const type = document.querySelector('input[name="type"]:checked');
            const icon = document.querySelector('input[name="icon"]:checked');
            const color = document.querySelector('input[name="color"]:checked');

            if (!name || !type || !icon || !color) {
                alert('Bitte fülle alle Pflichtfelder aus.');
                return false;
            }

            // Submit-Button deaktivieren
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Wird erstellt...';
            submitBtn.style.opacity = '0.6';

            formSubmitted = true;
            return true;
        }

        function updatePreview() {
            const name = document.getElementById('name').value || 'Kategorie-Name';
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

            // Focus auf ersten Input
            document.getElementById('name').focus();

            // Verhindere Browser-Rücktaste doppelte Submits
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });

        // Warnung bei Seitenverlassen während Formular-Eingabe
        let formChanged = false;
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged && !formSubmitted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>

</html>