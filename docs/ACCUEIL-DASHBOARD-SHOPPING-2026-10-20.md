# HOME CONFORT — accueil, administration et préparation Shopping

État local du 20 octobre 2026. Projet : `/Applications/XAMPP/xamppfiles/htdocs/HOMECONFORT`. Aucun déploiement, push Git, achat, envoi de message, paiement ou soumission Google n’a été effectué.

## Résultat visible

- Mention décorative « visuels d’illustration » retirée de l’accueil ; crédits et licences des médias conservés.
- Haut d’accueil ivoire translucide, jaune des textes et bordures adouci. Ajouter au panier compact avec dégradé discret, état indisponible distinct. Nous écrire plus compact et translucide ; cible tactile de 44 px conservée, bouton flottant réduit en icône sur mobile.
- Build Vite réussi. Recette Chrome locale : accueil/catalogue/produit à 320, 375, 768, 1024 et 1440 px, accueil EN/DE à 375 px ; aucun débordement dans ces cas. Après installation des textes, 36 fiches FR/EN/DE supplémentaires vérifiées à 375 px.

## Tableau de bord local

Ouvrir http://127.0.0.1:8000/admin. Le compte propriétaire a été créé et la gestion interne activée. Identifiants à lire dans le fichier privé local `storage/app/private/admin/access-20261020161229-1L1U0SCg.txt`, hors serveur public et Git. Ne pas copier ce fichier dans une sauvegarde publique ou un message.

Connexion, déconnexion et accès à 15 écrans exercés dans Chrome : commandes, stocks, retours, produits, livraisons, messages, activité, documents, gestionnaires, paramètres/RIB, intégrations, compte et notifications. Administration testée à 320/768/1440 px. Les tests métier couvrent les autorisations et opérations internes dans leur environnement isolé ; aucune commande réelle de recette ni modification de stock métier n’a été créée. Le schéma XAMPP existant est suffisant ; aucune migration ni restauration SQL n’a été nécessaire.

Les connexions externes GA4, Search Console, Google Ads, Merchant Center et Meta restent à configurer. Le paiement réel et l’envoi effectif des emails restent fermés/non vérifiés. L’administration distante reste désactivée et les ressources privées testées via HTTP sont interdites. L’activation locale ne constitue pas un lancement commercial.

## Expédition, retours et siège

Le propriétaire confirme le départ ET la réception des retours : **Rue Aristote, Mont de Terre, Fives, 59000 Lille, France**. Le siège légal demeure **1010 A ROUTE DU MAHURY, 97354 RÉMIRE-MONTJOLY** et conserve les correspondances et notifications postales. Aucun numéro de rue supplémentaire n’a été supposé.

Les adresses opérationnelles sont centralisées dans `config/shipping.php`. Livraison, contact, CGV, rétractation, aperçu et accusé téléchargeable reprennent la destination correcte en FR/EN/DE. Le hash des conditions tient compte des destinations pour demander une nouvelle validation en cas de changement ; les historiques et anciens documents restent conservés.

Conditions existantes confirmées : livraison gratuite en France, Belgique et Allemagne ; préparation 1–2 jours ouvrés puis transport 2–5 jours, soit 3–7 jours ouvrés après paiement confirmé ; lundi–vendredi, limite 14 h Europe/Paris. Retours gratuits selon la politique déjà publiée et organisation du transport avec HOME CONFORT. Ces éléments sont déclarés par le propriétaire ; aucune opération réelle de transport n’a été auditée.

## Sélection pour préparer Google

12 produits, quatre par famille, documentés dans `SELECTION-SHOPPING-HOME-CONFORT-2026-10-20.csv` :

- Chauffage : Panadero SYDNEY 18250, OVAL 18300, MAJA-S 18400 et MAJA 18405.
- Bois : palettes de granulés Piveteaubois HP+ 72×15 kg, Badger 65×15 kg, Crépito 72×15 kg et Moulin Bois Énergie 65×15 kg.
- Électroménager : LG GFT61PZCSE, LG F14R33WHS, Whirlpool WHK25364W4E et Indesit INK25402S4E.

Les 36 textes factuels originaux sont installés avec leur provenance algorithmique. Huit EAN et sept références fabricant sont documentés ; huit modèles disposent de leurs documents énergétiques exacts, soit 32 PDF vérifiés. Le champ LG de hauteur 1932 mm non corroboré est retiré sans le réinterpréter ; la hauteur hors tout de 1860 mm demeure. La classe acoustique Indesit passe de B à C d’après sa fiche EPREL exacte.

Les sept candidats prioritaires sont les quatre Panadero et les LG lave-linge, Whirlpool et Indesit. Ils restent soumis aux vérifications globales ci-dessous. Cinq offres restent en réserve produit : quatre palettes sans identifiant officiel du conditionnement complet et le congélateur LG dont la définition réelle de la photo reste faible. La photo Piveteaubois doit en outre être remplacée par un visuel autorisé de l’offre exacte adapté à Shopping. Aucune référence de sac n’a été présentée comme EAN de palette et aucune exemption d’identifiant n’a été inventée. Les combustibles dont le fournisseur reste inconnu ne sont pas déclarés prêts.

Le stock des 12 offres a été lu dans MariaDB XAMPP par requêtes SELECT : 500 unités disponibles chacune, zéro réservé, références et prix approuvés concordants. Cela ne constitue pas un inventaire physique de l’entrepôt.

Les 12 offres et leurs 36 contenus localisés, documents, provenance et réserves sont rassemblés dans le dossier privé `storage/app/private/dashboard-shopping-20261020/`. Le fichier `root/shopping-post-install.private.json` est une préparation privée, **pas un flux à importer** : domaine et liens publics restent vides, disponibilité Google non affirmée, approbations fausses. Les 1 075 revues du catalogue restent non approuvées, empreintes vides.

## Informations et contrôles encore nécessaires

- Domaine public HTTPS : Hostinger est l’hébergeur confirmé, pas un domaine. Nouvelle adresse email à communiquer, puis délivrance réelle à tester.
- Paiement de production/RIB à configurer et vérifier ; parcours d’achat public complet à recetter.
- Vérification/revendication du domaine et état des comptes Merchant Center/Google Ads ; paramétrage des livraisons/retours sur les comptes réels.
- Vérification publique des pages, robots, images, JSON-LD et cohérence prix/disponibilité ; notices de garantie, informations de sécurité et instructions exactes à finaliser par produit et langue avant lancement.
- Identifiants du conditionnement complet des quatre palettes ; photos exactes des lots et amélioration native des deux réserves médias signalées.

Ces conditions ne garantissent pas l’acceptation par Google. Le site reste local avec indexation et flux désactivés ; aucune demande d’examen n’a été envoyée.

## Vérifications et limites

**Suite complète : 573 tests / 71 230 assertions réussis.** Aucun test ignoré ou en échec signalé. Revue indépendante après installation : neuf pages Livraison/CGV/Rétractation FR/EN/DE répondent 200, affichent Lille et ne contiennent aucun paramètre d’adresse non résolu ; les 17 empreintes des fichiers d’adresse concordent.

Contrôles ciblés : catalogue 10 tests / 11 195 assertions ; adresses/retours/commande 71 / 4 206 ; administration 52 / 658. Pint et espaces Git des fichiers concernés réussis. Audit des dépendances de production Composer et npm : aucune alerte retournée à cette date ; dépendances de développement non auditées dans ce passage. Analyse des noms/symlinks publics sans alerte et six ressources privées refusées sur l’hôte local testé ; cela reste une revue de sécurité partielle.

11 127 projections comparées : seuls 12 produits changent par langue ; les 3 697 autres sont strictement identiques. Prix, promotions, images, références et 3 714 JSON source inchangés ; 448 autres profils officiels préservés. Audit hors base : 373 produits avec anomalies, 55 fiches incomplètes (48 vides / sept partielles), 3 270 sans identifiant fabricant, 108 réserves énergétiques Aquaflam maintenues. Son indicateur de stock désactivé décrit le mode hors ligne, pas la configuration réelle du site.

Preuves : `root/projection-verification.json`, `root/inventory-selected.json`, `root/audit-summary.json`, `root/dependency-security.json`, `qa/browser-results.json`, `qa/product-renders.json`, revues Merchant indépendantes et revue des nouvelles adresses. Sauvegardes avant modification dans `before/`, `before-disabled-state/`, `before-merchant/` et `before-shipping/` du dossier privé. Le fichier de sauvegarde `.env` et les identifiants restent exclusivement dans le dossier privé d’administration existant, permissions restrictives.
