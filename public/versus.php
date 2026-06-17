<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Versus', 'versus', true);

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// La paire du duel est choisie PAR LE SERVEUR et memorisee en session :
// impossible de voter pour une autre paire (anti-triche).
$pairStatement = $pdo->query("
    SELECT g.id, g.title, g.cover_url, g.elo, g.metascore,
           s.name AS studio_name,
           (SELECT GROUP_CONCAT(ge.name ORDER BY ge.name SEPARATOR ', ')
            FROM game_genres gg INNER JOIN genres ge ON ge.id = gg.genre_id
            WHERE gg.game_id = g.id) AS genre_names
    FROM games g
    INNER JOIN studios s ON s.id = g.studio_id
    WHERE g.cover_url IS NOT NULL
    ORDER BY RAND()
    LIMIT 2
");
$pair = $pairStatement->fetchAll();
$_SESSION['versus_pair'] = array_map(static fn (array $g): int => (int)$g['id'], $pair);

$duelsCount = (int)$pdo->query('SELECT COUNT(*) FROM duels')->fetchColumn();

$userXp = 0;
if (is_logged_in()) {
    $xpStatement = $pdo->prepare('SELECT xp FROM users WHERE id = :id');
    $xpStatement->execute(['id' => current_user_id()]);
    $userXp = (int)$xpStatement->fetchColumn();
}
?>

<section class="versus-arena" data-versus data-logged="<?= is_logged_in() ? '1' : '0' ?>" data-csrf="<?= e($_SESSION['csrf_token'] ?? '') ?>">
    <canvas class="particles-canvas" data-particles aria-hidden="true"></canvas>
    <span class="glow-orb glow-violet" style="width: 30rem; height: 30rem; top: -8rem; left: 50%; transform: translateX(-50%);"></span>
    <div class="container position-relative">
        <div class="text-center mb-4 reveal">
            <p class="eyebrow">Le choix de la communauté</p>
            <h1 class="page-title mb-1">Mode <?= flame_text('Versus') ?></h1>
            <p class="page-lead mb-0">Deux jeux. Un seul survivant. Ton vote fait bouger le classement Elo en direct.</p>
        </div>

        <div class="versus-hud reveal" data-versus-hud>
            <div class="hud-block"><span class="hud-value" data-hud-duels><?= number_format($duelsCount, 0, ',', ' ') ?></span><span class="hud-label">duels joués</span></div>
            <div class="hud-block"><span class="hud-value" data-hud-streak>0</span><span class="hud-label">série en cours</span></div>
            <?php if (is_logged_in()): ?>
                <div class="hud-block">
                    <span class="hud-value"><span class="level-badge"><i class="bi bi-lightning-charge-fill"></i> Niv. <span data-hud-level><?= level_from_xp($userXp) ?></span></span></span>
                    <span class="hud-label"><span data-hud-xp><?= $userXp ?></span> XP</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($pair) === 2): ?>
            <div class="versus-stage" data-versus-stage>
                <?php foreach ($pair as $side => $game): ?>
                    <button
                        type="button"
                        class="versus-card <?= $side === 0 ? 'versus-left' : 'versus-right' ?>"
                        data-versus-card
                        data-game-id="<?= (int)$game['id'] ?>"
                        data-game-title="<?= e($game['title']) ?>"
                        <?= !is_logged_in() ? 'disabled' : '' ?>
                    >
                        <span class="versus-cover">
                            <img src="<?= e($game['cover_url']) ?>" alt="Jaquette de <?= e($game['title']) ?>" data-versus-img>
                            <span class="versus-shine" aria-hidden="true"></span>
                        </span>
                        <span class="versus-card-body">
                            <span class="versus-title" data-versus-title><?= e($game['title']) ?></span>
                            <span class="versus-meta" data-versus-meta><?= e($game['studio_name']) ?><?= $game['genre_names'] ? ' · ' . e($game['genre_names']) : '' ?></span>
                            <span class="versus-elo">
                                <i class="bi bi-fire"></i> <span data-versus-elo><?= (int)$game['elo'] ?></span> Elo
                                <span class="versus-delta" data-versus-delta aria-live="polite"></span>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
                <div class="versus-bolt" data-versus-bolt aria-hidden="true">
                    <span class="versus-vs"><?= flame_text('VS') ?></span>
                </div>
            </div>

            <?php if (!is_logged_in()): ?>
                <div class="text-center mt-4 reveal">
                    <a class="btn btn-accent" href="login.php?redirect=versus.php">Connecte-toi pour voter</a>
                    <p class="text-soft small mt-2 mb-0">Chaque vote rapporte 5 XP et fait évoluer le classement.</p>
                </div>
            <?php else: ?>
                <p class="text-center text-soft mt-4 reveal">Clique sur ton préféré — ou utilise les flèches ← → du clavier.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><p class="mb-0">Pas assez de jeux dans la base pour lancer un duel.</p></div>
        <?php endif; ?>

        <div class="text-center mt-4 reveal">
            <a class="btn btn-ghost btn-sm" href="rankings.php"><i class="bi bi-trophy"></i> Voir le classement Elo</a>
        </div>
    </div>
</section>

<?php render_footer(); ?>
