<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

// Reponse JSON pour le mode versus (appele en fetch par app.js).
function duel_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAjax = (string)($_POST['ajax'] ?? '') === '1';

if ($pdo === null) {
    $isAjax ? duel_json(['ok' => false, 'error' => 'Base de données non connectée.'], 503) : redirect_to('versus.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('versus.php');
}

// "Je ne connais pas" : on sert une nouvelle paire sans toucher a l'Elo.
// Accessible meme sans connexion (aucune ecriture en base).
if ((string)($_POST['action'] ?? '') === 'skip') {
    $sentToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $sentToken)) {
        $isAjax ? duel_json(['ok' => false, 'error' => 'Session expirée, recharge la page.'], 403) : redirect_to('versus.php');
    }

    $skipStatement = $pdo->query("
        SELECT g.id, g.title, g.cover_url, g.elo, g.metascore,
               s.name AS studio_name,
               (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
                FROM game_genres gg INNER JOIN genres ge ON ge.id = gg.genre_id
                WHERE gg.game_id = g.id) AS genre_names
        FROM games g
        INNER JOIN studios s ON s.id = g.studio_id
        WHERE g.cover_url IS NOT NULL
        ORDER BY RAND()
        LIMIT 2
    ");
    $skipPair = $skipStatement->fetchAll();
    $_SESSION['versus_pair'] = array_map(static fn (array $g): int => (int)$g['id'], $skipPair);
    $_SESSION['versus_streak'] = 0;

    $isAjax ? duel_json(['ok' => true, 'skipped' => true, 'next' => $skipPair]) : redirect_to('versus.php');
}

if (!is_logged_in()) {
    $isAjax
        ? duel_json(['ok' => false, 'error' => 'Connecte-toi pour voter.', 'login' => true], 401)
        : redirect_to('login.php?redirect=versus.php');
}

// CSRF (verification manuelle pour pouvoir repondre en JSON).
$sentToken = (string)($_POST['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $sentToken)) {
    $isAjax ? duel_json(['ok' => false, 'error' => 'Session expirée, recharge la page.'], 403) : redirect_to('versus.php');
}

// Anti-spam : un vote toutes les 1,5 secondes maximum.
$now = microtime(true);
if ($now - (float)($_SESSION['versus_last_vote'] ?? 0) < 1.5) {
    duel_json(['ok' => false, 'error' => 'Doucement ! Un duel à la fois.'], 429);
}

// Anti-triche : on n'accepte QUE la paire que le serveur a servie en session.
$served = $_SESSION['versus_pair'] ?? null;
$winnerId = post_int('winner_id', 1, 1000000000);
$loserId = post_int('loser_id', 1, 1000000000);

if (
    $winnerId === null || $loserId === null || $winnerId === $loserId
    || !is_array($served)
    || !in_array($winnerId, $served, true) || !in_array($loserId, $served, true)
) {
    duel_json(['ok' => false, 'error' => 'Duel invalide, recharge la page.'], 422);
}

// Scores Elo actuels des deux jeux.
$eloStatement = $pdo->prepare('SELECT id, title, elo FROM games WHERE id IN (:a, :b)');
$eloStatement->execute(['a' => $winnerId, 'b' => $loserId]);
$rows = $eloStatement->fetchAll();

if (count($rows) !== 2) {
    duel_json(['ok' => false, 'error' => 'Un des jeux n’existe plus.'], 422);
}

$byId = [];
foreach ($rows as $row) {
    $byId[(int)$row['id']] = $row;
}

$winnerElo = (int)$byId[$winnerId]['elo'];
$loserElo = (int)$byId[$loserId]['elo'];

// Formule Elo classique (K = 32) : la victoire d'un outsider rapporte plus.
$expectedWinner = 1 / (1 + 10 ** (($loserElo - $winnerElo) / 400));
$delta = (int)round(32 * (1 - $expectedWinner));
$delta = max(1, $delta);

const XP_PER_DUEL = 5;

try {
    // Transaction : les deux scores, le duel et l'XP bougent ensemble.
    $pdo->beginTransaction();

    $updateElo = $pdo->prepare('UPDATE games SET elo = elo + :delta WHERE id = :id');
    $updateElo->execute(['delta' => $delta, 'id' => $winnerId]);
    $updateElo->execute(['delta' => -$delta, 'id' => $loserId]);

    $insertDuel = $pdo->prepare('
        INSERT INTO duels (user_id, winner_game_id, loser_game_id)
        VALUES (:user_id, :winner_id, :loser_id)
    ');
    $insertDuel->execute([
        'user_id' => current_user_id(),
        'winner_id' => $winnerId,
        'loser_id' => $loserId,
    ]);

    $updateXp = $pdo->prepare('UPDATE users SET xp = xp + :xp WHERE id = :id');
    $updateXp->execute(['xp' => XP_PER_DUEL, 'id' => current_user_id()]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Ludorivya] Duel impossible : ' . $exception->getMessage());
    duel_json(['ok' => false, 'error' => 'Le vote a échoué, réessaie.'], 500);
}

$_SESSION['versus_last_vote'] = $now;
$_SESSION['versus_streak'] = (int)($_SESSION['versus_streak'] ?? 0) + 1;

// XP et niveau a jour pour l'affichage.
$xpStatement = $pdo->prepare('SELECT xp FROM users WHERE id = :id');
$xpStatement->execute(['id' => current_user_id()]);
$xp = (int)$xpStatement->fetchColumn();

// Prochaine paire de jeux (servie et memorisee cote serveur).
$nextStatement = $pdo->query("
    SELECT g.id, g.title, g.cover_url, g.elo, g.metascore,
           s.name AS studio_name,
           (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
            FROM game_genres gg INNER JOIN genres ge ON ge.id = gg.genre_id
            WHERE gg.game_id = g.id) AS genre_names
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    WHERE g.cover_url IS NOT NULL
    ORDER BY RAND()
    LIMIT 2
");
$nextPair = $nextStatement->fetchAll();
$_SESSION['versus_pair'] = array_map(static fn (array $g): int => (int)$g['id'], $nextPair);

if (!$isAjax) {
    flash('success', '+' . $delta . ' Elo pour « ' . $byId[$winnerId]['title'] . ' » !');
    redirect_to('versus.php');
}

duel_json([
    'ok' => true,
    'delta' => $delta,
    'winner' => ['id' => $winnerId, 'elo' => $winnerElo + $delta],
    'loser' => ['id' => $loserId, 'elo' => $loserElo - $delta],
    'xp' => $xp,
    'level' => level_from_xp($xp),
    'levelProgress' => level_progress($xp),
    'streak' => (int)$_SESSION['versus_streak'],
    'next' => $nextPair,
]);
