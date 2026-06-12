<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Joueurs', 'users');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$users = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.email,
        up.avatar_url,
        up.bio,
        up.favorite_platform,
        COUNT(le.id) AS library_count,
        COALESCE(SUM(le.playtime_hours), 0) AS total_playtime
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN library_entries le ON le.user_id = u.id
    GROUP BY u.id, u.username, u.email, up.avatar_url, up.bio, up.favorite_platform
    ORDER BY u.username
")->fetchAll();

$libraryStatement = $pdo->query("
    SELECT u.username, g.title, le.status, le.playtime_hours
    FROM library_entries le
    INNER JOIN users u ON u.id = le.user_id
    INNER JOIN games g ON g.id = le.game_id
    ORDER BY u.username, le.added_at DESC
");
$libraryEntries = $libraryStatement->fetchAll();
?>

<section class="mb-4">
    <h1 class="h3">Joueurs et profils</h1>
    <p class="text-secondary">Cette page montre la relation 1-1 entre <code>users</code> et <code>user_profiles</code>, puis la bibliotheque N-N entre joueurs et jeux.</p>
</section>

<section class="row g-3 mb-4">
    <?php foreach ($users as $user): ?>
        <article class="col-md-6 col-xl-4">
            <div class="content-panel h-100">
                <div class="d-flex gap-3">
                    <img class="avatar" src="<?= e($user['avatar_url']) ?>" alt="">
                    <div>
                        <h2 class="h5 mb-1"><?= e($user['username']) ?></h2>
                        <p class="small text-secondary mb-2"><?= e($user['favorite_platform']) ?></p>
                    </div>
                </div>
                <p class="mt-3 mb-3"><?= e($user['bio']) ?></p>
                <div class="d-flex justify-content-between text-secondary small">
                    <span><?= (int)$user['library_count'] ?> jeux</span>
                    <span><?= number_format((float)$user['total_playtime'], 1, ',', ' ') ?> h</span>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="content-panel">
    <h2 class="h4">Bibliotheques utilisateur</h2>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
            <thead>
            <tr>
                <th>Joueur</th>
                <th>Jeu</th>
                <th>Statut</th>
                <th class="text-end">Temps de jeu</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($libraryEntries as $entry): ?>
                <tr>
                    <td><?= e($entry['username']) ?></td>
                    <td><?= e($entry['title']) ?></td>
                    <td><?= e($entry['status']) ?></td>
                    <td class="text-end"><?= number_format((float)$entry['playtime_hours'], 1, ',', ' ') ?> h</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>

