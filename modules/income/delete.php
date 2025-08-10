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

$income_id = $_GET['id'] ?? '';

// Validierung
if (empty($income_id)) {
    $_SESSION['error'] = 'Keine Einnahme angegeben.';
    header('Location: index.php');
    exit;
}

try {
    // Prüfe ob Einnahme existiert und dem Benutzer gehört
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ? AND t.user_id = ? AND t.type = 'income'
    ");
    $stmt->execute([$income_id, $user_id]);
    $income = $stmt->fetch();

    if (!$income) {
        $_SESSION['error'] = 'Einnahme nicht gefunden oder keine Berechtigung.';
        header('Location: index.php');
        exit;
    }

    // Einnahme löschen
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ? AND type = 'income'");
    $stmt->execute([$income_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = sprintf(
            'Einnahme "%s" (+€%s) erfolgreich gelöscht.',
            htmlspecialchars($income['description'] ?: $income['category_name']),
            number_format($income['amount'], 2, ',', '.')
        );
    } else {
        $_SESSION['error'] = 'Einnahme konnte nicht gelöscht werden.';
    }
} catch (PDOException $e) {
    error_log("Delete Income Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist beim Löschen aufgetreten.';
}

// Weiterleitung zur Übersicht
header('Location: index.php');
exit;
