<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('forum.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('forum.php');
}

require_valid_csrf('forum.php');

$replyId = post_int('reply_id', 1, 1000000000);
if ($replyId === null) {
    redirect_to('forum.php');
}

// On retrouve le sujet pour rediriger au bon endroit apres suppression.
$lookup = $pdo->prepare('SELECT topic_id FROM topic_replies WHERE id = :id AND user_id = :user_id');
$lookup->execute(['id' => $replyId, 'user_id' => current_user_id()]);
$topicId = $lookup->fetchColumn();

if ($topicId === false) {
    flash('warning', 'Tu ne peux supprimer que tes propres réponses.');
    redirect_to('forum.php');
}

try {
    $statement = $pdo->prepare('DELETE FROM topic_replies WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $replyId, 'user_id' => current_user_id()]);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Suppression de reponse impossible : ' . $exception->getMessage());
    flash('danger', 'La suppression a échoué, réessaie.');
    redirect_to('topic.php?id=' . (int)$topicId);
}

flash('success', 'Ta réponse a été supprimée.');
redirect_to('topic.php?id=' . (int)$topicId);
