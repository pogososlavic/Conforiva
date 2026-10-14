# HOMYA — connexion à Search Console et Merchant Center

## Préparation Merchant Center — 14 octobre 2026

### Compte, boutique et organisation

Compte **HOMYA** consulté dans le navigateur : `homya.eu` est **validé et revendiqué**. L'ajout des produits par URL XML est sélectionné dans l'assistant. À la lecture publique du 14 octobre à 23:25 UTC, `https://homya.eu/google/merchant/fr.xml` répond **404** : l'URL existe dans l'application, mais son ouverture en production reste à réaliser après contrôle des prérequis et autorisation de publication.

La structure actuelle est exploitable : **3 709 produits**, **65 types de produits**, aucun produit sans hiérarchie `product_type`. Conserver les univers et les URL actuelles :

| Univers | Produits | Préparation Merchant |
| --- | ---: | --- |
| Bois & combustibles | 52 | Unité/conditionnement et identité fabricant selon les informations réelles |
| Appareils de chauffage | 3 086 | Type précis, installation, identifiants et applicabilité énergétique par modèle |
| Électroménagers | 253 | Identifiants fabricant ; certification énergétique pour les appareils concernés |
| Mobiliers | 317 | Dimensions, finition, variantes et transport volumineux |
| Téléviseurs | 1 | Identifiants et étiquetage énergétique du modèle |

Les catégories du site alimentent `product_type`. Les identifiants de taxonomie Google restent facultatifs et doivent correspondre à une attribution réelle. Les pages produit, le panier et le flux utilisent le même prix final ; l'inventaire impose la référence et le prix validés. Les promotions ne sont pas recalculées une seconde fois dans le flux.

### Faits métier confirmés dans cette conversation

Le marchand confirme la validation interne du catalogue et l'origine fabricant/fournisseur des descriptions françaises. Les textes effectivement réécrits par IA dans le projet conservent leur provenance explicite. Aucun catalogue fournisseur confidentiel n'a été demandé à nouveau ni transmis à Google.

- Expédition depuis **la France** ; livraison **gratuite en France, Belgique et Allemagne**.
- Livraison totale **3–7 jours ouvrés après paiement confirmé** : préparation **1–2 jours**, puis transport **2–5 jours**, du lundi au vendredi ; limite **14 h, Europe/Paris**.
- Rétractation notifiée sous **14 jours après réception**, retour gratuit ; échanges pour changement d'avis acceptés.
- Traitement opérationnel du remboursement sous **10 jours après réception du retour**, avec respect de toute échéance légale plus courte. Le délai légal depuis notification et ses exceptions sont conservés.

Les neuf fichiers de traduction `lang/{fr,en,de}/{pages,commerce,legal}.php` ont été harmonisés localement. Leur publication Hostinger reste une étape distincte. La livraison a été enregistrée dans Merchant Center : confirmation « Vos informations de livraison ont été ajoutées », trois pays, livraison gratuite et 3–7 jours ouvrés. La règle de retour est préparée avec URL `https://homya.eu/fr/retractation`, produits neufs et légèrement utilisés, échanges, 14 jours, retour postal avec étiquette gratuite à télécharger/imprimer, aucun frais de remise en stock et remboursement 10 jours. Sa confirmation doit suivre la publication des nouvelles précisions sur le site.

### Premier lot concret : 151 articles français

Le nouvel audit retrouve **151 EAN explicitement libellés dans les caractéristiques** des produits LG. Tous ont 13 chiffres et une clé valide, sans doublon, conflit, variante ni anomalie catalogue signalée. La clé et l'unicité sont contrôlées ; l'attribution repose sur le catalogue validé par le marchand. Répartition : 47 lave-linge, 11 lave-linge séchants, 14 sèche-linge, 5 WashTower, 73 réfrigérateurs et 1 écran StanbyME.

Une revue interne française a été préparée dans `output/qa-merchant-20261014-first-batch/merchant-products-reviewed-fr.json`. Elle utilise les empreintes de la projection applicative courante, les EAN extraits, la provenance confirmée et les données déjà publiées. Seule la langue française est approuvée dans cette sélection. L'approbation interne n'est pas une décision de Google.

Résultat vérifié : **151 offres techniquement préparées**, **151 identifiants et EAN uniques**, tous les champs essentiels présents, XML valide de **196 361 octets**, aucun problème d'inventaire local ; **151 images principales présentes et d'au moins 500 × 500 pixels**. Aperçu privé : `merchant-preview-fr.xml` ; synthèse : `first-batch-verification.json`. L'audit local indique encore **0 export public**, car les verrous du site local sont conservés. Les valeurs de configuration locales ne constituent pas une lecture de la configuration Hostinger.

Ces appareils nécessitent une revue de l'étiquetage énergétique. Les codes EPREL ne figurent pas dans les données examinées. Google documente la possibilité de retrouver une certification à partir du GTIN/MPN : l'option explicite `allow_google_energy_lookup` est activée pour ce premier lot, avec **151 avertissements `eprel_google_lookup_pending`**. Le résultat de cette recherche et l'acceptation doivent être contrôlés après récupération par Google. Aucune certification n'a été inventée. [Fonctionnement EPREL Google](https://support.google.com/merchants/answer/16569525?hl=fr).

Les **3 558 autres produits** restent dans la liste de complément : les descriptions validées ne fournissent pas actuellement de CPU structuré exploitable. Les 26 groupes et 92 options nécessitent notamment leurs identifiants et images propres. Une référence interne ou un code de sortie de fumées n'est pas assimilé à un MPN. `identifier_exists=no` exige une absence réelle confirmée des identifiants applicables. [Règle Google](https://support.google.com/merchants/answer/6324478?hl=fr).

### Flux automatique et contrôles opérationnels

Liens stables conservés :

| URL | Langue | État de préparation |
| --- | --- | --- |
| `https://homya.eu/google/merchant/fr.xml` | Français | Premier lot privé de 151 articles ; publication et récupération Google à vérifier |
| `https://homya.eu/google/merchant/de.xml` | Allemand | Revue localisée à compléter |
| `https://homya.eu/google/merchant/en.xml` | Anglais | Revue localisée à compléter selon les marchés retenus |

Le flux est généré à chaque lecture depuis le catalogue et l'inventaire. Il conserve les identifiants stables, les prix EUR, les URL d'images publiques et les disponibilités ; il exclut les revues périmées et les incohérences de prix/référence/stock. Une récupération quotidienne est déjà proposée par l'assistant Merchant ; le planning doit suivre la fréquence de mise à jour réelle du catalogue. [Import planifié](https://support.google.com/merchants/answer/14991445?hl=fr).

Commande enrichie, sans écriture commerciale ni envoi Google :

```sh
php -d memory_limit=512M artisan homya:google-audit --locale=all --with-inventory --output=output/google-revue-nouvelle
```

`--with-inventory` effectue une lecture unique de l'inventaire pour toutes les langues. Le rapport sépare prérequis globaux, revue privée, données produit, avertissements et inventaire ; `data_ready` et `currently_exportable` ont des sens distincts. `--offline` ignore le paiement administratif et l'inventaire, marqués non évalués. Les modèles créés restent `approved:false`, y compris lorsqu'ils réemploient des preuves existantes. Les CSV `merchant-product-selection.csv` et `supplier-data-to-complete.csv` permettent de compléter la sélection sans communiquer le catalogue confidentiel.

Évolution de la sélection : compléter les identifiants et certificats connus, vérifier les variantes, réauditer puis installer une nouvelle revue privée avec sauvegarde. Le flux prend ensuite en compte les offres admissibles à l'URL existante. Toute modification des données couvertes par une empreinte demande une nouvelle revue. Une absence de caractéristiques structurées peut devenir un avertissement après validation explicite des informations essentielles ; les contradictions restent bloquantes.

Avant l'ouverture Hostinger, contrôler le moyen de paiement réellement disponible, l'origine canonique, les validations commerciales et l'inventaire sur cet environnement. Le verrou Merchant ne s'ouvre pas avec le seul mode devis différé. Après publication : vérifier HTTP 200 XML, total des offres, liens et images, installer la source puis contrôler **Attention requise**, dont l'énergie. Google reste décisionnaire de l'acceptation et du délai de traitement.

Vérification de cette préparation : **47 tests ciblés / 4 257 assertions réussis**, couvrant flux, audit, pages et informations légales. Une ancienne assertion interdisant la mention des jours ouvrés a été actualisée après confirmation explicite du marchand ; elle vérifie désormais origine, préparation, transport et fuseau. Revue indépendante : correction de l'accès au paiement pendant `--offline`. Aucun achat, paiement, changement de prix/stock, publication de flux ni soumission de produits effectué dans cette préparation.

Preuves privées : `output/qa-merchant-20261014-final/`, `output/qa-merchant-20261014-first-batch/`, `output/qa-merchant-20261014-public-network.json`. Le premier audit réseau dans le sandbox a échoué sur la résolution DNS ; la relance autorisée hors sandbox a permis les contrôles publics. Les sections suivantes retracent les opérations antérieures.

## Contrôle et corrections sitemap — 14 octobre 2026

### Suivi de l'index principal après accord utilisateur

**État vérifié à 23:04:50 UTC :** les 15 sous-sitemaps restent reconnus avec 12 957 URL découvertes. L'entrée principale HTTPS reste « Inconnu / Impossible de récupérer le sitemap / 0 », sans dernière lecture dans la table. Sa reconnaissance n'est pas acquise ; aucune cause technique actuelle n'est démontrée. Le conseiller Hostinger confirme le fonctionnement du fichier et suppose un délai côté Google, sans pouvoir établir cette cause.

À la demande de correction de l'URL exacte `/sitemap.xml`, contrôle strict complémentaire : le XML de 1 201 octets est valide contre le schéma officiel `siteindex.xsd` (zéro erreur libxml). Son empreinte est identique aux réponses publiques, y compris après purge CDN. Aucun défaut structurel de cet index n'est démontré.

Après confirmation explicite de l'utilisateur au moment de l'action, retrait de l'ancienne entrée HTTPS à **22:31:57 UTC** (confirmation « Sitemap supprimé »), puis recréation à la même URL à **22:32:09**. Demande de purge du CDN Hostinger via « Vider le cache » à **22:34:37**, puis nouvelle soumission de l'index à **22:35:43**. Le CDN reste actif et ses protections n'ont pas été modifiées. Contrôle public après purge réussi : index et 15 enfants HTTP 200, XML valides, 12 957 URL uniques et 18 pages représentatives cohérentes. GET de l'index à **22:36:51** : `DYNAMIC`, 200, XML de même empreinte, environ 0,98 seconde. Les caches de l'application et du CDN constituent des couches distinctes ; la purge ne démontre pas qu'une ancienne copie causait l'échec.

Nouveau test Google actif à **22:38:35 UTC** sur l'URL principale : exploration autorisée, récupération réussie, indexation autorisée. Le rapport Sitemaps conserve encore « Inconnu / Impossible de récupérer / 0 page » à ce stade ; sa reconnaissance n'est pas confirmée. Après accord explicite de l'utilisateur pour contacter Hostinger, diagnostic transmis à son Agent dans hPanel et demande de consultation en lecture seule des journaux CDN pour la fenêtre 22:30–22:40 UTC. La réponse du conseiller est consignée ci-dessous ; elle n'établit pas une résolution du rapport Google.

Preuves : `output/qa-sitemap-20261014/gsc-main-recovery.json`, `post-cdn-purge.json`, `transport-post-cdn-purge.json`, `gsc-main-live-after-cdn.jpg`, `hostinger-diagnostic-delivered.jpg` et `diagnostic-support-hostinger.txt`. Aucun changement du code de production dans cette phase. [Schéma officiel](https://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd), [portée de la purge CDN Hostinger](https://www.hostinger.com/support/hostinger-cdn-how-to-clear-the-cache/).

**Contrôle comparatif HTTPS :** propriété `https://homya.eu/` ajoutée à **22:45:18 UTC** et validée automatiquement grâce à la propriété Domaine existante. Aucun enregistrement DNS ni accès utilisateur supplémentaire créé. Le nouveau périmètre affiche déjà les soumissions HTTPS existantes, y compris les 15 enfants reconnus et l'index en erreur. Soumission de `sitemap.xml` dans ce périmètre à **22:46:40** : même état « Inconnu / Impossible de récupérer / 0 ». Ce test ne démontre pas une file de récupération indépendante ; il ne corrige pas à lui seul l'état de l'index. La propriété Domaine est conservée. [Soumissions associées à une propriété](https://developers.google.com/webmaster-tools/v1/sitemaps/submit).

**Assistance Hostinger :** l'Agent a consulté les journaux d'origine et rapporte quatre requêtes de l'index entre 22:30 et 22:40, toutes en 200, dont deux Google-InspectionTool à 22:38:35–36. Il rapporte aucun blocage pays/IP dans la configuration consultée, mais n'a pas accès aux journaux détaillés CDN edge. Les durées brutes de l'ordre de 535 ont été interprétées en secondes par l'Agent, alors que hPanel libelle sa colonne en millisecondes et le contrôle HTTP mesure environ une seconde avec `x-hcdn-upstream-rt: 0.538`. Cette contradiction a été soumise à un conseiller ; elle n'est pas retenue comme preuve de lenteur. Transfert humain demandé à **22:45:15**, note de contexte envoyée à **22:46:41**. Après une première réponse vérifiée par Aissam et une analyse externe confirmant 12 957 URL, la capture de l'erreur exacte a été transmise et une seconde revue obtenue. Réponse vérifiée par Aissam affichée à **23:00** : fichier fonctionnel côté hébergeur ; problème temporaire d'affichage ou de synchronisation Google supposé. Aucun journal détaillé des réponses CDN aux récupérations automatiques du sitemap n'a été fourni. Le [fil communautaire cité par Hostinger](https://support.google.com/webmasters/thread/345568731/google-search-console-shows-couldn-t-fetch-for-sitemap-even-after-fixing-all-known-issues?hl=en) contient une réponse de Product Expert, sans intervention identifiable d'un employé Google ni résolution démontrée ; ce parallèle ne prouve pas la cause pour HOMYA.

**Mesure externe IPv6 :** [Internet.nl, test du 14 octobre à 23:03 UTC](https://en.internet.nl/site/homya.eu/4351184/), confirme les deux adresses IPv6 natives, leur accessibilité, la cohérence du site servi entre IPv4 et IPv6, ainsi que HTTPS. Cette mesure porte sur le site et ses redirections, pas sur une récupération indépendante du chemin `/sitemap.xml` en IPv6. Elle écarte une indisponibilité IPv6 générale depuis ce point de mesure, sans prouver le traitement du sitemap par Google. Aucun changement DNS ou de sécurité effectué.

Preuves complémentaires : `gsc-main-final-status.json` et `gsc-main-final-status.jpg`, `hostinger-human-conclusion.jpg`, `internetnl-ipv6-details.txt`, dans `output/qa-sitemap-20261014/`.

**Cache robots de Google :** dernière lecture affichée toujours au 13 octobre 23:02, fichier récupéré correct de 62 octets. Une demande unique de nouvelle exploration de `https://homya.eu/robots.txt` a été envoyée à **22:50:01** après la réouverture récente du site ; Google confirme sa réception. Au contrôle de 23:05, la date reste inchangée : la nouvelle récupération et un effet sur l'index ne sont pas encore confirmés. Le fichier déjà conservé étant correct, cette demande ne prouve pas qu'il causait l'erreur. [Procédure Google](https://support.google.com/webmasters/answer/6062598?hl=en).

**Déploiement exécuté après accord utilisateur :** commit `df0f7e13df9f2795bc40525514428aabc072f116` envoyé sur `main`, puis publié par Hostinger le 14 octobre à 21:46 UTC. hPanel confirme « Terminé / Actuel » ; version précédente `c1c776ec` conservée dans l'historique. PHP 8.5 sélectionné pour le site. Le renouvellement a été exécuté à 21:52 UTC par `/opt/alt/php85/usr/bin/php artisan optimize` : configuration, événements, routes et vues indiquent tous `DONE`. Les commandes de reconstruction configuration/routes effacent elles-mêmes leurs caches précédents. La tâche ponctuelle a ensuite été supprimée après confirmation explicite, conformément à l'exigence de l'outil navigateur ; la tâche commerciale `artisan schedule:run` reste seule dans la liste. Aucun `.env`, compte SSH, base, stock, commande ni tâche commerciale modifié.

Le test Google en ligne réel du 14 octobre à **21:55:08 UTC** sur `https://homya.eu/` confirme désormais récupération réussie et indexation autorisée, canonical `https://homya.eu/fr` : le blocage `noindex` constaté avant le déploiement a disparu. Ce résultat ne constitue pas une indexation déjà effectuée.

**Vérification finale de production à 21:59 UTC :** index et 15 enfants accessibles en HTTP 200, XML valides, **12 957 URL uniques**, aucun doublon ni URL hors domaine ; **610 pages et 3 709 produits par langue**. Robots 200 avec référence exacte ; redirection racine 301 vers `/fr` sans en-tête `noindex`. Dix-huit pages représentatives (pagination et produits FR/EN/DE) répondent 200, avec canonical cohérent et indexation autorisée. Le test Google réel du sitemap à **21:56:26 UTC** confirme aussi exploration autorisée, récupération réussie et indexation autorisée. Le succès de traitement du rapport Sitemaps et l'indexation des produits ne sont pas encore confirmés ; aucune nouvelle soumission ni demande d'indexation effectuée par Codex dans cette intervention.

Preuves de production : `output/qa-sitemap-20261014/postdeploy-final.json`, `public-after-deployment.json`, `gsc-sitemap-after-deploy.jpg` et `google-live-tests-after-deploy.json`. Les informations d'exploitation de ce paragraphe sont mises à jour localement après déploiement ; elles ne déclenchent pas une nouvelle publication Git.

**Soumission du sitemap corrigé — 14 octobre à 22:03 UTC :** à la demande explicite de l'utilisateur, `https://homya.eu/sitemap.xml` a été soumis dans la propriété `sc-domain:homya.eu`. Google a confirmé « Sitemap envoyé ». Après actualisation du rapport, l'entrée reste de type **Inconnu**, avec **Impossible de récupérer le sitemap** et **0 page découverte** ; le détail indique une dernière lecture au **14/10/2026**, sans heure exploitable. La réception de la soumission est confirmée, mais la reconnaissance et le traitement réussi du fichier ne le sont pas encore. Aucune nouvelle URL de sitemap ni soumission répétée n'a été créée pendant ce contrôle.

Le rapport robots.txt de Google conserve depuis le **13/10/2026 à 23:02** un fichier récupéré de 62 octets, sans problème : `Allow: /` et `Sitemap: https://homya.eu/sitemap.xml`. Il correspond à la réponse publique actuelle. Les tests Google en ligne réussis et les XML valides restent des preuves d'accès ; ils ne remplacent pas un statut réussi du rapport Sitemaps. Google indique que les nouvelles tentatives après un échec peuvent prendre quelques jours, sans délai garanti ([rapport et dépannage](https://support.google.com/webmasters/answer/7451001?hl=fr)).

Preuves de soumission : `output/qa-sitemap-20261014/gsc-corrected-sitemap-submission.json`, `gsc-sitemap-submitted-corrected.jpg`, `gsc-sitemap-after-resubmission.jpg` et `gsc-cached-robots-valid.jpg`.

### Diagnostic complémentaire du rapport Sitemaps

Le 14 octobre, à la demande de l'utilisateur, comparaison du transport public, du code et des journaux Hostinger : GET et HEAD du sitemap répondent 200, HTTP/1.1 et HTTP/2 servent le même index XML, gzip donne le même XML après décompression, sans `noindex`. Les réponses de l'index ont été reçues en environ 0,85 à 1,11 seconde ; le changement de User-Agent entre audit, Googlebot et Google-InspectionTool ne change pas le contenu. Les deux adresses IPv4 annoncées au contrôle DNS servent aussi cet index en HTTPS avec certificat validé. Le poste n'a pas de route IPv6 par défaut : l'accès aux adresses IPv6 natives n'a donc pas été validé ; son échec local ne démontre pas une panne Hostinger.

Aucun filtre Googlebot, authentification ni throttle spécifique aux routes SEO n'a été trouvé. Les dépendances session/catalogue et les contrôles de production ont été examinés, sans défaut actuel démontré. Search Console indique aucune action manuelle et aucun problème d'hôte dans son rapport d'exploration, qui n'est actualisé qu'au 13 octobre. Le CDN est actif, sécurité « Moyen » et aucune IP bloquée ; aucun 429 ni 5xx dans les agrégats CDN consultés. La vue pays affiche un mode d'autorisation vide, contradictoire avec les récupérations effectives ; aucun changement de sécurité n'a été fait et cette vue n'est pas retenue comme cause prouvée.

Les journaux d'accès de l'index consultés montrent les téléchargements Google-InspectionTool, mais pas une nouvelle lecture par Googlebot associée à la soumission de 22:03. Cette absence dans les journaux consultés ne prouve pas l'absence de toute requête en amont du CDN. La soumission directe de `/sitemaps/fr/pages.xml` a, en revanche, donné **« Opération effectuée », 610 pages découvertes**, puis la même reconnaissance pour les pages anglaises et allemandes. La requête Googlebot du fichier français figure à **22:19:15 UTC** ; son IP a été validée par DNS inverse puis direct. Cela prouve la lecture effective du XML et dépasse la seule confirmation d'envoi.

L'index principal a été resoumis une fois après cette réussite ; l'ancienne entrée conserve encore son erreur. Les 15 fichiers enfants existants ont ensuite été soumis directement pour rendre leur découverte indépendante de cette entrée. **Bilan final à 22:27:20 UTC : les 15 fichiers sont de type « Sitemap », affichent « Opération effectuée » et une dernière lecture au 14 octobre. Total : 12 957 URL découvertes, soit 4 319 par langue (610 pages et 3 709 produits).** Les ensembles de fichiers correspondent exactement aux 15 enfants de l'index public validé. Certains fichiers ont d'abord affiché temporairement « Inconnu / Impossible de récupérer » avant leur reconnaissance ; leur réussite a été vérifiée après actualisation.

À 22:27, les deux anciennes entrées HTTP et HTTPS de l'index restent en erreur, avec colonne « Dernière lecture » vide dans la table finale, bien que le détail antérieurement affiché indiquait le 14 octobre. Leur lecture réussie n'est donc pas confirmée. La soumission directe des enfants constitue un contournement vérifié pour la découverte de l'intégralité du sitemap, pas une résolution démontrée de l'état de l'index ni une preuve d'indexation des pages. Supprimer puis recréer cette entrée n'est pas une réinitialisation technique garantie par Google ; le renouvellement autorisé ultérieur est décrit en tête de ce document.

Le bilan définitif est consigné dans `output/qa-sitemap-20261014/gsc-direct-sitemaps-final.json` et la capture `gsc-all-children-recognized.jpg` ; contrôles de transport dans `transport-diagnostic-public.json` et `transport-cdn-addresses.json`. Aucune URL de site, route, directive robots, configuration de sécurité ou donnée commerciale n'a été modifiée dans ce diagnostic. Aucun nouveau déploiement n'a été nécessaire. La cause profonde de l'état persistant de l'index n'est pas établie ; ne pas le présenter comme une panne de XML, de robots ou comme un délai Google garanti.

Sources consultées le 14 octobre : [rapport Sitemaps Google](https://support.google.com/webmasters/answer/7451001?hl=en), [robots Google et InspectionTool](https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers?hl=fr), [CDN Hostinger et moteurs](https://www.hostinger.com/support/hostinger-cdn-search-engine-crawlers-and-seo/). Google distingue les pages découvertes par un sitemap des pages déjà explorées ou indexées.

Preuves du déploiement et des caches : `output/qa-sitemap-20261014/hostinger-deploy-df0f7e1.jpg`, `hostinger-cache-renewed.jpg`, `cache-renewal-result.json`, `hostinger-temporary-cron-removed.jpg` et `gsc-home-after-deploy.jpg`. Les sections suivantes retracent la préparation et les contrôles antérieurs.

Le sitemap est généré par `app/Http/Controllers/SeoController.php`, déclaré dans `routes/web.php` et configuré dans `config/seo.php`. Il n'existe pas de fichier XML source à chercher dans `public/` : `/sitemap.xml` est un index dynamique, avec `/sitemaps/{fr,en,de}/pages.xml` et des blocs `/sitemaps/{fr,en,de}/products-N.xml` de 1 000 produits. Les produits publiés et leur langue viennent de la projection du catalogue.

`public/robots.txt` est un repli fermé pour les environnements privés. La règle de `public/.htaccess` transmet `/robots.txt` à Laravel avant de tester l'existence d'un fichier. En production, lorsque l'indexation est ouverte sur l'origine exacte, le contrôleur sert :

```text
User-agent: *
Allow: /

Sitemap: https://homya.eu/sitemap.xml
```

### État public observé

GET du 14 octobre à 21:26 UTC : robots 200 avec la référence ci-dessus ; sitemap 200 XML valide, 15 enfants ; accueils FR/EN/DE, contact, livraison et paiement indexables. Le rapport Search Console indique encore « Impossible de récupérer le sitemap », avec dernière lecture le 13 octobre. Le **test en ligne Google réel du 14 octobre à 21:30:45** sur l'URL exacte `https://homya.eu/sitemap.xml` confirme **Exploration autorisée : Oui**, **Récupération de page : Réussie**, **Indexation autorisée : Oui**. Cela confirme l'accès actuel depuis Google, pas le traitement complet du rapport des sitemaps ni l'indexation des pages. Aucune nouvelle soumission ni demande d'indexation du XML n'a été faite.

Un problème distinct est constaté dans le test Google déjà présent sur `https://homya.eu/` à 21:17:30 : `noindex` détecté dans `X-Robots-Tag`, avec destination canonique `/fr`. Le middleware ajoutait cette directive à la redirection racine car sa route n'était pas reconnue comme publique. Le correctif local nomme cette route `home.redirect` et dispense uniquement cette redirection du repli `noindex` lorsque l'indexation est réellement ouverte sur l'origine de production autorisée. Local, préproduction, erreurs et pages privées gardent leurs protections.

### Corrections vérifiées avant déploiement

- Sitemap de pages complété avec les paginations canoniques du catalogue, des univers, des catégories et des promotions actives. Page 1 sans paramètre ; autres pages avec `page=N`. Taille partagée de 24 produits par page dans `config/homya.php`, avec repli compatible avec un ancien cache de configuration.
- Nombre de blocs de produits calculé pour chaque langue, afin de ne pas référencer un enfant absent lorsqu'un produit personnalisé n'est publié que dans certaines langues. Une source/langue indisponible renvoie 503.
- Recherche, filtres, panier, commandes, administration et suivi exclus ; variantes regroupées sous la fiche canonique parent. Les pages d'information encore marquées préparatoires restent exclues ; cette correction ne les déclare pas finalisées.
- Aperçu complet généré depuis l'archive fichiers locale, sans administration, base réelle, stock réel ni réseau : **610 pages et 3 709 produits par langue**, soit **12 957 URL uniques**, dans 15 enfants XML valides. Cet aperçu est une preuve locale ; le catalogue administré en production peut différer. Ne pas recopier cet instantané à la place des routes dynamiques.
- **53 tests / 1 723 assertions** réussis : SEO/sitemap, catalogue, pagination, promotions et en-têtes. Syntaxe PHP, Pint et `git diff --check` vérifiés. Aucun `.env`, base, service XAMPP ou fichier distant modifié ; aucun déploiement ni push effectué.

Preuves locales : `output/qa-sitemap-20261014/public-network-check.json`, `gsc-sitemap-live-success.jpg`, `preview/sitemap.xml`, `preview/robots-production.txt` et `preview/validation.json`.

Procédure de déploiement validée et exécutée : installer les six fichiers applicatifs concernés, renouveler les caches de configuration et de routes avec le PHP compatible de l'hébergement, contrôler la redirection racine et le sitemap/enfants, puis vérifier le rapport Sitemaps. Conserver l'URL stable HTTPS ; ne pas ajouter de variante de sitemap pour contourner un état ancien. Les fichiers concernés sont `SeoController.php`, `CatalogueController.php`, `PromotionsController.php`, `SecurityHeaders.php`, `config/homya.php` et `routes/web.php`.

Sources officielles consultées le 14/10/2026 : [création et envoi des sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap?hl=fr), [index de sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/large-sitemaps?hl=fr), [rapport et dépannage Search Console](https://support.google.com/webmasters/answer/7451001?hl=fr). Un sitemap facilite la découverte ; il ne garantit pas l'indexation de toutes les URL.

---

Préparation du 13 octobre 2026. Domaine confirmé : **https://homya.eu**. Même compte Google pour les deux services. Livraison gratuite FR/BE/DE confirmée ; prix et stocks validés par HOMYA, données fabricant encore à compléter. Le commit SEO `8020cfc2` a été déployé par l'utilisateur. Après son autorisation explicite, l'ouverture de l'indexation et le renouvellement des caches ont été appliqués dans hPanel. Le correctif complémentaire de disponibilité du sitemap est inclus dans le dépôt pour le déploiement Git suivant. À la demande de résolution de l'erreur Search Console, un test actif Google et une nouvelle soumission du sitemap HTTPS ont ensuite été effectués ; le traitement du rapport reste à confirmer.

## 1. État vérifié et corrections

La lecture du catalogue SQL local et de l'administration a réussi hors sandbox. L'ancien message `Catalogue unavailable` provenait ici du blocage du socket MariaDB par le sandbox. Le catalogue et les images principales étaient présents. La nouvelle option `--offline` fournit un audit explicite de l'archive sans base ni administration ; elle ne remplace pas le contrôle des corrections administratives.

Audit courant FR/EN/DE : 3 709 produits dans chaque langue, titres/descriptions dans les limites de format, 418 fiches avec signalements, 3 683 produits simples sans identifiants fabricant documentés pour Merchant, 26 groupes/92 options nécessitant leur revue individuelle. Aucun produit n'a été approuvé automatiquement. La lecture distincte de l'inventaire local a observé 3 711 lignes, dont 3 709 vendables avec une quantité supérieure à la réservation, prix et date de validation présents. Elle ne vérifie pas physiquement les stocks.

| Correction dans le dépôt | Résultat |
| --- | --- |
| Sitemap | Toutes les pages d'information éligibles utilisent la même règle que leurs métadonnées ; contact/livraison/paiement ajoutés, pages privées ou encore préparatoires exclues. XML consultable sur l'origine HTTPS exacte en production sans debug, même avec l'indexation fermée ; `noindex` reste alors actif |
| Architecture Merchant | `product_type` dérivé de l'univers et de la catégorie affichés, dans la langue du flux |
| Identifiants | SKU HOMYA stable dans flux/JSON-LD ; GTIN/MPN structurés seulement après revue factuelle actuelle ; référence interne jamais assimilée automatiquement à un MPN |
| Catégorie Google / EPREL | Champs de revue facultatifs, format contrôlé ; aucun numéro de catégorie ou code de certification inventé |
| Administration | GTIN, MPN, URL fournisseur HTTPS et confirmation de vérification disponibles dans l'éditeur ; sauvegardes antérieures préservées, pas d'approbation Merchant automatique |
| Checkout | CGV, prise de connaissance de confidentialité et livraison gratuite confirmées côté serveur avant réservation ; horodatages et version des textes conservés dans le dossier chiffré |
| Version des conditions | Une modification des textes/coordonnées entre affichage et envoi exige de revoir le formulaire |
| Confidentialité | Devis PDF, messagerie Hostinger, téléphone facultatif, finalités, droits et absence d'inscription publicitaire précisés FR/EN/DE |

Fichiers de revue internes :

- `output/qa-google-20261013/catalogue-incomplete.csv` : les **418 fiches** signalées, dans chaque langue, avec les valeurs à confirmer.
- `output/qa-google-20261013/products-to-complete.csv` : tous les blocages produit, une ligne par motif/langue.
- `output/qa-google-20261013/google-audit-{fr,en,de}.json` : revue complète, variantes et empreintes.
- `output/qa-google-20261013/merchant-products-review-template.json` : modèle de travail ; **toutes les entrées ont `approved: false`**.

Les rapports restent hors Git et hors exposition Web. Ce sont des supports de correction, pas des fichiers à envoyer à Merchant Center.

## 2. Compléter les fiches avant le premier flux

1. Après installation des correctifs dans l'environnement utilisé, ouvrir **Produits et stocks → Modifier le produit** dans l'administration. Utiliser les codes de la fiche constructeur, de l'emballage ou du fournisseur pour ce modèle exact. Le GTIN reste une chaîne de caractères pour conserver ses zéros initiaux. La validation de sa clé ne prouve pas son attribution au modèle.
2. Renseigner GTIN/MPN et la source fournisseur. Cocher la vérification seulement après contrôle du modèle. Pour les appareils identifiés par un GTIN constructeur, fournir ce GTIN ; un MPN ne justifie pas l'omission d'un GTIN existant. [Règles Google GTIN](https://support.google.com/merchants/answer/6324461?hl=fr).
3. Pour les 418 fiches, obtenir les données qui tranchent les valeurs conflictuelles. Les écarts de poids peuvent être net/brut ; les zones d'installation peuvent être une liste. Ne pas sélectionner arbitrairement une valeur. Les archives restent intactes ; les corrections publiques doivent être liées à une preuve du modèle. L'éditeur ajouté ici collecte les identifiants, il ne réécrit pas les caractéristiques archivées.
4. Pour chaque variante, confirmer finition/couleur/matière/dimension réelle, image représentant exactement l'option et identifiants propres. Les identifiants du parent ne sont pas hérités. Le prix et l'inventaire communs au parent restent le fonctionnement existant ; toute différence commerciale doit être traitée avant revue.
5. Confirmer la provenance du titre et de la description : `default` ou `trained_algorithmic_media`. Les corrections par IA déjà connues sont préremplies ; les provenances inconnues restent `null`. Les textes doivent décrire réellement le produit. [Spécifications Google](https://support.google.com/merchants/answer/7052112?hl=fr).
6. Pour les appareils soumis à l'étiquetage énergétique dans l'UE, vérifier l'applicabilité et obtenir l'étiquette et la fiche énergétique du modèle. Un `eprel_code` confirmé émet une certification EC/EPREL. Google documente aussi la recherche automatique depuis GTIN/MPN ; son utilisation explicite conserve un avertissement jusqu'au diagnostic Google. Ne pas considérer la certification comme universellement facultative ; voir le suivi Merchant du 14 octobre en tête du document. [Certification Google](https://support.google.com/merchants/answer/13528839?hl=fr), [étiquetage UE](https://support.google.com/merchants/answer/16569525?hl=fr).
7. Refaire l'audit sur la source réellement utilisée par la boutique. Les nouvelles données fabricant vérifiées dans l'administration préremplissent le modèle de revue. Les changements de catégorie, textes, images ou données fabricant invalident une ancienne empreinte.

Commande locale, PHP 8.3 minimum, dossier de sortie nouveau à chaque passage :

```sh
php -d memory_limit=512M artisan homya:google-audit --locale=all --output=output/google-revue-suivante
```

Sans accès à MariaDB, utiliser `--offline` : l'en-tête du rapport indique `file_archive`, administration/stock non contrôlés. Aucun audit n'envoie de données à Google.

La revue active du flux reste `storage/app/private/google/merchant-products.json`. Préparer une sélection de produits dans le modèle, compléter les preuves et empreintes FR/EN/DE et n'approuver que les produits réellement vérifiés. `identifier_exists: false` est réservé à une absence réelle d'identifiants applicables, jamais à une information manquante. Pour les variantes, compléter d'abord attributs/images dans une revue non approuvée, réauditer puis reporter les empreintes des variantes depuis le rapport JSON. Un technicien peut installer ce fichier privé après validation du marchand ; conserver sa version précédente.

L'architecture de la boutique passe par `product_type`, distinct de la taxonomie Google. La catégorie Google numérique est facultative et ne doit être fournie que si elle correspond à la taxonomie officielle. [Hiérarchie boutique](https://support.google.com/merchants/answer/6324406?hl=fr), [catégorie Google](https://support.google.com/merchants/answer/6324436?hl=fr).

## 3. Ouvrir correctement l'indexation sur Hostinger

Contrôle public du 13 octobre : accueil FR accessible en HTTPS, directive `noindex, nofollow` ; robots `Disallow: /` ; `/sitemap.xml` et `/google/merchant/fr.xml` répondent 404. Les pages contact/livraison/paiement/retours/confidentialité sont accessibles, mais non indexables. Quelques lectures GET des accueils ont échoué au niveau réseau ; ce n'est pas une mesure d'indisponibilité globale.

Diagnostic hPanel du même jour : le commit `8020cfc2` est déjà déployé sur `main`. La configuration privée contient `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://homya.eu`, `HOMYA_PUBLIC_URL=https://homya.eu`, mais `HOMYA_SEO_INDEXING_ENABLED=false`. Un cache `bootstrap/cache/config.php` antérieur au dernier déploiement est présent. Le code déployé lie encore l'accès au sitemap à l'ouverture de l'indexation ; le correctif local sépare ces contrôles.

Un commit/push seul ne change pas le `.env` privé de Hostinger et ne renouvelle pas ce cache. La validation DNS de la propriété Search Console peut être faite avant le déploiement ; la soumission du sitemap doit attendre un XML public accessible et l'ouverture effective de l'exploration.

Installer les fichiers préparés sur l'hébergement avec sauvegarde, puis appliquer dans **la configuration privée de production**, après recette :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://homya.eu
HOMYA_PUBLIC_URL=https://homya.eu
HOMYA_SEO_INDEXING_ENABLED=true
```

Ces valeurs sont une procédure de production. Le 13 octobre, après autorisation explicite, seul `HOMYA_SEO_INDEXING_ENABLED` a été passé de `false` à `true` dans le `.env` distant ; les quatre autres valeurs étaient déjà correctes. Le `.env` local reste inchangé. Le serveur doit diriger le site vers Laravel `public/`, conserver la règle dynamique de `robots.txt`, rediriger HTTP et l'éventuel www vers l'origine HTTPS choisie. Les versions FR/EN/DE restent des chemins sur cette origine.

Après configuration, purger puis reconstruire les caches avec le PHP compatible de l'hébergement :

```sh
php artisan optimize:clear
php artisan config:cache
```

Avec le gestionnaire de fichiers hPanel, si aucun terminal compatible n'est disponible : après validation du changement de production, modifier uniquement les variables concernées dans le `.env` existant. Pour retirer le cache de configuration de façon réversible, renommer seulement `bootstrap/cache/config.php` en `config.before-indexing-20261013.php.bak`, dans le même dossier protégé. Ne pas modifier `packages.php`, `services.php` ni les autres fichiers. Laravel relira alors les variables privées à la prochaine requête. Le bouton hPanel **Vider le cache** renouvelle le cache Web/CDN ; il ne remplace pas cette opération Laravel. Reconstruire ensuite le cache de configuration avec `php artisan config:cache` lorsqu'un terminal compatible sera disponible. Les secrets du `.env` et de la sauvegarde de cache restent privés.

Contrôler de nouveau les réponses réelles :

- `https://homya.eu/robots.txt` : `Allow: /` et `Sitemap: https://homya.eu/sitemap.xml`.
- `https://homya.eu/sitemap.xml` : 200 XML, fichiers de pages et produits des trois langues accessibles.
- Accueil, catégories et fiches complètes : 200, canonical correct, meta et en-tête compatibles avec l'indexation.
- Panier/commande/suivi/administration : accès protégés et `noindex` conservé. Les pages légales encore préparatoires restent accessibles aux utilisateurs, sans prétendre à leur validation juridique.

Ne pas simplement supprimer le fichier robots fermé : il est un repli de sécurité. C'est le contrôleur Laravel, avec la bonne configuration de production, qui doit servir la réponse publique. Le script `python3 scripts/audit_google_public.py` facilite le relevé des statuts publics, sans cookies affichés ni formulaire soumis.

Vérification du correctif sitemap local : **9 tests / 327 assertions réussis** dans `GoogleSeoMerchantTest`, sans base réelle ni réseau. Couverture : index XML et enfants FR/EN/DE avec l'indexation fermée, conservation de `noindex`/robots fermé/Merchant fermé, refus hors origine HTTPS de production ou en debug, erreurs catalogue 503 et blocs inexistants 404. Pint et `git diff --check` réussis. Ce correctif local n'est pas encore inclus dans le commit déployé mentionné ci-dessus.

### Résultat de l'intervention hPanel autorisée

- Le réglage d'indexation a été enregistré à `true` ; l'ancien cache a été renommé en `bootstrap/cache/config.before-indexing-20261013.php.bak`, dans son dossier protégé. Aucun autre fichier de cache n'a été modifié. Le bouton hPanel **Vider le cache** a confirmé le succès. Le cache Laravel n'a pas été reconstruit, faute de terminal utilisé pour cette intervention.
- Contrôle public GET du 13 octobre 2026 à 22:55 UTC : `/sitemap.xml` répond **200 XML valide**, avec 15 enfants. Tous répondent **200 XML valide**, sans directive `noindex` : 79 URL de pages et 3 709 URL de produits par langue, réparties sur quatre blocs (1 000 / 1 000 / 1 000 / 709).
- `/robots.txt` répond 200 avec `Allow: /` et l'URL exacte du sitemap. L'accueil `/fr` répond 200 avec meta et en-tête `index, follow, max-image-preview:large`, et canonical `https://homya.eu/fr`.
- Le flux `/google/merchant/fr.xml` reste 404 : aucun prérequis ou produit n'a été approuvé pour ouvrir Merchant. Aucun prix, stock, commande, politique commerciale ni compte Google n'a été modifié.
- Preuve : `output/qa-google-20261013/public-sitemap-after-hostinger-fix.json`. Chrome contrôlé a affiché `ERR_BLOCKED_BY_CLIENT` pour les fichiers XML/texte ; la réponse serveur a été vérifiée séparément par HTTP HEAD puis GET, sans contourner de protection du navigateur. Ce contrôle confirme l'accessibilité publique, pas encore la récupération ou l'indexation par Google.

## 4. Connecter Search Console

1. Se connecter au compte Google retenu dans [Search Console](https://search.google.com/search-console).
2. Ajouter une propriété **Domaine**, saisir `homya.eu` sans protocole ni chemin.
3. Copier exactement l'enregistrement **TXT** donné par Google. Dans la zone DNS qui fait autorité pour `homya.eu` — hPanel si les DNS y sont gérés — ajouter ce TXT à la racine (`@` ou valeur demandée par l'interface). Conserver les autres enregistrements, puis cliquer sur **Valider** dans Search Console lorsque le TXT est propagé. [Validation officielle](https://support.google.com/webmasters/answer/9008080?hl=fr).
4. Dans **Sitemaps**, soumettre `https://homya.eu/sitemap.xml` après son passage en 200 public. Vérifier son état et les URL découvertes. [Rapport des sitemaps](https://support.google.com/webmasters/answer/7451001?hl=fr).
5. Dans **Inspection de l'URL**, tester accueil, catégorie et quelques fiches complètes ; contrôler lecture en direct, canonical et possibilité d'indexation. Demander l'indexation de ces pages représentatives. Le sitemap sert à découvrir l'ensemble du catalogue ; éviter de demander manuellement 3 709 URL.
6. Suivre les pages exclues, erreurs serveur, canonicals et résultats enrichis. La validation DNS prouve la propriété ; elle ne force pas l'indexation.

Le DNS couvre le domaine et ses variantes. La balise `HOMYA_GOOGLE_SITE_VERIFICATION` n'est nécessaire que si une méthode HTML/préfixe d'URL est choisie à la place ; utiliser alors la valeur fournie par Google, pas un code fictif. Aucun compte Analytics ou tag publicitaire n'est nécessaire pour cette méthode DNS.

### Diagnostic « Impossible de récupérer le sitemap » — 13 octobre 2026

Le compte contient deux tentatives, `http://homya.eu/sitemap.xml` et `https://homya.eu/sitemap.xml`. La propriété Domaine est confirmée ; elle a été ajoutée le 13 octobre. Lors de la lecture initiale, les deux lignes indiquaient un type inconnu, zéro page découverte et un échec. L'adresse HTTP répond 301 vers HTTPS : utiliser directement l'adresse HTTPS, les redirections n'étant pas suivies pour l'URL soumise dans ce rapport. [Rapport Google](https://support.google.com/webmasters/answer/7451001?hl=fr).

Contrôles publics renouvelés : sitemap HTTPS 200 XML valide, 15 enfants référencés, aucun `noindex`, aucune redirection, environ une seconde pour le GET. `robots.txt` répond 200 avec `Allow: /` et l'adresse exacte du sitemap. Les mêmes réponses ont été observées avec des User-Agent simulant Googlebot et l'outil d'inspection ; ces requêtes ne provenaient pas de Google et ne suffisent pas à prouver son accès.

La preuve indépendante provient du **Test en ligne réel dans Search Console** : le 13 octobre à **23:02:25 UTC**, l'outil d'inspection Google pour smartphone indique **Google a accès à cette URL**, **Exploration autorisée : Oui**, **Récupération de page : Réussie**, **Indexation autorisée : Oui**. Ce test prouve la récupération directe du sitemap, pas son analyse complète par le service de sitemaps ni l'indexation des produits. Aucune demande d'indexation du XML comme page de résultats n'a été envoyée.

Le sitemap exact `https://homya.eu/sitemap.xml` a été soumis de nouveau. Search Console a confirmé **Sitemap envoyé**. Juste après et lors du rechargement du rapport, la ligne conservait encore l'état d'échec et zéro page découverte : ne pas présenter la confirmation d'envoi comme un traitement réussi. Le rapport des actions manuelles indiquait **Aucun problème détecté**. Une précédente réponse fermée de `robots.txt` ou un état de récupération ancien reste une hypothèse ; Google conserve généralement robots jusqu'à 24 heures, parfois davantage lorsque le rafraîchissement échoue. Aucun cache Google ancien n'a été observé directement ; le rapport robots n'était pas encore disponible pour cette nouvelle propriété. [Cache robots Google](https://developers.google.com/crawling/docs/robots-txt/robots-txt-spec#caching).

Preuves privées : `output/qa-google-20261013/public-sitemap-google-agent-check.json`, `gsc-sitemap-live-fetch-success.jpg` et `gsc-sitemap-resubmitted.jpg`. Vérifier l'état après le prochain traitement ; si l'échec persiste, consulter le nouveau détail et l'heure de récupération avant de modifier le site ou l'hébergement. Ne pas créer de nouvelles URL de sitemap ou soumissions multiples pour contourner un état ancien.

## 5. Connecter Merchant Center

1. Ouvrir [Merchant Center](https://merchants.google.com/) avec **le même compte Google**. Renseigner les faits de HOMYA : établissement en France, identité/adresse/contacts tels que publiés sur le site. Confirmer téléphone et éventuelles informations demandées.
2. Dans **Paramètres → Infos sur l'entreprise → Boutique en ligne**, renseigner `https://homya.eu`, valider puis **revendiquer**. Avec un propriétaire Search Console validé parmi les utilisateurs Merchant, la validation du site peut déjà être reconnue ; la revendication reste à vérifier. [Procédure Google](https://support.google.com/merchants/answer/11586344?hl=fr).
3. Définir les pays réellement servis : FR/BE/DE, EUR. Configurer livraison gratuite **0 EUR**, délais de préparation et transport correspondant aux engagements réels. Les 3–7 jours annoncés depuis paiement doivent être qualifiés et répartis correctement ; ne pas inventer la différence ouvrés/calendaires ni les zones/logistiques.
4. Dans **Livraison et retours**, indiquer l'URL publique de la politique et des méthodes réellement opérationnelles. Règle HOMYA actuelle : rétractation notifiée sous 14 jours après réception, renvoi sous 14 jours après notification, retour gratuit, sans frais de remise en stock. Confirmer étiquette/enlèvement et délai de remboursement du compte ; celui-ci ne s'assimile pas automatiquement au délai légal ni au délai bancaire. [Configuration des retours](https://support.google.com/merchants/answer/14011730?hl=fr).
5. Vérifier le parcours d'achat : prix fixe TTC cohérent fiche/panier/devis, livraison gratuite, vente accessible aux particuliers, coordonnées et politiques claires, instructions de paiement disponibles directement sur le site. Un devis PDF envoyé en accompagnement n'est pas un paiement. Si le client doit négocier le prix ou attendre une intervention manuelle pour pouvoir acheter, régler ce point avant envoi. [Exigences boutique](https://support.google.com/merchants/answer/12160471?hl=fr), [règlement](https://support.google.com/merchants/answer/9158778?hl=fr).
6. Après les revues produit et opérationnelles, installer le fichier privé de revue et activer `HOMYA_MERCHANT_FEED_ENABLED=true`. Le flux exige également inventaire et conditions commerciales validés, origine APP_URL cohérente et moyen de paiement réel configuré. Ne pas mettre `HOMYA_COMMERCE_LEGAL_READY=true` pour masquer un prérequis manquant. Le service refuse un flux sans produit exportable.
7. Dans **Paramètres → Sources de données → Ajouter une source de produits → À partir d'un fichier**, choisir une URL XML :

| URL de source | Langue | Usage initial proposé |
| --- | --- | --- |
| `https://homya.eu/google/merchant/fr.xml` | Français | France et Belgique francophone selon configuration du compte |
| `https://homya.eu/google/merchant/de.xml` | Allemand | Allemagne |
| `https://homya.eu/google/merchant/en.xml` | Anglais | Complément seulement si marchés/langues retenus dans le compte ; ne remplace pas les informations locales nécessaires |

8. Choisir la récupération planifiée. Google propose une récupération quotidienne par défaut ; l'ajuster à la fréquence réelle des changements de prix/stock. Chaque URL doit répondre **200 XML directement**, sans authentification client, page HTML, blocage robots ou protection antibot. Les langues de flux, fiche et checkout doivent correspondre. [Import par fichier](https://support.google.com/merchants/answer/12158380?hl=fr), [planning](https://support.google.com/merchants/answer/14991445?hl=fr).
9. Commencer par les produits complètement vérifiés, consulter **Attention requise**, corriger les erreurs produit/source/site/compte puis étendre la sélection. Les diagnostics Google permettront de confirmer l'éligibilité réelle. Le téléchargement d'un fichier de revue ou d'un sitemap à la place du flux XML ne convient pas.

**Point documentaire à clarifier avant validation finale des retours :** une page Google de préparation aux examens cite une période de 30 jours pour l'UE, alors que l'information institutionnelle sur le droit de rétractation indique 14 jours. Cette mention ne permet pas de conclure seule à une obligation légale de 30 jours. La règle commerciale HOMYA de 14 jours est conservée ; faire confirmer l'exigence du programme/compte par Google avant de la déclarer approuvée, ou faire valider une extension commerciale distincte. [Page Google](https://support.google.com/merchants/answer/16993874?hl=en), [droit de rétractation UE](https://europa.eu/youreurope/citizens/consumers/shopping/returns/index_fr.htm).

## 6. Informations restantes et vérification

Les identifiants, caractéristiques et images spécifiques aux variantes exigent encore des données fournisseur. Les politiques décrivent maintenant Hostinger pour l'envoi des devis ; lieux de traitement/sauvegardes, contrats, transferts et calendrier effectif de conservation restent à vérifier. La proposition de conservation figure en fin de [INFORMATIONS-LEGALES-HOMYA.md](INFORMATIONS-LEGALES-HOMYA.md) et attend validation avant publication comme règle effective ou mise en place d'une suppression.

La publication des correctifs, l'ouverture de l'indexation, la revue des moyens de règlement et la connexion aux comptes Google sont des étapes distinctes. L'indexation est ouverte sur le serveur et le sitemap HTTPS a été soumis de nouveau dans Search Console après un test actif réussi ; son traitement reste à confirmer. Aucune campagne, dépense, commande réelle, émission d'e-mail ou demande d'indexation de page n'a été effectuée par Codex. Les tests locaux portent sur fixtures/SQLite ; la lecture du catalogue/inventaire réel est séparée de ces tests. Google décide de l'indexation et de l'approbation.

Vérification finale : **82 tests ciblés / 4 097 assertions réussis**, sur SEO/flux/audit, informations légales/retours, panier/checkout/virement et administration. Les anciens tests qui attendaient les routes et clés Stripe supprimées ou un RIB dans l'ancien écran de paramètres ont été alignés sur les routes de virement/RIB actuelles ; les protections et la vérification financière restent testées. Pint, syntaxe PHP/Python et `git diff --check` réussis. Aucune régression générale exhaustive, recette responsive, mesure CWV publique, réception réelle d'un devis ni validation de compte Google n'est revendiquée. Preuves dans `output/qa-google-20261013/verification-summary.json`, `inventory-observation.json`, `public-site-audit.json` et les rapports catalogue.
