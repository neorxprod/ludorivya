<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Fiche jeu', 'home');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$gameId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($gameId === false || $gameId === null) {
    flash('danger', 'Jeu introuvable.');
    redirect_to('index.php');
}

$gameStatement = $pdo->prepare("
    SELECT g.*, s.name AS studio_name, s.country AS studio_country, s.website AS studio_website
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    WHERE g.id = :id
");
$gameStatement->execute(['id' => $gameId]);
$game = $gameStatement->fetch();

if (!$game) {
    flash('danger', 'Jeu introuvable.');
    redirect_to('index.php');
}

$platformStatement = $pdo->prepare("
    SELECT p.name, p.manufacturer, gp.release_region
    FROM game_platforms gp
    INNER JOIN platforms p ON p.id = gp.platform_id
    WHERE gp.game_id = :game_id
    ORDER BY p.name
");
$platformStatement->execute(['game_id' => $gameId]);
$platforms = $platformStatement->fetchAll();

$genreStatement = $pdo->prepare("
    SELECT ge.name
    FROM game_genres gg
    INNER JOIN genres ge ON ge.id = gg.genre_id
    WHERE gg.game_id = :game_id
    ORDER BY ge.name
");
$genreStatement->execute(['game_id' => $gameId]);
$genres = $genreStatement->fetchAll();

$reviewStatement = $pdo->prepare("
    SELECT r.rating, r.comment, r.created_at, u.username
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.game_id = :game_id
    ORDER BY r.created_at DESC
");
$reviewStatement->execute(['game_id' => $gameId]);
$reviews = $reviewStatement->fetchAll();
?>

<section class="detail-header mb-4">
    <div class="detail-cover" style="background-image: url('<?= e($game['cover_url']) ?>')"></div>
    <div>
        <a class="small text-secondary text-decoration-none" href="index.php">&larr; Retour au catalogue</a>
        <h1 class="display-6 fw-bold mt-2"><?= e($game['title']) ?></h1>
        <p class="lead"><?= e($game['description']) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-primary"><?= e($game['studio_name']) ?></span>
            <span class="badge text-bg-dark"><?= e((string)$game['release_date']) ?></span>
            <span class="badge text-bg-success"><?= number_format((int)$game['live_players'], 0, ',', ' ') ?> joueurs live</span>
        </div>
    </div>
</section>

<div class="row g-4">
    <section class="col-lg-8">
        <div class="content-panel mb-4">
            <h2 class="h4">Relations du jeu</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Plateformes (N-N)</h3>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($platforms as $platform): ?>
                            <li><?= e($platform['name']) ?> <span class="text-secondary">- <?= e($platform['release_region']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Genres (N-N)</h3>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($genres as $genre): ?>
                            <li><?= e($genre['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="content-panel">
            <h2 class="h4">Avis des joueurs</h2>
            <?php if ($reviews === []): ?>
                <p class="text-secondary">Aucun avis pour le moment.</p>
            <?php endif; ?>
            <?php foreach ($reviews as $review): ?>
                <article class="review">
                    <div class="d-flex justify-content-between gap-3">
                        <strong><?= e($review['username']) ?></strong>
                        <span class="rating"><?= (int)$review['rating'] ?>/20</span>
                    </div>
                    <p class="mb-1"><?= e($review['comment']) ?></p>
                    <span class="small text-secondary"><?= e($review['created_at']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="col-lg-4">
        <div class="content-panel mb-4">
            <h2 class="h5">Studio (1-N)</h2>
            <p class="mb-1"><strong><?= e($game['studio_name']) ?></strong></p>
            <p class="text-secondary mb-2"><?= e($game['studio_country']) ?></p>
            <?php if ($game['studio_website']): ?>
                <a class="btn btn-sm btn-outline-light" href="<?= e($game['studio_website']) ?>" target="_blank" rel="noreferrer">Site officiel</a>
            <?php endif; ?>
        </div>

        <?php if (is_logged_in()): ?>
            <form class="content-panel needs-validation" method="post" action="review_store.php" novalidate data-review-form>
                <h2 class="h5">Ajouter mon avis</h2>
                <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                <div class="mb-3">
                    <label class="form-label" for="rating">Note /20</label>
                    <input class="form-control" type="number" id="rating" name="rating" min="0" max="20" required>
                    <div class="invalid-feedback">La note doit etre entre 0 et 20.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="comment">Commentaire</label>
                    <textarea class="form-control" id="comment" name="comment" rows="4" maxlength="1000" required></textarea>
                    <div class="invalid-feedback">Le commentaire est obligatoire.</div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Publier l'avis</button>
            </form>
        <?php else: ?>
            <div class="content-panel">
                <h2 class="h5">Ajouter un avis</h2>
                <p class="text-secondary">Il faut etre connecte pour publier une note sur ce jeu.</p>
                <a class="btn btn-primary w-100" href="login.php?redirect=game.php?id=<?= (int)$game['id'] ?>">Se connecter</a>
            </div>
        <?php endif; ?>
    </aside>
</div>

<?php render_footer(); ?>
