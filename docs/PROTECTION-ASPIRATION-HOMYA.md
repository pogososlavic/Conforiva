# HOMYA — protection contre l’aspiration automatisée

État au 15 octobre 2026 : correctifs publiés sur Hostinger, caches reconstruits et contrôles publics terminés après autorisation explicite. Le débit reste en **observation**. Les trois tâches hPanel temporaires ont été supprimées après confirmation ; seul le planificateur commercial `schedule:run` reste dans la liste Cron.

## Objectif et limites

Réduire la collecte massive et préserver la confidentialité des données commerciales privées. Une page, une image ou un prix accessible dans le navigateur reste copiable. Un aspirateur lent, distribué ou utilisant un navigateur ordinaire peut contourner ces mesures. Elles ne constituent ni une garantie contre le clonage ni une protection complète contre un déni de service.

Google et Merchant Center doivent accéder aux pages produit, images, robots, sitemaps et au flux des 151 offres. Le flux public contient volontairement les données publiques de ces offres ; son contenu peut aussi être téléchargé par un tiers. Ne pas y ajouter de données fournisseur confidentielles, prix d’achat ou informations clients.

## Observations

- Laravel 13 : contrôles d’accès et stockage privé déjà présents. Aucune exposition privée confirmée dans la revue statique ciblée ; les données clients de production n’ont pas été inspectées.
- Aucune limitation générale des pages catalogue avant ces correctifs. Les fichiers statiques évitent Laravel.
- hPanel consulté en lecture seule : CDN actif, sécurité **Moyen**, aucune IP bloquée. Vue pays ambiguë déjà relevée dans le dossier Google ; aucune modification effectuée et aucun blocage pays prouvé.
- Tableau CDN sur sept jours : 115 réponses 429, sans attribution à l’application, au serveur ou au CDN. Ce nombre ne prouve pas une attaque. Ne pas ajouter une limitation stricte avant d’examiner le trafic et les adresses effectivement transmises à Laravel.
- `Request::ip()` n’accorde actuellement aucune confiance aux en-têtes proxy fournis par un visiteur. Le transport réel des IP en production reste à vérifier ; aucune confiance globale aux proxys n’a été ajoutée.

## Correctifs publiés

| Mesure | Portée et comportement |
| --- | --- |
| Refus des outils déclarés | Les deux `.htaccess` refusent HTTrack/WinHTTrack, WebCopier, WebZIP, Offline Explorer, Teleport Pro et SiteSucker. Le nom peut être usurpé ; ce refus est une première barrière. |
| Fichiers sensibles | `FilesMatch` refuse secrets, clés, dumps et sauvegardes, y compris compressés, indépendamment de `mod_rewrite`. Les répertoires privés `storage/app`, `storage/framework`, `storage/logs` sont explicitement refusés par la réécriture. |
| Limitation des pages | Middleware avant les sessions/statistiques, uniquement GET/HEAD des accueils, catalogues, promotions et produits FR/EN/DE. Un même budget couvre les langues et les paramètres de recherche pour une adresse IP. |
| Mode initial | **observe**, sans nouveau refus de débit. Seuils proposés : 180 pages/minute et 1 800/heure, à calibrer sur le trafic réel. Les informations générales, commandes, paiements, assistance, administration, médias et routes Google ne partagent pas ce budget. |
| Google | Exception uniquement pour les IP des trois listes HTTPS officielles contrôlées par Google ; aucun privilège fondé sur le User-Agent. La liste des fetchers GCP utilisables par des tiers n’est pas autorisée. |
| Entretien | Téléchargement des listes par commande CLI, remplacement complet atomique, stockage privé 0700/0600, expiration de 48 h. Actualisation quotidienne à 04:20 UTC via le scheduler existant. Aucun appel réseau ni DNS pendant une visite. |
| Vie privée | Compteurs par HMAC d’IP, durée logique d’une heure, sans IP brute ni URL dans les journaux. Cache dédié ; purge horaire des fichiers inactifs depuis plus de deux heures, soit une conservation physique prévue de deux à trois heures si le scheduler fonctionne. Signaux agrégés, au plus une ligne par cause/mode/15 minutes. |
| Refus de débit | En mode `enforce` admissible : réponse 429 directe, `Retry-After`, `Cache-Control: no-store, private`, sans cache partagé. Aucun verrou ne fait attendre une requête concurrente. |

Le refus `.htaccess` intervient à l’origine. Une image déjà servie depuis le cache du CDN peut éviter ces règles. Le middleware ne couvre pas les médias ni les réponses servies directement au CDN : les protections Hostinger restent nécessaires pour le volume distribué.

## Configuration et entretien

Options ajoutées dans `.env.example`, sans modification du `.env` réel :

```dotenv
HOMYA_STOREFRONT_PROTECTION_MODE=observe
HOMYA_STOREFRONT_CLIENT_IP_VERIFIED=false
HOMYA_STOREFRONT_PROTECTION_CACHE_STORE=storefront_protection
HOMYA_STOREFRONT_REQUESTS_PER_MINUTE=180
HOMYA_STOREFRONT_REQUESTS_PER_HOUR=1800
```

`off` arrête les compteurs applicatifs ; les règles serveur restent distinctes. `enforce` n’est admissible qu’avec `CLIENT_IP_VERIFIED=true` et des listes Google valides et récentes. Sans ces conditions, la garde revient en observation. Si le cache ou son verrou est indisponible, la visite reste autorisée et un signal agrégé est produit. Ces choix préservent la disponibilité de la boutique mais limitent la protection en cas de panne.

Commandes disponibles, avec le PHP compatible du serveur :

```sh
php artisan security:refresh-google-crawlers
php artisan security:storefront-status
php artisan security:prune-storefront-cache
```

La commande de statut lit la configuration et les signaux agrégés, sans secrets ni données visiteurs. Elle reste utile si `LOG_LEVEL=error` filtre les notices ; elle ne simule pas de visite et ne certifie pas la disponibilité du cache. Les compteurs ne constituent pas des statistiques exhaustives : les robots Google reconnus et les requêtes hors périmètre sont exclus.

## Mise en production autorisée — procédure

1. Revue du diff puis sauvegarde de la configuration et des règles serveur existantes. Conserver les protections commerciales et les 151 offres Merchant.
2. Publier les correctifs avec le mode `observe`, reconstruire les caches Laravel, actualiser les listes Google et vérifier le maintien du scheduler existant. La nouvelle maintenance réutilise `schedule:run` ; aucune nouvelle tâche hPanel temporaire n’est requise si un accès CLI compatible est disponible.
3. Après autorisation des vérifications, contrôler à faible volume les pages clients, une image, robots, sitemaps, flux Merchant ; vérifier le refus d’un User-Agent HTTrack déclaré et la réponse d’un visiteur ordinaire. Vérifier le rendu Apache/LiteSpeed en production et les en-têtes 429 dans un périmètre local isolé. Pas de test de charge ni tentative de copie du site.
4. Vérifier les IP réellement reçues à partir d’un contrôle privé temporaire, sans affichage ni journalisation publique. Contrôler les signaux et le passage des véritables robots Google. Ajuster les seuils au trafic observé avant de passer à `enforce` après validation du réglage.
5. Conserver le CDN au niveau Moyen. Le mode « Under Attack » ne convient pas à une protection permanente du catalogue : il peut également challenger les robots de recherche. Ne pas superposer un second CDN sans étude du routage et du cache.

Retour arrière : restaurer les seules règles modifiées et mettre `MODE=off` si nécessaire, puis reconstruire la configuration. Ne pas désactiver les contrôles de stockage privé, d’accès client ou d’administration.

## Revue et preuves

Revue statique indépendante réalisée sur les règles serveur, la gestion des IP Google et le limiteur. Les corrections concernent notamment le verrou non bloquant, la fraîcheur fondée sur le téléchargement et la visibilité des états dégradés via la commande de statut.

Contrôles locaux réalisés après autorisation : **43 tests Laravel / 2 133 assertions réussis**, couvrant `GoogleSeoMerchantTest`, `SecurityHeadersTest`, `StorefrontChromeTest` et les onze premiers tests dédiés `StorefrontProtectionTest`. La suite dédiée a ensuite été complétée par deux cas d’actualisation des listes Google entièrement simulés et a réussi seule : **13 tests / 124 assertions**. Les périmètres se recoupent et ne doivent pas être additionnés. Aucune écriture en base XAMPP ni émission de mail.

Apache isolé éphémère : **36/36 vérifications réussies**, racine dépôt et racine `public/`, refus HTTrack sur page et image, fichiers publics/Google déclarés/flux/sitemap accessibles, secrets et sauvegardes synthétiques refusés, ACME accessible. Le serveur de recette a été arrêté ; aucun service existant modifié. Preuve : `output/security-review-20261015/apache-isolated-results.json`.

Syntaxe PHP et `git diff --check` réussis. Aucun test d’intrusion ou de charge exécuté. La publication et les contrôles publics sont documentés dans la section d’exécution ci-dessous. Capture de l’état initial hPanel : `output/security-review-20261015/hostinger-cdn-current-security.png`.

Sources officielles consultées le 15 octobre 2026 : [vérification des robots Google](https://developers.google.com/crawling/docs/crawlers-fetchers/verify-google-requests), [Hostinger CDN et moteurs de recherche](https://www.hostinger.com/support/hostinger-cdn-search-engine-crawlers-and-seo/), [IP visiteurs transmises par Hostinger](https://www.hostinger.com/support/hostinger-cdn-visitor-ip-addresses-in-logs-and-analytics/), [réponses 429 Hostinger](https://www.hostinger.com/support/hostinger-cdn-429-too-many-requests-errors/).

## Exécution en production — 15 octobre 2026

- Commit **6bf0258c0a67b9aefa9de5861cb51ad9136c47d8**, poussé sur `main`, affiché **Terminé / Actuel** dans Hostinger à **01:02 UTC**. Les seules modifications locales préexistantes du document de connexion Google ont été conservées hors de ce commit.
- Deux commandes hPanel combinées, prévues à 01:06 et 01:12 UTC, n’ont fourni aucune sortie exploitable ; elles ne constituent pas une preuve de réussite. La méthode a été remplacée par un script CLI dans `storage/app/private/security/security-maintenance.php`, conformément à la [méthode Hostinger pour les caractères spéciaux](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/). Aucun point d’entrée HTTP de maintenance créé.
- À **01:20:03 UTC**, le helper confirme séparément les retours **0** de `config:cache`, `event:cache`, `route:cache`, `view:cache`, puis redémarre une instance Laravel fraîche. Contexte production valide avant/après, sauvegarde privée de l’ancienne configuration, **mode observe**, indicateur de validation IP **false**, actualisation Google réussie et listes fraîches.
- Le cache fichiers dédié a été vérifié par un verrou et une clé aléatoire de contrôle : écriture/lecture correctes, clé retirée, aucun cache global vidé. Le statut générique conserve volontairement `runtime_cache_availability_verified=false`, car cette commande seule ne fait pas de test ; le contrôle effectif distinct du helper est vrai. Un ancien signal d’indisponibilité à 01:04:23 correspond à la période précédant la reconstruction de configuration ; il ne décrit pas le résultat positif de la sonde après reconstruction.
- Le helper vérifie par empreinte que **`.env` est inchangé** ; aucune donnée client, commande, configuration bancaire, offre, URL ou prix modifié. Script, listes, sauvegarde et rapport restent privés ; dossier 0700, fichiers opérationnels 0600. Le script ne fonctionne qu’en CLI.
- **37 GET publics aboutis**, séquentiels et à faible volume : accueils FR/EN/DE, catalogue, produits, panier en lecture seule, images, asset, robots, index XML, trois enfants représentatifs et flux Merchant accessibles en 200. **12/12 requêtes de miroirs déclarés refusées en 403**. Deux URL fictives de sauvegarde donnent 404 plutôt que 403 ; elles n’existent pas et aucune exposition n’est constatée. Les refus sur fichiers sensibles existants ont été vérifiés avec les fixtures Apache locales, pas en téléchargeant des secrets de production.
- Les trois GET refaits après maintenance (`/fr`, `/sitemap.xml`, `/google/merchant/fr.xml`) répondent **200**. Flux : **151 offres**, 198 970 octets, strictement identique au XML approuvé. Les essais réseau bloqués dans la sandbox n’ont reçu aucune réponse HTTP et sont distingués de ces 37 contrôles.
- **Search Console réel**, le **15 octobre à 01:04:25 UTC**, fiche `https://homya.eu/fr/produit/lg-f84b15whs-lave-linge` : outil Google pour smartphone, exploration autorisée, récupération **Réussie**, indexation autorisée. Cela prouve son accès après publication, sans prouver l’indexation ni l’approbation Merchant.
- **Merchant Center**, récupération manuelle de la source existante à **03:06:07 CEST / 01:06:07 UTC** : **151 produits mis à jour**, zéro nouveau, attributs **Tous reconnus**, fichier **Aucun problème trouvé**. La consultation des diagnostics indique **149 en examen et deux refusés pour « Image non traitée »**, avec retraitement annoncé sous trois jours, sans action requise. Les images des modèles **LG GTF8659PS** et **LG F964R71WRST** répondent 200 avec des dimensions respectives PNG 1059 × 1059 et JPEG 1600 × 1062. Aucune erreur d’accès à ces images démontrée ; leur traitement par Google reste distinct.
- Le planificateur existant `schedule:run` reste inchangé et une exécution commerciale **DONE** avec PHP 8.5 a été observée à 01:11:01 UTC. Les maintenances Google quotidiennes et la purge horaire réutilisent ce planificateur. Aucun changement du CDN, mode Under Attack, DNS ou abonnement.
- Après confirmation explicite de l’utilisateur au moment du nettoyage, les trois tâches temporaires prévues à **01:06, 01:12 et 01:20 UTC** ont été supprimées. À **01:26 UTC**, la liste hPanel ne contient plus que `schedule:run` chaque minute. Preuve : `output/security-review-20261015/hostinger-cleanup-complete.png`.

Preuves sous `output/security-review-20261015/` : `release-receipt.json`, `hostinger-maintenance-complete.json`, `public-http-results.json`, `post-maintenance-http.json`, captures Hostinger, Search Console et Merchant. Les preuves ne contiennent ni coordonnées bancaires, ni secrets, ni données clients.
