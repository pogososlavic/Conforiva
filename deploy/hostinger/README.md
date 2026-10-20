# HOME CONFORT — préparation Hostinger Web ou Cloud

Préparation locale uniquement. La cible Web ou Cloud est confirmée ; SSH, capacités et chemins du compte restent à vérifier dans hPanel. Aucun domaine définitif reçu ; aucun transfert, achat, DNS, migration ou cron activé. Le dossier est un kit de préparation, pas une preuve de publication.

## Livrables et données

Exécuter `python3 scripts/prepare_hostinger_release.py --output-dir output/hostinger/<identifiant-neuf>` après tests et build. Le répertoire de sortie doit être nouveau, privé et hors public/.

- `application.tar.gz` : application, migrations, ressources, langues, dépendances verrouillées et assets compilés. Sans .env, .git, vendor, node_modules, données privées, caches de configuration ni rapports de travail.
- `catalogue.tar.gz` : copie du catalogue fichiers, séparée de l’application. Les 3 709 entrées locales doivent être comparées au catalogue réellement approuvé avant ouverture ; cela ne transfère ni stocks ni commandes de MariaDB.
- `manifest.json` : empreintes SHA-256, état Git réel et liste des médias à transférer depuis public/media. Environ 4,2 Go de médias conservés à part, sans créer une copie supplémentaire inutile. Préserver les licences, documents et attributions.

Vérifier les empreintes avant/après transfert. Le manifeste est issu du répertoire de travail : s’il indique un état Git modifié, ce n’est pas une release identifiée uniquement par le SHA. Faire une revue puis un commit sélectif avant la publication finale. Ne pas pousser vers une branche déclenchant une publication sans autorisation.

## Organisation distante

Préférer une racine web qui pointe vers `home-confort/current/public`. Sources, .env et données persistantes restent hors racine publique.

Si public_html est imposé, disposition alternative à valider sur l’offre :

```text
<domaine>/home-confort/releases/<release>/  application privée
<domaine>/home-confort/shared/             .env, storage persistant
<domaine>/home-confort/current             release active
<domaine>/public_html/                     contenu public seulement
```

Le fichier `public-index.php` fourni remplace alors **uniquement** public_html/index.php ; il attend exactement le répertoire frère `home-confort/current`. Il garde le bootstrap Laravel 13 et fixe le public path à public_html. Adapter et vérifier ce chemin avant usage ; ne pas déposer l’application complète sous public_html. Conserver la copie privée de public/ pour les contrôles CLI, et synchroniser les mêmes assets vers la racine servie. Tester les deux jeux d’empreintes.

## Préparer la cible sans ouvrir le trafic

1. Vérifier dans hPanel et SSH PHP web **et** CLI >=8.4.1 (exigence du lockfile actuel ; Laravel seul demande >=8.3), extensions, quota (médias + sauvegarde + release précédente), Composer, cron, symlinks et certificat. Ne pas extrapoler les capacités d’une autre offre.
2. Sauvegarder application, données et base existantes et tester leur restauration avant remplacement. Garder la release précédente.
3. Extraire l’application hors racine web et vérifier d’abord les répertoires et permissions de l’étape 4 (le paquet fournit des répertoires vides). Installer depuis le lockfile : `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Ne pas faire composer update et ne pas ignorer les contraintes de plateforme. Si Composer manque, préparer vendor dans un environnement compatible séparé ; le paquet actuel n’en contient pas.
4. Créer les répertoires storage/framework/{cache/data,cache/locks,sessions,views}, storage/app/private, storage/app/public, storage/logs et bootstrap/cache. Propriétaire PHP/SSH selon l’offre ; répertoires privés/logs et cache/locks 0700, .env et fichiers privés 0600. Autoriser les écritures nécessaires seulement ; jamais 0777.
5. Créer le vrai .env à partir de `env.production.example`. Renseigner en privé APP_KEY et les accès. Pour une migration de données chiffrées existantes, **conserver leur APP_KEY** ; ne pas régénérer de clé et ne pas transférer les secrets via les archives. Pour une installation neuve, génération privée sur la cible seulement après choix explicite du périmètre.
6. Renseigner le nouveau domaine exact en minuscules dans HOMYA_PRODUCTION_HOST et les URLs HTTPS cohérentes dans APP_URL et HOMYA_PUBLIC_URL. Valeur vide/invalide ferme la production ; l’ancien domaine n’est plus codé dans la garde. Les contacts commerciaux doivent être confirmés indépendamment.
7. Garder le catalogue `files`. Ne pas supprimer la garde SQL locale ni activer le lecteur XAMPP sur Hostinger. Extraire le paquet catalogue dans la release ; transférer les médias suivant le manifeste. Valider les fiches/images et l’actualité du catalogue avant exposition.
8. Garder commerce, stock, administration distante, intégrations et indexation désactivés jusqu’à leur recette. Configurer les bases et utilisateurs distincts de moindre privilège. Renseigner explicitement les sockets vides et les ports fournis par Hostinger.

## Contrôles de la release

Sur la cible, après configuration privée et installation des dépendances :

```sh
composer check-platform-reqs --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan homya:security-audit --json
php artisan homya:hostinger-preflight --json
```

Les contrôles sont sans requête SQL, réseau ni migration. Un échec doit être compris, pas contourné. Les résultats ne valident pas TLS, les droits SQL, la délivrance SMTP, la racine web effective ou la restauration. Le cache de configuration doit être généré sur la cible, jamais copié du poste local. Exécuter toutes les commandes avec le même PHP compatible.

## Base commerciale, tâches et emails

Aucune migration incluse dans un script automatique. Préparer séparément un plan autorisé et une sauvegarde ; migrations commerce dans `database/migrations/commerce`, connexion `commerce`. Ne pas exécuter l’installateur local XAMPP sur Hostinger. La migration 000004 modifie des lignes existantes : revue préalable indispensable. Ne jamais employer migrate:fresh ou db:wipe.

Le modèle utilise `QUEUE_CONNECTION=sync` : les devis sont générés et soumis au transport SMTP pendant le parcours, sans worker permanent présumé. Le preflight vérifie les mêmes conditions locales que ce transport : SMTP configuré, adresse expéditrice valide et identique au contact commercial. Il ne se connecte pas au serveur SMTP et ne prouve pas la réception. Si les queues sont changées, préparer une stratégie bornée adaptée au compte et une recette distincte.

Avant activation des commandes : vérifier schéma, stocks réels, prix, documents, RIB confirmé, SMTP et délivrance, expiration des réservations et parcours de commande en recette. Le scheduler existant comporte des actions d’écriture et des appels réseau : configurer un cron `schedule:run` seulement dans le plan de mise en service autorisé, avec le PHP exact. Rien n’est planifié par ce kit. L’expiration des réservations est prévue chaque minute avec un verrou anti-chevauchement ; vérifier les écritures dans cache/locks, le PHP CLI exact et la sortie du cron sur la cible. Hostinger distingue les commandes simples et les scripts shell pour les redirections : ne pas coller une commande avec caractères spéciaux dans un champ qui les refuse. Garder tout wrapper et son journal hors public_html, avec accès privé. Le scheduler peut aussi appeler Google selon les options : ne pas l’exécuter pendant une recette sans isoler ces intégrations. Les services externes restent désactivés ; aucune MFA improvisée n’est ajoutée. Protéger l’accès admin distant par MFA/protection d’accès adaptée avant activation.

## Données privées à préparer séparément

Le paquet ne contient aucun contenu de storage/app/private. Préparer une migration privée sélective et autorisée du RIB chiffré, des documents commerciaux et de leur numérotation, des demandes de retour et des enregistrements Google retenus, si ces données doivent être reprises. Garder leur APP_KEY et tester leur lecture dans une copie isolée. Ne pas copier automatiquement tout le dossier admin, ses accès, jetons, archives ou données de test. Le stock et les commandes SQL demandent un plan distinct. Les répertoires partagés persistants doivent être exclus d’un remplacement de release ; les archives applicatives seules ne rendent pas le commerce opérationnel.

Régénérer les paquets et leurs empreintes après les corrections finales. Le kit daté du début de journée peut précéder les changements du catalogue ou du checkout ; son existence ne prouve pas qu’il contient la version vérifiée.

## Recette avant ouverture et retour arrière

Tester HTTPS, alias, cookies Secure/HttpOnly, CSP, URLs profondes, public/index.php et les refus de .env/.git/logs/dumps sur **chaque** domaine/alias. Tester anonymat et rôles admin, mobiles, formulaires, catalogue complet et médias. Garder indexation/flux Google fermés tant que la recette n’est pas validée.

En cas d’échec : fermer le trafic, revenir à la release précédente compatible, restaurer les fichiers publics correspondants et leurs empreintes. Préserver .env, APP_KEY, uploads et sessions persistantes ; aucun rsync --delete global. Le retour du code ne rétablit pas une base migrée : restaurer la base uniquement selon le plan de sauvegarde autorisé.

Sources officielles à vérifier pour le compte : [Laravel 13 — déploiement](https://laravel.com/framework/docs/13.x/deployment), [Hostinger — accès SSH](https://support.hostinger.com/en/collections/3185633-ssh-connection-and-ssh-keys), [Hostinger — déploiement Laravel](https://www.hostinger.com/ca/tutorials/how-to-deploy-laravel/). Le tutoriel VPS n’est pas une validation des capacités de l’offre Web ; ne pas reprendre ses commandes root sur l’offre Web.

Références Hostinger vérifiées le 20 octobre 2026 : [PHP web par domaine et distinction CLI](https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/), [Composer avec PHP explicite](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/), [capacités de processus Web/Cloud](https://www.hostinger.com/support/which-server-capabilities-are-supported-at-hostinger/), [cron et caractères spéciaux](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/). Les exemples de l’aide ne remplacent pas le contrôle du compte.
