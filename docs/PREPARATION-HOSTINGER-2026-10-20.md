# Préparation locale Hostinger — 20 octobre 2026

## Résultat

Préparation locale terminée pour une offre **Web avec SSH**, confirmée par le propriétaire. Nouveau domaine à recevoir ; aucun déploiement, DNS, migration, cron, paiement ou connexion à la base cliente effectué.

- Domaine de production rendu configurable par `HOMYA_PRODUCTION_HOST` ; vide ou invalide = fermeture de la production. APP_URL doit être le HTTPS exact correspondant. L’ancien domaine n’est plus codé dans les gardes.
- Permissions des répertoires privés et logs locaux resserrées à 0700 ; `.env` conservé à 0600. Sessions et configuration de développement restent locales, sans conversion du site de travail en production.
- Modèle sécurisé de .env de production fourni sans valeurs secrètes, sockets XAMPP supprimés du modèle, commerce/admin distant/indexation désactivés initialement.
- Commande `homya:hostinger-preflight --json` ajoutée : configuration de sécurité, PHP réel du lockfile, catalogue fichiers, assets, cible commerce et transport email. Ni accès DB ni réseau ni migration.
- Kit de déploiement Web/SSH avec alternative public_html contenant uniquement les assets publics et un front controller adapté à Laravel 13. Racine et chemin privé à confirmer sur la cible.
- Script de préparation reproductible, deux paquets et manifeste médias. Archives contrôlées : pas de .env/.git/vendor/node_modules, comptes, sessions, logs ni données clients. Paquets contenant des sources privées : ne pas les placer dans le dossier public.

## Livraison générée

Dossier : `output/hostinger/livraison-20261020/`.

| Élément | Contenu |
| --- | --- |
| application.tar.gz | 358 fichiers applicatifs et répertoires vides requis, environ 2,5 Mo ; vendor à installer par Composer sur la cible |
| catalogue.tar.gz | 3 753 fichiers, environ 6,9 Mo ; archive fichiers de 3 709 entrées à revalider contre le catalogue commercial approuvé |
| manifest.json | SHA-256 des paquets et fichiers ; inventaire des 28 412 médias, 4 196 126 291 octets, à transférer séparément |

Les médias n’ont pas été recopiés dans une archive supplémentaire de 4,2 Go. Prévoir quota pour médias + release précédente + sauvegardes. Aucune donnée stock/commande MariaDB incluse ; migration de ces données à préparer dans un plan distinct. Conserver licences et provenance.

Le manifeste indique le SHA et **working_tree_dirty=true** : le dépôt contenait de nombreux changements existants. La release locale est identifiée par ses empreintes, sans prétendre qu’elle correspond à un commit unique propre. Aucun commit global ou push exécuté.

## Validation

- Suite complète hors sandbox après refus des sockets Unix de fixture : **592 tests / 89 386 assertions réussis**. La première tentative sous sandbox avait 86 erreurs de création de socket ; ces restrictions ne sont pas contournées dans le code.
- Quatre tests ciblés de préparation : 33 assertions, domaines invalides, nouveau domaine/alias, ancien domaine refusé, garde SQL XAMPP maintenue et sortie du preflight sans secret.
- Pint sur les fichiers modifiés et `git diff --check` réussis ; Vite réussi ; correction de l’ordre d’import préexistant dans AppServiceProvider.
- Archive finale inspectée et SHA-256 validés ; extraction dans un dossier temporaire isolé. Installation depuis le lockfile de **81 paquets de production**, sans dev/plugins/scripts automatiques, puis découverte explicite des paquets réussie.
- Configuration fictive créée uniquement dans cette copie : domaine `deployment.example.test`, clé éphémère générée, aucun secret du projet copié. Caches config/routes/views réussis ; preflight `configuration_passed=true` avec commerce et administration distante fermés. Cette copie, sa clé et ses caches ne font pas partie du livrable.
- Dépendances verrouillées Symfony : **PHP >=8.4.1**, bien que Laravel seul annonce >=8.3. Vérifier PHP web et CLI Hostinger, ne pas contourner composer check-platform-reqs.

## Reste à faire sur la cible

Nouveau domaine ; PHP web/CLI et quota exacts ; chemins et racine public effective ; certificat et alias ; identifiants privés, SMTP et contacts confirmés ; schéma/stock/données commerciales selon plan autorisé ; sauvegarde restaurable ; protection MFA ou accès renforcé avant administration distante. Vérifications HTTP et recette des parcours après déploiement autorisé.

Guide opérationnel : [deploy/hostinger/README.md](../deploy/hostinger/README.md). Modèle expurgé : [env.production.example](../deploy/hostinger/env.production.example).

Références : [Laravel 13 — déploiement](https://laravel.com/framework/docs/13.x/deployment), [Hostinger — SSH](https://support.hostinger.com/en/collections/3185633-ssh-connection-and-ssh-keys). Les capacités du compte Hostinger restent à observer, malgré la confirmation de l’offre Web/SSH.
