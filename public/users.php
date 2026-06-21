<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Joueurs', 'users');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$me = current_user_id();

// Recherche par pseudo (facultative).
$search = trim((string)($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 60);

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.username LIKE :q';
    $params['q'] = '%' . addcslashes($search, '\\%_') . '%';
}

// Joueurs + profil (1-1) + plateforme preferee + agregats. Emails jamais exposes.
$usersStatement = $pdo->prepare("
    SELECT
        u.id, u.username, u.created_at, u.xp,
        up.bio, p.name AS favorite_platform_name,
        (SELECT COUNT(*) FROM library_entries le WHERE le.user_id = u.id) AS library_count,
        (SELECT COALESCE(SUM(le.playtime_hours), 0) FROM library_entries le WHERE le.user_id = u.id) AS total_hours,
        (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS review_count
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN platforms p ON p.id = up.favorite_platform_id
    {$where}
    ORDER BY total_hours DESC, u.username ASC
");
$usersStatement->execute($params);
$players = $usersStatement->fetchAll();

// Statut d'amitie de chaque joueur vis-a-vis de moi (pour les boutons).
$statusOf = [];
if ($me !== null) {
    foreach ($players as $pl) {
        $statusOf[(int)$pl['id']] = friendship_status($pdo, (int)$me, (int)$pl['id']);
    }
}
?>

<section class="container page-head">
    <span class="glow-orb glow-violet" style="width: 24rem; height: 24rem; top: -10rem; right: -6rem;"></span>
    <p class="eyebrow reveal">Communauté</p>
    <h1 class="page-title reveal">Les joueurs<span class="text-soft"> de Ludorivya.</span></h1>
    <p class="page-lead reveal">Cherche un joueur, visite son profil, ajoute-le en ami.</p>
</section>

<section class="container" style="padding-bottom: 4rem;">
    <form class="filter-bar reveal mb-4" method="get" action="users.php" style="grid-template-columns: 1fr auto;">
        <div class="filter-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="q">Rechercher un joueur</label>
            <input class="form-control" id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Rechercher un joueur par pseudo…">
        </div>
        <button class="btn btn-accent" type="submit">Rechercher</button>
    </form>

    <?php if ($players === []): ?>
        <div class="empty-state reveal">
            <i class="bi bi-people empty-icon" aria-hidden="true"></i>
            <h2 class="h5">Aucun joueur trouvé</h2>
            <p>Essaie un autre pseudo.</p>
        </div>
    <?php else: ?>
        <div class="player-grid">
            <?php foreach ($players as $i => $player): ?>
                <?php $st = $statusOf[(int)$player['id']] ?? 'guest'; ?>
                <article class="player-card content-panel reveal" style="--reveal-delay: <?= ($i % 3) * 80 ?>ms">
                    <a class="player-card-head" href="player.php?id=<?= (int)$player['id'] ?>">
                        <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($player['username'], 0, 1))) ?></span>
                        <span class="player-card-id">
                            <strong><?= e($player['username']) ?></strong>
                            <span class="level-badge">Niv. <?= level_from_xp((int)$player['xp']) ?></span>
                        </span>
                    </a>
                    <?php if (!empty($player['favorite_platform_name'])): ?>
                        <p class="text-soft small mb-2"><i class="bi bi-controller"></i> <?= e($player['favorite_platform_name']) ?></p>
                    <?php endif; ?>
                    <div class="player-stats">
                        <div><strong><?= (int)$player['library_count'] ?></strong><span>jeux</span></div>
                        <div><strong><?= number_format((float)$player['total_hours'], 0, ',', ' ') ?></strong><span>h</span></div>
                        <div><strong><?= (int)$player['review_count'] ?></strong><span>avis</span></div>
                    </div>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <a class="btn btn-ghost btn-sm" href="player.php?id=<?= (int)$player['id'] ?>">Voir le profil</a>
                        <?php if ($st === 'none'): ?>
                            <form method="post" action="friend_store.php" class="m-0">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$player['id'] ?>"><input type="hidden" name="action" value="request">
                                <button class="btn btn-accent btn-sm" type="submit"><i class="bi bi-person-plus"></i> Ajouter</button>
                            </form>
                        <?php elseif ($st === 'friends'): ?>
                            <span class="meta-pill meta-pill-success align-self-center"><i class="bi bi-check-circle-fill"></i> Ami</span>
                        <?php elseif ($st === 'pending_sent'): ?>
                            <span class="meta-pill align-self-center"><i class="bi bi-hourglass-split"></i> En attente</span>
                        <?php elseif ($st === 'pending_received'): ?>
                            <form method="post" action="friend_store.php" class="m-0">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$player['id'] ?>"><input type="hidden" name="action" value="accept">
                                <button class="btn btn-accent btn-sm" type="submit"><i class="bi bi-check-lg"></i> Accepter</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
