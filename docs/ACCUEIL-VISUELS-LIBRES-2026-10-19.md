# Accueil HOMYA — visuels libres et présentation

Demande du 19 octobre 2026 : installer des images libres adaptées aux produits
vendus et rendre la page d'accueil plus attractive, sur l'installation locale.

## Réalisation

- Bannière en deux colonnes sur ordinateur, empilée sur téléphone : salon lumineux,
  fond ivoire, identité jaune conservée, titre et actions lisibles.
- Deux photographies d'ambiance pour le chauffage et l'électroménager, avec liens
  vers les univers correspondants. Ce sont des illustrations, pas des modèles
  identifiés du catalogue ni des locaux HOMYA.
- Cinq vignettes issues de la projection catalogue existante : poêle Orta, table
  Tips 2, réfrigérateur Samsung RB33B610ESA, lave-linge LG F14R15WHS et Philips
  Baristina BAR307/20. La sélection et ses filtres sont dans `config/home.php`.
  Les dimensions, variantes et règles de provenance proviennent du catalogue.
- Le panneau conseil utilise aussi la nouvelle photo de salon ; les crédits sont
  consultables au bas de l'accueil. Textes et descriptions FR/EN/DE adaptés.

## Sources et droits

Trois photos sous **CC0 1.0**, vérifiées sur leurs fiches Wikimedia Commons :

1. [Living room — Jarosław Ceborski](https://commons.wikimedia.org/wiki/File:Living_room_(Unsplash).jpg).
2. [Blue white kitchen interior — NeONBRAND](https://commons.wikimedia.org/wiki/File:Blue_white_kitchen_interior_(Unsplash).jpg).
3. [2016-366-319 On Fire — Alan Levine / cogdogblog](https://commons.wikimedia.org/wiki/File:2016-366-319_On_Fire_(25358789789).jpg).

Les quatre fichiers WebP totalisent 304 834 octets. Les originaux sont conservés
dans `storage/app/private/home-assets-20261019/`. Aucune génération IA ni
modification des produits. Redimensionnement proportionnel, qualité WebP 84 ;
le cadrage à l'écran utilise `object-fit: cover` pour les photos d'ambiance.
Sources, auteurs, licences, SHA-256 et dimensions :
`docs/HOME-IMAGE-CREDITS-2026-10-19.json`.

Les nouvelles photos `public/media/home/open-*.webp` sont incluses dans le périmètre
versionnable par Git, contrairement aux anciens médias volumineux du catalogue.
Aucun commit, push ni déploiement effectué par cette opération.

## Vérifications

- Build Vite réussi, Pint réussi.
- 16 tests ciblés existants (accueil, merchandising et navigation partagée) :
  3 954 assertions réussies. Le test des liens permet les crédits vers quatre URL
  précises, uniquement dans le bloc crédits ; les autres liens restent contrôlés.
- Contrôles visuels bureau 1 265 px, tablette 768 px, téléphone 390 px ; largeur
  320 px vérifiée en FR/EN/DE. Aucun débordement horizontal observé. L'ajustement
  du titre à 768 px a été contrôlé après correction.
- Bannière, deux photos d'inspiration et cinq vignettes produits observées
  chargées. Focus clavier visible observé sur une carte de rayon.
- Captures : `output/qa-home-20261019/desktop.png`, `mobile.png`, `tablet.png`,
  `inspiration.png`. La recette indépendante est dans
  `docs/QA-ACCUEIL-2026-10-19.md`.

La revue reste ciblée sur l'accueil local ; elle ne constitue pas un audit complet
d'accessibilité ou de préparation commerciale. Aucun prix, stock, compte,
commande, paiement, table SQL ou secret n'a été modifié par ce travail.

Sauvegarde complémentaire :
`storage/app/private/media-backups/HOMYA-accueil-20261019.tar` (photos d'ambiance,
originaux, sources modifiées, crédits et preuves de recette).
