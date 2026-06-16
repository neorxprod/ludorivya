<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php?redirect=profile.php');

render_header('Mon profil', 'profile');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$userId = (int)current_user_id();

// Relation 1-1 : compte (users) + profil public (user_profiles),
// et la plateforme preferee via sa cle etrangere.
$profileStatement = $pdo->prepare("
    SELECT
        u.username, u.email, u.created_at,
        up.bio, up.favorite_platform_id,
        p.name AS favorite_platform_name
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN platforms p ON p.id = up.favorite_platform_id
    WHERE u.id = :id
");
$profileStatement->execute(['id' => $userId]);
$profile = $profileStatement->fetch();

// Ma bibliotheque (relation N-N porteuse users <-> games).
$libraryStatement = $pdo->prepare("
    SELECT g.id AS game_id, g.title, le.status, le.playtime_hours, le.added_at
    FROM library_entries le
    INNER JOIN games g ON g.id = le.game_id
    WHERE le.user_id = :id
    ORDER BY le.added_at DESC
");
$libraryStatement->execute(['id' => $userId]);
$library = $libraryStatement->fetchAll();

// Les jeux que j'ai ajoutes au catalogue (relation 1-N users -> games).
$myGamesStatement = $pdo->prepare("
    SELECT g.id, g.title, g.release_date,
           (SELECT COUNT(*) FROM reviews r WHERE r.game_id = g.id) AS review_count
    FROM games g
    WHERE g.created_by = :id
    ORDER BY g.created_at DESC
");
$myGamesStatement->execute(['id' => $userId]);
$myGames = $myGamesStatement->fetchAll();

$platforms = $pdo->query('SELECT id, name FROM platforms ORDER BY name')->fetchAll();

$totalHours = array_sum(array_map(static fn (array $row): float => (float)$row['playtime_hours'], $library));
?>

<section class="container page-head">
    <div class="profile-head reveal">
        <span class="avatar-initial avatar-initial-lg" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)$profile['username'], 0, 1))) ?></span>
        <div>
            <h1 class="page-title mb-1"><?= e($profile['username']) ?></h1>
            <p class="text-soft mb-0">
                Membre depuis le <?= format_date_fr(substr((string)$profile['created_at'], 0, 10)) ?>
                · <?= e($profile['email']) ?>
                <?php if (!empty($profile['favorite_platform_name'])): ?>
                    · joue surtout sur <strong><?= e($profile['favorite_platform_name']) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<section class="container">
    <div class="detail-layout">
        <div class="detail-main">
            <div class="content-panel reveal">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0"><i class="bi bi-bookmark-heart"></i> Ma bibliothèque</h2>
                    <span class="text-soft small"><?= count($library) ?> jeu<?= count($library) > 1 ? 'x' : '' ?> · <?= number_format($totalHours, 1, ',', ' ') ?> h de jeu</span>
                </div>
                <?php if ($library === []): ?>
                    <p class="text-soft mb-0">Ta bibliothèque est vide : ouvre une <a href="games.php">fiche de jeu</a> et clique sur « Ajouter à ma bibliothèque ».</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr><th>Jeu</th><th>Statut</th><th class="text-end">Heures</th><th>Ajouté le</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($library as $entry): ?>
                                    <tr>
                                        <td><a href="game.php?id=<?= (int)$entry['game_id'] ?>"><?= e($entry['title']) ?></a></td>
                                        <td><span class="status-badge status-<?= e($entry['status']) ?>"><?= e(library_status_label((string)$entry['status'])) ?></span></td>
                                        <td class="text-end"><?= number_format((float)$entry['playtime_hours'], 1, ',', ' ') ?></td>
                                        <td class="text-soft"><?= format_date_fr(substr((string)$entry['added_at'], 0, 10)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="content-panel reveal">
                <h2 class="h4 mb-3"><i class="bi bi-joystick"></i> Mes jeux ajoutés au catalogue</h2>
                <?php if ($myGames === []): ?>
                    <p class="text-soft mb-0">Tu n’as pas encore ajouté de jeu. <a href="create.php">Propose ta première fiche !</a></p>
                <?php else: ?>
                    <ul class="my-games-list">
                        <?php foreach ($myGames as $game): ?>
                            <li>
                                <a href="game.php?id=<?= (int)$game['id'] ?>"><strong><?= e($game['title']) ?></strong></a>
                                <span class="text-soft"><?= e(substr((string)$game['release_date'], 0, 4)) ?> · <?= (int)$game['review_count'] ?> avis</span>
                                <a class="btn btn-ghost btn-sm" href="edit.php?id=<?= (int)$game['id'] ?>"><i class="bi bi-pencil"></i> Modifier</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <aside class="detail-aside">
            <div class="content-panel reveal">
                <h2 class="h5 mb-3"><i class="bi bi-person-gear"></i> Modifier mon profil</h2>
                <form method="post" action="profile_update.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="bio">Bio publique</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500" placeholder="Quelques mots sur toi…"><?= e((string)($profile['bio'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="favorite_platform_id">Plateforme préférée</label>
                        <select class="form-select" id="favorite_platform_id" name="favorite_platform_id">
                            <option value="">Aucune préférence</option>
                            <?php foreach ($platforms as $platform): ?>
                                <option value="<?= (int)$platform['id'] ?>" <?= (int)$platform['id'] === (int)($profile['favorite_platform_id'] ?? 0) ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-accent btn-sm w-100" type="submit">Enregistrer</button>
                </form>
            </div>
        </aside>
    </div>
</section>

<?php render_footer(); ?>
