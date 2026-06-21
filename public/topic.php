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

$userId = current_user_id();
$loggedIn = $userId !== null;
$voteUserId = $userId ?? 0;

// Sujet + auteur + jeu lie + score de vote + mon vote.
$topicStatement = $pdo->prepare("
    SELECT t.id, t.title, t.body, t.created_at, t.user_id,
           u.username,
           g.id AS game_id, g.title AS game_title,
           (SELECT COALESCE(SUM(value), 0) FROM topic_votes tv WHERE tv.topic_id = t.id) AS score,
           (SELECT value FROM topic_votes tv WHERE tv.topic_id = t.id AND tv.user_id = :me) AS my_vote
    FROM topics t
    INNER JOIN users u ON u.id = t.user_id
    LEFT JOIN games g ON g.id = t.game_id
    WHERE t.id = :id
");
$topicStatement->execute(['id' => $topicId, 'me' => $voteUserId]);
$topic = $topicStatement->fetch();

if (!$topic) {
    flash('warning', 'Ce sujet n’existe pas.');
    redirect_to('forum.php');
}

// Toutes les reponses du sujet, a plat, avec score + mon vote.
$repliesStatement = $pdo->prepare("
    SELECT r.id, r.body, r.created_at, r.user_id, r.parent_id, u.username,
           (SELECT COALESCE(SUM(value), 0) FROM reply_votes rv WHERE rv.reply_id = r.id) AS score,
           (SELECT value FROM reply_votes rv WHERE rv.reply_id = r.id AND rv.user_id = :me) AS my_vote
    FROM topic_replies r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.topic_id = :id
    ORDER BY r.created_at ASC, r.id ASC
");
$repliesStatement->execute(['id' => $topicId, 'me' => $voteUserId]);
$flat = $repliesStatement->fetchAll();

// On construit l'arbre des reponses (parent_id) en memoire.
$children = [];
foreach ($flat as $r) {
    $children[(int)$r['parent_id']][] = $r;
}
$replyCount = count($flat);

render_header($topic['title'], 'forum');

// Formulaire de reponse reutilisable (sujet ou reponse imbriquee).
$replyForm = static function (int $parentId = 0) use ($loggedIn, $topic): void {
    if (!$loggedIn) {
        return;
    }
    ?>
    <details class="reply-toggle">
        <summary><i class="bi bi-reply"></i> Répondre</summary>
        <form class="reply-inline" method="post" action="reply_store.php">
            <?= csrf_field() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <?php if ($parentId > 0): ?><input type="hidden" name="parent_id" value="<?= $parentId ?>"><?php endif; ?>
            <textarea class="form-control" name="body" rows="2" required minlength="2" maxlength="5000" placeholder="Ta réponse…"></textarea>
            <button class="btn btn-accent btn-sm mt-2" type="submit">Envoyer</button>
        </form>
    </details>
    <?php
};

// Rendu recursif d'une reponse et de ses enfants.
$renderReply = static function (array $r, int $depth) use (&$renderReply, $children, $userId, $loggedIn, $replyForm): void {
    $myVote = $r['my_vote'] !== null ? (int)$r['my_vote'] : 0;
    ?>
    <article class="reply-node" style="--depth: <?= min($depth, 6) ?>">
        <?= render_vote('reply', (int)$r['id'], (int)$r['score'], $myVote, $loggedIn) ?>
        <div class="reply-body">
            <div class="reply-head">
                <a class="reply-author" href="player.php?id=<?= (int)$r['user_id'] ?>"><?= e($r['username']) ?></a>
                <span class="text-soft small"><?= format_date_fr(substr((string)$r['created_at'], 0, 10)) ?></span>
                <?php if ($userId !== null && (int)$r['user_id'] === $userId): ?>
                    <form method="post" action="reply_delete.php" class="d-inline" data-confirm="Supprimer cette réponse ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="mb-2"><?= nl2br(e($r['body'])) ?></p>
            <?php $replyForm((int)$r['id']); ?>
        </div>
    </article>
    <?php
    foreach ($children[(int)$r['id']] ?? [] as $child) {
        $renderReply($child, $depth + 1);
    }
};

$topicMyVote = $topic['my_vote'] !== null ? (int)$topic['my_vote'] : 0;
?>

<section class="container page-head">
    <p class="eyebrow reveal"><a href="forum.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Retour au forum</a></p>
    <h1 class="page-title reveal" style="font-size: clamp(1.6rem, 3.5vw, 2.4rem);"><?= e($topic['title']) ?></h1>
    <?php if ($topic['game_title'] !== null): ?>
        <p class="reveal"><a class="chip" href="game.php?id=<?= (int)$topic['game_id'] ?>"><i class="bi bi-controller"></i> <?= e($topic['game_title']) ?></a></p>
    <?php endif; ?>
</section>

<section class="container" style="max-width: 940px; padding-bottom: 4rem;">
    <article class="content-panel reveal forum-post forum-post-op">
        <?= render_vote('topic', (int)$topic['id'], (int)$topic['score'], $topicMyVote, $loggedIn) ?>
        <div class="reply-body">
            <div class="reply-head">
                <a class="reply-author" href="player.php?id=<?= (int)$topic['user_id'] ?>"><?= e($topic['username']) ?></a>
                <span class="text-soft small">auteur · <?= format_date_fr(substr((string)$topic['created_at'], 0, 10)) ?></span>
                <?php if ($userId !== null && (int)$topic['user_id'] === $userId): ?>
                    <form method="post" action="topic_delete.php" class="d-inline" data-confirm="Supprimer ce sujet et toutes ses réponses ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                        <button class="btn btn-link btn-sm text-danger p-0" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="mb-2 lh-lg"><?= nl2br(e($topic['body'])) ?></p>
            <?php $replyForm(0); ?>
        </div>
    </article>

    <h2 class="h5 mt-4 mb-3"><?= $replyCount ?> réponse<?= $replyCount > 1 ? 's' : '' ?></h2>

    <?php if ($replyCount === 0): ?>
        <p class="text-soft">Aucune réponse pour l’instant.<?= $loggedIn ? ' Lance la discussion !' : '' ?></p>
    <?php else: ?>
        <div class="reply-tree">
            <?php foreach ($children[0] ?? [] as $root): ?>
                <?php $renderReply($root, 0); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$loggedIn): ?>
        <p class="text-soft mt-4"><a href="login.php?redirect=<?= e(urlencode('topic.php?id=' . (int)$topic['id'])) ?>">Connecte-toi</a> pour voter et répondre.</p>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
