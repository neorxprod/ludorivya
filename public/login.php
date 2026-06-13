<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect_to('profile.php');
}

$redirect = post_string('redirect', 200);
if ($redirect === '') {
    $redirect = trim((string)($_GET['redirect'] ?? 'profile.php'));
}

render_header('Connexion', 'login');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}
?>

<section class="auth-layout">
    <div class="auth-intro">
        <span class="badge text-bg-info mb-3">Espace joueur</span>
        <h1 class="display-6 fw-bold">Connecte-toi a Ludorivya</h1>
        <p class="lead">La connexion permet d'ajouter des jeux, publier des avis et preparer une vraie bibliotheque personnelle.</p>
        <div class="auth-demo">
            <strong>Compte de test</strong>
            <span>Email: <code>nora@example.test</code></span>
            <span>Mot de passe: <code>Ludorivya2026!</code></span>
        </div>
    </div>

    <form class="content-panel needs-validation" method="post" action="login_store.php" novalidate>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <h2 class="h4 mb-3">Connexion</h2>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required autocomplete="email">
            <div class="invalid-feedback">Entre un email valide.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
            <div class="invalid-feedback">Le mot de passe est obligatoire.</div>
        </div>
        <button class="btn btn-primary w-100" type="submit">Se connecter</button>

        <div class="auth-divider"><span>ou</span></div>
        <div class="d-grid gap-2">
            <a class="btn btn-outline-light" href="oauth_start.php?provider=google">
                <i class="bi bi-google"></i> Continuer avec Google
            </a>
            <a class="btn btn-outline-light" href="oauth_start.php?provider=apple">
                <i class="bi bi-apple"></i> Continuer avec Apple
            </a>
        </div>

        <p class="small text-secondary mt-3 mb-0">
            Google et Apple sont prevus dans la base, mais il faudra des cles OAuth et une URL HTTPS pour les activer vraiment.
        </p>
        <hr>
        <p class="mb-0">Pas encore de compte ? <a href="register.php">Creer un compte</a></p>
    </form>
</section>

<?php render_footer(); ?>

