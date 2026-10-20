# Revue des deux photos LG — 19 octobre 2026

Périmètre : amélioration des images de GFT61SWCSE (blanc, EAN 8806091276001) et GFM61MCCSD (carbone, EAN 8806091257659). La recette porte sur le modèle photographié, la définition réellement utile et l’intégration locale des médias. Elle ne constitue pas une recette commerciale complète ni une validation Google.

## État initial vérifié

Les deux petites images ont été ouvertes. Les fiches LG archivées sont cohérentes avec la référence, la couleur et les EAN attendus :

- GFT61SWCSE : 152 × 465 pixels natifs ; blanc, poignée verticale externe à droite, absence d’afficheur frontal.
- GFM61MCCSD : 152 × 467 pixels natifs ; carbone, poignée verticale externe à droite, afficheur tactile centré en haut de la porte.

Les états antérieurs des substitutions, du manifeste responsive et les SHA des JSON catalogue sont conservés dans `storage/app/private/photo-quality-upgrade-20261019/review/`.

## Contrôles avant intégration

Les candidats sont examinés individuellement : lien publié avec la référence et l’EAN, concordance de finition et de carrosserie, cadrage complet, absence de filigrane ou de promotion, dimensions du produit dans l’image et gain réel de détail. Les dimensions d’une grande toile blanche ne suffisent pas à démontrer une amélioration. Un agrandissement artificiel est exclu.

Premiers candidats écartés : le fichier CEDI identifié comme logo LG ; le visuel Ubaldi du congélateur blanc de 537 × 410, dont le produit n’occupe qu’environ 129 × 400 pixels, inférieurs aux 152 × 465 existants. Motifs et SHA dans `review/rejections.json`.

GFM61MCCSD est accepté après ouverture du JPEG Darty de 1 200 × 1 200 et comparaison directe avec l’image de la fiche LG. Le produit occupe 289 × 897 pixels : environ 1,92 fois la hauteur native précédente. Le grain de porte, l’afficheur ThinQ/-19, la poignée, les pieds et le logo sont réellement mieux définis. La grande toile blanche ne constitue pas elle-même le gain de détail. Produit entier, aucun filigrane ni texte promotionnel. Le JSON-LD de la [fiche Darty exacte](https://martinique.darty-dom.com/p/lg-gfm61mccsd) relie GFM61MCCSD et EAN 8806091257659 à la même galerie 470997 ; la variante 1200 est explicitement publiée dans data-zoom. SHA du JPEG : `b765a9b37d3685dbc85493a329a721f3efe4c98a516d073c8db82919d6c7d279`.

GFT61SWCSE est accepté après ouverture du JPEG CEDI de 5 000 × 5 000 et de détails sans redimensionnement. Le relief du logo LG, la texture de la poignée et le joint de porte sont nets. Le produit occupe 1 479 × 4 599 pixels, soit environ 9,89 fois la hauteur native précédente. Blanc, absence d’afficheur frontal, poignée droite, silhouette et pieds concordent avec la photo individuelle de la fiche LG. La seconde vue de la même galerie montre l’intérieur du congélateur et sa commande interne. La [fiche CEDI exacte](https://www.cedi-electromenager.com/produits/famille-congelateur1/91088-GFT61SWCSE/) porte la référence et l’EAN 8806091276001 ; le bouton galerie et le script public appellent l’API de galerie avec les mêmes identifiants. La réponse JSON relie la miniature publiée à l’original 44261 téléchargé. Aucune URL de média devinée. SHA : `0a1dbf057be89ab58080b50f7868390f013c0e032ca00d8adf85c09ff8f67020`.

Le titre générique de CEDI emploie « réfrigérateur » : ce libellé est impropre mais ne remplace pas la preuve référence/EAN/galerie. Aucune description commerciale de cette page n’est importée. L’historique technique des fichiers maîtres en amont du distributeur reste inconnu ; les détails observés dépassent réellement ceux des anciennes petites images et aucun agrandissement local n’a été effectué.

**État : les deux images exactes sont acceptées et installées ; contrôles indépendants après intégration réussis, zéro anomalie.**

## Contrôle après intégration

| Contrôle exécuté par QA | Résultat observé |
|---|---|
| Delta des substitutions | Exactement GFT61SWCSE et GFM61MCCSD modifiés ; les 33 autres entrées restent identiques |
| JSON catalogue | Seul `media-overrides.json` diffère de l’état antérieur ; index, fiches et traductions inchangés |
| Identité des fichiers | Les deux JPEG publics et les deux copies originales sont octet pour octet identiques aux candidats indépendamment acceptés |
| Transformations des photos principales | Aucun recadrage, fond ajouté ou agrandissement ; pixels et dimensions publiés conservés |
| Anciennes photos substituées des autres produits | 33 fichiers publics contrôlés : SHA et tailles conformes |
| Responsive antérieur | Les 3 691 entrées antérieures du manifeste restent identiques |
| Responsive ajouté | Deux sources ajoutées ; six variantes WebP de 400, 800 et 1 200 pixels, dimensions et SHA conformes, sans agrandissement |
| Fichiers installés | Formats réels ouverts, dimensions et tailles contrôlées |

Les contrôles HTTP, les captures navigateur et la couverture du présentateur sont réalisés par le pilote ; cette revue ne leur attribue pas de résultat avant réception de ses preuves. Aucun accès SQL, changement de configuration ou de données commerciales réalisé par QA.

Preuves privées : `review/gft-review.json`, `review/gfm-review.json`, `review/gfm-source-jsonld.json`, `review/installation-review.json`, `review/source-pixels-review.json`, détails natifs du logo et de la poignée GFT, rejets et états antérieurs. L’audit ciblé est reproductible via `review/audit_installed.py` avec le Python du projet.

## Provenance et limites

L’autorisation d’utilisation des visuels constructeur pour HOMYA repose sur la déclaration utilisateur consignée dans DECISION-016. Les contrats n’ont pas été audités ; aucune nouvelle licence libre n’est revendiquée. L’attribution à LG d’un rendu publié chez un distributeur est une inférence à rapprocher de la fiche constructeur, distincte d’une licence fournie par le distributeur.

Le chemin documentaire `/Users/akm/Codex-Workspace/.codex/workflows/README.md` cité par les instructions locales est absent. Les procédures commerce-pilot, commerce-assets et commerce-qa disponibles ont été lues et appliquées dans le périmètre ciblé.
