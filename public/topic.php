<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$topicId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pdo === null) {
    render_header('Forum', 'forum');
    render_database_error($databaseError);
    render_footer();
    exit;
}

if ($topicId === false || $topicId === null || $topicId < 1) {
    flash('warning', 'Ce sujet n’existe pas.');
    redirect_to('forum.php');
}

$topicStatement = $pdo->prepare("
    SELECT t.id, t.title, t.body, t.created_at, t.user_id,
           u.username,
           g.id AS game_id, g.title AS game_title, g.cover_url
    FROM topics t
    INNER JOIN users u ON u.id = t.user_id
    LEFT JOIN games g ON g.id = t.game_id
    WHERE t.id = :id
");
$topicStatement->execute(['id' => $topicId]);
$topic = $topicStatement->fetch();

if (!$topic) {
    flash('warning', 'Ce sujet n’existe pas.');
    redirect_to('forum.php');
}

$repliesStatement = $pdo->prepare("
    SELECT r.id, r.body, r.created_at, r.user_id, u.username
    FROM topic_replies r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.topic_id = :id
    ORDER BY r.created_at ASC, r.id ASC
");
$repliesStatement->execute(['id' => $topicId]);
$replies = $repliesStatement->fetchAll();

$userId = current_user_id();

render_header($topic['title'], 'forum');
?>

<section class="container page-head">
    <p class="eyebrow reveal"><a href="forum.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Retour au forum</a></p>
    <h1 class="page-title reveal" style="font-size: clamp(1.6rem, 3.5vw, 2.4rem);"><?= e($topic['title']) ?></h1>
    <?php if ($topic['game_title'] !== null): ?>
        <p class="reveal"><a class="chip" href="game.php?id=<?= (int)$topic['game_id'] ?>"><i class="bi bi-controller"></i> <?= e($topic['game_title']) ?></a></p>
    <?php endif; ?>
</section>

<section class="container" style="max-width: 920px; padding-bottom: 4rem;">
    <article class="content-panel reveal forum-post forum-post-op">
        <div class="review-quote-head">
            <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($topic['username'], 0, 1))) ?></span>
            <div>
                <strong><?= e($topic['username']) ?></strong>
                <span class="review-quote-game">auteur du sujet · <?= format_date_fr(substr((string)$topic['created_at'], 0, 10)) ?></span>
            </div>
            <?php if ($userId !== null && (int)$topic['user_id'] === $userId): ?>
                <form method="post" action="topic_delete.php" data-confirm="Supprimer ce sujet et toutes ses réponses ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                    <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                </form>
            <?php endif; ?>
        </div>
        <p class="mb-0 mt-3 lh-lg"><?= nl2br(e($topic['body'])) ?></p>
    </article>

    <?php foreach ($replies as $reply): ?>
        <article class="content-panel reveal forum-post">
            <div class="review-quote-head">
                <span class="avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($reply['username'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($reply['username']) ?></strong>
                    <span class="review-quote-game"><?= format_date_fr(substr((string)$reply['created_at'], 0, 10)) ?></span>
                </div>
                <?php if ($userId !== null && (int)$reply['user_id'] === $userId): ?>
                    <form method="post" action="reply_delete.php" data-confirm="Supprimer ta réponse ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reply_id" value="<?= (int)$reply['id'] ?>">
                        <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="mb-0 mt-3 lh-lg"><?= nl2br(e($reply['body'])) ?></p>
        </article>
    <?php endforeach; ?>

    <?php if ($userId !== null): ?>
        <form class="content-panel reveal mt-4" method="post" action="reply_store.php">
            <?= csrf_field() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <label class="form-label" for="body"><i class="bi bi-reply"></i> Ta réponse <span class="text-soft small">(+5 XP)</span></label>
            <textarea class="form-control" id="body" name="body" rows="3" required minlength="2" maxlength="5000" placeholder="Apporte ta pierre au débat…"></textarea>
            <button class="btn btn-accent btn-sm mt-3" type="submit">Répondre</button>
        </form>
    <?php else: ?>
        <p class="text-soft mt-4"><a href="login.php?redirect=<?= e(urlencode('topic.php?id=' . (int)$topic['id'])) ?>">Connecte-toi</a> pour répondre.</p>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
