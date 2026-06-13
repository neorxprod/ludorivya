<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$provider = trim((string)($_GET['provider'] ?? ''));

if (!in_array($provider, ['google', 'apple'], true)) {
    flash('danger', 'Fournisseur OAuth inconnu.');
    redirect_to('login.php');
}

flash('info', ucfirst($provider) . ' est prevu dans la base, mais il faut creer les cles OAuth et activer HTTPS avant de le connecter vraiment.');
redirect_to('login.php');

