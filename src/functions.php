<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_page(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function post_string(string $key, int $maxLength): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_substr($value, 0, $maxLength);
}

function post_int(string $key, int $min, int $max): ?int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < $min || $value > $max) {
        return null;
    }

    return $value;
}

function post_int_array(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($value): ?int {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int === false ? null : $int;
    }, $values)));
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'jeu-video';
}

function render_header(string $title, string $current = ''): void
{
    $flash = pull_flash();
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - Ludorivya</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/styles.css" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Ludorivya</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Ouvrir la navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto gap-lg-2">
                    <li class="nav-item"><a class="nav-link <?= current_page('home', $current) ?>" href="index.php">Jeux</a></li>
                    <li class="nav-item"><a class="nav-link <?= current_page('create', $current) ?>" href="create.php">Ajouter</a></li>
                    <li class="nav-item"><a class="nav-link <?= current_page('platforms', $current) ?>" href="platforms.php">Plateformes</a></li>
                    <li class="nav-item"><a class="nav-link <?= current_page('users', $current) ?>" href="users.php">Joueurs</a></li>
                    <li class="nav-item"><a class="nav-link <?= current_page('stats', $current) ?>" href="stats.php">Statistiques</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container py-4">
        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="border-top py-4 mt-5">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small text-secondary">
            <span>Ludorivya - SAE PHP/PDO/MySQL</span>
            <span>Modele relationnel: 1-1, 1-N, N-N</span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    </body>
    </html>
    <?php
}

function render_database_error(?Throwable $error): void
{
    ?>
    <section class="setup-panel">
        <h1 class="h3">Base de donnees non connectee</h1>
        <p>Importe d'abord <code>database/schema.sql</code> dans MySQL/MariaDB, puis verifie <code>config/database.php</code> si tes identifiants XAMPP/WAMP ne sont pas ceux par defaut.</p>
        <?php if ($error !== null): ?>
            <pre class="small bg-dark text-light p-3 rounded-2 overflow-auto"><?= e($error->getMessage()) ?></pre>
        <?php endif; ?>
    </section>
    <?php
}
