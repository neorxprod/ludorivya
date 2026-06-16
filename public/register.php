<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect_to('profile.php');
}

render_header('Inscription', 'register');

$platforms = [];
if ($pdo !== null) {
    $platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();
}
?>

<section class="container auth-wrap">
    <div class="auth-card content-panel reveal">
        <div class="text-center mb-4">
            <p class="eyebrow">Bienvenue</p>
            <h1 class="h3 mb-1">Créer un compte</h1>
            <p class="text-soft mb-0">Ta bibliothèque personnelle t’attend.</p>
        </div>

        <form class="needs-validation" method="post" action="register_store.php" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Pseudo</label>
                <input class="form-control" id="username" name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_\-]+" autocomplete="username">
                <div class="invalid-feedback">3 à 60 caractères : lettres, chiffres, tirets et underscores.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Adresse email</label>
                <input class="form-control" id="email" name="email" type="email" required maxlength="160" autocomplete="email">
                <div class="invalid-feedback">Une adresse email valide est requise.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input class="form-control" id="password" name="password" type="password" required minlength="8" maxlength="255" autocomplete="new-password">
                <div class="invalid-feedback">Au moins 8 caractères.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="favorite_platform_id">Plateforme préférée <span class="text-soft">(facultatif)</span></label>
                <select class="form-select" id="favorite_platform_id" name="favorite_platform_id">
                    <option value="">Aucune préférence</option>
                    <?php foreach ($platforms as $platform): ?>
                        <option value="<?= (int)$platform['id'] ?>"><?= e($platform['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label" for="bio">Quelques mots sur toi <span class="text-soft">(facultatif)</span></label>
                <textarea class="form-control" id="bio" name="bio" rows="2" maxlength="500" placeholder="Ex : fan de RPG et de jeux indés…"></textarea>
            </div>
            <button class="btn btn-accent w-100" type="submit">Créer mon compte</button>
        </form>

        <p class="text-center text-soft mt-4 mb-0">Déjà inscrit ? <a href="login.php">Connecte-toi</a></p>
    </div>
</section>

<?php render_footer(); ?>
