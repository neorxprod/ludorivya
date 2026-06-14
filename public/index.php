<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Observatoire arcade', 'home');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$search = trim((string)($_GET['q'] ?? ''));
$platformFilter = filter_input(INPUT_GET, 'platform', FILTER_VALIDATE_INT);

$platformsStatement = $pdo->query('SELECT id, name FROM platforms ORDER BY name');
$platforms = $platformsStatement->fetchAll();

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(g.title LIKE :search OR g.description LIKE :search OR s.name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($platformFilter !== false && $platformFilter !== null) {
    $conditions[] = 'EXISTS (
        SELECT 1
        FROM game_platforms gp_filter
        WHERE gp_filter.game_id = g.id AND gp_filter.platform_id = :platform_id
    )';
    $params['platform_id'] = $platformFilter;
}

$where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$sql = "
    SELECT
        g.id,
        g.title,
        g.description,
        g.release_date,
        g.cover_url,
        g.live_players,
        s.name AS studio_name,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS platform_names,
        ROUND(AVG(r.rating), 1) AS average_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    LEFT JOIN game_platforms gp ON gp.game_id = g.id
    LEFT JOIN platforms p ON p.id = gp.platform_id
    LEFT JOIN reviews r ON r.game_id = g.id
    $where
    GROUP BY g.id, g.title, g.description, g.release_date, g.cover_url, g.live_players, s.name
    ORDER BY g.live_players DESC, g.title ASC
";

$statement = $pdo->prepare($sql);
$statement->execute($params);
$games = $statement->fetchAll();
?>

<section class="hero mb-4">
    <div class="hero-grid"></div>
    <div class="row align-items-center g-4 position-relative">
        <div class="col-lg-7">
            <span class="badge text-bg-info mb-3">Observatoire arcade</span>
            <h1 class="display-5 fw-bold mb-3">Ludorivya Arena</h1>
            <p class="lead mb-3">Un site specialise dans les jeux multijoueurs: joueurs live, plateformes, notes et fiches detaillees, avec une carte 3D interactive.</p>
            <a class="btn btn-primary btn-lg" href="arena.php"><i class="bi bi-stars"></i> Ouvrir l'arene 3D</a>
        </div>
        <div class="col-lg-5">
            <div class="live-panel">
                <span class="small text-uppercase text-secondary">Tendance live</span>
                <strong><?= number_format(array_sum(array_column($games, 'live_players')), 0, ',', ' ') ?></strong>
                <span>joueurs live suivis dans l'arene</span>
            </div>
        </div>
    </div>
</section>

<section class="toolbar mb-4">
    <form class="row g-2 align-items-end" method="get" action="index.php" data-filter-form>
        <div class="col-md-6">
            <label for="q" class="form-label">Recherche</label>
            <input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Titre, description, studio...">
        </div>
        <div class="col-md-4">
            <label for="platform" class="form-label">Plateforme</label>
            <select class="form-select" id="platform" name="platform">
                <option value="">Toutes les plateformes</option>
                <?php foreach ($platforms as $platform): ?>
                    <option value="<?= (int)$platform['id'] ?>" <?= (int)$platform['id'] === (int)$platformFilter ? 'selected' : '' ?>>
                        <?= e($platform['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary" type="submit">Filtrer</button>
        </div>
    </form>
</section>

<section class="row g-3" aria-live="polite">
    <?php if ($games === []): ?>
        <div class="col-12">
            <div class="empty-state">
                <h2 class="h5">Aucun jeu trouve</h2>
                <p>Essaie une autre recherche ou ajoute un nouveau jeu au catalogue.</p>
                <a class="btn btn-outline-light" href="create.php">Ajouter un jeu</a>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($games as $game): ?>
        <article class="col-md-6 col-xl-4">
            <a class="game-card" href="game.php?id=<?= (int)$game['id'] ?>">
                <div class="game-cover" style="background-image: url('<?= e($game['cover_url']) ?>')"></div>
                <div class="game-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <h2 class="h5 mb-1"><?= e($game['title']) ?></h2>
                        <span class="rating"><?= $game['average_rating'] !== null ? e((string)$game['average_rating']) . '/20' : 'NR' ?></span>
                    </div>
                    <p class="small text-secondary mb-2"><?= e($game['studio_name']) ?> - <?= e((string)$game['release_date']) ?></p>
                    <p class="description"><?= e($game['description']) ?></p>
                    <div class="d-flex justify-content-between gap-2 align-items-center">
                        <span class="platforms"><?= e($game['platform_names']) ?></span>
                        <span class="live-count"><?= number_format((int)$game['live_players'], 0, ',', ' ') ?> live</span>
                    </div>
                </div>
            </a>
        </article>
    <?php endforeach; ?>
</section>

<?php render_footer(); ?>
