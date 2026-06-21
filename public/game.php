<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$gameId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pdo === null) {
    render_header('Jeu', 'games', true);
    render_database_error($databaseError);
    render_footer();
    exit;
}

if ($gameId === false || $gameId === null || $gameId < 1) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

// Fiche du jeu : relation 1-N studios -> games + auteur de la fiche.
$gameStatement = $pdo->prepare("
    SELECT
        g.id, g.title, g.description, g.release_date, g.age_rating,
        g.cover_url, g.created_by, g.metascore, g.elo,
        s.name AS studio_name, s.country AS studio_country,
        s.founded_year AS studio_year, s.website AS studio_website,
        creator.username AS creator_name,
        (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.game_id = g.id) AS average_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.game_id = g.id) AS review_count,
        (SELECT COUNT(*) FROM duels d WHERE d.winner_game_id = g.id) AS duel_wins,
        (SELECT COUNT(*) FROM duels d WHERE d.loser_game_id = g.id) AS duel_losses
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    LEFT JOIN users creator ON creator.id = g.created_by
    WHERE g.id = :id
");
$gameStatement->execute(['id' => $gameId]);
$game = $gameStatement->fetch();

if (!$game) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

// Relation N-N : plateformes du jeu (avec l'attribut de liaison release_region).
$platformsStatement = $pdo->prepare("
    SELECT p.name, p.manufacturer, gp.release_region
    FROM game_platforms gp
    INNER JOIN platforms p ON p.id = gp.platform_id
    WHERE gp.game_id = :id
    ORDER BY p.name
");
$platformsStatement->execute(['id' => $gameId]);
$gamePlatforms = $platformsStatement->fetchAll();

// Deuxieme relation N-N : genres du jeu.
$genresStatement = $pdo->prepare("
    SELECT ge.name
    FROM game_genres gg
    INNER JOIN genres ge ON ge.id = gg.genre_id
    WHERE gg.game_id = :id
    ORDER BY ge.name
");
$genresStatement->execute(['id' => $gameId]);
$gameGenres = $genresStatement->fetchAll();

// Avis avec leur auteur.
$reviewsStatement = $pdo->prepare("
    SELECT r.user_id, r.rating, r.comment, r.created_at, u.username
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.game_id = :id
    ORDER BY r.created_at DESC, r.id DESC
");
$reviewsStatement->execute(['id' => $gameId]);
$reviews = $reviewsStatement->fetchAll();

// Donnees personnelles du visiteur connecte (bibliotheque + avis existant).
$userId = current_user_id();
$libraryEntry = null;
$myReview = null;

if ($userId !== null) {
    $libraryStatement = $pdo->prepare('SELECT status, playtime_hours FROM library_entries WHERE user_id = :user_id AND game_id = :game_id');
    $libraryStatement->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $libraryEntry = $libraryStatement->fetch() ?: null;

    $myReviewStatement = $pdo->prepare('SELECT rating, comment FROM reviews WHERE user_id = :user_id AND game_id = :game_id');
    $myReviewStatement->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $myReview = $myReviewStatement->fetch() ?: null;
}

$isOwner = $userId !== null && $game['created_by'] !== null && (int)$game['created_by'] === $userId;

// Jeux similaires : ceux qui partagent le plus de genres avec ce jeu
// (exploitation de la relation N-N game_genres dans les deux sens).
$similarStatement = $pdo->prepare("
    SELECT
        g.id, g.title, g.cover_url, g.release_date, g.metascore,
        s.name AS studio_name,
        COUNT(*) AS shared_genres,
        (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.game_id = g.id) AS average_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.game_id = g.id) AS review_count,
        (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
         FROM game_genres gg2 INNER JOIN genres ge ON ge.id = gg2.genre_id
         WHERE gg2.game_id = g.id) AS genre_names
    FROM game_genres gg
    INNER JOIN games g ON g.id = gg.game_id
    INNER JOIN studios s ON s.id = g.studio_id
    WHERE gg.genre_id IN (SELECT genre_id FROM game_genres WHERE game_id = :id)
      AND g.id <> :current_id
    GROUP BY g.id, g.title, g.cover_url, g.release_date, s.name
    ORDER BY shared_genres DESC, average_rating DESC
    LIMIT 3
");
$similarStatement->execute(['id' => $gameId, 'current_id' => $gameId]);
$similarGames = $similarStatement->fetchAll();

render_header($game['title'], 'games', true);
?>

<!-- ============ HERO SOMBRE DU JEU ============ -->
<section class="game-hero" data-bs-theme="dark">
    <?php if (!empty($game['cover_url'])): ?>
        <div class="game-hero-backdrop" style="background-image: url('<?= e($game['cover_url']) ?>')" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="container game-hero-inner">
        <div class="game-hero-cover reveal" data-cover-3d>
            <?php if (!empty($game['cover_url'])): ?>
                <img src="<?= e($game['cover_url']) ?>" alt="Jaquette de <?= e($game['title']) ?>">
            <?php else: ?>
                <div class="game-card-placeholder" aria-hidden="true"><?= e(mb_substr((string)$game['title'], 0, 1)) ?></div>
            <?php endif; ?>
        </div>
        <div class="game-hero-copy reveal" style="--reveal-delay: 90ms">
            <p class="eyebrow eyebrow-glow"><?= e($game['studio_name']) ?></p>
            <h1 class="game-hero-title"><?= e($game['title']) ?></h1>
            <div class="game-hero-meta">
                <span class="rating-pill rating-pill-lg" title="Note moyenne des joueurs">
                    <i class="bi bi-star-fill"></i>
                    <?= $game['average_rating'] !== null ? format_stars10((string)$game['average_rating']) . '/10' : 'Pas encore noté' ?>
                </span>
                <?php if ($game['metascore'] !== null): ?>
                    <span class="meta-pill" title="Note presse"><i class="bi bi-newspaper"></i> Metascore <?= (int)$game['metascore'] ?></span>
                <?php endif; ?>
                <span class="meta-pill" title="Classement communautaire du mode versus"><i class="bi bi-fire"></i> <?= (int)$game['elo'] ?> Elo · <?= (int)$game['duel_wins'] ?> V / <?= (int)$game['duel_losses'] ?> D</span>
                <span class="meta-pill"><i class="bi bi-calendar3"></i> <?= format_date_fr((string)$game['release_date']) ?></span>
                <span class="meta-pill"><i class="bi bi-person-badge"></i> <?= (int)$game['age_rating'] ?>+</span>
                <span class="meta-pill"><i class="bi bi-chat-text"></i> <?= (int)$game['review_count'] ?> avis</span>
            </div>
            <?php if ($gameGenres !== []): ?>
                <p class="game-card-genres mt-3">
                    <?php foreach ($gameGenres as $genre): ?>
                        <span class="chip chip-dark"><?= e($genre['name']) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <div class="game-hero-actions">
                <?php if ($userId === null): ?>
                    <a class="btn btn-accent" href="login.php?redirect=game.php%3Fid%3D<?= (int)$game['id'] ?>"><i class="bi bi-bookmark-plus"></i> Se connecter pour l’ajouter</a>
                <?php elseif ($libraryEntry === null): ?>
                    <form method="post" action="library_store.php" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <input type="hidden" name="status" value="souhaite">
                        <input type="hidden" name="playtime_hours" value="0">
                        <button class="btn btn-accent" type="submit"><i class="bi bi-bookmark-plus"></i> Ajouter à ma bibliothèque</button>
                    </form>
                <?php else: ?>
                    <span class="meta-pill meta-pill-success"><i class="bi bi-bookmark-check-fill"></i> Dans ma bibliothèque · <?= e(library_status_label((string)$libraryEntry['status'])) ?></span>
                <?php endif; ?>
                <?php if ($isOwner): ?>
                    <a class="btn btn-ghost" href="edit.php?id=<?= (int)$game['id'] ?>"><i class="bi bi-pencil"></i> Modifier</a>
                    <form method="post" action="game_delete.php" class="m-0" data-confirm="Supprimer définitivement « <?= e($game['title']) ?> » et toutes ses données liées ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <button class="btn btn-danger-soft" type="submit"><i class="bi bi-trash"></i> Supprimer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ CONTENU ============ -->
<section class="container section-pad-sm">
    <div class="detail-layout">
        <div class="detail-main">
            <div class="content-panel reveal">
                <h2 class="h4 mb-3">À propos du jeu</h2>
                <p class="mb-0 text-body-secondary lh-lg"><?= nl2br(e($game['description'])) ?></p>
            </div>

            <div class="content-panel reveal">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Avis des joueurs</h2>
                    <span class="text-soft small"><?= count($reviews) ?> avis · un seul avis par joueur</span>
                </div>

                <?php if ($userId !== null): ?>
                    <form class="review-form" method="post" action="review_store.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <label class="form-label mb-1">Ta note</label>
                        <div class="star-picker" data-star-picker>
                            <input type="hidden" name="stars" value="<?= $myReview !== null ? (int)round((int)$myReview['rating'] / 2) : '' ?>" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <button type="button" class="star-btn" data-star="<?= $i ?>" aria-label="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?> sur 10"><i class="bi bi-star"></i></button>
                            <?php endfor; ?>
                            <span class="star-picker-value text-soft small" data-star-value><?= $myReview !== null ? (int)round((int)$myReview['rating'] / 2) . '/10' : 'Clique sur les étoiles' ?></span>
                        </div>
                        <label class="form-label mt-3" for="comment">Un commentaire ? <span class="text-soft">(facultatif)</span></label>
                        <textarea class="form-control" id="comment" name="comment" rows="2" maxlength="1000" placeholder="Qu’en as-tu pensé ?"><?= $myReview !== null ? e($myReview['comment']) : '' ?></textarea>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-accent btn-sm" type="submit"><?= $myReview !== null ? 'Mettre à jour ma note' : 'Publier ma note' ?></button>
                        </div>
                    </form>
                    <?php if ($myReview !== null): ?>
                        <form method="post" action="review_delete.php" class="mt-2" data-confirm="Supprimer ton avis sur ce jeu ?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                            <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i> Supprimer mon avis</button>
                        </form>
                    <?php endif; ?>
                    <hr class="my-4">
                <?php else: ?>
                    <p class="text-soft"><a href="login.php?redirect=game.php%3Fid%3D<?= (int)$game['id'] ?>">Connecte-toi</a> pour publier ton avis.</p>
                <?php endif; ?>

                <?php if ($reviews === []): ?>
                    <p class="text-soft mb-0">Aucun avis pour l’instant · sois le premier&nbsp;!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <article class="review">
                            <div class="review-quote-head">
                                <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($review['username'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($review['username']) ?></strong>
                                    <span class="review-quote-game"><?= format_date_fr(substr((string)$review['created_at'], 0, 10)) ?></span>
                                </div>
                                <?= render_stars((int)$review['rating']) ?>
                            </div>
                            <?php if (trim((string)$review['comment']) !== ''): ?>
                                <p class="mb-0 mt-2 text-body-secondary"><?= nl2br(e($review['comment'])) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <aside class="detail-aside">
            <div class="content-panel reveal">
                <h2 class="h5 mb-3"><i class="bi bi-building"></i> Studio</h2>
                <p class="mb-1"><strong><?= e($game['studio_name']) ?></strong></p>
                <p class="text-soft mb-1"><?= e($game['studio_country']) ?><?= $game['studio_year'] !== null ? ' · fondé en ' . (int)$game['studio_year'] : '' ?></p>
                <?php if (!empty($game['studio_website']) && str_starts_with((string)$game['studio_website'], 'http')): ?>
                    <a class="small" href="<?= e($game['studio_website']) ?>" target="_blank" rel="noreferrer noopener">Site officiel <i class="bi bi-box-arrow-up-right"></i></a>
                <?php endif; ?>
                <?php if (!empty($game['creator_name'])): ?>
                    <hr>
                    <p class="small text-soft mb-0">Fiche ajoutée par <strong><?= e($game['creator_name']) ?></strong></p>
                <?php endif; ?>
            </div>

            <div class="content-panel reveal">
                <h2 class="h5 mb-3"><i class="bi bi-display"></i> Plateformes</h2>
                <?php if ($gamePlatforms === []): ?>
                    <p class="text-soft mb-0">Aucune plateforme renseignée.</p>
                <?php else: ?>
                    <ul class="platform-list">
                        <?php foreach ($gamePlatforms as $platform): ?>
                            <li>
                                <strong><?= e($platform['name']) ?></strong>
                                <span class="text-soft"><?= e($platform['manufacturer']) ?> · <?= e($platform['release_region']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($userId !== null && $libraryEntry !== null): ?>
                <div class="content-panel reveal">
                    <h2 class="h5 mb-3"><i class="bi bi-bookmark-heart"></i> Ma bibliothèque</h2>
                    <form method="post" action="library_store.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <label class="form-label" for="status">Statut</label>
                        <select class="form-select form-select-sm mb-2" id="status" name="status">
                            <?php foreach (['souhaite', 'en_cours', 'termine', 'abandonne'] as $status): ?>
                                <option value="<?= $status ?>" <?= $libraryEntry['status'] === $status ? 'selected' : '' ?>><?= e(library_status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label" for="playtime_hours">Heures de jeu</label>
                        <input class="form-control form-control-sm mb-3" id="playtime_hours" name="playtime_hours" type="number" min="0" max="99999" step="0.5" value="<?= e((string)$libraryEntry['playtime_hours']) ?>">
                        <div class="d-flex gap-2">
                            <button class="btn btn-accent btn-sm" type="submit">Mettre à jour</button>
                        </div>
                    </form>
                    <form method="post" action="library_delete.php" class="mt-2" data-confirm="Retirer ce jeu de ta bibliothèque ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i> Retirer de ma bibliothèque</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($similarGames !== []): ?>
        <div class="pb-5">
            <div class="section-head reveal">
                <p class="eyebrow">Dans le même univers</p>
                <h2 class="section-title">Jeux similaires</h2>
                <p class="section-sub">Sélectionnés par genres partagés, directement en SQL.</p>
            </div>
            <div class="game-grid">
                <?php foreach ($similarGames as $i => $similar): ?>
                    <?php render_game_card($similar, $i * 90); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
