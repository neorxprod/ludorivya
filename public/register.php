<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect_to('profile.php');
}

render_header('Inscription', 'login');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}
?>

<section class="auth-layout">
    <div class="auth-intro">
        <span class="badge text-bg-success mb-3">Nouveau joueur</span>
        <h1 class="display-6 fw-bold">Cree ton compte</h1>
        <p class="lead">Le compte sert a publier des avis et a construire plus tard une bibliotheque personnelle de jeux.</p>
    </div>

    <form class="content-panel needs-validation" method="post" action="register_store.php" novalidate>
        <h2 class="h4 mb-3">Inscription</h2>
        <div class="mb-3">
            <label class="form-label" for="username">Pseudo</label>
            <input class="form-control" id="username" name="username" maxlength="60" required autocomplete="username">
            <div class="invalid-feedback">Le pseudo est obligatoire.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="160" required autocomplete="email">
            <div class="invalid-feedback">Entre un email valide.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
            <div class="invalid-feedback">Le mot de passe doit faire au moins 8 caracteres.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="bio">Petite bio</label>
            <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500" placeholder="Ex: fan de RPG et de jeux multijoueurs"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="favorite_platform">Plateforme preferee</label>
            <input class="form-control" id="favorite_platform" name="favorite_platform" maxlength="100" placeholder="PC, Switch, PlayStation...">
        </div>
        <button class="btn btn-primary w-100" type="submit">Creer mon compte</button>
        <hr>
        <p class="mb-0">Deja inscrit ? <a href="login.php">Se connecter</a></p>
    </form>
</section>

<?php render_footer(); ?>

