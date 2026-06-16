<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php?redirect=create.php');

render_header('Ajouter un jeu', 'games');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$studios = $pdo->query('SELECT id, name FROM studios ORDER BY name')->fetchAll();
$platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();
$genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
?>

<section class="container page-head">
    <p class="eyebrow reveal">Contribution</p>
    <h1 class="page-title reveal">Ajouter un jeu<span class="text-soft"> au catalogue.</span></h1>
    <p class="page-lead reveal">La fiche t’appartiendra : toi seul pourras la modifier ou la supprimer.</p>
</section>

<section class="container narrow-form">
    <form class="content-panel needs-validation reveal" method="post" action="game_store.php" novalidate data-game-form>
        <?= csrf_field() ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="title">Titre du jeu</label>
                <input class="form-control" id="title" name="title" required minlength="2" maxlength="150" placeholder="Ex : Starlane Runners">
                <div class="invalid-feedback">Un titre d’au moins 2 caractères est requis.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="release_date">Date de sortie</label>
                <input class="form-control" id="release_date" name="release_date" type="date" required>
                <div class="invalid-feedback">Une date de sortie est requise.</div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="studio_id">Studio (relation 1-N)</label>
                <select class="form-select" id="studio_id" name="studio_id" required>
                    <option value="">Choisir un studio…</option>
                    <?php foreach ($studios as $studio): ?>
                        <option value="<?= (int)$studio['id'] ?>"><?= e($studio['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choisis le studio du jeu.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="age_rating">Âge minimum</label>
                <input class="form-control" id="age_rating" name="age_rating" type="number" min="3" max="18" required value="3">
                <div class="invalid-feedback">Entre 3 et 18.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="cover_url">URL de la jaquette <span class="text-soft">(facultatif)</span></label>
                <input class="form-control" id="cover_url" name="cover_url" type="url" maxlength="500" placeholder="https://…">
                <div class="invalid-feedback">L’URL doit commencer par http:// ou https://.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" required minlength="10" maxlength="5000" placeholder="Présente le jeu en quelques phrases…"></textarea>
                <div class="invalid-feedback">Une description d’au moins 10 caractères est requise.</div>
            </div>

            <div class="col-md-6">
                <fieldset>
                    <legend class="form-label">Plateformes <span class="text-soft">(relation N-N)</span></legend>
                    <div class="choice-grid" data-required-group="platform_ids[]">
                        <?php foreach ($platforms as $platform): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="platform_ids[]" value="<?= (int)$platform['id'] ?>" id="platform-<?= (int)$platform['id'] ?>">
                                <label class="form-check-label" for="platform-<?= (int)$platform['id'] ?>"><?= e($platform['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback d-block group-feedback" hidden>Choisis au moins une plateforme.</div>
                </fieldset>
            </div>
            <div class="col-md-6">
                <fieldset>
                    <legend class="form-label">Genres <span class="text-soft">(relation N-N)</span></legend>
                    <div class="choice-grid" data-required-group="genre_ids[]">
                        <?php foreach ($genres as $genre): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="genre_ids[]" value="<?= (int)$genre['id'] ?>" id="genre-<?= (int)$genre['id'] ?>">
                                <label class="form-check-label" for="genre-<?= (int)$genre['id'] ?>"><?= e($genre['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback d-block group-feedback" hidden>Choisis au moins un genre.</div>
                </fieldset>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-accent" type="submit"><i class="bi bi-plus-lg"></i> Ajouter le jeu</button>
            <a class="btn btn-ghost" href="games.php">Annuler</a>
        </div>
    </form>
</section>

<?php render_footer(); ?>
