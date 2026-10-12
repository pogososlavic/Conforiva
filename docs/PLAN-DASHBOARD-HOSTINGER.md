# Plan proposé — Dashboard commerce en production Hostinger

**État au 12 octobre 2026 :** la base commerce Hostinger et ses tables existent. Les dernières lectures fournies par l’utilisateur confirmaient 3 709 lignes d’inventaire, toutes à 0, aucune vente activée et aucune commande. L’utilisateur a maintenant confirmé 500 unités par produit. Le code de synchronisation est préparé localement ; il n’a pas encore été déployé ni exécuté en production.
**Projet :** HOMYA, Laravel 13, domaine `homya.eu`.
**Objectif :** activer en production l’inventaire confirmé à 500 unités par produit et reprendre les prix TTC courants du catalogue, sans remplacer les bases existantes.

## 1. Constats et preuves

- L’extension du dashboard est active dans les copies locales. Le schéma commerce local contient quatre tables (`commerce_inventory`, `commerce_orders`, `commerce_order_items`, `commerce_payment_events`) et l’extension six autres tables `commerce_admin_*`.
- L’inventaire local observé lors de son installation contenait 3 709 références, avec une valeur initiale déclarée de 1 000 unités par produit. Ce chiffre décrit l’installation locale historique ; il ne certifie pas le stock physique actuel en production.
- L’utilisateur a indiqué que Hostinger dispose actuellement des bases principale et catalogue, sans base commerce séparée.
- Le `.env` de production partagé précédemment ne présentait pas les variables `HOMYA_COMMERCE_DB_*` ni `HOMYA_ADMIN_MANAGEMENT_ENABLED`. Ne pas recopier ni publier ce fichier : il contenait des secrets.
- `config/database.php` configure la connexion `commerce` séparément de la connexion Laravel `DB_*`. Elle attend les variables `HOMYA_COMMERCE_DB_HOST`, `HOMYA_COMMERCE_DB_PORT`, `HOMYA_COMMERCE_DB_DATABASE`, `HOMYA_COMMERCE_DB_USERNAME`, `HOMYA_COMMERCE_DB_PASSWORD` et `HOMYA_COMMERCE_DB_SOCKET`.
- Les installateurs actuels ciblent explicitement XAMPP / `homya_local`. `homya:commerce-schema` et `scripts/install_admin_management.py` ne sont pas des installateurs Hostinger ; ne pas les lancer sur le serveur.
- Hostinger indique MariaDB pour ses hébergements Web/Cloud et documente `localhost` pour une connexion locale au site et le port standard 3306. L’offre exacte, ses quotas et permissions restent à confirmer dans le hPanel du projet ([source Hostinger](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/), consultée le 11 octobre 2026).

### Décision utilisateur — activation confirmée à 500 unités

La décision antérieure d’affichage seul est remplacée par l’autorisation explicite : « 500 unités à chaque produit ». `homya:commerce-activate-inventory` prépare un manifeste privé avec 500 unités, le prix TTC courant du catalogue et les motifs de blocage. Son mode `--apply --sha256=<empreinte>` ne fonctionne que si l’inventaire correspond encore exactement au catalogue, toutes les lignes sont à quantité/réservation zéro et aucune commande n’existe. Il met à jour les lignes existantes sans réinitialiser une commande ou réservation. Les produits refusés par les règles métier du panier restent identifiés dans les motifs de blocage.

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
3. Les tables commerce et lignes catalogue existent déjà. Ne pas les recréer. Après une sauvegarde de la base commerce, utiliser la commande dédiée d’activation à 500 unités ; elle s’arrête si l’état réel diffère des zéros examinés ou si une commande existe.

Les migrations de schéma sont additives. Une migration MariaDB peut laisser un état partiel ; aucun `DROP`, rollback automatique ou migration générale Laravel n’est prévu. L’installation nécessitera un plan/script dédié au serveur, avec cible vérifiée, empreinte des fichiers SQL, sauvegarde privée, contrôles des collisions, droits DDL temporaires puis retirés, et arrêt sur la première différence.

## 3. Activation progressive

1. Vérifier le commit déployé, les versions PHP/MariaDB et la disponibilité d’une sauvegarde restaurable.
2. Créer et inspecter la base commerce vide ; confirmer le nom de l’hôte, la base et l’utilisateur depuis hPanel.
3. Préparer et faire valider le plan DDL propre à cette cible. La validation du plan ne vaut pas autorisation de son exécution.
4. Exécuter le schéma et comparer les dix tables, clés, index et contraintes à leurs migrations ; retirer les droits DDL temporaires.
5. Configurer la connexion commerce en privé et exécuter `php artisan config:clear` puis `php artisan config:cache`.
6. Vérifier la connexion et les tables par des lectures sans secret ni modification de données.
7. Après déploiement du code et sauvegarde récente de la base commerce, exécuter `php artisan homya:commerce-activate-inventory`. Contrôler le nombre de produits, unités (produits × 500), ventes éligibles et motifs de blocage. Puis exécuter `php artisan homya:commerce-activate-inventory --apply --sha256=<empreinte exacte affichée>`. La commande applique l’activation une seule fois ; si les stocks ou commandes ont changé depuis l’inspection, elle refuse sans écraser cet état.
8. Vérifier `HOMYA_INVENTORY_ENABLED=true`, vider/reconstruire le cache de configuration, puis contrôler les totaux commerce : lignes attendues, quantités 500, réservations inchangées, ventes actives pour les seuls produits éligibles, prix approuvés égaux aux prix TTC du catalogue. Charger une fiche produit et vérifier que son bouton d’achat est actif.
9. Vérifier les écrans d’administration sans créer de commande ni modifier de stock ; toute recette avec écriture réelle aura son propre jeu de données et son autorisation.

## 4. Décisions encore requises

- Aucun arbitrage de quantité supplémentaire : 500 par produit a été confirmé par l’utilisateur. L’exécution en production reste à faire par l’utilisateur après sauvegarde et déploiement, car Codex n’a pas de session SSH active sur Hostinger.

## 5. État de livraison

Le schéma de production est installé par l’utilisateur. L’activation locale est préparée et syntaxiquement vérifiée. Déployer le code, faire une sauvegarde, préparer et examiner le manifeste, puis appliquer son empreinte exacte sur Hostinger et vérifier les résultats.
