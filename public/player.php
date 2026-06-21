<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$playerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pdo === null) {
    render_header('Joueur', 'users');
    render_database_error($databaseError);
    render_footer();
    exit;
}

if ($playerId === false || $playerId === null || $playerId < 1) {
    flash('warning', 'Ce joueur n’existe pas.');
    redirect_to('users.php');
}

// Si on regarde son propre profil, on redirige vers la page profil editable.
if ($playerId === current_user_id()) {
    redirect_to('profile.php');
}

$profileStatement = $pdo->prepare("
    SELECT u.id, u.username, u.created_at, u.xp,
           up.bio, p.name AS favorite_platform_name,
           (SELECT COUNT(*) FROM library_entries le WHERE le.user_id = u.id) AS library_count,
           (SELECT COALESCE(SUM(le.playtime_hours), 0) FROM library_entries le WHERE le.user_id = u.id) AS total_hours,
           (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS review_count,
           (SELECT COUNT(*) FROM duels d WHERE d.user_id = u.id) AS duel_count
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN platforms p ON p.id = up.favorite_platform_id
    WHERE u.id = :id
");
$profileStatement->execute(['id' => $playerId]);
$player = $profileStatement->fetch();

if (!$player) {
    flash('warning', 'Ce joueur n’existe pas.');
    redirect_to('users.php');
}

// Bibliotheque publique (relation N-N porteuse).
$libStatement = $pdo->prepare("
    SELECT g.id AS game_id, g.title, le.status, le.playtime_hours
    FROM library_entries le INNER JOIN games g ON g.id = le.game_id
    WHERE le.user_id = :id
    ORDER BY le.added_at DESC LIMIT 12
");
$libStatement->execute(['id' => $playerId]);
$library = $libStatement->fetchAll();

$status = is_logged_in() ? friendship_status($pdo, (int)current_user_id(), $playerId) : 'guest';

render_header($player['username'], 'users');
?>

<section class="container page-head">
    <span class="glow-orb glow-violet" style="width: 22rem; height: 22rem; top: -10rem; right: -6rem;"></span>
    <p class="eyebrow reveal"><a href="users.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Tous les joueurs</a></p>
    <div class="profile-head reveal">
        <span class="avatar-initial avatar-initial-lg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)$player['username'], 0, 1))) ?></span>
        <div class="flex-grow-1">
            <h1 class="page-title mb-1"><?= e($player['username']) ?>
                <span class="level-badge align-middle ms-2"><i class="bi bi-lightning-charge-fill"></i> Niveau <?= level_from_xp((int)$player['xp']) ?></span>
            </h1>
            <p class="text-soft mb-2">
                Membre depuis <?= e(substr((string)$player['created_at'], 0, 4)) ?>
                <?php if (!empty($player['favorite_platform_name'])): ?> · joue surtout sur <strong><?= e($player['favorite_platform_name']) ?></strong><?php endif; ?>
            </p>
            <?php if (!empty($player['bio'])): ?><p class="text-soft mb-3" style="max-width:540px;"><?= e($player['bio']) ?></p><?php endif; ?>

            <div class="friend-actions">
                <?php if ($status === 'guest'): ?>
                    <a class="btn btn-accent btn-sm" href="login.php?redirect=<?= e(urlencode('player.php?id=' . $playerId)) ?>"><i class="bi bi-person-plus"></i> Connecte-toi pour l’ajouter</a>
                <?php elseif ($status === 'friends'): ?>
                    <span class="meta-pill meta-pill-success"><i class="bi bi-check-circle-fill"></i> Amis</span>
                    <form method="post" action="friend_store.php" class="d-inline" data-confirm="Retirer <?= e($player['username']) ?> de tes amis ?">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $playerId ?>"><input type="hidden" name="action" value="remove">
                        <button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                    </form>
                <?php elseif ($status === 'pending_sent'): ?>
                    <span class="meta-pill"><i class="bi bi-hourglass-split"></i> Demande envoyée</span>
                    <form method="post" action="friend_store.php" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $playerId ?>"><input type="hidden" name="action" value="remove">
                        <button class="btn btn-ghost btn-sm" type="submit">Annuler</button>
                    </form>
                <?php elseif ($status === 'pending_received'): ?>
                    <form method="post" action="friend_store.php" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $playerId ?>"><input type="hidden" name="action" value="accept">
                        <button class="btn btn-accent btn-sm" type="submit"><i class="bi bi-check-lg"></i> Accepter la demande</button>
                    </form>
                    <form method="post" action="friend_store.php" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $playerId ?>"><input type="hidden" name="action" value="remove">
                        <button class="btn btn-ghost btn-sm" type="submit">Refuser</button>
                    </form>
                <?php else: /* none */ ?>
                    <form method="post" action="friend_store.php" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $playerId ?>"><input type="hidden" name="action" value="request">
                        <button class="btn btn-accent btn-sm" type="submit"><i class="bi bi-person-plus"></i> Ajouter en ami</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="container" style="padding-bottom: 4rem;">
    <div class="player-stats-row reveal">
        <div><strong><?= (int)$player['library_count'] ?></strong><span>jeux suivis</span></div>
        <div><strong><?= number_format((float)$player['total_hours'], 0, ',', ' ') ?></strong><span>heures</span></div>
        <div><strong><?= (int)$player['review_count'] ?></strong><span>avis</span></div>
        <div><strong><?= (int)$player['duel_count'] ?></strong><span>duels</span></div>
    </div>

    <div class="content-panel reveal mt-4">
        <h2 class="h5 mb-3"><i class="bi bi-bookmark-heart"></i> Sa bibliothèque</h2>
        <?php if ($library === []): ?>
            <p class="text-soft mb-0">Bibliothèque vide pour l’instant.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Jeu</th><th>Statut</th><th class="text-end">Heures</th></tr></thead>
                    <tbody>
                        <?php foreach ($library as $entry): ?>
                            <tr>
                                <td><a href="game.php?id=<?= (int)$entry['game_id'] ?>"><?= e($entry['title']) ?></a></td>
                                <td><span class="status-badge status-<?= e($entry['status']) ?>"><?= e(library_status_label((string)$entry['status'])) ?></span></td>
                                <td class="text-end"><?= number_format((float)$entry['playtime_hours'], 1, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
