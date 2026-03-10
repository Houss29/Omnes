<?php
// php/footer.php — inclure en bas de chaque page
if (!isset($root)) $root = '..';
?>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="logo"><span class="logo-icon">🏛</span> Omnes <span>MarketPlace</span></div>
      <p>La plateforme d'e-commerce qui réunit acheteurs et vendeurs autour d'articles rares, premium et réguliers. Enchères, négociations, achat immédiat.</p>
    </div>
    <div class="footer-col">
      <h4>Acheter</h4>
      <a href="<?= $root ?>/pages/catalogue.php?type=immediat">Achat immédiat</a>
      <a href="<?= $root ?>/pages/catalogue.php?type=negotiation">Négociation</a>
      <a href="<?= $root ?>/pages/catalogue.php?type=meilleure_offre">Meilleure offre</a>
      <a href="<?= $root ?>/pages/panier.php">Mon panier</a>
    </div>
    <div class="footer-col">
      <h4>Mon Compte</h4>
      <a href="<?= $root ?>/pages/compte.php">Mon profil</a>
      <a href="<?= $root ?>/pages/compte.php?tab=commandes">Mes commandes</a>
      <a href="<?= $root ?>/pages/notifications.php">Notifications</a>
      <?php if (isVendeur()): ?>
      <a href="<?= $root ?>/pages/dashboard.php">Tableau de bord</a>
      <?php endif; ?>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <a href="mailto:contact@omnes-marketplace.fr"><i class="fa fa-envelope" style="width:18px"></i> contact@omnes-marketplace.fr</a>
      <a href="tel:+33100000000"><i class="fa fa-phone" style="width:18px"></i> +33 1 00 00 00 00</a>
      <a href="#map"><i class="fa fa-map-marker" style="width:18px"></i> 10 rue sextius Michel, 75015 Paris</a>
    </div>
  </div>

  <!-- Mini Map -->
  <div id="map" style="height:200px;border-radius:10px;overflow:hidden;margin-bottom:32px;">
    <iframe
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d5250.732589431659!2d2.2859909760050408!3d48.851225171331215!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47e6701b4f58251b%3A0x167f5a60fb94aa76!2sECE%20-%20Ecole%20d&#39;ing%C3%A9nieurs%20-%20Campus%20de%20Paris!5e0!3m2!1sfr!2sfr!4v1773184910595!5m2!1sfr!2sfr"
          width="100%" height="200" style="border:0" allowfullscreen loading="lazy"
      referrerpolicy="no-referrer-when-downgrade">
    </iframe>
  </div>

  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Omnes MarketPlace. Tous droits réservés à Nyarale Houssna et Emir</span>
    
  </div>
</footer>


</div><!-- /wrapper -->

<script src="<?= $root ?>/js/main.js"></script>
</body>
</html>
