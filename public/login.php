<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect_to('profile.php');
}

// Page interne vers laquelle revenir apres connexion (validee aussi cote serveur).
$redirect = trim((string)($_GET['redirect'] ?? ''));

render_header('Connexion', 'login');
?>

<section class="container auth-wrap">
    <div class="auth-card content-panel reveal">
        <div class="text-center mb-4">
            <p class="eyebrow">Bon retour</p>
            <h1 class="h3 mb-1">Connexion à Ludorivya</h1>
            <p class="text-soft mb-0">Retrouve ta bibliothèque et tes avis.</p>
        </div>

        <form class="needs-validation" method="post" action="login_store.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="mb-3">
                <label class="form-label" for="email">Adresse email</label>
                <input class="form-control" id="email" name="email" type="email" autocomplete="email" required maxlength="160">
                <div class="invalid-feedback">Une adresse email valide est requise.</div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Mot de passe</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required minlength="8">
                <div class="invalid-feedback">Ton mot de passe fait au moins 8 caractères.</div>
            </div>
            <button class="btn btn-accent w-100" type="submit">Se connecter</button>
        </form>

        <div class="auth-demo">
            <strong><i class="bi bi-info-circle"></i> Compte de démonstration</strong>
            <span>Email : <code>nora@example.test</code></span>
            <span>Mot de passe : <code>Ludorivya2026!</code></span>
        </div>

        <p class="text-center text-soft mt-4 mb-0">Pas encore de compte ? <a href="register.php">Inscris-toi</a></p>
    </div>
</section>

<?php render_footer(); ?>
