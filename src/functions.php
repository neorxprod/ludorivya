<?php

declare(strict_types=1);

/* =========================================================
   Helpers generaux
   ========================================================= */

function e(?string $value): string
{
    // Petite fonction pratique pour eviter d'afficher du HTML dangereux.
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
    // Securite : on ne redirige que vers des pages internes du site.
    // Refuse les chemins absolus (/ ou \) et tout schema d'URL (https:, javascript:...).
    if ($path === '' || $path[0] === '/' || $path[0] === '\\' || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path) === 1) {
        $path = 'index.php';
    }

    header('Location: ' . $path);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_login(string $redirect = 'login.php'): void
{
    if (!is_logged_in()) {
        // On garde les actions importantes pour les utilisateurs connectes.
        flash('warning', 'Connecte-toi pour accéder à cette action.');
        redirect_to($redirect);
    }
}

/* =========================================================
   Protection CSRF
   ========================================================= */

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token'] ?? '') . '">';
}

function require_valid_csrf(string $redirect = 'index.php'): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');

    // hash_equals : comparaison en temps constant (anti timing attack).
    if ($expected === '' || !hash_equals($expected, $sent)) {
        flash('danger', 'Formulaire expiré ou invalide, merci de réessayer.');
        redirect_to($redirect);
    }
}

/* =========================================================
   Lecture et validation des entrees POST / GET
   ========================================================= */

function post_string(string $key, int $maxLength): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_substr($value, 0, $maxLength);
}

// Pour les mots de passe : pas de trim (les espaces font partie du secret).
function post_raw_string(string $key, int $maxLength): string
{
    return mb_substr((string)($_POST[$key] ?? ''), 0, $maxLength);
}

function post_int(string $key, int $min, int $max): ?int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < $min || $value > $max) {
        return null;
    }

    return $value;
}

function post_float(string $key, float $min, float $max): ?float
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_FLOAT);
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

    // Les checkbox arrivent en tableau, donc on nettoie chaque valeur.
    return array_values(array_filter(array_map(static function ($value): ?int {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int === false ? null : $int;
    }, $values)));
}

// Verifie qu'une date est bien au format YYYY-MM-DD et existe au calendrier.
function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

// Verifie qu'une URL d'image est http(s) (ou un chemin local assets/...).
function is_valid_cover_url(string $value): bool
{
    if (str_starts_with($value, 'assets/')) {
        return true;
    }

    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
}

/* =========================================================
   Validation du formulaire de jeu (ajout ET modification)
   ========================================================= */

// Retourne ['data' => array, 'errors' => array de messages].
// Toutes les valeurs sont revalidees cote serveur, meme si le
// JavaScript du navigateur a deja valide le formulaire.
function validate_game_form(PDO $pdo): array
{
    $errors = [];

    $title = post_string('title', 150);
    if (mb_strlen($title) < 2) {
        $errors[] = 'Le titre doit faire au moins 2 caractères.';
    }

    $description = post_string('description', 5000);
    if (mb_strlen($description) < 10) {
        $errors[] = 'La description doit faire au moins 10 caractères.';
    }

    $studioId = post_int('studio_id', 1, 1000000);
    if ($studioId !== null) {
        $studioCheck = $pdo->prepare('SELECT COUNT(*) FROM studios WHERE id = :id');
        $studioCheck->execute(['id' => $studioId]);
        if ((int)$studioCheck->fetchColumn() === 0) {
            $studioId = null;
        }
    }
    if ($studioId === null) {
        $errors[] = 'Choisis un studio valide.';
    }

    $releaseDate = post_string('release_date', 10);
    if (!is_valid_date($releaseDate)) {
        $errors[] = 'La date de sortie doit être une vraie date (AAAA-MM-JJ).';
    }

    $ageRating = post_int('age_rating', 3, 18);
    if ($ageRating === null) {
        $errors[] = 'L’âge minimum doit être compris entre 3 et 18.';
    }

    $coverUrl = post_string('cover_url', 500);
    if ($coverUrl !== '' && !is_valid_cover_url($coverUrl)) {
        $errors[] = 'La jaquette doit être une adresse http:// ou https:// (ou rester vide).';
    }

    // On ne garde que des ids qui existent vraiment dans la base.
    $validPlatformIds = array_map('intval', array_column($pdo->query('SELECT id FROM platforms')->fetchAll(), 'id'));
    $validGenreIds = array_map('intval', array_column($pdo->query('SELECT id FROM genres')->fetchAll(), 'id'));

    $platformIds = array_values(array_intersect(post_int_array('platform_ids'), $validPlatformIds));
    $genreIds = array_values(array_intersect(post_int_array('genre_ids'), $validGenreIds));

    if ($platformIds === []) {
        $errors[] = 'Choisis au moins une plateforme.';
    }
    if ($genreIds === []) {
        $errors[] = 'Choisis au moins un genre.';
    }

    return [
        'data' => [
            'title' => $title,
            'description' => $description,
            'studio_id' => $studioId,
            'release_date' => $releaseDate,
            'age_rating' => $ageRating,
            'cover_url' => $coverUrl === '' ? null : $coverUrl,
            'platform_ids' => $platformIds,
            'genre_ids' => $genreIds,
        ],
        'errors' => $errors,
    ];
}

/* =========================================================
   Formatage pour l'affichage
   ========================================================= */

function format_date_fr(?string $sqlDate): string
{
    if ($sqlDate === null || $sqlDate === '') {
        return '';
    }

    $date = date_create($sqlDate);
    return $date ? $date->format('d/m/Y') : e($sqlDate);
}

function format_rating(?string $rating): string
{
    if ($rating === null) {
        return '—';
    }

    return number_format((float)$rating, 1, ',', ' ');
}

function library_status_label(string $status): string
{
    return match ($status) {
        'souhaite' => 'Souhaité',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
        'abandonne' => 'Abandonné',
        default => $status,
    };
}

// Ajoute de l'XP a un joueur (duel, avis, contribution au catalogue).
function award_xp(PDO $pdo, int $userId, int $amount): void
{
    $statement = $pdo->prepare('UPDATE users SET xp = xp + :xp WHERE id = :id');
    $statement->execute(['xp' => max(0, $amount), 'id' => $userId]);
}

// Niveau d'un joueur a partir de son XP : palier de 250 XP, simple et lisible.
function level_from_xp(int $xp): int
{
    return 1 + intdiv(max(0, $xp), 250);
}

// Progression (0 a 100) dans le niveau courant, pour les barres d'XP.
function level_progress(int $xp): int
{
    return (int)round((max(0, $xp) % 250) / 250 * 100);
}

// Effet "flamme" facon Splaze : trois calques du meme texte (deux flous
// anime, un net) tous distordus par le filtre SVG #flame-filter injecte
// une fois dans le header. Le texte reste lisible pour les lecteurs d'ecran.
function flame_text(string $text): string
{
    $safe = e($text);
    return '<span class="flame">'
        . '<span class="flame-glow flame-glow-1" aria-hidden="true">' . $safe . '</span>'
        . '<span class="flame-glow flame-glow-2" aria-hidden="true">' . $safe . '</span>'
        . '<span class="flame-core">' . $safe . '</span>'
        . '</span>';
}

/* =========================================================
   Rendu : layout commun (header / footer)
   ========================================================= */

function render_header(string $title, string $current = '', bool $fullBleed = false): void
{
    $flash = pull_flash();
    $connectedUsername = $_SESSION['user_username'] ?? null;
    ?>
    <!doctype html>
    <html lang="fr" data-bs-theme="dark" class="no-js">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ludorivya — la médiathèque de jeux vidéo : catalogue, avis des joueurs et bibliothèque personnelle.">
        <meta name="theme-color" content="#050508">
        <title><?= e($title) ?> — Ludorivya</title>
        <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
        <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
        <link href="assets/css/styles.css" rel="stylesheet">
    </head>
    <body>
    <!-- Filtre SVG reutilise par tous les textes "flamme" (effet Splaze). -->
    <svg class="flame-filter-def" aria-hidden="true" focusable="false">
        <defs>
            <filter id="flame-filter" x="-20%" y="-40%" width="140%" height="200%">
                <feTurbulence type="fractalNoise" baseFrequency="0.015 0.08" numOctaves="3" seed="2">
                    <animate attributeName="seed" from="1" to="100" dur="8s" repeatCount="indefinite"></animate>
                </feTurbulence>
                <feDisplacementMap in="SourceGraphic" scale="6" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap>
            </filter>
        </defs>
    </svg>
    <header class="site-header" data-site-header>
        <nav class="navbar navbar-expand-lg" aria-label="Navigation principale">
            <div class="container">
                <a class="navbar-brand" href="index.php">Ludorivya<span class="brand-dot">.</span></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Ouvrir la navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav mx-auto gap-lg-1">
                        <li class="nav-item"><a class="nav-link <?= current_page('home', $current) ?>" href="index.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link <?= current_page('games', $current) ?>" href="games.php">Jeux</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-hot <?= current_page('versus', $current) ?>" href="versus.php">Versus</a></li>
                        <li class="nav-item"><a class="nav-link <?= current_page('rankings', $current) ?>" href="rankings.php">Classements</a></li>
                        <li class="nav-item"><a class="nav-link <?= current_page('users', $current) ?>" href="users.php">Joueurs</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2 nav-actions">
                        <?php if ($connectedUsername !== null): ?>
                            <a class="btn btn-accent btn-sm" href="create.php"><i class="bi bi-plus-lg"></i> Ajouter un jeu</a>
                            <div class="dropdown">
                                <button class="btn btn-ghost btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle"></i> <?= e((string)$connectedUsername) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Mon profil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="logout.php" class="m-0">
                                            <?= csrf_field() ?>
                                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right"></i> Déconnexion</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a class="btn btn-ghost btn-sm" href="login.php">Connexion</a>
                            <a class="btn btn-accent btn-sm" href="register.php">Créer un compte</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main<?= $fullBleed ? '' : ' class="page-shell"' ?>>
        <?php if ($flash !== null): ?>
            <div class="flash-wrap container">
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            </div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <span class="footer-brand">Ludorivya<span class="brand-dot">.</span></span>
                    <p class="footer-tagline">La médiathèque de jeux vidéo — SAE PHP · PDO · MySQL.</p>
                </div>
                <nav class="footer-links" aria-label="Liens du pied de page">
                    <a href="games.php">Catalogue</a>
                    <a href="versus.php">Versus</a>
                    <a href="rankings.php">Classements</a>
                    <a href="stats.php">Statistiques</a>
                </nav>
            </div>
            <p class="footer-note">Modèle relationnel : 1-1, 1-N, N-N — données de démonstration fictives.</p>
        </div>
    </footer>
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    </body>
    </html>
    <?php
}

/* =========================================================
   Rendu : composants reutilisables
   ========================================================= */

// Carte de jeu utilisee sur l'accueil et le catalogue.
// Attend : id, title, cover_url, studio_name, release_date,
//          average_rating, review_count, genre_names (peut etre null).
function render_game_card(array $game, int $revealDelay = 0): void
{
    $genres = $game['genre_names'] !== null && $game['genre_names'] !== ''
        ? explode(', ', (string)$game['genre_names'])
        : [];
    ?>
    <article class="game-card reveal" style="--reveal-delay: <?= (int)$revealDelay ?>ms">
        <a class="game-card-link" href="game.php?id=<?= (int)$game['id'] ?>">
            <div class="game-card-media">
                <?php if (!empty($game['cover_url'])): ?>
                    <img src="<?= e($game['cover_url']) ?>" alt="Jaquette de <?= e($game['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="game-card-placeholder" aria-hidden="true"><?= e(mb_substr((string)$game['title'], 0, 1)) ?></div>
                <?php endif; ?>
                <span class="game-card-rating" title="Note moyenne des joueurs sur 20">
                    <i class="bi bi-star-fill"></i>
                    <?= $game['average_rating'] !== null ? format_rating((string)$game['average_rating']) : 'NR' ?>
                </span>
                <?php if (isset($game['metascore']) && $game['metascore'] !== null): ?>
                    <?php $ms = (int)$game['metascore']; ?>
                    <span class="game-card-metascore <?= $ms >= 75 ? 'ms-good' : ($ms >= 50 ? 'ms-mid' : 'ms-bad') ?>" title="Note presse (Metascore)"><?= $ms ?></span>
                <?php endif; ?>
            </div>
            <div class="game-card-body">
                <h3 class="game-card-title"><?= e($game['title']) ?></h3>
                <p class="game-card-meta"><?= e($game['studio_name']) ?> · <?= e(substr((string)$game['release_date'], 0, 4)) ?></p>
                <?php if ($genres !== []): ?>
                    <p class="game-card-genres">
                        <?php foreach ($genres as $genre): ?>
                            <span class="chip"><?= e($genre) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        </a>
    </article>
    <?php
}

// Pagination du catalogue. $baseQuery = parametres GET a conserver (q, genre...).
// Fenetree autour de la page courante pour rester lisible avec beaucoup de pages.
function render_pagination(int $page, int $totalPages, array $baseQuery): void
{
    if ($totalPages <= 1) {
        return;
    }

    $link = static function (int $target) use ($baseQuery): string {
        return 'games.php?' . http_build_query(array_merge($baseQuery, ['page' => $target]));
    };

    // Pages affichees : la premiere, la derniere, et la page courante +/- 2.
    $windowStart = max(2, $page - 2);
    $windowEnd = min($totalPages - 1, $page + 2);
    ?>
    <nav class="d-flex justify-content-center mt-5" aria-label="Pagination du catalogue">
        <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($link(max(1, $page - 1))) ?>" aria-label="Page précédente">&laquo;</a>
            </li>
            <li class="page-item <?= $page === 1 ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($link(1)) ?>">1</a>
            </li>
            <?php if ($windowStart > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e($link($i)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item <?= $page === $totalPages ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($link($totalPages)) ?>"><?= $totalPages ?></a>
            </li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($link(min($totalPages, $page + 1))) ?>" aria-label="Page suivante">&raquo;</a>
            </li>
        </ul>
    </nav>
    <?php
}

function render_database_error(?Throwable $error): void
{
    // On loggue le detail technique cote serveur, sans l'afficher au visiteur.
    if ($error !== null) {
        error_log('[Ludorivya] Connexion base impossible : ' . $error->getMessage());
    }
    ?>
    <section class="container section-pad">
        <div class="setup-panel">
            <h1 class="h3">Base de données non connectée</h1>
            <p class="mb-2">Pour utiliser le site en local :</p>
            <ol class="mb-0">
                <li>Démarre <strong>MySQL</strong> dans le panneau XAMPP/WAMP.</li>
                <li>Importe <code>database/schema.sql</code> (voir le README).</li>
                <li>Si tes identifiants MySQL ne sont pas ceux par défaut, copie <code>config/database.example.php</code> vers <code>config/database.php</code> et adapte-les.</li>
            </ol>
        </div>
    </section>
    <?php
}
