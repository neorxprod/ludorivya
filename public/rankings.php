<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Classements', 'rankings');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Classement Elo : alimente par les duels du mode versus.
$eloRanking = $pdo->query("
    SELECT g.id, g.title, g.cover_url, g.elo, s.name AS studio_name,
           (SELECT COUNT(*) FROM duels d WHERE d.winner_game_id = g.id) AS wins,
           (SELECT COUNT(*) FROM duels d WHERE d.loser_game_id = g.id) AS losses
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    ORDER BY g.elo DESC, g.title ASC
    LIMIT 12
")->fetchAll();

// Top des notes de la communaute (au moins 2 avis).
$topRated = $pdo->query("
    SELECT g.id, g.title, ROUND(AVG(r.rating), 1) AS average_rating, COUNT(r.id) AS review_count
    FROM games g
    INNER JOIN reviews r ON r.game_id = g.id
    GROUP BY g.id, g.title
    HAVING COUNT(r.id) >= 2
    ORDER BY average_rating DESC, review_count DESC
    LIMIT 8
")->fetchAll();

// Classement des joueurs par XP (duels + avis + contributions).
$topPlayers = $pdo->query("
    SELECT u.id, u.username, u.xp,
           (SELECT COUNT(*) FROM duels d WHERE d.user_id = u.id) AS duels_count,
           (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS reviews_count
    FROM users u
    ORDER BY u.xp DESC, u.username ASC
    LIMIT 10
")->fetchAll();

$podium = array_slice($eloRanking, 0, 3);
$rest = array_slice($eloRanking, 3);
?>

<section class="container page-head">
    <span class="glow-orb glow-violet" style="width: 24rem; height: 24rem; top: -10rem; right: -6rem;"></span>
    <p class="eyebrow reveal">La hiérarchie, votée par vous</p>
    <h1 class="page-title reveal">Les <?= flame_text('classements') ?></h1>
    <p class="page-lead reveal">Le Elo bouge à chaque duel du <a href="versus.php">mode versus</a> — la communauté décide.</p>
</section>

<section class="container">
    <?php if ($podium !== []): ?>
        <div class="podium reveal">
            <?php
            // Ordre d'affichage du podium : 2e, 1er, 3e.
            $order = [];
            if (isset($podium[1])) { $order[] = [2, $podium[1]]; }
            if (isset($podium[0])) { $order[] = [1, $podium[0]]; }
            if (isset($podium[2])) { $order[] = [3, $podium[2]]; }
            ?>
            <?php foreach ($order as [$place, $game]): ?>
                <a class="podium-card podium-<?= $place ?>" href="game.php?id=<?= (int)$game['id'] ?>">
                    <span class="podium-medal"><?= $place === 1 ? '🥇' : ($place === 2 ? '🥈' : '🥉') ?></span>
                    <?php if (!empty($game['cover_url'])): ?>
                        <img src="<?= e($game['cover_url']) ?>" alt="Jaquette de <?= e($game['title']) ?>">
                    <?php endif; ?>
                    <span class="podium-body">
                        <strong><?= e($game['title']) ?></strong>
                        <span class="text-soft small"><?= e($game['studio_name']) ?></span>
                        <span class="rating-pill mt-1"><i class="bi bi-fire"></i> <?= (int)$game['elo'] ?> Elo</span>
                        <span class="text-soft small"><?= (int)$game['wins'] ?> V · <?= (int)$game['losses'] ?> D</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid mt-4">
        <div class="content-panel reveal">
            <h2 class="h5 mb-3"><i class="bi bi-fire"></i> Classement Elo (suite)</h2>
            <?php if ($rest === []): ?>
                <p class="text-soft mb-0">Lance des duels pour départager les jeux !</p>
            <?php else: ?>
                <?php foreach ($rest as $i => $game): ?>
                    <div class="rank-row">
                        <span class="rank-pos"><?= $i + 4 ?></span>
                        <span><a href="game.php?id=<?= (int)$game['id'] ?>"><?= e($game['title']) ?></a>
                            <span class="text-soft small d-block"><?= (int)$game['wins'] ?> victoires · <?= (int)$game['losses'] ?> défaites</span>
                        </span>
                        <span class="rating-pill"><i class="bi bi-fire"></i> <?= (int)$game['elo'] ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="content-panel reveal" style="--reveal-delay: 90ms">
            <h2 class="h5 mb-3"><i class="bi bi-star-fill"></i> Les mieux notés <span class="text-soft small">(2 avis minimum)</span></h2>
            <?php if ($topRated === []): ?>
                <p class="text-soft mb-0">Pas encore assez d’avis.</p>
            <?php else: ?>
                <?php foreach ($topRated as $i => $game): ?>
                    <div class="rank-row">
                        <span class="rank-pos <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>"><?= $i + 1 ?></span>
                        <span><a href="game.php?id=<?= (int)$game['id'] ?>"><?= e($game['title']) ?></a>
                            <span class="text-soft small d-block"><?= (int)$game['review_count'] ?> avis</span>
                        </span>
                        <span class="rating-pill"><i class="bi bi-star-fill"></i> <?= format_rating((string)$game['average_rating']) ?>/20</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="content-panel reveal">
            <h2 class="h5 mb-3"><i class="bi bi-lightning-charge-fill"></i> Joueurs les plus actifs</h2>
            <?php foreach ($topPlayers as $i => $player): ?>
                <div class="rank-row">
                    <span class="rank-pos <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>"><?= $i + 1 ?></span>
                    <span>
                        <strong><?= e($player['username']) ?></strong>
                        <span class="text-soft small d-block"><?= (int)$player['duels_count'] ?> duels · <?= (int)$player['reviews_count'] ?> avis</span>
                        <span class="xp-track mt-1" style="max-width: 220px;"><span class="xp-fill" style="--xp-width: <?= level_progress((int)$player['xp']) ?>%"></span></span>
                    </span>
                    <span class="level-badge">Niv. <?= level_from_xp((int)$player['xp']) ?> · <?= (int)$player['xp'] ?> XP</span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content-panel reveal" style="--reveal-delay: 90ms">
            <h2 class="h5 mb-3"><i class="bi bi-graph-up"></i> Et aussi</h2>
            <p class="text-soft">Toutes les statistiques détaillées de la base (répartitions par genre, par plateforme, totaux) restent disponibles :</p>
            <a class="btn btn-ghost btn-sm" href="stats.php"><i class="bi bi-bar-chart"></i> Voir les statistiques</a>
            <hr>
            <p class="text-soft mb-2">Envie de faire bouger ce classement ?</p>
            <a class="btn btn-accent btn-sm" href="versus.php"><i class="bi bi-lightning-fill"></i> Lancer un duel</a>
        </div>
    </div>
</section>

<?php render_footer(); ?>
