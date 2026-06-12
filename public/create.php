<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Ajouter un jeu', 'create');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$studios = $pdo->query('SELECT id, name FROM studios ORDER BY name')->fetchAll();
$platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();
$genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
?>

<section class="content-panel">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ajouter un jeu</h1>
            <p class="text-secondary mb-0">Le formulaire valide les donnees cote client et le serveur revalide avant insertion PDO.</p>
        </div>
        <a class="btn btn-outline-light align-self-lg-start" href="index.php">Annuler</a>
    </div>

    <form class="needs-validation" method="post" action="store_game.php" novalidate data-game-form>
        <div class="row g-3">
            <div class="col-lg-8">
                <label class="form-label" for="title">Titre</label>
                <input class="form-control" id="title" name="title" maxlength="150" required>
                <div class="invalid-feedback">Le titre est obligatoire.</div>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="release_date">Date de sortie</label>
                <input class="form-control" type="date" id="release_date" name="release_date" required>
                <div class="invalid-feedback">La date est obligatoire.</div>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="studio_id">Studio</label>
                <select class="form-select" id="studio_id" name="studio_id" required>
                    <option value="">Choisir...</option>
                    <?php foreach ($studios as $studio): ?>
                        <option value="<?= (int)$studio['id'] ?>"><?= e($studio['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choisis un studio.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="age_rating">Age conseille</label>
                <input class="form-control" type="number" id="age_rating" name="age_rating" min="3" max="18" value="12" required>
                <div class="invalid-feedback">Age entre 3 et 18.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="live_players">Joueurs live</label>
                <input class="form-control" type="number" id="live_players" name="live_players" min="0" max="100000000" value="0" required>
                <div class="invalid-feedback">Nombre positif attendu.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="cover_url">URL image de couverture</label>
                <input class="form-control" type="url" id="cover_url" name="cover_url" maxlength="500" placeholder="https://...">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="5" maxlength="2000" required></textarea>
                <div class="invalid-feedback">La description est obligatoire.</div>
            </div>
            <div class="col-lg-6">
                <fieldset>
                    <legend class="h6">Plateformes au lancement</legend>
                    <div class="choice-grid">
                        <?php foreach ($platforms as $platform): ?>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="platform_ids[]" value="<?= (int)$platform['id'] ?>">
                                <span class="form-check-label"><?= e($platform['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-secondary mt-2">Relation N-N via <code>game_platforms</code>.</p>
                </fieldset>
            </div>
            <div class="col-lg-6">
                <fieldset>
                    <legend class="h6">Genres</legend>
                    <div class="choice-grid">
                        <?php foreach ($genres as $genre): ?>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="genre_ids[]" value="<?= (int)$genre['id'] ?>">
                                <span class="form-check-label"><?= e($genre['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-secondary mt-2">Deuxieme relation N-N via <code>game_genres</code>.</p>
                </fieldset>
            </div>
            <div class="col-12 d-grid d-md-flex justify-content-md-end">
                <button class="btn btn-primary btn-lg" type="submit">Enregistrer le jeu</button>
            </div>
        </div>
    </form>
</section>

<?php render_footer(); ?>

