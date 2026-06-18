<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Accueil', 'home', true);

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Jeux les mieux notes (1-N studios + avis agreges).
$topStatement = $pdo->query("
    SELECT
        g.id, g.title, g.cover_url, g.release_date, g.metascore,
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
$collageGames = array_values(array_filter($topGames, static fn (array $g): bool => !empty($g['cover_url'])));

// Chiffres cles.
$counts = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM games) AS games_count,
        (SELECT COUNT(*) FROM users) AS users_count,
        (SELECT COUNT(*) FROM reviews) AS reviews_count,
        (SELECT COALESCE(ROUND(SUM(playtime_hours)), 0) FROM library_entries) AS hours_count
")->fetch();

// Derniers avis.
$latestReviews = $pdo->query("
    SELECT r.rating, r.comment, r.created_at, u.username, g.id AS game_id, g.title AS game_title
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN games g ON g.id = r.game_id
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT 3
")->fetchAll();

$heroCover = $collageGames[0]['cover_url'] ?? '';

// Jaquettes pour le bandeau defilant (marquee).
$marqueeGames = $pdo->query("
    SELECT id, title, cover_url FROM games
    WHERE cover_url IS NOT NULL
    ORDER BY RAND()
    LIMIT 18
")->fetchAll();

// Deux jeux pour l'apercu du mode versus.
$versusTeaser = $pdo->query("
    SELECT id, title, cover_url FROM games
    WHERE cover_url IS NOT NULL
    ORDER BY RAND()
    LIMIT 2
")->fetchAll();
?>

<!-- ============ HERO ============ -->
<section class="hero-landing">
    <?php if ($heroCover !== ''): ?>
        <div class="hero-bg" style="background-image: url('<?= e($heroCover) ?>')"></div>
    <?php endif; ?>
    <span class="glow-orb glow-violet" style="width: 32rem; height: 32rem; top: -8rem; right: -6rem;"></span>
    <span class="glow-orb glow-cyan" style="width: 24rem; height: 24rem; bottom: -6rem; left: -4rem;"></span>
    <div class="container">
        <div class="hero-copy reveal">
            <p class="eyebrow">Le réseau de tes jeux vidéo</p>
            <h1 class="hero-title">Rejoins les<br><?= flame_text('joueurs') ?></h1>
            <p class="hero-lead">Rassemble ta collection, note les jeux que tu termines, grimpe dans les classements et compare-toi à toute la communauté.</p>
            <div class="hero-actions">
                <a class="btn btn-accent" href="games.php">Explorer le catalogue</a>
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-ghost" href="register.php">Rejoindre</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (count($collageGames) >= 3): ?>
            <div class="hero-stage" aria-hidden="true" data-hero-stage>
                <div class="collage-card c-1"><img src="<?= e($collageGames[1]['cover_url']) ?>" alt=""></div>
                <div class="collage-card c-2"><img src="<?= e($collageGames[0]['cover_url']) ?>" alt=""></div>
                <div class="collage-card c-3"><img src="<?= e($collageGames[2]['cover_url']) ?>" alt=""></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ MARQUEE DE JAQUETTES ============ -->
<?php if (count($marqueeGames) >= 8): ?>
<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        <?php for ($loop = 0; $loop < 2; $loop++): ?>
            <?php foreach ($marqueeGames as $mGame): ?>
                <a href="game.php?id=<?= (int)$mGame['id'] ?>" tabindex="-1"><img src="<?= e($mGame['cover_url']) ?>" alt="" loading="lazy"></a>
            <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============ ETAPES ============ -->
<section class="section-pad">
    <div class="grid-bg"></div>
    <span class="glow-orb glow-violet" style="width: 26rem; height: 26rem; top: 4rem; left: -8rem;"></span>
    <div class="container">
        <div class="section-head reveal mx-auto text-center">
            <h2 class="section-title">Tous tes jeux dans <span class="text-flame">une seule appli</span></h2>
        </div>
        <div class="row g-5 align-items-center">
            <div class="col-lg-4">
                <div class="steps-row">
                    <div class="step-item reveal">
                        <span class="step-num text-flame">01</span>
                        <div><h3>Explore le catalogue</h3><p>Cherche par titre, studio ou genre et découvre de nouveaux jeux.</p></div>
                    </div>
                    <div class="step-item reveal" style="--reveal-delay: 90ms">
                        <span class="step-num text-flame">02</span>
                        <div><h3>Construis ta bibliothèque</h3><p>Ajoute tes jeux, suis leur statut et tes heures de jeu.</p></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <?php if (count($collageGames) >= 3): ?>
                    <div class="hero-stage reveal" aria-hidden="true" data-hero-stage style="height: 360px;">
                        <div class="collage-card c-1"><img src="<?= e($collageGames[2]['cover_url']) ?>" alt=""></div>
                        <div class="collage-card c-2"><img src="<?= e($collageGames[0]['cover_url']) ?>" alt=""></div>
                        <div class="collage-card c-3"><img src="<?= e($collageGames[1]['cover_url']) ?>" alt=""></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <div class="steps-row">
                    <div class="step-item reveal">
                        <span class="step-num text-flame">03</span>
                        <div><h3>Note les jeux que tu termines</h3><p>Donne ton avis et aide la communauté à se faire une idée.</p></div>
                    </div>
                    <div class="step-item reveal" style="--reveal-delay: 90ms">
                        <span class="step-num text-flame">04</span>
                        <div><h3>Compare-toi aux autres</h3><p>Gagne de l'XP, grimpe les niveaux et vise le haut du classement.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ JEUX A LA UNE ============ -->
<section class="section-pad section-dark">
    <span class="glow-orb glow-rose" style="width: 26rem; height: 26rem; bottom: -6rem; right: -6rem;"></span>
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">À la une</p>
            <h2 class="section-title">Les mieux notés <span class="text-flame">par les joueurs</span></h2>
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

<!-- ============ PROMO VERSUS ============ -->
<?php if (count($versusTeaser) === 2): ?>
<section class="section-pad">
    <div class="grid-bg"></div>
    <span class="glow-orb glow-cyan" style="width: 24rem; height: 24rem; top: 30%; left: -8rem;"></span>
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <p class="eyebrow reveal">Nouveau · le jeu dans le jeu</p>
                <h2 class="section-title reveal">Mode <?= flame_text('Versus') ?></h2>
                <p class="section-sub reveal">Deux jeux face à face, un seul clic. Ton vote fait monter ou descendre leur score Elo, et le classement de la communauté bouge en direct. Simple. Addictif. Impitoyable.</p>
                <div class="reveal mt-4 d-flex gap-3 flex-wrap">
                    <a class="btn btn-accent" href="versus.php"><i class="bi bi-lightning-fill"></i> Lancer un duel</a>
                    <a class="btn btn-ghost" href="rankings.php">Voir le classement</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="versus-stage reveal" style="max-width: 460px; pointer-events: none;" aria-hidden="true">
                    <span class="versus-card versus-left">
                        <span class="versus-cover"><img src="<?= e($versusTeaser[0]['cover_url']) ?>" alt=""></span>
                    </span>
                    <span class="versus-card versus-right">
                        <span class="versus-cover"><img src="<?= e($versusTeaser[1]['cover_url']) ?>" alt=""></span>
                    </span>
                    <span class="versus-bolt"><span class="versus-vs"><?= flame_text('VS') ?></span></span>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ CHIFFRES ============ -->
<section class="section-pad">
    <div class="grid-bg"></div>
    <div class="container">
        <div class="section-head reveal mx-auto text-center">
            <p class="eyebrow eyebrow-glow">La communauté</p>
            <h2 class="section-title">Une bibliothèque vivante</h2>
            <p class="section-sub">Chaque chiffre sort directement de la base relationnelle.</p>
        </div>
        <div class="stat-band">
            <div class="stat-block reveal"><span class="stat-number" data-count-to="<?= (int)$counts['games_count'] ?>">0</span><span class="stat-label">jeux</span></div>
            <div class="stat-block reveal" style="--reveal-delay: 90ms"><span class="stat-number" data-count-to="<?= (int)$counts['users_count'] ?>">0</span><span class="stat-label">joueurs</span></div>
            <div class="stat-block reveal" style="--reveal-delay: 180ms"><span class="stat-number" data-count-to="<?= (int)$counts['reviews_count'] ?>">0</span><span class="stat-label">avis publiés</span></div>
            <div class="stat-block reveal" style="--reveal-delay: 270ms"><span class="stat-number" data-count-to="<?= (int)$counts['hours_count'] ?>">0</span><span class="stat-label">heures suivies</span></div>
        </div>
    </div>
</section>

<!-- ============ FONCTIONNALITES ============ -->
<section class="section-pad section-dark">
    <span class="glow-orb glow-violet" style="width: 28rem; height: 28rem; top: 2rem; right: -8rem;"></span>
    <div class="container">
        <div class="section-head reveal mx-auto text-center">
            <h2 class="section-title">Pensé pour <span class="text-flame">les joueurs</span></h2>
        </div>
        <div class="d-flex flex-column gap-4">
            <div class="feature-card reveal">
                <i class="bi bi-collection-play feature-card-icon"></i>
                <div>
                    <h3>Un catalogue complet</h3>
                    <p>Recherche, filtres par genre et plateforme, tri et pagination. Chaque fiche détaille studio, plateformes, genres et avis.</p>
                </div>
            </div>
            <div class="feature-card reverse reveal">
                <i class="bi bi-bookmark-heart feature-card-icon"></i>
                <div>
                    <h3>Ta bibliothèque personnelle</h3>
                    <p>Ajoute tes jeux, passe-les de « souhaité » à « terminé », suis tes heures de jeu — tout est sauvegardé dans la base.</p>
                </div>
            </div>
            <div class="feature-card reveal">
                <i class="bi bi-trophy feature-card-icon"></i>
                <div>
                    <h3>Compare-toi à la communauté</h3>
                    <p>Statistiques en direct, top des jeux les mieux notés et classement des joueurs les plus actifs.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ DERNIERS AVIS ============ -->
<?php if ($latestReviews !== []): ?>
<section class="section-pad">
    <div class="grid-bg"></div>
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Avis récents</p>
            <h2 class="section-title">Ce que disent <span class="text-flame">les joueurs</span></h2>
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
<?php endif; ?>

<!-- ============ CTA ============ -->
<section class="section-pad section-dark section-cta">
    <span class="glow-orb glow-violet" style="width: 30rem; height: 30rem; top: 50%; left: 50%; transform: translate(-50%, -50%);"></span>
    <div class="container">
        <h2 class="cta-title reveal">Rejoins <?= flame_text('Ludorivya') ?></h2>
        <p class="section-sub reveal">Crée ton compte, construis ta bibliothèque et grimpe dans les classements.</p>
        <div class="reveal mt-4">
            <?php if (is_logged_in()): ?>
                <a class="btn btn-accent" href="profile.php">Ouvrir mon profil</a>
            <?php else: ?>
                <a class="btn btn-accent" href="register.php">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php render_footer(); ?>
