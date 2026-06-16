<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Joueurs', 'users');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Joueurs + profil (relation 1-1) + plateforme preferee (FK) + agregats
// sur la bibliotheque et les avis. Les emails restent prives.
$usersStatement = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.created_at,
        up.bio,
        p.name AS favorite_platform_name,
        (SELECT COUNT(*) FROM library_entries le WHERE le.user_id = u.id) AS library_count,
        (SELECT COALESCE(SUM(le.playtime_hours), 0) FROM library_entries le WHERE le.user_id = u.id) AS total_hours,
        (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS review_count
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN platforms p ON p.id = up.favorite_platform_id
    ORDER BY total_hours DESC, u.username ASC
");
$players = $usersStatement->fetchAll();

// Activite recente des bibliotheques (N-N porteuse affichee publiquement).
$activityStatement = $pdo->query("
    SELECT u.username, g.id AS game_id, g.title, le.status, le.playtime_hours, le.added_at
    FROM library_entries le
    INNER JOIN users u ON u.id = le.user_id
    INNER JOIN games g ON g.id = le.game_id
    ORDER BY le.added_at DESC, le.id DESC
    LIMIT 8
");
$activity = $activityStatement->fetchAll();
?>

<section class="container page-head">
    <p class="eyebrow reveal">Communauté</p>
    <h1 class="page-title reveal">Les joueurs<span class="text-soft"> de Ludorivya.</span></h1>
    <p class="page-lead reveal">Profils publics, bibliothèques et temps de jeu — sans données privées.</p>
</section>

<section class="container">
    <div class="player-grid">
        <?php foreach ($players as $i => $player): ?>
            <article class="player-card content-panel reveal" style="--reveal-delay: <?= ($i % 3) * 90 ?>ms">
                <div class="review-quote-head">
                    <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($player['username'], 0, 1))) ?></span>
                    <div>
                        <strong><?= e($player['username']) ?></strong>
                        <span class="review-quote-game">membre depuis <?= e(substr((string)$player['created_at'], 0, 4)) ?></span>
                    </div>
                    <?php if (!empty($player['favorite_platform_name'])): ?>
                        <span class="chip"><?= e($player['favorite_platform_name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($player['bio'])): ?>
                    <p class="text-soft mt-3 mb-3"><?= e($player['bio']) ?></p>
                <?php endif; ?>
                <div class="player-stats">
                    <div><strong><?= (int)$player['library_count'] ?></strong><span>jeux suivis</span></div>
                    <div><strong><?= number_format((float)$player['total_hours'], 0, ',', ' ') ?></strong><span>heures</span></div>
                    <div><strong><?= (int)$player['review_count'] ?></strong><span>avis</span></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($activity !== []): ?>
        <div class="content-panel reveal mt-4">
            <h2 class="h4 mb-3"><i class="bi bi-activity"></i> Activité récente des bibliothèques</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Joueur</th><th>Jeu</th><th>Statut</th><th class="text-end">Heures</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $entry): ?>
                            <tr>
                                <td><?= e($entry['username']) ?></td>
                                <td><a href="game.php?id=<?= (int)$entry['game_id'] ?>"><?= e($entry['title']) ?></a></td>
                                <td><span class="status-badge status-<?= e($entry['status']) ?>"><?= e(library_status_label((string)$entry['status'])) ?></span></td>
                                <td class="text-end"><?= number_format((float)$entry['playtime_hours'], 1, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
