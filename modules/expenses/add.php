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

// Kategorien für Dropdown laden
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? '';

    $errors = [];

    // Validierung
    if (empty($category_id)) {
        $errors[] = 'Bitte wähle eine Kategorie aus.';
    }

    if (empty($amount)) {
        $errors[] = 'Betrag ist erforderlich.';
    } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
        $errors[] = 'Betrag muss eine positive Zahl sein.';
    }

    if (empty($transaction_date)) {
        $errors[] = 'Datum ist erforderlich.';
    } elseif (!strtotime($transaction_date)) {
        $errors[] = 'Ungültiges Datum.';
    }

    if (empty($description)) {
        $description = 'Ausgabe'; // Standard-Beschreibung
    }

    // Prüfe ob Kategorie dem Benutzer gehört
    if (!empty($category_id)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = 'expense'");
        $stmt->execute([$category_id, $user_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ungültige Kategorie ausgewählt.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, description, transaction_date, type, created_at)
                VALUES (?, ?, ?, ?, ?, 'expense', datetime('now'))
            ");

            $stmt->execute([
                $user_id,
                $category_id,
                floatval($amount),
                $description,
                $transaction_date
            ]);

            $_SESSION['success'] = 'Ausgabe erfolgreich hinzugefügt!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Standardwerte für Formular
$form_data = [
    'category_id' => $_POST['category_id'] ?? '',
    'amount' => $_POST['amount'] ?? '',
    'description' => $_POST['description'] ?? '',
    'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d')
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Ausgabe - Finance Tracker</title>
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

        .amount-input-wrapper {
            position: relative;
        }

        .currency-symbol {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-primary-a20);
            font-weight: 600;
            pointer-events: none;
        }

        .amount-input {
            padding-left: 30px;
        }

        .category-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: var(--clr-surface-a20);
            border-radius: 6px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-preview.visible {
            opacity: 1;
        }

        .category-icon {
            font-size: 20px;
        }

        .category-name {
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

        .tips {
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .tips h4 {
            color: var(--clr-primary-a20);
            margin-bottom: 10px;
            font-size: 14px;
        }

        .tips ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips li {
            color: var(--clr-surface-a50);
            font-size: 13px;
            margin-bottom: 5px;
            padding-left: 15px;
            position: relative;
        }

        .tips li:before {
            content: '💡';
            position: absolute;
            left: 0;
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
                    <li><a href="index.php" class="active">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">💸 Neue Ausgabe</h1>
                    <p style="color: var(--clr-surface-a50);">Füge eine neue Ausgabe zu deinem Budget hinzu</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2>💸 Ausgabe hinzufügen</h2>
                        <p>Erfasse alle Details deiner Ausgabe</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($categories)): ?>
                        <div class="alert alert-error">
                            <strong>Keine Kategorien vorhanden!</strong><br>
                            Du musst zuerst <a href="../categories/add.php" style="color: var(--clr-primary-a20);">Ausgaben-Kategorien erstellen</a>,
                            bevor du Ausgaben hinzufügen kannst.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label" for="category_id">Kategorie *</label>
                                <select id="category_id" name="category_id" class="form-select" required onchange="updateCategoryPreview()">
                                    <option value="">Kategorie wählen...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"
                                            data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                            data-color="<?= htmlspecialchars($category['color']) ?>"
                                            <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="categoryPreview" class="category-preview">
                                    <span class="category-icon" id="previewIcon">📁</span>
                                    <span class="category-name" id="previewName">Kategorie</span>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="amount">Betrag *</label>
                                    <div class="amount-input-wrapper">
                                        <span class="currency-symbol">€</span>
                                        <input type="number" id="amount" name="amount"
                                            class="form-input amount-input"
                                            step="0.01" min="0.01"
                                            value="<?= htmlspecialchars($form_data['amount']) ?>"
                                            placeholder="0,00" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="transaction_date">Datum *</label>
                                    <input type="date" id="transaction_date" name="transaction_date"
                                        class="form-input"
                                        value="<?= htmlspecialchars($form_data['transaction_date']) ?>"
                                        max="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="description">Beschreibung</label>
                                <textarea id="description" name="description"
                                    class="form-textarea" rows="3"
                                    placeholder="Was hast du gekauft? (optional)"><?= htmlspecialchars($form_data['description']) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                                <button type="submit" class="btn">💾 Ausgabe speichern</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="tips">
                        <h4>💡 Tipps für bessere Übersicht</h4>
                        <ul>
                            <li>Wähle die passende Kategorie für deine Ausgabe</li>
                            <li>Gib eine aussagekräftige Beschreibung ein</li>
                            <li>Trage das korrekte Ausgabedatum ein</li>
                            <li>Runde Beträge auf Cent genau</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateCategoryPreview() {
            const select = document.getElementById('category_id');
            const preview = document.getElementById('categoryPreview');
            const icon = document.getElementById('previewIcon');
            const name = document.getElementById('previewName');

            if (select.value) {
                const option = select.options[select.selectedIndex];
                const categoryIcon = option.getAttribute('data-icon');
                const categoryColor = option.getAttribute('data-color');
                const categoryName = option.text;

                icon.textContent = categoryIcon;
                name.textContent = categoryName;
                preview.style.borderLeft = `4px solid ${categoryColor}`;
                preview.classList.add('visible');
            } else {
                preview.classList.remove('visible');
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryPreview();

            // Focus auf ersten Input
            const firstInput = document.querySelector('select, input');
            if (firstInput) firstInput.focus();
        });

        // Amount-Input formatieren
        document.getElementById('amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>

</html><?php
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

        // Kategorien für Dropdown laden (nur Ausgaben-Kategorien)
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name");
        $stmt->execute([$user_id]);
        $categories = $stmt->fetchAll();

        // Form-Verarbeitung
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $category_id = $_POST['category_id'] ?? '';
            $amount = $_POST['amount'] ?? '';
            $note = trim($_POST['note'] ?? '');
            $date = $_POST['date'] ?? '';

            $errors = [];

            // Validierung
            if (empty($category_id)) {
                $errors[] = 'Bitte wähle eine Kategorie aus.';
            }

            if (empty($amount)) {
                $errors[] = 'Betrag ist erforderlich.';
            } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
                $errors[] = 'Betrag muss eine positive Zahl sein.';
            }

            if (empty($date)) {
                $errors[] = 'Datum ist erforderlich.';
            } elseif (!strtotime($date)) {
                $errors[] = 'Ungültiges Datum.';
            }

            if (empty($note)) {
                $note = 'Ausgabe'; // Standard-Beschreibung
            }

            // Prüfe ob Kategorie dem Benutzer gehört und vom Typ 'expense' ist
            if (!empty($category_id)) {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = 'expense'");
                $stmt->execute([$category_id, $user_id]);
                if (!$stmt->fetch()) {
                    $errors[] = 'Ungültige Kategorie ausgewählt.';
                }
            }

            if (empty($errors)) {
                try {
                    // UPDATED: Neue Schema-Struktur (note, date, kein type)
                    $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, note, date, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'))
            ");

                    $stmt->execute([
                        $user_id,
                        $category_id,
                        floatval($amount),
                        $note,
                        $date
                    ]);

                    $_SESSION['success'] = 'Ausgabe erfolgreich hinzugefügt!';
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
                }
            }
        }

        // Standardwerte für Formular
        $form_data = [
            'category_id' => $_POST['category_id'] ?? '',
            'amount' => $_POST['amount'] ?? '',
            'note' => $_POST['note'] ?? '',
            'date' => $_POST['date'] ?? date('Y-m-d')
        ];
        ?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Ausgabe - Finance Tracker</title>
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

        .amount-input-wrapper {
            position: relative;
        }

        .currency-symbol {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-primary-a20);
            font-weight: 600;
            pointer-events: none;
        }

        .amount-input {
            padding-left: 30px;
        }

        .category-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: var(--clr-surface-a20);
            border-radius: 6px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-preview.visible {
            opacity: 1;
        }

        .category-icon {
            font-size: 20px;
        }

        .category-name {
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

        .tips {
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .tips h4 {
            color: var(--clr-primary-a20);
            margin-bottom: 10px;
            font-size: 14px;
        }

        .tips ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips li {
            color: var(--clr-surface-a50);
            font-size: 13px;
            margin-bottom: 5px;
            padding-left: 15px;
            position: relative;
        }

        .tips li:before {
            content: '💡';
            position: absolute;
            left: 0;
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
                    <li><a href="index.php" class="active">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">💸 Neue Ausgabe</h1>
                    <p style="color: var(--clr-surface-a50);">Füge eine neue Ausgabe zu deinem Budget hinzu</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2>💸 Ausgabe hinzufügen</h2>
                        <p>Erfasse alle Details deiner Ausgabe</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($categories)): ?>
                        <div class="alert alert-error">
                            <strong>Keine Kategorien vorhanden!</strong><br>
                            Du musst zuerst <a href="../categories/add.php?type=expense" style="color: var(--clr-primary-a20);">Ausgaben-Kategorien erstellen</a>,
                            bevor du Ausgaben hinzufügen kannst.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label" for="category_id">Kategorie *</label>
                                <select id="category_id" name="category_id" class="form-select" required onchange="updateCategoryPreview()">
                                    <option value="">Kategorie wählen...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"
                                            data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                            data-color="<?= htmlspecialchars($category['color']) ?>"
                                            <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="categoryPreview" class="category-preview">
                                    <span class="category-icon" id="previewIcon">📁</span>
                                    <span class="category-name" id="previewName">Kategorie</span>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="amount">Betrag *</label>
                                    <div class="amount-input-wrapper">
                                        <span class="currency-symbol">€</span>
                                        <input type="number" id="amount" name="amount"
                                            class="form-input amount-input"
                                            step="0.01" min="0.01"
                                            value="<?= htmlspecialchars($form_data['amount']) ?>"
                                            placeholder="0,00" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="date">Datum *</label>
                                    <input type="date" id="date" name="date"
                                        class="form-input"
                                        value="<?= htmlspecialchars($form_data['date']) ?>"
                                        max="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="note">Beschreibung</label>
                                <textarea id="note" name="note"
                                    class="form-textarea" rows="3"
                                    placeholder="Was hast du gekauft? (optional)"><?= htmlspecialchars($form_data['note']) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                                <button type="submit" class="btn">💾 Ausgabe speichern</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="tips">
                        <h4>💡 Tipps für bessere Übersicht</h4>
                        <ul>
                            <li>Wähle die passende Kategorie für deine Ausgabe</li>
                            <li>Gib eine aussagekräftige Beschreibung ein</li>
                            <li>Trage das korrekte Ausgabedatum ein</li>
                            <li>Runde Beträge auf Cent genau</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateCategoryPreview() {
            const select = document.getElementById('category_id');
            const preview = document.getElementById('categoryPreview');
            const icon = document.getElementById('previewIcon');
            const name = document.getElementById('previewName');

            if (select.value) {
                const option = select.options[select.selectedIndex];
                const categoryIcon = option.getAttribute('data-icon');
                const categoryColor = option.getAttribute('data-color');
                const categoryName = option.text;

                icon.textContent = categoryIcon;
                name.textContent = categoryName;
                preview.style.borderLeft = `4px solid ${categoryColor}`;
                preview.classList.add('visible');
            } else {
                preview.classList.remove('visible');
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryPreview();

            // Focus auf ersten Input
            const firstInput = document.querySelector('select, input');
            if (firstInput) firstInput.focus();
        });

        // Amount-Input formatieren
        document.getElementById('amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>

</html>