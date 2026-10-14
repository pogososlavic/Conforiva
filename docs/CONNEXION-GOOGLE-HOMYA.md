# HOMYA — connexion à Search Console et Merchant Center

## Contrôle et corrections sitemap — 14 octobre 2026

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

### Corrections locales vérifiées, à déployer

- Sitemap de pages complété avec les paginations canoniques du catalogue, des univers, des catégories et des promotions actives. Page 1 sans paramètre ; autres pages avec `page=N`. Taille partagée de 24 produits par page dans `config/homya.php`, avec repli compatible avec un ancien cache de configuration.
- Nombre de blocs de produits calculé pour chaque langue, afin de ne pas référencer un enfant absent lorsqu'un produit personnalisé n'est publié que dans certaines langues. Une source/langue indisponible renvoie 503.
- Recherche, filtres, panier, commandes, administration et suivi exclus ; variantes regroupées sous la fiche canonique parent. Les pages d'information encore marquées préparatoires restent exclues ; cette correction ne les déclare pas finalisées.
- Aperçu complet généré depuis l'archive fichiers locale, sans administration, base réelle, stock réel ni réseau : **610 pages et 3 709 produits par langue**, soit **12 957 URL uniques**, dans 15 enfants XML valides. Cet aperçu est une preuve locale ; le catalogue administré en production peut différer. Ne pas recopier cet instantané à la place des routes dynamiques.
- **53 tests / 1 723 assertions** réussis : SEO/sitemap, catalogue, pagination, promotions et en-têtes. Syntaxe PHP, Pint et `git diff --check` vérifiés. Aucun `.env`, base, service XAMPP ou fichier distant modifié ; aucun déploiement ni push effectué.

Preuves locales : `output/qa-sitemap-20261014/public-network-check.json`, `gsc-sitemap-live-success.jpg`, `preview/sitemap.xml`, `preview/robots-production.txt` et `preview/validation.json`.

Après validation du déploiement : installer les six fichiers applicatifs concernés, renouveler les caches de configuration et de routes avec le PHP compatible de l'hébergement, contrôler la redirection racine et le sitemap/enfants, puis vérifier le rapport Sitemaps. Conserver l'URL stable HTTPS ; ne pas ajouter de variante de sitemap pour contourner un état ancien. Les fichiers concernés sont `SeoController.php`, `CatalogueController.php`, `PromotionsController.php`, `SecurityHeaders.php`, `config/homya.php` et `routes/web.php`.

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
6. Pour les appareils concernés, obtenir l'étiquette et la fiche énergétique fournisseur. Un `eprel_code` confirmé peut être ajouté à la revue ; le flux émet alors une certification EC/EPREL. L'attribut est facultatif dans la spécification Google examinée et ne remplace pas les obligations sectorielles du vendeur. [Certification Google](https://support.google.com/merchants/answer/13528839?hl=fr).
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
