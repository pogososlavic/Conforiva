# HOMYA — connexion à Search Console et Merchant Center

Préparation du 13 octobre 2026. Domaine confirmé : **https://homya.eu**. Même compte Google pour les deux services. Livraison gratuite FR/BE/DE confirmée ; prix et stocks validés par HOMYA, données fabricant encore à compléter. Les modifications de ce travail sont dans le dépôt HOMYA1 ; aucune installation sur Hostinger ni action dans un compte Google effectuée.

## 1. État vérifié et corrections

La lecture du catalogue SQL local et de l'administration a réussi hors sandbox. L'ancien message `Catalogue unavailable` provenait ici du blocage du socket MariaDB par le sandbox. Le catalogue et les images principales étaient présents. La nouvelle option `--offline` fournit un audit explicite de l'archive sans base ni administration ; elle ne remplace pas le contrôle des corrections administratives.

Audit courant FR/EN/DE : 3 709 produits dans chaque langue, titres/descriptions dans les limites de format, 418 fiches avec signalements, 3 683 produits simples sans identifiants fabricant documentés pour Merchant, 26 groupes/92 options nécessitant leur revue individuelle. Aucun produit n'a été approuvé automatiquement. La lecture distincte de l'inventaire local a observé 3 711 lignes, dont 3 709 vendables avec une quantité supérieure à la réservation, prix et date de validation présents. Elle ne vérifie pas physiquement les stocks.

| Correction dans le dépôt | Résultat |
| --- | --- |
| Sitemap | Toutes les pages d'information éligibles utilisent la même règle que leurs métadonnées ; contact/livraison/paiement ajoutés, pages privées ou encore préparatoires exclues |
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

Installer les fichiers préparés sur l'hébergement avec sauvegarde, puis appliquer dans **la configuration privée de production**, après recette :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://homya.eu
HOMYA_PUBLIC_URL=https://homya.eu
HOMYA_SEO_INDEXING_ENABLED=true
```

Ces valeurs sont une procédure de production ; elles n'ont pas été appliquées à `.env` local ou distant pendant ce travail. L'exemple de configuration indique désormais le domaine confirmé. Le serveur doit diriger le site vers Laravel `public/`, conserver la règle dynamique de `robots.txt`, rediriger HTTP et l'éventuel www vers l'origine HTTPS choisie. Les versions FR/EN/DE restent des chemins sur cette origine.

Après configuration, purger puis reconstruire les caches avec le PHP compatible de l'hébergement :

```sh
php artisan optimize:clear
php artisan config:cache
```

Contrôler de nouveau les réponses réelles :

- `https://homya.eu/robots.txt` : `Allow: /` et `Sitemap: https://homya.eu/sitemap.xml`.
- `https://homya.eu/sitemap.xml` : 200 XML, fichiers de pages et produits des trois langues accessibles.
- Accueil, catégories et fiches complètes : 200, canonical correct, meta et en-tête compatibles avec l'indexation.
- Panier/commande/suivi/administration : accès protégés et `noindex` conservé. Les pages légales encore préparatoires restent accessibles aux utilisateurs, sans prétendre à leur validation juridique.

Ne pas simplement supprimer le fichier robots fermé : il est un repli de sécurité. C'est le contrôleur Laravel, avec la bonne configuration de production, qui doit servir la réponse publique. Le script `python3 scripts/audit_google_public.py` facilite le relevé des statuts publics, sans cookies affichés ni formulaire soumis.

## 4. Connecter Search Console

1. Se connecter au compte Google retenu dans [Search Console](https://search.google.com/search-console).
2. Ajouter une propriété **Domaine**, saisir `homya.eu` sans protocole ni chemin.
3. Copier exactement l'enregistrement **TXT** donné par Google. Dans la zone DNS qui fait autorité pour `homya.eu` — hPanel si les DNS y sont gérés — ajouter ce TXT à la racine (`@` ou valeur demandée par l'interface). Conserver les autres enregistrements, puis cliquer sur **Valider** dans Search Console lorsque le TXT est propagé. [Validation officielle](https://support.google.com/webmasters/answer/9008080?hl=fr).
4. Dans **Sitemaps**, soumettre `https://homya.eu/sitemap.xml` après son passage en 200 public. Vérifier son état et les URL découvertes. [Rapport des sitemaps](https://support.google.com/webmasters/answer/7451001?hl=fr).
5. Dans **Inspection de l'URL**, tester accueil, catégorie et quelques fiches complètes ; contrôler lecture en direct, canonical et possibilité d'indexation. Demander l'indexation de ces pages représentatives. Le sitemap sert à découvrir l'ensemble du catalogue ; éviter de demander manuellement 3 709 URL.
6. Suivre les pages exclues, erreurs serveur, canonicals et résultats enrichis. La validation DNS prouve la propriété ; elle ne force pas l'indexation.

Le DNS couvre le domaine et ses variantes. La balise `HOMYA_GOOGLE_SITE_VERIFICATION` n'est nécessaire que si une méthode HTML/préfixe d'URL est choisie à la place ; utiliser alors la valeur fournie par Google, pas un code fictif. Aucun compte Analytics ou tag publicitaire n'est nécessaire pour cette méthode DNS.

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

La publication des correctifs, l'ouverture de l'indexation, la revue des moyens de règlement et la connexion aux comptes Google sont des étapes distinctes. Aucune campagne, dépense, commande réelle, émission d'e-mail ou demande d'indexation n'a été effectuée pour ces corrections. Les tests locaux portent sur fixtures/SQLite ; la lecture du catalogue/inventaire réel est séparée de ces tests. Google décide de l'indexation et de l'approbation.

Vérification finale : **82 tests ciblés / 4 097 assertions réussis**, sur SEO/flux/audit, informations légales/retours, panier/checkout/virement et administration. Les anciens tests qui attendaient les routes et clés Stripe supprimées ou un RIB dans l'ancien écran de paramètres ont été alignés sur les routes de virement/RIB actuelles ; les protections et la vérification financière restent testées. Pint, syntaxe PHP/Python et `git diff --check` réussis. Aucune régression générale exhaustive, recette responsive, mesure CWV publique, réception réelle d'un devis ni validation de compte Google n'est revendiquée. Preuves dans `output/qa-google-20261013/verification-summary.json`, `inventory-observation.json`, `public-site-audit.json` et les rapports catalogue.
