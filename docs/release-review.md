# Revue locale — HOME CONFORT

## 20 octobre 2026 — complément documentaire énergétique

Périmètre : installation locale des documents de134références, retrait de35dossiers contradictoires, ancrages vers les pages exactes des fiches. Le [bilan détaillé](ENERGIE-DOCUMENTS-2026-10-20.md) et les preuves privées `storage/app/private/energy-documents-20261020/` donnent les sources, SHA, exclusions, sauvegardes et contrôles.

| Contrôle | Statut observé | Preuve |
| --- | --- | --- |
| Modèles, sources, langues et pages des134nouveaux dossiers | Conforme observé, avec exclusions documentées | qa/review.md, assessment.json, import-assessment.json |
| Conservation des636autres associations et retrait35HS | Conforme observé | qa/hs-withdrawals.json, preflight.json |
| Tests énergie | Conforme observé :33tests/35 747assertions | energy-tests.log |
| Suite complète | Conforme observé :539tests/49 538assertions | full-phpunit.log |
| Format PHP et espaces Git | Conforme observé | pint-test.log, git-diff-check.log |
| Dix contrôles Chrome, clavier, mobile, liensPDF | Conforme observé dans les cas exercés | qa/browser.json, captures output/qa-energy-20261020/ |
| Catalogue source | Conforme observé :3714JSON identiques | catalogue-integrity.json |
| Langue FR pour les51nouveaux dossiers restants | À compléter | suiviCSV ; documents EN/DE explicitement indiqués |
| Identités/générations ou valeurs fournisseur contradictoires exclues | Inconnu à résoudre avec le fournisseur | pending/quarantine et bilan détaillé |
| Build d’assets | Non applicable au changement | Aucun CSS/JS applicatif modifié |
| Paiement, stock, livraison, emails/webhooks et lancement | Non exercé dans cette recette | Aucun feu vert commercial ou production |
| Déploiement ou soumissionGoogle | Non applicable | Aucune action de publication |

## 20 octobre 2026 — deuxième complément documentaire

134 références ajoutées : 26 nouveaux dossiers FR utilisables près du prix, 108 Aquaflam accessibles avec avertissement traduit et flèche désactivée. Les 770 associations existantes sont identiques. Voir le [bilan et les réserves](ENERGIE-DOCUMENTS-COMPLEMENT-2-2026-10-20.md).

| Contrôle | Statut / preuve |
| --- | --- |
| Sources, modèles exacts, langues et pages des 134 références | Vérifiés ; preuves privées `storage/app/private/energy-documents-20261020-more/qa/` |
| Préservation des 770 associations / extraction des deux ZIP EPREL | PASS, qa/preflight.json |
| Aquaflam : erreur imprimée 2018/1186 | Réserve explicite visible, prix désactivé pour 108 ; correction fabricant nécessaire |
| Dovre : titre allemand erroné | Aucune association DE ; original FR/EN inchangé |
| Tests et navigateur | Résultats définitifs dans verification.json et journaux de recette |
| Paiement, stock, emails, publication, Merchant Center | Non exercés dans cette recette documentaire locale |

Résultat après installation du deuxième complément : **540 tests / 55 845 assertions PASS**, ciblage énergie **34 / 42 054**, Pint et espaces Git PASS. **Neuf contrôles Chrome PASS** ; avertissement Aquaflam traduit et documents accessibles sans flèche au prix, cas Dovre DE sans fiche DE associée. **3 714 JSON source et 770 associations précédentes inchangés**. Captures relues dans `output/qa-energy-20261020-more/`, résultats dans `storage/app/private/energy-documents-20261020-more/verification.json`. Les réserves fournisseur restent ouvertes.

## 20 octobre 2026 — responsive, identité HOME CONFORT et commande locale

Voir le [bilan complet](RESPONSIVE-SEO-IDENTITE-2026-10-20.md). 548 tests / 56 007 assertions PASS, build Vite et Pint PASS. Recette indépendante : 40 vues Chrome 320–1440 px, FR/EN/DE, formulaire accessible, focus et champs contrôlés, congélateur exact sur l’accueil. Catalogue source et énergie inchangés. Stock déclaré et importé : 500 par produit (3 709 produits). Configuration locale des commandes différées ouverte ; paiement effectif fermé.

| Domaine | Statut observé |
| --- | --- |
| Responsive, identité visible / JSON-LD et modèle accueil | Conforme observé dans les cas vérifiés ; captures et qa/seo-final.json |
| Commande et sécurité métier | Tests isolés réussis ; aucune commande persistante de recette sur MariaDB |
| Stock MariaDB post-import | Conforme observé immédiatement après import ; relecture finale non exécutée après refus |
| Indexation et publication | Non applicables au site local ; noindex conservé, aucun domaine public choisi |
| Merchant Center / Search Console actuels | Inconnus et non soumis ; revues catalogue et domaine à finaliser |
| Dépendances et noms de fichiers publics | npm sans vulnérabilité signalée ; Composer non concluant (réseau) ; noms publics sans alerte. Audit complet non revendiqué |
| Paiement réel, délivrance SMTP, appareils physiques et CWV terrain | Non exercés ; pas de feu vert global de lancement |

## 20 octobre 2026 — corrections catalogue Merchant

[Bilan et informations manquantes](CATALOGUE-MERCHANT-CORRECTIONS-2026-10-20.md). Trois fiches corrigées, 418→415 références avec anomalies, 98→97 sans caractéristiques. 556 tests / 56143 assertions réussis. Quatre couples GTIN/MPN fabricant et 267 associations EPREL documentés, 961 brouillons non approuvés ; 108 réserves Aquaflam protégées. Audit FR/EN/DE sans base ; stock 500 inchangé, aucune soumission Google. La revue fabricant des autres références, variantes, provenance texte et données de production reste ouverte.

## 20 octobre 2026 — identifiants et fiches fabricant

[Lot installé et réserves](CATALOGUE-DONNEES-OFFICIELLES-2026-10-20.md) : 182 nouveaux EAN, 153 MPN, neuf fiches/46 caractéristiques vérifiées, deux EAN LG corrigés et douze lignes secondaires regroupées. 559 tests / 63 795 assertions réussis ; 12 rendus de fiches et 11 127 projections contrôlés en trois langues, sans SQL. 3 714 JSON source, prix, images, registre énergétique et stock déclaré de 500 conservés. Référence Siemens/Bosch contradictoire exclue. 968 revues non approuvées, 406 anomalies et 88 fiches sans caractéristiques restent ouvertes. Domaine, paiement, revue fabricant des autres références et validation Google non réalisés. Aucune soumission.

## 20 octobre 2026 — autres marques et 88 fiches

[Résultat et réserves](CATALOGUE-AUTRES-MARQUES-2026-10-20.md) : 98 nouvelles identités (24 EAN / 88 MPN), 31 fiches renseignées et huit partielles, six poids nets Wamsler, deux marques de combustibles, un titre Whirlpool et deux classifications Haier corrigés. 562 tests / 66 693 assertions, Pint et 27 rendus FR/EN/DE réussis ; 11 127 projections comparées sans SQL. Il reste 375 anomalies, 57 fiches à compléter et 3 419 identifiants à documenter. Les origines de 35 combustibles restent inconnues ; aucune attribution inventée. Les 1 027 revues restent non approuvées et les 108 réserves Aquaflam maintenues. Prix, images, descriptions, 3 714 sources, registre énergétique et stock déclaré de 500 conservés. Domaine et compte Google non validés ; aucune soumission.


## 20 octobre 2026 — recherches complémentaires sur les 57 fiches

[Lot installé et informations manquantes](CATALOGUE-RECHERCHES-COMPLEMENTAIRES-2026-10-20.md) : 149 nouvelles références fabricant dont 40 EAN ; 439 offres avec identifiant documenté (250 EAN / 394 MPN). Deux palettes renseignées et un pack enrichi du seul composant poêle, sept faits nouveaux et huit anciens faits préservés. Restent 55 fiches incomplètes (48 vides / sept partielles), 373 produits avec anomalies et 3 270 sans aucun identifiant. 566 tests / 70 448 assertions, 30 rendus et 11 127 projections vérifiés ; QA indépendante 37 894 assertions sur les services installés. Bronpi contradictoire et Woodstock historique exclus ; 13 sous-marques LN granulés restent à confirmer. 1 072 revues non approuvées, 108 réserves Aquaflam et 3 714 sources préservées. Prix, promotions, photos, descriptions, titres, marques, environnement et stock déclaré de 500 inchangés. Aucune requête MariaDB, publication ni soumission Google ; contrôles de production/compte non réalisés.


## 20 octobre 2026 — accueil, tableau de bord local, sélection Shopping et retours Lille

[Bilan, sélection et informations manquantes](ACCUEIL-DASHBOARD-SHOPPING-2026-10-20.md). Accueil adouci et boutons compacts ; tableau de bord activé avec compte propriétaire privé. 12 textes produits originaux FR/EN/DE et deux corrections officielles installés. Expédition ET retours à Rue Aristote, Mont de Terre, Fives, 59000 Lille ; siège de Guyane conservé séparément, frais et délais existants confirmés.

| Contrôle | Résultat et portée |
| --- | --- |
| Tests applicatifs | PASS : 573 tests / 71 230 assertions ; ciblage catalogue 10 / 11 195, adresses/retours/commande 71 / 4 206 et administration 52 / 658 |
| Interface | PASS cas exercés : 17 pages responsive 320–1440 px, 36 fiches FR/EN/DE à 375 px ; build Vite, Pint et espaces Git réussis |
| Administration locale | PASS connexion/déconnexion, 15 écrans et trois largeurs ; redirection anonyme, noindex/no-store et six frontières HTTP privées vérifiés. Services externes non configurés |
| Catalogue | PASS 11 127 projections ; 3 697 produits hors sélection identiques par langue, prix/promotions/images/références et 3 714 sources inchangés ; 1 075 brouillons non approuvés |
| Stock et logistique | SELECT XAMPP ciblé : 12 × 500 disponibles, références/prix validés ; aucune mutation stock/commande lors de cette lecture. Adresses, gratuité FR/BE/DE et total 3–7 jours ouvrés confirmés par le propriétaire ; transport et stock physique non audités |
| Sécurité | Audits dépendances de production Composer/npm sans alerte ; développement non audité dans ce passage. Noms/symlinks publics et protections HTTP locales vérifiés ; contrôle partiel, aucune certification globale |
| Publication commerciale | Bloquée par domaine/email définitifs, configuration du paiement et délivrance email, parcours public et revue légale/sécurité par produit à finaliser |
| Soumission Google | Aucune soumission. Sept candidats prioritaires après contrôles globaux ; quatre palettes sans identifiants exacts et un congélateur à photo faible en réserve. Réglages et diagnostics comptes Google non exercés |
| Hors périmètre | Déploiement/push, paiement réel, commande de recette persistante, expédition réelle, promesse d’acceptation Google |

Preuves dans `storage/app/private/dashboard-shopping-20261020/` ; accès propriétaire dans le dossier privé distinct `storage/app/private/admin/`, jamais recopié dans les artefacts publics.

## En-tête « Clair et accueillant » — 20 octobre 2026

Intégration autorisée dans le layout public partagé, avec logo actuel, fond blanc, recherche jaune, catégories et liens secondaires distincts. Administration privée conservée avec son propre layout.

- Conforme observé : 19 tests ciblés / 4 087 assertions (StorefrontChromeTest, HomeNavigationTest, ContactButtonsTest, CatalogueUxRegressionTest), sans migrations ; compilation Vite et diff whitespace réussis.
- Conforme observé dans le navigateur local : accueil 1280, 375 et 320 px ; menu mobile ouvert puis fermé via Échap ; absence de débordement horizontal aux largeurs contrôlées. Catalogue, panier et à-propos : en-tête partagé visible ; fond blanc confirmé sur accueil et catalogue.
- Couverture limitée : contrôles responsive et navigation de l’en-tête ; aucune nouvelle recette du paiement, de la base ou des services externes. Aucun push ni déploiement.

### Ajustement mobile de l’en-tête

Recherche sur toute la largeur du conteneur ; panier et menu sur la ligne du logo, langues conservées sur une ligne distincte. Vérifié dans le navigateur à 375 et 320 px : recherche de largeur égale au conteneur, absence de débordement horizontal, menu ouvrable et fermeture Échap. Compilation Vite réussie. Changement CSS limité à moins de 600 px.

### Langues dans la barre supérieure sur mobile

Navigation de langue unique déplacée à moins de 600 px par matchMedia, replacée auprès des actions sur ordinateur. Liens et gestion de langue existants conservés. Le numéro WhatsApp est masqué dans cette barre sur mobile pour laisser la place aux langues ; les autres accès contact restent présents. Sans JavaScript, langues accessibles sous la recherche. Contrôles navigateur : 375 px visuel, 320 px sans débordement, retour 1280 px avec langues à leur emplacement ordinateur. Compilation Vite et vérification des espaces réussies.

### Menu mobile plus compact

Espacement entre catégories supprimé, marges verticales réduites et liens secondaires sur deux colonnes. Contrôle navigateur à 375 et 320 px : liens visibles de hauteur minimale mesurée 44 px, aucun débordement horizontal. Capture du menu ouvert conservée dans le dossier de visualisations de la conversation. Build Vite réussi.

### En-tête Signature chaleureuse — 20 octobre 2026

Variante demandée appliquée au layout public partagé : crème et olive, logo officiel centré et recherche sur une ligne distincte sur ordinateur. Mobile : recherche pleine largeur, panier/menu auprès du logo, langues en barre supérieure et menu compact conservés. Build Vite réussi. Vérification visuelle 1280/375 px ; contrôles à 320 px (aucun débordement, liens du menu ≥44 px), 1024 px (aucun débordement ni chevauchement logo/actions). Aucun déploiement.

### Retour demandé au haut de page initial jaune

Variantes et ajustements d’en-tête de cette conversation retirés : layout et navigation initiale restaurés, déplacement mobile des langues supprimé, styles ajoutés Clair et accueillant/Signature chaleureuse retirés. Ancienne surcharge crème de l’accueil supprimée pour respecter le jaune uni demandé (#f5c842). Autres styles de contenu préservés. Build réussi ; couleur mesurée navigateur rgb(245,200,66), contrôle ordinateur 1280 px et mobile 375 px sans débordement, langues à leur place initiale et menu ouvrable.


## 20 octobre 2026 — élargissement Shopping à 150 offres

[Bilan et données manquantes](SELECTION-150-SHOPPING-2026-10-20.md), [liste des 150 offres](SELECTION-150-SHOPPING-HOME-CONFORT-2026-10-20.csv). 13 chauffages, 133 électroménagers et 4 lots de granulés ; 149 offres identifiées, 141 EAN / 104 MPN, 146 modèles EPREL / 584 documents. Trois identifiants de sacs vérifiés associés aux lots vendeur de 72 ou 65 sacs ; prix et stock du lot conservés. Deux nouvelles photos exactes et 34 vues principales existantes choisies ; Piveteaubois 473 px reste réservé pour Google. Deux catégories de lavants-séchants corrigées.

| Contrôle | Résultat et portée |
| --- | --- |
| Tests application | PASS : 586 tests / 88 381 assertions ; ciblage 52 / 32 950 ; Pint et espaces Git PASS |
| Projections catalogue | PASS : 11 127 comparaisons, 3 559 produits hors sélection identiques par langue ; prix, promotions, références et 3 709 JSON produits conservés |
| Sources et textes visibles | 450 descriptions identiques à leur projection ; 310 autres profils et 925 autres revues inchangés |
| Images | Deux nouvelles photos natives sans retouche ; 34 vues existantes propres. JPEG Piveteaubois égal au flux DCT du PDF ; extraction initialement réencodée corrigée avant installation |
| Frontières d’image | Exception site 100 px uniquement avec revue, référence et SHA ; cache séparé 500/100 ; photo native 473 px exclue de la préparation Google |
| Navigateur local | PASS : 31 pages FR/EN/DE sur mobile, tablette et ordinateur ; aucune commande de recette créée |
| Inventaire | SELECT XAMPP : 150 × 500 disponibles, références et prix validés, aucune surcharge administrateur ; stock physique non audité |
| Préparation Google | Aucune offre prête à soumettre ; 148 sans blocage technique local propre au produit ; photo Piveteaubois et identifiants Badger en réserve ; 1 075 revues non approuvées |
| Publication commerciale et Google | Non réalisées ; domaine, email, paiement et contrôles publics, commerciaux et comptes à finaliser. Aucun push, déploiement ni soumission |

Preuves et sauvegardes privées : `storage/app/private/shopping150-20261020/`. Aucun secret ou `.env` livré ; conditions Lille et siège de Guyane préservés. Aucun CSS/JS modifié par ce lot catalogue ; aucune compilation supplémentaire requise pour ce lot. Cette recette ne constitue pas une certification légale ou de sécurité globale.

### Boutons compacts du projet

Styles partagés harmonisés : boutons principaux/secondaires, liens d’action d’accueil, contact, achat immédiat, validation commande/panier, paiement et administration. Marges 8–12 px et typographie 14 px ; minimum tactile de 44 px, validations principales 48 px. Achat immédiat et envoi support ajustés au libellé ; actions fixes du panier et montant de validation conservés. Boutons icônes d’administration préservés. Jaune uni d’ajout panier conservé.

Build Vite et vérification des espaces réussis. Navigateur : fiche produit ordinateur (achat immédiat mesuré 163×44 px), accueil mobile (actions mesurées 44 px), catalogue 375/320 px sans débordement, connexion administration 375 px sans débordement avec hauteur 44 px. Écrans privés après authentification et validation de commande non exercés ; modification uniquement CSS, aucune commande ni connexion soumise.

### Deux actions dans la barre d’achat mobile

Ajout panier et achat immédiat réunis dans la barre fixe, prix sur la ligne supérieure. Boutons du même formulaire avec intents cart/checkout et règles de disponibilité inchangées ; aucune duplication ni JavaScript de soumission ajouté. Bouton panier jaune et achat immédiat sombre pour différencier les actions. Contrôles navigateur : 375 px (deux boutons 160,5×44 px, même formulaire), 320 px (hauteur 47,2 px, même ligne, aucun débordement) et ordinateur 1280 px sans débordement. Deux tests existants achat immédiat/variantes passent (24 assertions), build Vite et espaces validés. Aucun achat réel soumis.

### Actions d’achat sous le prix

Formulaire d’achat déplacé après prix/TTC/disponibilité, avant caractéristiques et informations pour l’achat. Ordre DOM cohérent avec affichage et clavier, pas de formulaire dupliqué. Vérifié sur ordinateur 1280 px ; barre mobile conservée (position fixed, deux boutons, aucun débordement à 375 px). Deux tests existants achat immédiat/variantes passent (24 assertions). Changement Blade seul, aucune soumission réelle.


## 20 octobre 2026 — complément Badger et Piveteaubois

[Bilan et preuves](RECHERCHES-BADGER-PIVETEAUBOIS-2026-10-20.md), [demande fabricant préparée sans envoi](DEMANDE-BADGER-PIVETEAUBOIS-2026-10-20.txt). Aucun nouvel EAN/MPN Badger officiellement établi ni packshot exact Piveteaubois de meilleure définition ; les deux réserves restent ouvertes.

- Badger : brochure 2022, article 2026 et cinq originaux officiels inspectés, dont face papier 1221 × 1920. Code-barres d'un autre sac plastique de 14 kg exclu ; références de certification et codes tiers non importés. Registre ENplus partiel/HTTP403 et GS1 inaccessible conservés comme limites, sans conclusion d'absence.
- Piveteaubois : bibliothèque publique réellement récupérée après escalade réseau, 27 entrées / 17 URLs distinctes. Les deux PNG de 2016/2017 sont identiques à l'ancienne archive de 1000 × 750 ; identité HP+ 15 kg / G00003249 non établie. Photo exacte native 473 conservée pour le site, liste Google vide ; pas d'agrandissement artificiel.
- Conforme observé : SHA de 3 709 JSON produits et quatre registres conservés ; recette indépendante des preuves et images. Aucun changement fonctionnel, CSS ou JS ; nouvelle suite applicative et build non applicables à ce complément documentaire.
- Aucun SQL, changement de prix ou stock, contact fournisseur, push, déploiement ou soumission Google. Aucune nouvelle offre déclarée prête ou approuvée. Preuves privées dans `storage/app/private/remaining2-20261020/`.

### Navigation de rubrique compacte sur fiche mobile

Liens de section sur une seule ligne à défilement horizontal sous 600 px ; texte 12 px, espacements réduits et cibles de 44 px. Contrôle visuel 375 px : photo davantage visible, barre d’achat inchangée. Mesure 320 px : navigation 55 px de haut, liens 44 px et aucun débordement global. Build Vite réussi. Affichage ordinateur conservé.

### Flèche de défilement des rubriques mobiles

Ajout d’un bouton flèche discret de 28 px dans une cible de 44 px, visible sur mobile si les rubriques débordent. Clic : défilement de 70 % de la largeur ; masquage à la fin, réduction des animations respectée, libellé accessible FR/EN/DE. Vérification navigateur 375 px : flèche visible, clic fait passer scrollLeft à 238,5 px. Compilation Vite et espaces validés.

### Retour aux premières rubriques mobiles

Flèche gauche ajoutée, masquée au début puis visible dès défilement. Bouton 44 px avec libellés FR/EN/DE ; comportement avec réduction des animations conservé. Contrôle navigateur 375 px : gauche initialement absente, apparition après clic droit, clic gauche ramène scrollLeft à 0. Build Vite et vérification espaces réussis.

## Audit responsive transversal — 20 octobre 2026

Voir [rapport détaillé](AUDIT-RESPONSIVE-2026-10-20.md). 60 affichages principaux et compléments langues/paysage/menu : aucun débordement global constaté. Correction des cibles tactiles quantité, checkout, retours et volets administratifs ; champs admin à 16 px sur mobile/tactile. Build réussi, 22 tests / 3 869 assertions, espaces validés. Administration authentifiée et checkout rempli non vérifiés visuellement : revue du code uniquement pour ces écrans. Aucun déploiement ni transaction réelle.


## 20 octobre 2026 — deux remplaçants, sélection Shopping de 150 sans réserve produit locale

[Sélection actualisée et limites](SELECTION-150-SHOPPING-2026-10-20.md), [CSV des 150 références](SELECTION-150-SHOPPING-HOME-CONFORT-2026-10-20.csv). Les lots Badger et Piveteaubois sortent de la sélection ; ils restent au catalogue avec leurs réserves. Entrées : LG GBBS312AEV (EAN 8806096800317, EPREL 2485358) et LG GBBS726AMB (EAN 8806096795118, EPREL 2441588), deux photos existantes propres natives 1044 × 1044. Type de construction corrigé selon EPREL : pose libre. GBBS726AMB arrêté côté fabricant, sans déduire une rupture du stock vendeur.

| Contrôle | Résultat et portée |
| --- | --- |
| Sélection | 150 uniques et identifiées, 143 EAN / 105 MPN, 148 EPREL / 592 PDF, 450 contenus FR/EN/DE ; zéro blocage technique local propre aux produits |
| Tests | PASS : 586 tests / 89 310 assertions ; Pint, syntaxe PHP et espaces Git réussis ; aucun CSS/JS modifié, build non applicable |
| Catalogue | 11 127 projections comparées, seuls deux nouveaux produits changés par langue ; 148 candidats conservés et 3 559 produits hors sélection identiques ; 3 709 JSON produits, prix, promotions, variantes et photos conservés |
| Inventaire | SELECT XAMPP : 150 × 500 disponibles, zéro réservé et surcharge administrateur, prix/références concordants ; aucun stock écrit, inventaire physique non audité |
| Sources et images | Revue indépendante des deux archives LG, deux records EPREL, huit PDF et deux JPEG exacts ; aucune transformation d'image |
| Navigateur | PASS : 12 pages FR/EN/DE, 375/768/1440 px, images décodées et JSON-LD valides, aucun débordement, aucune commande |
| Revue Google | 1 075 revues toujours non approuvées ; zéro offre déclarée prête à soumettre. Domaine, email, paiement et contrôles publics/commerciaux/comptes restent à finaliser |

Sélection active privée : `storage/app/private/google/shopping150-selection.json`. Pack révisé, preuves et sauvegardes : `storage/app/private/replacement2-20261020/`. Ancien pack préservé. Aucun secret livré, push, déploiement, contact fournisseur, activation d'indexation/flux ni soumission Google.

## Revue sécurité avant déploiement — 20 octobre 2026

Rapport expurgé privé : `storage/app/private/security-review-20261020/rapport.md` (répertoire 0700, fichier 0600). Aucune faille critique/élevée exploitable confirmée dans le périmètre examiné ; traçabilité d’authentification admin complétée, tests dédiés ajoutés. Audits Composer/npm sans alerte ; 138 tests existants et 2 nouveaux réussis, retest ciblé 21 tests réussi. HTTP privé : 404 sur prévisualisation8000, 403 sous /HOMECONFORT sur Apache80 ; 8080 injoignable. Publication non validée : configuration locale non production, domaine technique à confirmer, racine web/TLS/permissions/sauvegarde et accès distant de la cible à vérifier. Aucun déploiement réalisé.

## Préparation Hostinger locale — 20 octobre 2026

[Compte rendu](PREPARATION-HOSTINGER-2026-10-20.md) et [guide Web/SSH](../deploy/hostinger/README.md). Domaine rendu configurable et fermé sans valeur valide ; permissions privées/logs 0700 ; modèle sécurisé, preflight sans DB, front controller optionnel et paquets vérifiés. 592 tests / 89 386 assertions réussis, Pint/build/espaces validés. Installation isolée de 81 dépendances prod et caches réussis ; configuration fictive preflight conforme. PHP réel du lockfile >=8.4.1. Livrable `output/hostinger/livraison-20261020/`, médias 4,2 Go séparés. Domaine à recevoir et recette Hostinger non exécutée ; aucun déploiement, migration ou cron.


## 20 octobre 2026 — remplaçant encore commercialisé, LG GBBS323CPY

[Sélection et limites actualisées](SELECTION-150-SHOPPING-2026-10-20.md), [liste des 150 produits](SELECTION-150-SHOPPING-HOME-CONFORT-2026-10-20.csv). LG GBBS726AMB retiré de la sélection sur demande ; LG GBBS323CPY ajouté. Commercialisation observée sur la page actuelle LG France : ACTIVE, vente activée, offre InStock, HTML effectivement téléchargé et SHA archivé ; confirmation indépendante par la liste des combinés.

EAN France 8806096643501, MPN LG GBBS323CPY, EPREL 2391595 ; 375 L, classe C, photo existante propre native 1044 × 1044. Pose libre confirmée par EPREL et corrigée dans les trois langues ; le tiroir de 37 L est inclus dans les 262 L de réfrigération. Prix HOME CONFORT 749 € conservé ; l'offre fabricant 849 € ne remplace pas ce prix.

| Contrôle | Résultat et portée |
| --- | --- |
| Sélection | 150 uniques identifiées, 149 candidats conservés, 143 EAN / 105 MPN, 148 EPREL / 592 PDF ; zéro blocage technique local propre aux produits |
| Affichage énergétique | Défaut ancien corrigé : le texte anglais conserve la classe de bruit B au lieu de la remplacer par la classe énergétique C ; régression FR/EN/DE ajoutée, premier échec conservé |
| Tests après correctif commun | PASS : 593 tests / 89 389 assertions ; Pint, syntaxe et espaces Git réussis ; aucun CSS/JS modifié, build non applicable |
| Catalogue et contenus | 11 127 projections comparées : seul nouveau modèle modifié par langue ; 3 708 autres projections identiques ; 450 descriptions rendues concordantes après correctif |
| Navigateur | PASS : six pages FR/EN/DE à 375 px et FR à 768/1440 px, ancien modèle encore accessible, JSON-LD/images/textes corrects, zéro débordement et commande ; aucun redémarrage serveur |
| Inventaire | SELECT XAMPP : 150 × 500 disponibles, zéro réservé et surcharge administrateur, prix/références concordants ; aucun stock écrit ni comptage physique |
| Préservation | 3 709 JSON produits et photos inchangés ; deux profils et deux revues privés modifiés, ancien 726 conservé au catalogue hors sélection |
| Google | 1 075 revues non approuvées ; aucune offre déclarée prête à soumettre. Domaine, email, paiement et contrôles publics/commerciaux/comptes restent à finaliser |

Preuves et sauvegardes dans `storage/app/private/active150-20261020/`, sélection active dans `storage/app/private/google/shopping150-selection.json`. Révisions précédentes préservées. Aucun push, déploiement, contact fournisseur, activation d'indexation/flux, paiement ou soumission Google. La commercialisation fabricant n'a été revérifiée que pour ce remplaçant, pas pour tous les autres produits.

## Formulaires commande et rétractation — 20 octobre 2026

Mentions « facultatif » retirées des libellés téléphone checkout et email/logistique/notes rétractation (FR/EN/DE), sans rendre les champs supplémentaires obligatoires. Choix de téléchargement remplacé visuellement par téléphone : validation serveur, brouillon chiffré, récapitulatif, accusé téléchargeable et lecture admin mis à jour. Moyen technique download conservé et validé côté serveur. Message de prise en compte affiché après enregistrement réussi seulement. Deux tests dédiés ajoutés ; 25 tests retours/informations légales passent (3 219 assertions), Pint et espaces validés. Aucun formulaire réel soumis. Régénérer le livrable Hostinger après les modifications intervenues depuis l’archive livraison-20261020.


## 20 octobre 2026 — cycle de commercialisation des 150 références

[Sélection, substitutions et limites](SELECTION-150-SHOPPING-2026-10-20.md), [liste des 150 avec sources actuelles](SELECTION-150-SHOPPING-HOME-CONFORT-2026-10-20.csv). Toutes les références de la sélection ont une preuve de commercialisation/gamme officielle actuelle observée. Quatorze substitutions : quatre arrêts LG explicites et dix retraits préventifs pour statut actuel non confirmé ; aucune fausse affirmation d’arrêt pour ces dix. KGE36ALCA conservé grâce à Bosch Belgique, Fagor grâce au catalogue rendu et à l’API publique.

150 uniques, 143 EAN / 109 MPN, 148 EPREL / 592 PDF, 150 photos principales natives ≥500 pixels, 13 chauffages / 2 lots / 135 appareils. Prix et stocks conservés, SELECT XAMPP150 ×500, 3 709 sources inchangées. Tests finaux PASS : 594 / 92817 assertions ; 450 descriptions concordantes et 26 pages navigateur sans débordement. Deux tests de contact/navigation adaptés au retrait parallèle de la barre supérieure ; vues préservées.

Dossier privé `storage/app/private/lifecycle150-20261020/`, révision `shopping150-lifecycle-20261020`. Aucune preuve de continuité future de fabrication ou de stock physique, aucun compte Google audité/soumis. Domaine, nouvel e-mail et recette commerce/public restent des obstacles. Aucune activation, publication ou soumission ; les 1 075 revues restent non approuvées.

## Sous-catégories et indicatifs téléphoniques — 20 octobre 2026

Sous-menus natifs ajoutés aux cinq univers de l’en-tête partagé : catégories localisées dérivées du catalogue, lien vers l’univers conservé, panneau défilable et cibles 44 px ; fermeture Échap/clic extérieur. Contrôles visuels ordinateur 1280 px et mobile 375 px, panneau chauffage mesuré 16 liens avec contenu défilable 716 px pour 318 px visibles. Aucun intitulé de sous-catégorie inventé.

Commande : sélecteur natif de 245 pays/territoires et indicatifs, noms FR/EN/DE produits depuis ICU/CLDR local et codes issus de `giggsey/libphonenumber-for-php-lite` 9.0.41. Aucun service externe appelé pour le numéro client. Normalisation serveur E.164, conservation des zéros significatifs, pays incohérent rejeté, retour des valeurs après erreur ; téléphone toujours non obligatoire et pays de livraison inchangés. Licence et métadonnées conservées par la dépendance Apache-2.0. Source : https://github.com/giggsey/libphonenumber-for-php (version légère documentée).

Validation : suites commerce/sous-menus/téléphone réussies (52 tests avant ajout du dernier cas HTTP ; suite téléphone 6 tests / 1 498 assertions réussie ensuite), régression chrome/navigation 10 tests / 4 453 assertions, Pint/build/espaces validés. Composer a installé un paquet nouveau sans mise à jour d’autres dépendances et n’a signalé aucun advisory. Sélecteur mesuré à 245 options, aucun débordement global à 320 px. Panier temporaire ajouté puis vidé pour affichage de la commande ; aucune commande soumise ni réservation effectuée. Captures menus et indicatifs dans le dossier de visualisations de la conversation. Régénérer le livrable Hostinger avec les sources/lockfile/assets actualisés avant publication.

### Sous-menus au survol — 20 octobre 2026

Ouverture au passage du pointeur sur ordinateur (souris précise et largeur ≥960 px), délai court de 100 ms et fermeture temporisée 180 ms ; ouverture au focus clavier, fermeture Échap/clic extérieur. Ouverture native tactile conservée. Chevrons retirés, panneau blanc élargi à 300 px, accents jaunes, liens aérés et transition respectant prefers-reduced-motion. Contrôle navigateur : entrée du pointeur ouvre le panneau, Échap le ferme (0 ouvert), toucher mobile ouvre (1 ouvert). Deux tests sous-catégories passent, syntaxe JS/build/espaces validés. Capture sous-menu-survol.jpg dans les visualisations. Livrable Hostinger à régénérer avant publication.

### 2026-10-20 — Photo Espace conseil
- Remplacement du doublon du visuel principal par une photo distincte de Breather, licence CC0 vérifiée sur Wikimedia Commons ; provenance et empreintes dans `docs/ADVICE-IMAGE-CREDITS-2026-10-20.json`.
- WebP local optimisé, texte alternatif FR/EN/DE, crédits complétés. Composant conseil partagé mis à jour.
- Vérification visuelle locale à 1280 px et 390 px : chargement et cadrage conformes observés. Compilation Blade réussie ; cache vidé après contrôle. Visuel principal conservé.

### 2026-10-20 — Marques dans le menu Électroménagers
- Ajout de toutes les marques des produits publiés de l’univers appliances dans le sous-menu partagé, avec liens filtrés par univers et marque ; liste calculée depuis le catalogue.
- Lecture locale vérifiée : 12 marques, dont LG ; aucun ajout de marque sans produit correspondant.
- Tests ciblés : 3 tests / 11 assertions réussis (déduplication, séparation des univers, lien LG). Compilation Vite réussie, diff sans erreur d’espacement.


## 20 octobre 2026 — cohérence des 150 produits entre site, JSON-LD et XML

[Audit détaillé](AUDIT-COHERENCE-150-SITE-JSONLD-FLUX-2026-10-20.md) et [relevé des 150 produits](AUDIT-COHERENCE-150-SITE-JSONLD-FLUX-2026-10-20.csv). PASS : 450 fiches locales puis 450 rendus simulant la publication, 3 XML privés de 150 entrées, 31 768 comparaisons et 12 012 contrôles indépendants, zéro anomalie produit. 44 promotions, 2 lots, 148 EPREL / 592 PDF et 150 images Google natives ≥500 px. Aucun code applicatif, catalogue, prix, stock ou revue réelle modifié. Les trois routes de flux locales retournent 404 volontairement ; les XML réservés à example.org sont des preuves de test, jamais un flux à soumettre. Domaine, nouvel e-mail, validation juridique/commerciale, paiement, compte et contrôles publics restent à finaliser. Dossier `storage/app/private/consistency150-20261020/`.

## 20 octobre 2026 — navigation et régressions de recette

Correction des lectures répétées du catalogue par les listes de catégories et marques : le menu accepte une projection commune et l’accueil lui transmet la sienne. Les pages catalogue indisponibles fournissent explicitement une projection vide, sans tentative de relecture. Les autres pages gardent leur navigation dynamique. Aucun changement de durée de vie du catalogue ni de données commerciales. Crédit de la quatrième photo d’ambiance ajouté aux destinations autorisées du test de navigation, conformément au manifeste de provenance existant.

Contrôles ciblés réussis : 39 tests / 4 628 assertions et 4 tests de menu / 16 assertions. Le test ajouté vérifie l’absence de chargement supplémentaire pour une projection fournie, même vide. Preuves finales et limites dans [le rapport de recette sécurité](AUDIT-HEROZION-2026-10-20.md). Aucun déploiement effectué.

Validation finale de la version corrigée : **621 tests sur 621 réussis, 95 589 assertions**, durée 169,025 secondes. Suite complète exécutée hors bac à sable pour autoriser les sockets Unix synthétiques. Résultat : `output/security/phpunit-four-defects-20261020.json`. `git diff --check` est réussi sur les fichiers du correctif. Les quatre défauts demandés sont résolus ; les résultats antérieurs ci-dessus restent conservés comme étapes de diagnostic. Aucun déploiement ni migration de base réelle.


## 20 octobre 2026 — panier, commande, livraison, retours et kit Web/Cloud

[Audit et limites](AUDIT-PANIER-COMMANDE-LIVRAISON-RETOURS-2026-10-20.md). Liens panier avec variante conservée, changement de langue restant au checkout, récupération après validation bornée et sûre. Devis alignés sur les jours ouvrés et la reprise gratuite, snapshot de reprise conservé, anciens contact/domaine retirés de ces modèles. Rétractation : choix explicite sans défaut, accusé HTML directement transmis à la confirmation, reprise privée idempotente avec date stable, y compris après panne de rendu. Preflight SMTP aligné sur l’envoi réel ; dossier de verrous privé précréé.

25 fichiers installés avec SHA et sauvegardes privées. Tests ciblés 89 / 4 549 assertions et suite complète 621 / 95 589 réussis ; revue indépendante 14 / 236, Pint, espaces et build réussis. 36 rendus synthétiques Chrome FR/EN/DE à 320/768/1440 px sans débordement ; aucune soumission réelle. Kit régénéré `output/hostinger/livraison-checkout-20261020/` : 362 fichiers applicatifs, 3 753 catalogue, médias séparés (28 413 / 4 196 184 013 octets), archives 0600 relues et SHA contrôlés. Preuves privées `storage/app/private/checkout-audit-20261020/`.

Aucun stock/client réel écrit, paiement, e-mail, migration, cron ou déploiement. Cible confirmée Web ou Cloud ; domaine, nouvel e-mail, capacités hPanel/PHP web et CLI >=8.4.1, migration sélective, sauvegarde/restauration et recette réelle restent à finaliser. Transmission HTTP prouvée ; pas de certification juridique globale ni d’approbation Google. Le kit remplace celui du début de journée pour cette révision seulement.

## 20 octobre 2026 — audit manuel de sécurité complémentaire

Revue du code par domaines (accès, paiements/stock, uploads/documents, SQL/XSS, HTTP/OAuth, scripts/livraison). Aucun critique/élevé exploitable confirmé dans le périmètre examiné. Défaut modéré de redirection du collecteur local corrigé : destination validée avant contact à chaque saut. Correctif limité à scripts/collect_damdam.py et scripts/test_collect_damdam.py. **621 tests Laravel / 95 589 assertions et 51 tests Python réussis**. Rapport et preuves privés : storage/app/private/audits/manual-security-20261020/rapport.md. Pas de qualification individuelle des 73 alertes masquées, ni de validation de production/MariaDB réelle. Aucun déploiement.
