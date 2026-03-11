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
 'Admin123!','admin');
 INSERT INTO users (nom, prenom, email, pseudo, password, role) VALUES
('boutajar','AdminHoussna','houssna@omnes.fr','admin_houssna',
 'password','admin');


INSERT INTO users (nom, prenom, email, pseudo, password, role) VALUES
('Martin','Sophie','sophie@vendor.fr','sophie_shop',
 'Admin123!','vendeur'),
('Bernard','Luc','luc@vendor.fr','luc_collectibles',
 'Admin123!');

INSERT INTO users (nom, prenom, email, pseudo, password, role, adresse1, ville, code_postal, pays, telephone) VALUES
('Leclerc','Marie','marie@client.fr','marie_l',
 'Admin123!','acheteur',
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
(1,'https://www.bing.com/images/search?view=detailV2&ccid=qwGmZsQw&id=161BFB6CCE98BA661016E7A2C63886922AF7F94D&thid=OIP.qwGmZsQwdFTZkSrcOjudKQHaKs&mediaurl=https%3a%2f%2fi.pinimg.com%2f736x%2ff7%2f06%2f62%2ff706629ecc7448fa7e8c49964f919cda--reproduction-tableau-mona-lisa.jpg&cdnurl=https%3a%2f%2fth.bing.com%2fth%2fid%2fR.ab01a666c4307454d9912adc3a3b9d29%3frik%3dTfn3KpKGOMai5w%26pid%3dImgRaw%26r%3d0&exph=1063&expw=736&q=tableau+joconde&FORM=IRPRST&ck=E5D4F25C53BF14E7B9F72A07B934A1E8&selectedIndex=1&itb=0',0),
(2,'https://www.bing.com/images/search?view=detailV2&ccid=jex9eE92&id=A3853FA68AD1411A7B11451C164F13CEAEFFB981&thid=OIP.jex9eE92XecZ-Ri2A4wkbAHaJe&mediaurl=https%3a%2f%2fsaclab.com%2fwp-content%2fuploads%2f2023%2f06%2f3262_Hermes_Birkin_35_Black_XL_1M.jpg&cdnurl=https%3a%2f%2fth.bing.com%2fth%2fid%2fR.8dec7d784f765de719f918b6038c246c%3frik%3dgbn%252frs4TTxYcRQ%26pid%3dImgRaw%26r%3d0&exph=1920&expw=1500&q=birkin&FORM=IRPRST&ck=9CC81024C34FBD076B7C890E1FE5C17F&selectedIndex=9&itb=0',0),
(3,'https://www.bing.com/aclick?ld=e8qzihEoEOIiyebHwq4URqujVUCUyz3ntMDlsbXYrEf2F3wjBndNrWO0FEW_HWRsoCxPzkpgvzaK1P0niDG1dVM0AAdRWtwMk4AYg3PWg8-OJpF2N7LxBvyibeFyQREIgcsCh5vbX-khbFnt9qsHjVfxGzd0WAVt2y0bkSW3RKCeHsjVwIAn_cJ9l5m_Zi14QpKoCXbE8Ma-u1Bmg9o-_x0COPxyw&u=aHR0cHMlM2ElMmYlMmZ3d3cucmViaWtlLmZyJTNma2xhcl9zb3VyY2UlM2RiaW5nJTI2a2xhcl9jcGlkJTNkNjc4NDY4ODE4JTI2a2xhcl9hZGlkJTNkJTI2dXRtX3Rlcm0lM2R3d3cucmViaWtlLmZyJTI2dXRtX2NhbXBhaWduJTNkUmViaWtlJTI1N0MlMmJGUiUyYiUyNTdDJTJiUE1BWC1CaW5nJTI2dXRtX3NvdXJjZSUzZGJpbmclMjZ1dG1fbWVkaXVtJTNkUE1BWCUyNm1zY2xraWQlM2RjZGFiMmRmMWFkNGExZTZjNDMxMDQ0NDA3Zjg3ODVkOA&rlid=cdab2df1ad4a1e6c431044407f8785d8&ntb=1',0),
(4,'https://www.bing.com/images/search?view=detailV2&ccid=DaaeKihQ&id=1C8213EC601A8105F9240C4536612F238D71AAEF&thid=OIP.DaaeKihQM0RruiYA5STAsgHaE8&mediaurl=https%3a%2f%2fswisswatches-magazine.com%2fwp-content%2fuploads%2f2023%2f06%2fRolex-Cosmograph-Daytona-24h-Lemans-Ref-M126529ln-0001-Credit-JVA-for-Rolex-05.jpg&cdnurl=https%3a%2f%2fth.bing.com%2fth%2fid%2fR.0da69e2a285033446bba2600e524c0b2%3frik%3d76pxjSMvYTZFDA%26pid%3dImgRaw%26r%3d0&exph=1400&expw=2100&q=rolex&FORM=IRPRST&ck=6BF1BA3C843C936160F60BEF339CABCC&selectedIndex=0&itb=0',0),
(5,'https://www.bing.com/images/search?view=detailV2&ccid=GDBu6%2b1p&id=C2EFDD2CD3FEEE6272ABEF1A378E5A1046CE1196&thid=OIP.GDBu6-1pl_PQpns3M7AZzAHaHa&mediaurl=https%3a%2f%2ffr.louisvuitton.com%2fimages%2fis%2fimage%2flv%2f1%2fPP_VP_L%2flouis-vuitton-sac-speedy-bandouli%c3%a8re-20-toile-monogram-sacs-%c3%a0-main--M46234_PM1_Back+view.jpg&cdnurl=https%3a%2f%2fth.bing.com%2fth%2fid%2fR.18306eebed6997f3d0a67b3733b019cc%3frik%3dlhHORhBajjca7w%26pid%3dImgRaw%26r%3d0&exph=2000&expw=2000&q=sac+lv&FORM=IRPRST&ck=A4171F2894EA0A0C7A314668657AEBAA&selectedIndex=1&itb=1',0),
(6,'data:image/webp;base64,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',0);

-- Enchère sur item 1
INSERT INTO encheres (item_id, date_debut, date_fin, prix_depart) VALUES
(1, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 3000.00);

-- Enchère sur item 4
INSERT INTO encheres (item_id, date_debut, date_fin, prix_depart) VALUES
(4, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 5000.00);

-- Carte cadeau test
INSERT INTO cartes_cadeaux (code, montant) VALUES ('OMNES2026-XXXX', 50.00);
