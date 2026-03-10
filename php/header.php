<?php
// php/header.php  — inclure en haut de chaque page
// Usage: include __DIR__.'/../php/header.php';
// Nécessite que $pageTitle soit défini avant l'include.
if (!isset($pageTitle)) $pageTitle = 'Omnes MarketPlace';
$root = defined('ROOT') ? ROOT : '..';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — Omnes MarketPlace</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="<?= $root ?>/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="wrapper">

<!-- HEADER -->
<header class="header">
  <a href="<?= $root ?>/index.php" class="logo">
    <span class="logo-icon">🏛</span>
    Omnes <span>MarketPlace</span>
  </a>

  <nav class="nav">
    <a href="<?= $root ?>/index.php"                class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">
      <i class="fa fa-home"></i> Accueil
    </a>
    <a href="<?= $root ?>/pages/catalogue.php"      class="<?= basename($_SERVER['PHP_SELF'])=='catalogue.php'?'active':'' ?>">
      <i class="fa fa-th-large"></i> Tout Parcourir
    </a>
    <?php if (isLoggedIn()): ?>
    <a href="<?= $root ?>/pages/notifications.php"  class="<?= basename($_SERVER['PHP_SELF'])=='notifications.php'?'active':'' ?>">
      <i class="fa fa-bell"></i> Notifications
      <?php
        $nbNotif = 0;
        try {
          $s = getPDO()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0');
          $s->execute([currentUserId()]);
          $nbNotif = (int)$s->fetchColumn();
        } catch(Exception $e) {}
        if ($nbNotif > 0): ?>
        <span class="nav-badge"><?= $nbNotif ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $root ?>/pages/panier.php"         class="<?= basename($_SERVER['PHP_SELF'])=='panier.php'?'active':'' ?>">
      <i class="fa fa-shopping-cart"></i> Panier
      <?php
        $nbPanier = 0;
        try {
          $s = getPDO()->prepare('SELECT COUNT(*) FROM panier WHERE acheteur_id=?');
          $s->execute([currentUserId()]);
          $nbPanier = (int)$s->fetchColumn();
        } catch(Exception $e) {}
        if ($nbPanier > 0): ?>
        <span class="nav-badge"><?= $nbPanier ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
  </nav>

  <div class="nav-user">
    <?php if (isLoggedIn()): ?>
      <div class="user-avatar" onclick="window.location='<?= $root ?>/pages/compte.php'">
        <?= strtoupper(substr($_SESSION['prenom'] ?? 'U', 0, 1)) ?>
      </div>
      <a href="<?= $root ?>/pages/compte.php">Votre Compte</a>
      <a href="<?= $root ?>/pages/logout.php" class="btn-outline">Déconnexion</a>
    <?php else: ?>
      <a href="<?= $root ?>/pages/login.php">Connexion</a>
      <a href="<?= $root ?>/pages/register.php">Créer un compte</a>
    <?php endif; ?>
  </div>
</header>
<!-- /HEADER -->
