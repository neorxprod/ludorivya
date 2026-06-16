<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Accueil', 'home', true);

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Jeux les mieux notes (relation 1-N studios + N-N plateformes + avis agreges).
$topStatement = $pdo->query("
    SELECT
        g.id,
        g.title,
        g.cover_url,
        g.release_date,
        s.name AS studio_name,
        (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.game_id = g.id) AS average_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.game_id = g.id) AS review_count,
        (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
         FROM game_genres gg INNER JOIN genres ge ON ge.id = gg.genre_id
         WHERE gg.game_id = g.id) AS genre_names
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    ORDER BY average_rating DESC, review_count DESC
    LIMIT 3
");
$topGames = $topStatement->fetchAll();

// Chiffres cles pour la section sombre (agregats simples).
$counts = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM games) AS games_count,
        (SELECT COUNT(*) FROM users) AS users_count,
        (SELECT COUNT(*) FROM reviews) AS reviews_count,
        (SELECT COALESCE(ROUND(SUM(playtime_hours)), 0) FROM library_entries) AS hours_count
")->fetch();

// Derniers avis publies (jointure reviews + users + games).
$reviewsStatement = $pdo->query("
    SELECT r.rating, r.comment, r.created_at, u.username, g.id AS game_id, g.title AS game_title
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN games g ON g.id = r.game_id
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 3
");
$latestReviews = $reviewsStatement->fetchAll();
?>

<!-- ============ HERO ============ -->
<section class="hero-landing">
    <div class="container">
        <div class="hero-copy reveal">
            <p class="eyebrow">Médiathèque de jeux vidéo</p>
            <h1 class="hero-title">Tous tes jeux.<br><span class="text-gradient">Une seule bibliothèque.</span></h1>
            <p class="hero-lead">Explore le catalogue, note tes jeux préférés et construis une bibliothèque qui te ressemble — propulsée par PHP, PDO et MySQL.</p>
            <div class="hero-actions">
                <a class="btn btn-accent btn-lg" href="games.php">Explorer le catalogue</a>
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-ghost btn-lg" href="register.php">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        // La scene 3D n'affiche que des jeux qui ont une jaquette.
        $collageGames = array_values(array_filter($topGames, static fn (array $g): bool => !empty($g['cover_url'])));
        ?>
        <?php if (count($collageGames) >= 3): ?>
            <div class="hero-stage" aria-hidden="true" data-hero-stage>
                <div class="collage-card c-1"><img src="<?= e($collageGames[1]['cover_url']) ?>" alt=""></div>
                <div class="collage-card c-2"><img src="<?= e($collageGames[0]['cover_url']) ?>" alt=""></div>
                <div class="collage-card c-3"><img src="<?= e($collageGames[2]['cover_url']) ?>" alt=""></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ JEUX A LA UNE ============ -->
<section class="section-pad">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">À la une</p>
            <h2 class="section-title">Les mieux notés par les joueurs</h2>
        </div>
        <div class="game-grid">
            <?php foreach ($topGames as $i => $game): ?>
                <?php render_game_card($game, $i * 90); ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5 reveal">
            <a class="btn btn-outline-ink" href="games.php">Voir les <?= (int)$counts['games_count'] ?> jeux du catalogue <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ============ SHOWCASE 3D AU SCROLL ============ -->
<?php if (count($collageGames) >= 3): ?>
<section class="showcase" data-showcase data-step="0">
    <div class="showcase-sticky">
        <div class="container">
            <div class="showcase-grid">
                <div>
                    <p class="eyebrow eyebrow-glow">L'expérience Ludorivya</p>
                    <div class="showcase-steps">
                        <div class="showcase-step">
                            <h3>Explore <span class="text-gradient-cyan">le catalogue.</span></h3>
                            <p>Recherche par titre, studio ou description, filtre par genre et par plateforme. Chaque carte sort tout droit de la base relationnelle.</p>
                        </div>
                        <div class="showcase-step">
                            <h3>Construis <span class="text-gradient">ta bibliothèque.</span></h3>
                            <p>Ajoute tes jeux, passe-les de « souhaité » à « terminé » et suis tes heures de jeu, sauvegardées en SQL.</p>
                        </div>
                        <div class="showcase-step">
                            <h3>Partage <span class="text-gradient-rose">tes avis.</span></h3>
                            <p>Une note sur 20, un commentaire, un seul avis par jeu — et le classement de la communauté se met à jour instantanément.</p>
                        </div>
                    </div>
                    <div class="showcase-progress" aria-hidden="true"><span></span><span></span><span></span></div>
                </div>
                <div class="showcase-visual" aria-hidden="true">
                    <div class="showcase-card">
                        <?php foreach (array_slice($collageGames, 0, 3) as $showcaseGame): ?>
                            <div class="showcase-face">
                                <img src="<?= e($showcaseGame['cover_url']) ?>" alt="">
                                <span class="showcase-caption"><?= e($showcaseGame['title']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ SECTION SOMBRE : CHIFFRES ============ -->
<section class="section-dark section-pad" data-bs-theme="dark">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow eyebrow-glow">La communauté</p>
            <h2 class="section-title">Une bibliothèque vivante</h2>
            <p class="section-sub">Chaque chiffre sort directement de la base relationnelle, en temps réel.</p>
        </div>
        <div class="stat-band">
            <div class="stat-block reveal" style="--reveal-delay: 0ms">
                <span class="stat-number" data-count-to="<?= (int)$counts['games_count'] ?>">0</span>
                <span class="stat-label">jeux référencés</span>
            </div>
            <div class="stat-block reveal" style="--reveal-delay: 90ms">
                <span class="stat-number" data-count-to="<?= (int)$counts['users_count'] ?>">0</span>
                <span class="stat-label">joueurs inscrits</span>
            </div>
            <div class="stat-block reveal" style="--reveal-delay: 180ms">
                <span class="stat-number" data-count-to="<?= (int)$counts['reviews_count'] ?>">0</span>
                <span class="stat-label">avis publiés</span>
            </div>
            <div class="stat-block reveal" style="--reveal-delay: 270ms">
                <span class="stat-number" data-count-to="<?= (int)$counts['hours_count'] ?>">0</span>
                <span class="stat-label">heures de jeu suivies</span>
            </div>
        </div>
    </div>
</section>

<!-- ============ DERNIERS AVIS ============ -->
<section class="section-pad">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Avis récents</p>
            <h2 class="section-title">Ce que disent les joueurs</h2>
        </div>
        <div class="review-grid">
            <?php foreach ($latestReviews as $i => $review): ?>
                <blockquote class="review-quote reveal" style="--reveal-delay: <?= $i * 90 ?>ms">
                    <div class="review-quote-head">
                        <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($review['username'], 0, 1))) ?></span>
                        <div>
                            <strong><?= e($review['username']) ?></strong>
                            <span class="review-quote-game">à propos de <a href="game.php?id=<?= (int)$review['game_id'] ?>"><?= e($review['game_title']) ?></a></span>
                        </div>
                        <span class="rating-pill"><i class="bi bi-star-fill"></i> <?= (int)$review['rating'] ?>/20</span>
                    </div>
                    <p class="review-quote-text">« <?= e($review['comment']) ?> »</p>
                </blockquote>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ CTA FINAL (SOMBRE) ============ -->
<section class="section-dark section-cta section-pad" data-bs-theme="dark">
    <div class="container text-center">
        <h2 class="cta-title reveal">Prêt à construire ta bibliothèque&nbsp;?</h2>
        <p class="section-sub reveal">Crée un compte, note tes jeux et suis ton temps de jeu.</p>
        <div class="reveal">
            <?php if (is_logged_in()): ?>
                <a class="btn btn-accent btn-lg" href="profile.php">Ouvrir mon profil</a>
            <?php else: ?>
                <a class="btn btn-accent btn-lg" href="register.php">Créer un compte gratuitement</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php render_footer(); ?>
