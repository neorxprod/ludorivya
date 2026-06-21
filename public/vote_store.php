<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

// Vote like/dislike sur un sujet ou une reponse du forum (appel en fetch).
function vote_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pdo === null) {
    vote_json(['ok' => false, 'error' => 'Base de données non connectée.'], 503);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vote_json(['ok' => false, 'error' => 'Méthode invalide.'], 405);
}
if (!is_logged_in()) {
    vote_json(['ok' => false, 'error' => 'Connecte-toi pour voter.', 'login' => true], 401);
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    vote_json(['ok' => false, 'error' => 'Session expirée.'], 403);
}

$type = (string)($_POST['type'] ?? '');          // 'topic' ou 'reply'
$targetId = post_int('target_id', 1, 1000000000);
$value = post_int('value', -1, 1);                // -1 ou 1

if ($targetId === null || $value === null || $value === 0 || !in_array($type, ['topic', 'reply'], true)) {
    vote_json(['ok' => false, 'error' => 'Vote invalide.'], 422);
}

// On choisit la table selon la cible (noms codes en dur, jamais l'entree brute).
$table = $type === 'topic' ? 'topic_votes' : 'reply_votes';
$col = $type === 'topic' ? 'topic_id' : 'reply_id';
$userId = (int)current_user_id();

try {
    // Le vote existant de ce joueur sur cette cible (pour gerer le bascule/retrait).
    $current = $pdo->prepare("SELECT value FROM {$table} WHERE user_id = :u AND {$col} = :t");
    $current->execute(['u' => $userId, 't' => $targetId]);
    $existing = $current->fetchColumn();

    if ($existing !== false && (int)$existing === $value) {
        // Re-cliquer le meme vote => on l'annule (retrait).
        $del = $pdo->prepare("DELETE FROM {$table} WHERE user_id = :u AND {$col} = :t");
        $del->execute(['u' => $userId, 't' => $targetId]);
        $myVote = 0;
    } else {
        // Sinon on pose / bascule le vote.
        $up = $pdo->prepare("INSERT INTO {$table} (user_id, {$col}, value) VALUES (:u, :t, :v)
                             ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $up->execute(['u' => $userId, 't' => $targetId, 'v' => $value]);
        $myVote = $value;
    }

    // Score total a jour.
    $sum = $pdo->prepare("SELECT COALESCE(SUM(value), 0) FROM {$table} WHERE {$col} = :t");
    $sum->execute(['t' => $targetId]);
    $score = (int)$sum->fetchColumn();
} catch (Throwable $e) {
    error_log('[Ludorivya] Vote forum impossible : ' . $e->getMessage());
    vote_json(['ok' => false, 'error' => 'Le vote a échoué.'], 500);
}

vote_json(['ok' => true, 'score' => $score, 'myVote' => $myVote]);
