<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

render_header('Mon profil', 'profile');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$userId = (int)$_SESSION['user_id'];

$profileStatement = $pdo->prepare('
    SELECT u.username, u.email, u.created_at, up.avatar_url, up.bio, up.favorite_platform
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE u.id = :id
');
$profileStatement->execute(['id' => $userId]);
$profile = $profileStatement->fetch();

$libraryStatement = $pdo->prepare('
    SELECT g.title, le.status, le.playtime_hours
    FROM library_entries le
    INNER JOIN games g ON g.id = le.game_id
    WHERE le.user_id = :user_id
    ORDER BY le.added_at DESC
');
$libraryStatement->execute(['user_id' => $userId]);
$library = $libraryStatement->fetchAll();
?>

<section class="detail-header mb-4">
    <img class="profile-avatar" src="<?= e($profile['avatar_url']) ?>" alt="">
    <div>
        <span class="badge text-bg-primary mb-2">Profil connecte</span>
        <h1 class="display-6 fw-bold"><?= e($profile['username']) ?></h1>
        <p class="lead"><?= e($profile['bio']) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-dark"><?= e($profile['email']) ?></span>
            <span class="badge text-bg-success"><?= e($profile['favorite_platform']) ?></span>
        </div>
    </div>
</section>

<section class="content-panel">
    <h2 class="h4">Ma bibliotheque</h2>
    <?php if ($library === []): ?>
        <p class="text-secondary mb-0">Ta bibliotheque est vide pour l'instant.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                <tr>
                    <th>Jeu</th>
                    <th>Statut</th>
                    <th class="text-end">Temps</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($library as $entry): ?>
                    <tr>
                        <td><?= e($entry['title']) ?></td>
                        <td><?= e($entry['status']) ?></td>
                        <td class="text-end"><?= number_format((float)$entry['playtime_hours'], 1, ',', ' ') ?> h</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>

