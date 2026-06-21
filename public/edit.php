<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$gameId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($gameId === false || $gameId === null || $gameId < 1) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

// Apres connexion, on revient directement sur cette page d'edition.
require_login('login.php?redirect=' . urlencode('edit.php?id=' . $gameId));

if ($pdo === null) {
    render_header('Modifier un jeu', 'games');
    render_database_error($databaseError);
    render_footer();
    exit;
}

$gameStatement = $pdo->prepare('SELECT * FROM games WHERE id = :id');
$gameStatement->execute(['id' => $gameId]);
$game = $gameStatement->fetch();

if (!$game) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

// Autorisation : seul le createur de la fiche peut la modifier.
if ($game['created_by'] === null || (int)$game['created_by'] !== current_user_id()) {
    flash('danger', 'Tu ne peux modifier que les jeux que tu as ajoutés.');
    redirect_to('game.php?id=' . (int)$game['id']);
}

$studios = $pdo->query('SELECT id, name FROM studios ORDER BY name')->fetchAll();
$platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();
$genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();

// Liaisons N-N actuelles, pour pre-cocher les cases.
$platformStatement = $pdo->prepare('SELECT platform_id FROM game_platforms WHERE game_id = :id');
$platformStatement->execute(['id' => $gameId]);
$checkedPlatforms = array_map('intval', array_column($platformStatement->fetchAll(), 'platform_id'));

$genreStatement = $pdo->prepare('SELECT genre_id FROM game_genres WHERE game_id = :id');
$genreStatement->execute(['id' => $gameId]);
$checkedGenres = array_map('intval', array_column($genreStatement->fetchAll(), 'genre_id'));

render_header('Modifier · ' . $game['title'], 'games');
?>

<section class="container page-head">
    <p class="eyebrow reveal">Édition</p>
    <h1 class="page-title reveal">Modifier<span class="text-soft"> « <?= e($game['title']) ?> »</span></h1>
</section>

<section class="container narrow-form">
    <form class="content-panel needs-validation reveal" method="post" action="game_update.php" novalidate data-game-form>
        <?= csrf_field() ?>
        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="title">Titre du jeu</label>
                <input class="form-control" id="title" name="title" required minlength="2" maxlength="150" value="<?= e($game['title']) ?>">
                <div class="invalid-feedback">Un titre d’au moins 2 caractères est requis.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="release_date">Date de sortie</label>
                <input class="form-control" id="release_date" name="release_date" type="date" required value="<?= e((string)$game['release_date']) ?>">
                <div class="invalid-feedback">Une date de sortie est requise.</div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="studio_id">Studio</label>
                <select class="form-select" id="studio_id" name="studio_id" required>
                    <?php foreach ($studios as $studio): ?>
                        <option value="<?= (int)$studio['id'] ?>" <?= (int)$studio['id'] === (int)$game['studio_id'] ? 'selected' : '' ?>><?= e($studio['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choisis le studio du jeu.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="age_rating">Âge minimum</label>
                <input class="form-control" id="age_rating" name="age_rating" type="number" min="3" max="18" required value="<?= (int)$game['age_rating'] ?>">
                <div class="invalid-feedback">Entre 3 et 18.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="cover_url">Jaquette <span class="text-soft">(URL http(s), ou laisser tel quel)</span></label>
                <input class="form-control" id="cover_url" name="cover_url" maxlength="500" value="<?= e((string)$game['cover_url']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" required minlength="10" maxlength="5000"><?= e($game['description']) ?></textarea>
                <div class="invalid-feedback">Une description d’au moins 10 caractères est requise.</div>
            </div>

            <div class="col-md-6">
                <fieldset>
                    <legend class="form-label">Plateformes</legend>
                    <div class="choice-grid" data-required-group="platform_ids[]">
                        <?php foreach ($platforms as $platform): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="platform_ids[]" value="<?= (int)$platform['id'] ?>" id="platform-<?= (int)$platform['id'] ?>" <?= in_array((int)$platform['id'], $checkedPlatforms, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="platform-<?= (int)$platform['id'] ?>"><?= e($platform['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback d-block group-feedback" hidden>Choisis au moins une plateforme.</div>
                </fieldset>
            </div>
            <div class="col-md-6">
                <fieldset>
                    <legend class="form-label">Genres</legend>
                    <div class="choice-grid" data-required-group="genre_ids[]">
                        <?php foreach ($genres as $genre): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="genre_ids[]" value="<?= (int)$genre['id'] ?>" id="genre-<?= (int)$genre['id'] ?>" <?= in_array((int)$genre['id'], $checkedGenres, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="genre-<?= (int)$genre['id'] ?>"><?= e($genre['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback d-block group-feedback" hidden>Choisis au moins un genre.</div>
                </fieldset>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-accent" type="submit"><i class="bi bi-check-lg"></i> Enregistrer les modifications</button>
            <a class="btn btn-ghost" href="game.php?id=<?= (int)$game['id'] ?>">Annuler</a>
        </div>
    </form>
</section>

<?php render_footer(); ?>
