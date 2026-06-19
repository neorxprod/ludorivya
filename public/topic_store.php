<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php?redirect=forum.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('forum.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('forum.php');
}

require_valid_csrf('forum.php');

$title = post_string('title', 150);
$body = post_string('body', 5000);
$gameId = post_int('game_id', 1, 1000000000);

if (mb_strlen($title) < 4 || mb_strlen($body) < 2) {
    flash('danger', 'Il faut un titre (4 caractères minimum) et un message.');
    redirect_to('forum.php');
}

// Le jeu lie est facultatif mais doit exister s'il est fourni.
if ($gameId !== null) {
    $gameCheck = $pdo->prepare('SELECT COUNT(*) FROM games WHERE id = :id');
    $gameCheck->execute(['id' => $gameId]);
    if ((int)$gameCheck->fetchColumn() === 0) {
        $gameId = null;
    }
}

try {
    $statement = $pdo->prepare('
        INSERT INTO topics (user_id, game_id, title, body)
        VALUES (:user_id, :game_id, :title, :body)
    ');
    $statement->execute([
        'user_id' => current_user_id(),
        'game_id' => $gameId,
        'title' => $title,
        'body' => $body,
    ]);
    $topicId = (int)$pdo->lastInsertId();

    // Lancer une discussion rapporte de l'XP.
    award_xp($pdo, (int)current_user_id(), 15);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Creation de sujet impossible : ' . $exception->getMessage());
    flash('danger', 'La création du sujet a échoué, réessaie.');
    redirect_to('forum.php');
}

flash('success', 'Ton sujet est en ligne (+15 XP) !');
redirect_to('topic.php?id=' . $topicId);
