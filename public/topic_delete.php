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

$topicId = post_int('topic_id', 1, 1000000000);
if ($topicId === null) {
    redirect_to('forum.php');
}

try {
    // Le WHERE user_id garantit qu'on ne supprime QUE ses propres sujets.
    // Les reponses partent avec (ON DELETE CASCADE).
    $statement = $pdo->prepare('DELETE FROM topics WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $topicId, 'user_id' => current_user_id()]);
    $deleted = $statement->rowCount() > 0;
} catch (Throwable $exception) {
    error_log('[Ludorivya] Suppression de sujet impossible : ' . $exception->getMessage());
    flash('danger', 'La suppression a échoué, réessaie.');
    redirect_to('topic.php?id=' . $topicId);
}

flash($deleted ? 'success' : 'warning', $deleted ? 'Le sujet a été supprimé.' : 'Tu ne peux supprimer que tes propres sujets.');
redirect_to('forum.php');
