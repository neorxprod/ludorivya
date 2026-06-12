<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Statistiques', 'stats');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$totals = [
    'games' => (int)$pdo->query('SELECT COUNT(*) FROM games')->fetchColumn(),
    'studios' => (int)$pdo->query('SELECT COUNT(*) FROM studios')->fetchColumn(),
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'reviews' => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
];

$topPlatforms = $pdo->query("
    SELECT p.name, COUNT(gp.game_id) AS games_count
    FROM platforms p
    INNER JOIN game_platforms gp ON gp.platform_id = p.id
    GROUP BY p.id, p.name
    ORDER BY games_count DESC, p.name ASC
")->fetchAll();

$topRated = $pdo->query("
    SELECT g.title, ROUND(AVG(r.rating), 1) AS average_rating, COUNT(r.id) AS review_count
    FROM games g
    INNER JOIN reviews r ON r.game_id = g.id
    GROUP BY g.id, g.title
    HAVING review_count > 0
    ORDER BY average_rating DESC, review_count DESC
    LIMIT 5
")->fetchAll();
?>

<section class="mb-4">
    <h1 class="h3">Statistiques relationnelles</h1>
    <p class="text-secondary">Ces indicateurs utilisent des jointures SQL et montrent la valeur de la modelisation.</p>
</section>

<section class="row g-3 mb-4">
    <?php foreach ($totals as $label => $value): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span><?= e($label) ?></span>
                <strong><?= number_format($value, 0, ',', ' ') ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<div class="row g-4">
    <section class="col-lg-6">
        <div class="content-panel h-100">
            <h2 class="h5">Jeux par plateforme</h2>
            <?php foreach ($topPlatforms as $platform): ?>
                <div class="metric-row">
                    <span><?= e($platform['name']) ?></span>
                    <strong><?= (int)$platform['games_count'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="col-lg-6">
        <div class="content-panel h-100">
            <h2 class="h5">Meilleures notes</h2>
            <?php foreach ($topRated as $game): ?>
                <div class="metric-row">
                    <span><?= e($game['title']) ?> <small class="text-secondary">(<?= (int)$game['review_count'] ?> avis)</small></span>
                    <strong><?= e((string)$game['average_rating']) ?>/20</strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php render_footer(); ?>

