# HOMYA — revue des photos alternatives du 19 octobre 2026

Recette ciblée de correspondance des modèles, indépendante de la recherche et de l’intégration. Les sources sont les fiches du catalogue récupéré, les documents constructeur conservés en privé, et les images candidates visualisées. Cette revue ne constitue pas une recette commerciale ou une vérification de licence indépendante.

## Contrôles réalisés

- Matrice des 21 références, EAN disponibles, finitions, catégories, portes et dimensions extraite des fiches catalogue.
- Dix références de lavage : titres et EAN relus directement dans les PDF, hachages des sources et photos vérifiés, dimensions mesurées, images complètes visualisées et comparées aux finitions. Dix correspondances acceptées. LSWD100E est une photo publiée de 535 × 535 pixels, comparée directement au même visuel dans une fiche LG italienne ; son attribution fabricant reste une inférence consignée.
- Sept références de froid acceptées : GFT61SWCSE et GLT71SWCSE blancs et GFM61MCCSD carbone, en plus de GBB72SWVDN et GBV3200DSW blancs, GLM71MCCSD carbone, GFT61PZCSE platinium. Références et EAN contrôlés ; le scan GFT61PZCSE a été lu visuellement, ses trois bandes reconstruites sans rééchantillonnage, et le recadrage comparé pixel par pixel. Les trois autres candidats sont également identiques aux pixels des images natives extraites. Les images étroites nécessitent une marge blanche sans agrandissement. Les deux candidats supplémentaires GFT61SWCSE (152 × 465) et GFM61MCCSD (152 × 467) sont exacts mais de basse définition : façade, porte et poignée restent lisibles dans une carte modérée, tandis que les petits textes et détails du produit ne le sont pas. Un fond blanc de 500 × 500 pixels permet l’affichage local sans prétendre recréer du détail. Ces images ne conviennent pas à une présentation annoncée comme zoom haute définition.
- Bronpi NICOL : capture native du film officiel du modèle, silhouette comparée au PNG constructeur. Recadrage de 1 000 × 1 000 pixels vérifié comme identique à la zone de la trame source, sans agrandissement. L’appareil et ses pieds sont visibles.
- Un PDF dont le lien mentionnait F964R33WRS a été rejeté : le contenu présentait F164R33WRS et l’EAN 8806084220486. La fiche exacte Procie a ensuite été vérifiée pour F964R33WRS.

GSK6876SC : la page SAV dont les liens pointent sur GSS6876SC reste exclue. Le rejet initial d’un ancien JPEG à partir d’une traverse horizontale en façade était trop affirmatif ; ce seul détail ne prouve pas le nombre de battants. Deux vues de 900 × 900 pixels ont ensuite été retrouvées sur la fiche Darty DOM avec GSK6876SC et EAN 8806098739011 explicitement liés. Les vues ouvertes 4 et 5 établissent finalement deux battants pleine hauteur et une sous-porte Door-in-Door. Le rejet de carrosserie est retiré et les deux photos de 900 × 900 pixels sont acceptées ; la correction et les preuves sont consignées dans `review/gsk-review.json`.

GLT71SWCSE a été amélioré avec une nouvelle source native de 235 × 690 pixels, dont le titre, l’EAN et la couleur ont été lus sur le scan ; son recadrage est identique aux pixels reconstruits, sans agrandissement.

Les 21 correspondances sont désormais acceptées : 10 références de lavage, 10 de froid et Bronpi NICOL. Deux vues de froid restent de faible définition et GBB62PZFFN est natif de 480 × 480 pixels ; les limites d’agrandissement sont conservées.

GMX945NS9F : photo native de 294 × 511 pixels extraite de sa fiche constructeur française, référence et EAN 8806091820235 exacts, finition Inox Brossé. La photo entière montre quatre portes, distributeur supérieur gauche et fenêtre InstaView supérieure droite.

GBB62PZFFN : photo native de 480 × 480 pixels extraite du PDF LG allemand, même référence complète, même aspect et finition métallique que la photo française archivée dans la fiche catalogue. Le PDF allemand porte l’EAN 8806098343539, différent de l’EAN français 8806098467846. L’acceptation concerne la correspondance photographique corroborée par l’archive FR ; elle ne prétend pas que les deux GTIN soient identiques et ne modifie aucune donnée commerciale.

## Vérification indépendante après installation

Contrôle exécuté le 19 octobre 2026 à 19:04:53 UTC avec `review/verify_installation.py` ; résultat enregistré dans `review/installation-review.json`.

- **21 produits sur 21 : contrôles réussis**, aucun candidat non installé, aucun échec.
- **44 variantes responsives** présentes, décodables, dimensions, tailles et SHA-256 conformes à leur manifeste.
- Photos publiques et originaux contrôlés par taille, format, dimensions et SHA-256 ; chaque candidat correspond à une acceptation indépendante pour sa référence.
- Pour les **10 images avec marge blanche**, la zone du produit conserve exactement les pixels natifs ; aucune interpolation n’a été observée.
- L’index catalogue est inchangé, les **14 autres remplacements de photos** sont inchangés et aucun produit hors des 21 attendus n’a été installé.
- L’overlay et le manifeste responsive correspondent aux hachages du reçu d’installation.

Cette vérification locale ne lance ni requête réseau, ni HTTP, ni SQL, ni modification de configuration. Les contrôles des pages HTTP et du présentateur multilingue sont effectués séparément par le pilote. Elle ne démontre pas une équivalence d’EAN pour le cas allemand GBB62PZFFN ni une définition supérieure aux pixels natifs déclarés.

## Conditions et limites

Les photos F14V13WHS (481 × 611) et F864V31WR (442 × 556) nécessitent une marge blanche sans agrandissement pour satisfaire le seuil d’affichage local. Le remplissage ne doit modifier aucun pixel du produit. Les packshots de froid étroits requièrent également cette marge blanche. Deux ont en outre la réserve de basse définition décrite ci-dessus ; ce traitement ne transforme pas leur résolution native.

La capacité « 1 » dans la fiche catalogue F14V13WHS est incohérente avec le PDF constructeur de 10,5 kg. La référence complète et l’EAN correspondent ; cette donnée commerciale n’a pas été corrigée dans le périmètre photo.

Les droits sont fondés sur la déclaration utilisateur déjà consignée, sans audit de contrats ni affirmation de licence ouverte. Aucun filigrane n’a été supprimé, aucune photo de modèle voisin acceptée, aucune image générée utilisée. La disponibilité HTTP et le rendu de l’application sont vérifiés séparément par le pilote ; les fichiers installés et les variantes responsives ont été vérifiés ci-dessus. Les tests base/paiement/lancement ne sont pas concernés par cette recette ciblée.

Le module externe `/Users/akm/Codex-Workspace/.codex/workflows/README.md` indiqué par le dépôt est absent ; les instructions locales ainsi que les skills commerce-assets, commerce-qa et commerce-pilot disponibles ont été lus.

Preuves privées : `storage/app/private/product-photo-alternatives-20261019/review/expected-models.json`, `laundry-review.json`, `cooling-review.json`, `bronpi-review.json`, `gsk-review.json`, `gbb-review.json` et planches de contrôle. Aucune source, configuration, base ou donnée commerciale modifiée par le relecteur.
