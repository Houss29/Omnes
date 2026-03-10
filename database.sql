-- ============================================================
--  Omnes MarketPlace — Base de données
-- ============================================================
CREATE DATABASE IF NOT EXISTS omnes_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE omnes_marketplace;

-- ─── UTILISATEURS ───────────────────────────────────────────
CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nom          VARCHAR(100) NOT NULL,
  prenom       VARCHAR(100) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  pseudo       VARCHAR(80),
  password     VARCHAR(255) NOT NULL,
  role         ENUM('admin','vendeur','acheteur') NOT NULL DEFAULT 'acheteur',
  adresse1     VARCHAR(200),
  adresse2     VARCHAR(200),
  ville        VARCHAR(100),
  code_postal  VARCHAR(20),
  pays         VARCHAR(80),
  telephone    VARCHAR(30),
  photo        VARCHAR(255),
  image_fond   VARCHAR(255),
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ─── CATÉGORIES ─────────────────────────────────────────────
CREATE TABLE categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  nom  VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE
);
INSERT INTO categories (nom, slug) VALUES
  ('Articles rares','articles-rares'),
  ('Articles hauts de gamme','articles-hauts-de-gamme'),
  ('Articles réguliers','articles-reguliers');

-- ─── ITEMS ──────────────────────────────────────────────────
CREATE TABLE items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  vendeur_id    INT NOT NULL,
  categorie_id  INT NOT NULL,
  nom           VARCHAR(200) NOT NULL,
  description   TEXT,
  defaut        TEXT,
  prix          DECIMAL(10,2) NOT NULL,
  type_vente    SET('immediat','negotiation','meilleure_offre') NOT NULL,
  statut        ENUM('disponible','vendu','suspendu') DEFAULT 'disponible',
  video_url     VARCHAR(300),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vendeur_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

-- ─── PHOTOS ITEMS ───────────────────────────────────────────
CREATE TABLE item_photos (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  item_id  INT NOT NULL,
  url      VARCHAR(255) NOT NULL,
  ordre    INT DEFAULT 0,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- ─── ENCHÈRES (meilleure offre) ─────────────────────────────
CREATE TABLE encheres (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  item_id      INT NOT NULL,
  date_debut   DATETIME NOT NULL,
  date_fin     DATETIME NOT NULL,
  prix_depart  DECIMAL(10,2) NOT NULL,
  statut       ENUM('en_cours','terminee','annulee') DEFAULT 'en_cours',
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE offres_enchere (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  enchere_id  INT NOT NULL,
  acheteur_id INT NOT NULL,
  montant_max DECIMAL(10,2) NOT NULL,
  montant_actuel DECIMAL(10,2),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enchere_id)  REFERENCES encheres(id) ON DELETE CASCADE,
  FOREIGN KEY (acheteur_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── NÉGOCIATIONS ───────────────────────────────────────────
CREATE TABLE negociations (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  item_id      INT NOT NULL,
  acheteur_id  INT NOT NULL,
  vendeur_id   INT NOT NULL,
  statut       ENUM('en_cours','acceptee','refusee','expiree') DEFAULT 'en_cours',
  nb_tours     INT DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id)     REFERENCES items(id),
  FOREIGN KEY (acheteur_id) REFERENCES users(id),
  FOREIGN KEY (vendeur_id)  REFERENCES users(id)
);

CREATE TABLE offres_negociation (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  negociation_id   INT NOT NULL,
  emetteur_id      INT NOT NULL,
  montant          DECIMAL(10,2) NOT NULL,
  message          TEXT,
  statut           ENUM('en_attente','acceptee','contre_offre','refusee') DEFAULT 'en_attente',
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negociation_id) REFERENCES negociations(id) ON DELETE CASCADE,
  FOREIGN KEY (emetteur_id)    REFERENCES users(id)
);

-- ─── PANIER ─────────────────────────────────────────────────
CREATE TABLE panier (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  acheteur_id INT NOT NULL,
  item_id     INT NOT NULL,
  type_achat  ENUM('immediat','negociation','enchere') NOT NULL,
  prix_final  DECIMAL(10,2),
  added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acheteur_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)     REFERENCES items(id)
);

-- ─── COMMANDES ──────────────────────────────────────────────
CREATE TABLE commandes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  acheteur_id   INT NOT NULL,
  montant_total DECIMAL(10,2) NOT NULL,
  statut        ENUM('en_attente','payee','expediee','livree','annulee') DEFAULT 'en_attente',
  adresse_livraison TEXT,
  type_carte    VARCHAR(30),
  num_carte_masked VARCHAR(20),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acheteur_id) REFERENCES users(id)
);

CREATE TABLE commande_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  item_id     INT NOT NULL,
  prix        DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)     REFERENCES items(id)
);

-- ─── NOTIFICATIONS ──────────────────────────────────────────
CREATE TABLE notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  message    TEXT NOT NULL,
  type       VARCHAR(50),
  lu         BOOLEAN DEFAULT FALSE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE alertes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  mot_cle     VARCHAR(200),
  categorie_id INT,
  prix_max    DECIMAL(10,2),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

-- ─── CARTES CADEAUX ─────────────────────────────────────────
CREATE TABLE cartes_cadeaux (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(30) NOT NULL UNIQUE,
  montant    DECIMAL(10,2) NOT NULL,
  utilise    BOOLEAN DEFAULT FALSE,
  user_id    INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ─── DONNÉES DE TEST ────────────────────────────────────────
-- Mot de passe = "Admin123!" (bcrypt)
INSERT INTO users (nom, prenom, email, pseudo, password, role) VALUES
('Dupont','Admin','admin@omnes.fr','admin_omnes',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJrGvXqDS','admin');
 INSERT INTO users (nom, prenom, email, pseudo, password, role) VALUES
('boutajar','AdminHoussna','houssna@omnes.fr','admin_houssna',
 'password','admin');


INSERT INTO users (nom, prenom, email, pseudo, password, role) VALUES
('Martin','Sophie','sophie@vendor.fr','sophie_shop',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJrGvXqDS','vendeur'),
('Bernard','Luc','luc@vendor.fr','luc_collectibles',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJrGvXqDS','vendeur');

INSERT INTO users (nom, prenom, email, pseudo, password, role, adresse1, ville, code_postal, pays, telephone) VALUES
('Leclerc','Marie','marie@client.fr','marie_l',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJrGvXqDS','acheteur',
 '12 rue de la Paix','Paris','75001','France','+33612345678');


-- Items pour vendeur_id = 6
INSERT INTO items (vendeur_id, categorie_id, nom, description, defaut, prix, type_vente) VALUES
(6,1,'Tableau Basquiat Lithographie','Lithographie signée numérotée 12/50','Cadre légèrement abîmé',9500.00,'meilleure_offre'),
(6,2,'Sac Hermès Birkin 30','Birkin 30 taupage, quincaillerie dorée','Quelques marques d usage intérieur',7200.00,'negotiation'),
(6,3,'Vélo de Route Trek Émonda','Carbone, Shimano Ultegra, taille 56',NULL,1100.00,'immediat');

-- Items pour vendeur_id = 9
INSERT INTO items (vendeur_id, categorie_id, nom, description, defaut, prix, type_vente) VALUES
(9,1,'Montre Rolex Submariner','Montre Rolex 2020, état neuf','Rayure minime',12000.00,'meilleure_offre'),
(9,2,'Chaussures Gucci','Chaussures cuir taille 42','Usure légère',450.00,'negotiation,immediat'),
(9,3,'Sac Louis Vuitton','Sac LV Damier','État impeccable',2500.00,'immediat');

INSERT INTO items (vendeur_id, categorie_id, nom, description, defaut, prix, type_vente) VALUES
(8,1,'Bague Cartier Or Jaune 18K','Superbe bague Cartier 4.8g, certificat authentique','Légère rayure invisible','4800.00','meilleure_offre'),
(8,2,'Montre Omega Seamaster','Montre homme automatique, calibre 8800','Bracelet légèrement usé','1850.00','negotiation,immediat'),
(8,3,'Veste en Cuir Vintage','Veste années 80, cuir véritable, taille M',NULL,'120.00','immediat');

-- Items vendeur Luc (id=3)
INSERT INTO items (vendeur_id, categorie_id, nom, description, defaut, prix, type_vente) VALUES
(7,1,'Tableau Basquiat Lithographie','Lithographie signée numérotée 12/50','Cadre légèrement abîmé','9500.00','meilleure_offre')
(7,2,'Sac Hermès Birkin 30','Birkin 30 taupage, quincaillerie dorée','Quelques marques d usage intérieur','7200.00','negotiation'),
(7,3,'Vélo de Route Trek Émonda','Carbone, Shimano Ultegra, taille 56',NULL,'1100.00','immediat');

-- Photos
INSERT INTO item_photos (item_id, url, ordre) VALUES
(1,'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600',0),
(2,'https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=600',0),
(3,'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=600',0),
(4,'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?w=600',0),
(5,'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600',0),
(6,'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=600',0);

-- Enchère sur item 1
INSERT INTO encheres (item_id, date_debut, date_fin, prix_depart) VALUES
(1, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 3000.00);

-- Enchère sur item 4
INSERT INTO encheres (item_id, date_debut, date_fin, prix_depart) VALUES
(4, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 5000.00);

-- Carte cadeau test
INSERT INTO cartes_cadeaux (code, montant) VALUES ('OMNES2026-XXXX', 50.00);
