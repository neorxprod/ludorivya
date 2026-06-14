<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Arena 3D', 'arena');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$statement = $pdo->query("
    SELECT
        g.id,
        g.title,
        g.description,
        g.live_players,
        g.cover_url,
        s.name AS studio_name,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS platform_names,
        ROUND(AVG(r.rating), 1) AS average_rating
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    LEFT JOIN game_platforms gp ON gp.game_id = g.id
    LEFT JOIN platforms p ON p.id = gp.platform_id
    LEFT JOIN reviews r ON r.game_id = g.id
    GROUP BY g.id, g.title, g.description, g.live_players, g.cover_url, s.name
    ORDER BY g.live_players DESC
");

$games = array_map(static function (array $game): array {
    return [
        'id' => (int)$game['id'],
        'title' => $game['title'],
        'description' => $game['description'],
        'players' => (int)$game['live_players'],
        'cover' => $game['cover_url'],
        'studio' => $game['studio_name'],
        'platforms' => $game['platform_names'] ?? '',
        'rating' => $game['average_rating'] !== null ? (float)$game['average_rating'] : 0,
    ];
}, $statement->fetchAll());

$arenaJson = json_encode($games, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$totalPlayers = array_sum(array_column($games, 'players'));
?>

<section class="arena-shell">
    <div class="arena-copy">
        <span class="badge text-bg-info mb-3">Three.js</span>
        <h1 class="display-5 fw-bold">L'arene 3D des jeux multijoueurs</h1>
        <p class="lead">Chaque planete represente un jeu. Plus elle est grande, plus elle a de joueurs live. La couleur montre la note moyenne des avis.</p>
        <div class="arena-kpis">
            <div>
                <span>Jeux suivis</span>
                <strong><?= count($games) ?></strong>
            </div>
            <div>
                <span>Joueurs live</span>
                <strong><?= number_format($totalPlayers, 0, ',', ' ') ?></strong>
            </div>
        </div>
    </div>

    <div class="arena-stage">
        <canvas id="arenaCanvas" aria-label="Carte 3D interactive des jeux"></canvas>
        <div class="arena-hud">
            <span class="small text-uppercase text-secondary">Jeu selectionne</span>
            <h2 id="arenaTitle" class="h4 mb-1">Survole une planete</h2>
            <p id="arenaMeta" class="mb-2 text-secondary">Clique sur une planete pour ouvrir sa fiche.</p>
            <p id="arenaDescription" class="small mb-0">La scene utilise les donnees de la base MySQL pour generer une visualisation dynamique.</p>
        </div>
    </div>
</section>

<section class="content-panel mt-4">
    <h2 class="h4">Legende</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="legend-item"><span class="legend-dot dot-cyan"></span> Tres bien note</div>
        </div>
        <div class="col-md-4">
            <div class="legend-item"><span class="legend-dot dot-amber"></span> Note moyenne</div>
        </div>
        <div class="col-md-4">
            <div class="legend-item"><span class="legend-dot dot-rose"></span> Peu d'avis ou note basse</div>
        </div>
    </div>
</section>

<script id="arena-data" type="application/json"><?= $arenaJson ?></script>
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.180.0/examples/jsm/"
    }
}
</script>
<script type="module" src="assets/js/arena3d.js"></script>

<?php render_footer(); ?>

