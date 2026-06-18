<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Catalogue', 'games');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

const GAMES_PER_PAGE = 9;

// Lecture des filtres GET (tous valides ou ignores).
$search = trim((string)($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 100);
$platformFilter = filter_input(INPUT_GET, 'platform', FILTER_VALIDATE_INT);
$genreFilter = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT);
$sort = (string)($_GET['sort'] ?? 'recent');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page === false || $page === null || $page < 1) ? 1 : $page;

// Listes pour les <select> des filtres.
$platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();
$genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();

// Construction du WHERE dynamique : fragments SQL constants,
// les valeurs passent toujours par des parametres prepares.
$conditions = [];
$params = [];

if ($search !== '') {
    // En prepares natifs (non emules), un parametre nomme ne peut pas etre
    // reutilise : on cree donc trois parametres pour la meme valeur.
    $conditions[] = '(g.title LIKE :search_title OR g.description LIKE :search_desc OR s.name LIKE :search_studio)';
    // On echappe les jokers SQL (% et _) pour qu'ils soient cherches tels quels.
    $like = '%' . addcslashes($search, '\\%_') . '%';
    $params['search_title'] = $like;
    $params['search_desc'] = $like;
    $params['search_studio'] = $like;
}

if ($platformFilter !== false && $platformFilter !== null) {
    // Filtre via la relation N-N games <-> platforms.
    $conditions[] = 'EXISTS (
        SELECT 1 FROM game_platforms gp
        WHERE gp.game_id = g.id AND gp.platform_id = :platform_id
    )';
    $params['platform_id'] = $platformFilter;
}

if ($genreFilter !== false && $genreFilter !== null) {
    // Filtre via la deuxieme relation N-N games <-> genres.
    $conditions[] = 'EXISTS (
        SELECT 1 FROM game_genres gg
        WHERE gg.game_id = g.id AND gg.genre_id = :genre_id
    )';
    $params['genre_id'] = $genreFilter;
}

$where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$orderBy = match ($sort) {
    'rating' => 'average_rating DESC, review_count DESC, g.title ASC',
    'title' => 'g.title ASC',
    default => 'g.release_date DESC, g.title ASC',
};

// 1) Nombre total de jeux pour la pagination (memes conditions).
$countStatement = $pdo->prepare("
    SELECT COUNT(*)
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    $where
");
$countStatement->execute($params);
$totalGames = (int)$countStatement->fetchColumn();
$totalPages = max(1, (int)ceil($totalGames / GAMES_PER_PAGE));
$page = min($page, $totalPages);
$offset = ($page - 1) * GAMES_PER_PAGE;

// 2) Page courante du catalogue.
// Les agregats (note, genres) sont calcules en sous-requetes pour eviter
// les produits cartesiens entre plusieurs LEFT JOIN agreges.
$sql = "
    SELECT
        g.id,
        g.title,
        g.cover_url,
        g.release_date,
        g.metascore,
        s.name AS studio_name,
        (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.game_id = g.id) AS average_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.game_id = g.id) AS review_count,
        (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
         FROM game_genres gg INNER JOIN genres ge ON ge.id = gg.genre_id
         WHERE gg.game_id = g.id) AS genre_names
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    $where
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

$statement = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $statement->bindValue($key, $value);
}
// LIMIT/OFFSET doivent etre lies en entiers (prepares non emules).
$statement->bindValue('limit', GAMES_PER_PAGE, PDO::PARAM_INT);
$statement->bindValue('offset', $offset, PDO::PARAM_INT);
$statement->execute();
$games = $statement->fetchAll();

// Parametres a conserver dans les liens de pagination.
$baseQuery = array_filter([
    'q' => $search,
    'platform' => ($platformFilter !== false && $platformFilter !== null) ? $platformFilter : null,
    'genre' => ($genreFilter !== false && $genreFilter !== null) ? $genreFilter : null,
    'sort' => $sort !== 'recent' ? $sort : null,
], static fn ($v) => $v !== null && $v !== '');
?>

<section class="container page-head">
    <p class="eyebrow reveal">Catalogue</p>
    <h1 class="page-title reveal">Tous les jeux<span class="text-soft">, sans exception.</span></h1>
    <p class="page-lead reveal"><?= (int)$totalGames ?> jeu<?= $totalGames > 1 ? 'x' : '' ?> trouvé<?= $totalGames > 1 ? 's' : '' ?> dans la base.</p>
</section>

<section class="container">
    <form class="filter-bar reveal" method="get" action="games.php" data-filter-form>
        <div class="filter-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="q">Recherche</label>
            <input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Titre, description ou studio…">
        </div>
        <div>
            <label class="visually-hidden" for="genre">Genre</label>
            <select class="form-select" id="genre" name="genre" data-auto-submit>
                <option value="">Tous les genres</option>
                <?php foreach ($genres as $genre): ?>
                    <option value="<?= (int)$genre['id'] ?>" <?= (int)$genre['id'] === (int)$genreFilter ? 'selected' : '' ?>><?= e($genre['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="visually-hidden" for="platform">Plateforme</label>
            <select class="form-select" id="platform" name="platform" data-auto-submit>
                <option value="">Toutes les plateformes</option>
                <?php foreach ($platforms as $platform): ?>
                    <option value="<?= (int)$platform['id'] ?>" <?= (int)$platform['id'] === (int)$platformFilter ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="visually-hidden" for="sort">Tri</label>
            <select class="form-select" id="sort" name="sort" data-auto-submit>
                <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Ordre alphabétique</option>
            </select>
        </div>
        <button class="btn btn-accent" type="submit">Filtrer</button>
    </form>

    <?php if ($games === []): ?>
        <div class="empty-state reveal">
            <i class="bi bi-controller empty-icon" aria-hidden="true"></i>
            <h2 class="h5">Aucun jeu ne correspond</h2>
            <p>Essaie une autre recherche, ou enrichis toi-même le catalogue.</p>
            <a class="btn btn-accent" href="create.php">Ajouter un jeu</a>
        </div>
    <?php else: ?>
        <div class="game-grid mt-4" aria-live="polite">
            <?php foreach ($games as $i => $game): ?>
                <?php render_game_card($game, ($i % 3) * 90); ?>
            <?php endforeach; ?>
        </div>
        <?php render_pagination($page, $totalPages, $baseQuery); ?>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
