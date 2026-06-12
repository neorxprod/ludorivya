<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

render_header('Plateformes', 'platforms');

if ($pdo === null) {
    render_database_error($databaseError);
    render_footer();
    exit;
}

$statement = $pdo->query("
    SELECT p.id, p.name, p.manufacturer, COUNT(gp.game_id) AS games_count
    FROM platforms p
    LEFT JOIN game_platforms gp ON gp.platform_id = p.id
    GROUP BY p.id, p.name, p.manufacturer
    ORDER BY games_count DESC, p.name ASC
");
$platforms = $statement->fetchAll();
?>

<section class="content-panel">
    <h1 class="h3 mb-4">Plateformes</h1>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
            <thead>
            <tr>
                <th>Plateforme</th>
                <th>Constructeur</th>
                <th class="text-end">Jeux lies</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($platforms as $platform): ?>
                <tr>
                    <td><?= e($platform['name']) ?></td>
                    <td><?= e($platform['manufacturer']) ?></td>
                    <td class="text-end"><?= (int)$platform['games_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>

