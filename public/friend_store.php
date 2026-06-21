<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('users.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('users.php');
}
require_valid_csrf('users.php');

$action = (string)($_POST['action'] ?? '');
$otherId = post_int('user_id', 1, 1000000000);
$me = (int)current_user_id();

// On revient sur le profil du joueur concerne (ou la liste).
$back = $otherId !== null ? 'player.php?id=' . $otherId : 'users.php';

if ($otherId === null || $otherId === $me) {
    flash('warning', 'Action impossible.');
    redirect_to('users.php');
}

// Le joueur cible doit exister.
$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
$check->execute(['id' => $otherId]);
if ((int)$check->fetchColumn() === 0) {
    flash('warning', 'Ce joueur n’existe pas.');
    redirect_to('users.php');
}

$status = friendship_status($pdo, $me, $otherId);

try {
    switch ($action) {
        case 'request': // envoyer une demande d'ami
            if ($status === 'none') {
                $pdo->prepare('INSERT INTO friendships (requester_id, addressee_id, status) VALUES (:r, :a, \'pending\')')
                    ->execute(['r' => $me, 'a' => $otherId]);
                flash('success', 'Demande d’ami envoyée.');
            }
            break;

        case 'accept': // accepter une demande recue
            if ($status === 'pending_received') {
                $pdo->prepare('UPDATE friendships SET status = \'accepted\' WHERE requester_id = :r AND addressee_id = :a')
                    ->execute(['r' => $otherId, 'a' => $me]);
                flash('success', 'Vous êtes maintenant amis !');
            }
            break;

        case 'remove': // refuser / annuler / supprimer (dans tous les sens)
            $pdo->prepare('DELETE FROM friendships
                WHERE (requester_id = :me1 AND addressee_id = :other1)
                   OR (requester_id = :other2 AND addressee_id = :me2)')
                ->execute(['me1' => $me, 'other1' => $otherId, 'other2' => $otherId, 'me2' => $me]);
            flash('info', 'Relation mise à jour.');
            break;

        default:
            flash('warning', 'Action inconnue.');
    }
} catch (Throwable $e) {
    error_log('[Ludorivya] Action ami impossible : ' . $e->getMessage());
    flash('danger', 'L’action a échoué, réessaie.');
}

redirect_to($back);
