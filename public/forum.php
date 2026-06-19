<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Forum', 'forum');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

// Sujets tries par derniere activite (creation du sujet ou derniere reponse).
$topics = $pdo->query("
    SELECT
        t.id, t.title, t.created_at,
        u.username,
        g.id AS game_id, g.title AS game_title,
        (SELECT COUNT(*) FROM topic_replies r WHERE r.topic_id = t.id) AS reply_count,
        COALESCE((SELECT MAX(r.created_at) FROM topic_replies r WHERE r.topic_id = t.id), t.created_at) AS last_activity
    FROM topics t
    INNER JOIN users u ON u.id = t.user_id
    LEFT JOIN games g ON g.id = t.game_id
    ORDER BY last_activity DESC
    LIMIT 50
")->fetchAll();

$gamesList = $pdo->query('SELECT id, title FROM games ORDER BY title')->fetchAll();
?>

<section class="container page-head">
    <span class="glow-orb glow-cyan" style="width: 22rem; height: 22rem; top: -8rem; right: -4rem;"></span>
    <p class="eyebrow reveal">La place du village</p>
    <h1 class="page-title reveal">Le <?= flame_text('forum') ?></h1>
    <p class="page-lead reveal">Débats, conseils et mauvaise foi assumée — comme au bon vieux temps des forums jeux vidéo.</p>
</section>

<section class="container" style="padding-bottom: 4rem;">
    <?php if (is_logged_in()): ?>
        <details class="content-panel reveal mb-4">
            <summary class="h5 mb-0" style="cursor: pointer;"><i class="bi bi-plus-circle"></i> Créer un nouveau sujet <span class="text-soft small">(+15 XP)</span></summary>
            <form class="mt-3" method="post" action="topic_store.php">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="title">Titre du sujet</label>
                        <input class="form-control" id="title" name="title" required minlength="4" maxlength="150" placeholder="Ex : Votre boss le plus dur de tous les temps ?">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="game_id">Jeu concerné <span class="text-soft">(facultatif)</span></label>
                        <select class="form-select" id="game_id" name="game_id">
                            <option value="">Discussion générale</option>
                            <?php foreach ($gamesList as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"><?= e($g['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="body">Ton message</label>
                        <textarea class="form-control" id="body" name="body" rows="3" required minlength="2" maxlength="5000" placeholder="Lance le débat…"></textarea>
                    </div>
                </div>
                <button class="btn btn-accent btn-sm mt-3" type="submit"><i class="bi bi-send"></i> Publier le sujet</button>
            </form>
        </details>
    <?php else: ?>
        <p class="text-soft reveal"><a href="login.php?redirect=forum.php">Connecte-toi</a> pour créer un sujet ou répondre.</p>
    <?php endif; ?>

    <?php if ($topics === []): ?>
        <div class="empty-state reveal">
            <i class="bi bi-chat-square-text empty-icon" aria-hidden="true"></i>
            <h2 class="h5">Aucun sujet pour l'instant</h2>
            <p>Sois le premier à lancer une discussion !</p>
        </div>
    <?php else: ?>
        <div class="content-panel reveal p-0 overflow-hidden">
            <?php foreach ($topics as $topic): ?>
                <a class="forum-row" href="topic.php?id=<?= (int)$topic['id'] ?>">
                    <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($topic['username'], 0, 1))) ?></span>
                    <span class="forum-row-main">
                        <strong><?= e($topic['title']) ?></strong>
                        <span class="text-soft small">
                            par <?= e($topic['username']) ?> · <?= format_date_fr(substr((string)$topic['created_at'], 0, 10)) ?>
                            <?php if ($topic['game_title'] !== null): ?>
                                · <span class="chip"><?= e($topic['game_title']) ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="forum-row-replies">
                        <strong><?= (int)$topic['reply_count'] ?></strong>
                        <span>rép.</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
