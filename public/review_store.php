<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

if ($pdo === null) {
    flash('danger', 'Base de donnees non connectee.');
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$gameId = post_int('game_id', 1, 1000000);
$userId = (int)$_SESSION['user_id'];
$rating = post_int('rating', 0, 20);
$comment = post_string('comment', 1000);

if ($gameId === null || $userId === null || $rating === null || $comment === '') {
    flash('danger', 'Avis incomplet ou invalide.');
    redirect_to('index.php');
}

$statement = $pdo->prepare("
    INSERT INTO reviews (game_id, user_id, rating, comment)
    VALUES (:game_id, :user_id, :rating, :comment)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP
");
$statement->execute([
    'game_id' => $gameId,
    'user_id' => $userId,
    'rating' => $rating,
    'comment' => $comment,
]);

flash('success', 'Avis enregistre.');
redirect_to('game.php?id=' . $gameId);
