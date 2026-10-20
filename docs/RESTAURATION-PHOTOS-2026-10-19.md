# Restauration des photos HOMYA — 19 octobre 2026

Demande utilisateur : récupérer les photos des produits depuis les liens conservés
dans le catalogue restauré afin de retrouver l'affichage local.

## Périmètre et provenance

Le catalogue métier contient 18 454 chemins d'images CDN, correspondant à
12 615 contenus SHA-256 distincts et à 3 450 463 473 octets logiques. Les sources
sont les URL HTTPS `damdam.b-cdn.net` enregistrées dans l'index et les fiches.
Les chemins locaux, tailles, empreintes et types attendus sont conservés.

35 produits possèdent également des photos de remplacement recensées dans
`media-overrides.json`, avec source constructeur, vérification de modèle,
transformations et déclaration d'autorisation déjà archivées. Ces déclarations
ne sont pas présentées comme des contrats examinés.

Le manifeste privé est créé **avant** les téléchargements dans
`storage/app/private/media-recovery-20261019/cdn/inventory.json`. Il conserve
les URL, produits associés, destinations, tailles, SHA-256 et base d'autorisation,
ainsi que l'empreinte de tous les documents JSON du catalogue avant récupération.
Les résultats ajoutent date, type réel, dimensions et empreinte observée.

## Procédure

Les téléchargements sont limités aux URL archivées et à leur hôte, sans cookies,
identifiants ni contournement des refus. Les fichiers sont installés seulement
après comparaison exacte de leurs octets/SHA-256 et validation de leur format.
Les destinations existantes différentes sont refusées. Les fichiers identiques
partagés entre produits sont dédupliqués par liens physiques ; leurs chemins
historiques restent disponibles. Pour modifier une photo, préparer un nouvel
actif et une nouvelle référence plutôt que modifier un original en place.

Le plafond est de 16 requêtes/s, avec au plus 32 transferts actifs, pauses sur
HTTP 429 et reprises bornées sur les erreurs temporaires. Les fichiers temporaires
ne sont pas exposés dans le répertoire public. Les JSON, prix, traductions,
stocks et bases SQL ne sont pas modifiés par cette opération.

Les photos constructeur sont régénérées uniquement lorsque l'original et la
transformation permettent de retrouver exactement l'empreinte historique.
Aucune nouvelle photo ni capture approximative n'est fabriquée.

## Outils et preuves

- `scripts/recover_catalogue_media.py` : inventaire hors réseau par défaut ;
  `--apply --workers 32` reprend les fichiers manquants en conservant ceux vérifiés.
- `scripts/recover_manufacturer_photos.py` : sources et transformations constructeur.
- `scripts/verify_recovered_media.py` : contrôle final local sans réseau ni SQL.
- Preuves privées, ignorées par Git : `storage/app/private/media-recovery-20261019/`.

Les médias restent dans les répertoires `public/media/catalogue` et
`storage/app/catalogue/media-originals`, déjà exclus de Git. Sauvegarder ces
répertoires avec le catalogue et les données privées pour éviter une nouvelle perte.

## Résultat vérifié

Récupération CDN terminée : les **18 454 images attendues** sont présentes,
avec taille et SHA-256 conformes aux archives, soit **3 450 463 473 octets**
logiques. Aucun échec CDN et aucun document JSON du catalogue modifié.
Le contrôle indépendant a terminé le 19 octobre 2026 à 18:01 UTC.

Lot constructeur : 14 photos et leurs originaux restaurés à l'identique
(10 Spartherm, Bosch, BEKO, Woodstock et LG GTF8659PS). Les 20 liens directs LG
et une page produit publique LG renvoient HTTP 403 ; aucun contournement tenté.
Pour Bronpi NICOL, la vidéo source est conservée privée mais l'ancienne capture
ne peut pas être reproduite exactement avec les éléments disponibles.

**3 688 produits sur 3 709 disposent d'une photo affichable** selon les critères
du site (dimensions minimales 500 × 500 et validation du format). Les 21 produits
restants correspondent aux 20 modèles LG bloqués et à Bronpi NICOL. Leur liste
exacte et les fichiers manquants figurent dans
`storage/app/private/media-recovery-20261019/verification.json`.

Le générateur responsive existant a également préparé les variantes WebP :
3 670 entrées, 10 384 références de variantes aux largeurs 400/800/1 200 px
selon les dimensions disponibles, sans agrandissement ni recadrage.
Les originaux restent inchangés. Les trois tests existants du générateur,
les contrôles de syntaxe et les contrôles d'intégrité de récupération réussissent.
Les 24 photos de la première page ont été observées chargées dans le navigateur
local ; le contrôle HTTP final est enregistré dans `http-verification.json`.

Les images d'ambiance de l'accueil ne font pas partie des liens du catalogue.
Huit visuels restent absents. Trois URL actuellement publiées ont été identifiées,
sans correspondance historique exacte vérifiée ni remplacement installé ; les
cinq autres sources restent inconnues. Cette limite est distincte de la
restauration des photos produits.

## Sauvegarde locale

Archive privée : `storage/app/private/media-backups/HOMYA-photos-20261019.tar`.
Elle regroupe les photos, variantes, archives du catalogue, originaux constructeur,
preuves et scripts de récupération, sans `.env` ni accès SQL. Son inventaire et
son SHA-256 sont enregistrés dans `backup-metadata.json` dans le même dossier.
Copier cette sauvegarde sur un autre support : les médias sont ignorés par Git
et une réinitialisation du disque pourrait aussi supprimer cette copie locale.
