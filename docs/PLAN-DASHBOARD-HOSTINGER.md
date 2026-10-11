# Plan proposé — Dashboard commerce en production Hostinger

**État :** préparation seulement — aucune base Hostinger lue ou modifiée par Codex ; aucune migration autorisée ou exécutée.
**Projet :** HOMYA, Laravel 13, domaine `homya.eu`.
**Objectif :** rendre disponibles en production les mêmes fonctions de gestion que dans le dashboard local, sans remplacer les bases existantes ni inventer un stock réel.

## 1. Constats et preuves

- L’extension du dashboard est active dans les copies locales. Le schéma commerce local contient quatre tables (`commerce_inventory`, `commerce_orders`, `commerce_order_items`, `commerce_payment_events`) et l’extension six autres tables `commerce_admin_*`.
- L’inventaire local observé lors de son installation contenait 3 709 références, avec une valeur initiale déclarée de 1 000 unités par produit. Ce chiffre décrit l’installation locale historique ; il ne certifie pas le stock physique actuel en production.
- L’utilisateur a indiqué que Hostinger dispose actuellement des bases principale et catalogue, sans base commerce séparée.
- Le `.env` de production partagé précédemment ne présentait pas les variables `HOMYA_COMMERCE_DB_*` ni `HOMYA_ADMIN_MANAGEMENT_ENABLED`. Ne pas recopier ni publier ce fichier : il contenait des secrets.
- `config/database.php` configure la connexion `commerce` séparément de la connexion Laravel `DB_*`. Elle attend les variables `HOMYA_COMMERCE_DB_HOST`, `HOMYA_COMMERCE_DB_PORT`, `HOMYA_COMMERCE_DB_DATABASE`, `HOMYA_COMMERCE_DB_USERNAME`, `HOMYA_COMMERCE_DB_PASSWORD` et `HOMYA_COMMERCE_DB_SOCKET`.
- Les installateurs actuels ciblent explicitement XAMPP / `homya_local`. `homya:commerce-schema` et `scripts/install_admin_management.py` ne sont pas des installateurs Hostinger ; ne pas les lancer sur le serveur.
- Hostinger indique MariaDB pour ses hébergements Web/Cloud et documente `localhost` pour une connexion locale au site et le port standard 3306. L’offre exacte, ses quotas et permissions restent à confirmer dans le hPanel du projet ([source Hostinger](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/), consultée le 11 octobre 2026).

## 2. Proposition technique à valider

Créer une troisième base dédiée commerce dans hPanel, assignée à `homya.eu`, en conservant les bases principale et catalogue. Vérifier d’abord que l’offre permet cette base et que son utilisateur peut être limité après l’installation. Ne pas réutiliser une base existante sans avoir inventorié ses tables et sauvegardé son contenu.

Configurer uniquement dans le `.env` privé de production :

```dotenv
HOMYA_COMMERCE_DB_HOST=localhost
HOMYA_COMMERCE_DB_PORT=3306
HOMYA_COMMERCE_DB_DATABASE=<nom communiqué par hPanel>
HOMYA_COMMERCE_DB_USERNAME=<utilisateur lié à cette base>
HOMYA_COMMERCE_DB_PASSWORD=<nouveau secret privé>
HOMYA_COMMERCE_DB_SOCKET=
```

Le driver Laravel de cette connexion est déjà fixé à `mariadb`. Le mot de passe doit être distinct des identifiants précédemment partagés et ne doit jamais apparaître dans Git ou le chat.

Installer de façon additive, après sauvegarde et contrôle de cible :

1. Les quatre tables commerce définies par `database/migrations/commerce/2026_10_09_000001_create_commerce_tables.php`.
2. Les six tables de gestion définies par `database/migrations/commerce/2026_10_09_000002_create_admin_tables.php`.
3. L’inventaire initial uniquement après validation explicite de chaque quantité, prix et état de vente proposés pour la production. Aucune commande, réservation, conversation ou donnée analytique locale n’est copiée dans la base de production.

Les migrations de schéma sont additives. Une migration MariaDB peut laisser un état partiel ; aucun `DROP`, rollback automatique ou migration générale Laravel n’est prévu. L’installation nécessitera un plan/script dédié au serveur, avec cible vérifiée, empreinte des fichiers SQL, sauvegarde privée, contrôles des collisions, droits DDL temporaires puis retirés, et arrêt sur la première différence.

## 3. Activation progressive

1. Vérifier le commit déployé, les versions PHP/MariaDB et la disponibilité d’une sauvegarde restaurable.
2. Créer et inspecter la base commerce vide ; confirmer le nom de l’hôte, la base et l’utilisateur depuis hPanel.
3. Préparer et faire valider le plan DDL propre à cette cible. La validation du plan ne vaut pas autorisation de son exécution.
4. Exécuter le schéma et comparer les dix tables, clés, index et contraintes à leurs migrations ; retirer les droits DDL temporaires.
5. Configurer la connexion commerce en privé et exécuter `php artisan config:clear` puis `php artisan config:cache`.
6. Vérifier la connexion et les tables par des lectures sans secret ni modification de données.
7. Importer l’inventaire seulement après décision métier distincte sur le stock réel. Vérifier le nombre de lignes, les références, prix, réservations et états de vente contre le manifeste approuvé.
8. Activer `HOMYA_ADMIN_MANAGEMENT_ENABLED=true` après réussite des contrôles précédents. Maintenir les fonctions panier/commande/stock fermées jusqu’à validation du stock et des prérequis de production.
9. Vérifier les écrans d’administration sans créer de commande ni modifier de stock ; toute recette avec écriture réelle aura son propre jeu de données et son autorisation.

## 4. Décisions encore requises

- L’offre Hostinger permet-elle une troisième base et l’utilisateur/permission requis ? **À vérifier dans hPanel.**
- Le stock local déclaré à 1 000 unités par produit correspond-il au stock physique actuel à vendre en production ? **Non confirmé.**
- La base principale Hostinger contient-elle déjà des tables ou données de commandes à conserver ? **Non vérifié.**
- L’utilisateur autorise-t-il l’exécution du plan d’installation production une fois la cible, la sauvegarde, les droits et le manifeste de stock examinés ? **Non accordé à ce stade.**

## 5. État de livraison

Ce document est une préparation révisable, pas un plan validé. Aucun secret, compte, base, droit, schéma, inventaire ou réglage de production n’a été modifié. Ne pas activer `HOMYA_ADMIN_MANAGEMENT_ENABLED` seul : le code de gestion requiert les tables commerce et les six tables `commerce_admin_*`.
