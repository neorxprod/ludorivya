<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Statistiques', 'stats');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Totaux generaux.
$totals = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM games) AS games_count,
        (SELECT COUNT(*) FROM studios) AS studios_count,
        (SELECT COUNT(*) FROM users) AS users_count,
        (SELECT COUNT(*) FROM reviews) AS reviews_count
")->fetch();

// Repartition des jeux par genre (relation N-N games <-> genres).
$byGenre = $pdo->query("
    SELECT ge.name, COUNT(gg.game_id) AS games_count
    FROM genres ge
    LEFT JOIN game_genres gg ON gg.genre_id = ge.id
    GROUP BY ge.id, ge.name
    ORDER BY games_count DESC, ge.name ASC
")->fetchAll();

// Repartition des jeux par plateforme (relation N-N games <-> platforms).
$byPlatform = $pdo->query("
    SELECT p.name, COUNT(gp.game_id) AS games_count
    FROM platforms p
    LEFT JOIN game_platforms gp ON gp.platform_id = p.id
    GROUP BY p.id, p.name
    ORDER BY games_count DESC, p.name ASC
")->fetchAll();

// Top 5 des jeux les mieux notes, base sur au moins 2 avis
// pour que la moyenne soit un minimum fiable (AVG + HAVING + LIMIT).
$topGames = $pdo->query("
    SELECT g.id, g.title, ROUND(AVG(r.rating), 1) AS average_rating, COUNT(r.id) AS review_count
    FROM games g
    INNER JOIN reviews r ON r.game_id = g.id
    GROUP BY g.id, g.title
    HAVING COUNT(r.id) >= 2
    ORDER BY average_rating DESC, review_count DESC
    LIMIT 5
")->fetchAll();

// Joueurs les plus actifs (SUM sur la N-N porteuse library_entries).
$topPlayers = $pdo->query("
    SELECT u.username, COALESCE(SUM(le.playtime_hours), 0) AS total_hours
    FROM users u
    LEFT JOIN library_entries le ON le.user_id = u.id
    GROUP BY u.id, u.username
    ORDER BY total_hours DESC
    LIMIT 5
")->fetchAll();

$maxGenre = max(1, (int)max(array_column($byGenre, 'games_count') ?: [1]));
$maxPlatform = max(1, (int)max(array_column($byPlatform, 'games_count') ?: [1]));
$maxHours = max(1.0, (float)max(array_column($topPlayers, 'total_hours') ?: [1]));
?>

<section class="container page-head">
    <p class="eyebrow reveal">Données live</p>
    <h1 class="page-title reveal">Les chiffres<span class="text-soft"> de la base.</span></h1>
    <p class="page-lead reveal">Chaque graphique est une requête SQL avec jointures et agrégats.</p>
</section>

<section class="container">
    <div class="stat-cards reveal">
        <div class="stat-card"><strong><?= (int)$totals['games_count'] ?></strong><span>jeux</span></div>
        <div class="stat-card"><strong><?= (int)$totals['studios_count'] ?></strong><span>studios</span></div>
        <div class="stat-card"><strong><?= (int)$totals['users_count'] ?></strong><span>joueurs</span></div>
        <div class="stat-card"><strong><?= (int)$totals['reviews_count'] ?></strong><span>avis</span></div>
    </div>

    <div class="stats-grid mt-4">
        <div class="content-panel reveal">
            <h2 class="h5 mb-3"><i class="bi bi-tags"></i> Jeux par genre</h2>
            <?php foreach ($byGenre as $row): ?>
                <div class="bar-row">
                    <span class="bar-label"><?= e($row['name']) ?></span>
                    <div class="bar-track"><div class="bar-fill" style="--bar-width: <?= round(((int)$row['games_count'] / $maxGenre) * 100) ?>%"></div></div>
                    <span class="bar-value"><?= (int)$row['games_count'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content-panel reveal" style="--reveal-delay: 90ms">
            <h2 class="h5 mb-3"><i class="bi bi-display"></i> Jeux par plateforme</h2>
            <?php foreach ($byPlatform as $row): ?>
                <div class="bar-row">
                    <span class="bar-label"><?= e($row['name']) ?></span>
                    <div class="bar-track"><div class="bar-fill bar-fill-alt" style="--bar-width: <?= round(((int)$row['games_count'] / $maxPlatform) * 100) ?>%"></div></div>
                    <span class="bar-value"><?= (int)$row['games_count'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content-panel reveal">
            <h2 class="h5 mb-3"><i class="bi bi-trophy"></i> Top des jeux les mieux notés <span class="text-soft small">(2 avis minimum)</span></h2>
            <?php if ($topGames === []): ?>
                <p class="text-soft mb-0">Pas encore d’avis dans la base.</p>
            <?php else: ?>
                <ol class="top-list">
                    <?php foreach ($topGames as $row): ?>
                        <li>
                            <a href="game.php?id=<?= (int)$row['id'] ?>"><?= e($row['title']) ?></a>
                            <span class="rating-pill"><i class="bi bi-star-fill"></i> <?= format_rating((string)$row['average_rating']) ?>/20</span>
                            <span class="text-soft small"><?= (int)$row['review_count'] ?> avis</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <div class="content-panel reveal" style="--reveal-delay: 90ms">
            <h2 class="h5 mb-3"><i class="bi bi-fire"></i> Joueurs les plus actifs</h2>
            <?php foreach ($topPlayers as $row): ?>
                <div class="bar-row">
                    <span class="bar-label"><?= e($row['username']) ?></span>
                    <div class="bar-track"><div class="bar-fill" style="--bar-width: <?= round(((float)$row['total_hours'] / $maxHours) * 100) ?>%"></div></div>
                    <span class="bar-value"><?= number_format((float)$row['total_hours'], 0, ',', ' ') ?> h</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php render_footer(); ?>
