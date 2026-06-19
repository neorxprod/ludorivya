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

$topicId = post_int('topic_id', 1, 1000000000);
$body = post_string('body', 5000);

if ($topicId === null || mb_strlen($body) < 2) {
    flash('danger', 'Réponse invalide.');
    redirect_to('forum.php');
}

// Le sujet doit exister.
$topicCheck = $pdo->prepare('SELECT COUNT(*) FROM topics WHERE id = :id');
$topicCheck->execute(['id' => $topicId]);
if ((int)$topicCheck->fetchColumn() === 0) {
    flash('warning', 'Ce sujet n’existe plus.');
    redirect_to('forum.php');
}

try {
    $statement = $pdo->prepare('
        INSERT INTO topic_replies (topic_id, user_id, body)
        VALUES (:topic_id, :user_id, :body)
    ');
    $statement->execute([
        'topic_id' => $topicId,
        'user_id' => current_user_id(),
        'body' => $body,
    ]);

    award_xp($pdo, (int)current_user_id(), 5);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Reponse impossible : ' . $exception->getMessage());
    flash('danger', 'La publication a échoué, réessaie.');
    redirect_to('topic.php?id=' . $topicId);
}

flash('success', 'Réponse publiée (+5 XP) !');
redirect_to('topic.php?id=' . $topicId);
