# Amélioration des deux photos LG — 19 octobre 2026

Les deux photos de faible définition ont été remplacées par des fichiers publiés plus détaillés, après contrôle indépendant du modèle exact, de l’EAN, de la finition et de la carrosserie.

| Produit exact | EAN | Ancien visuel natif | Nouveau fichier publié | Produit utile mesuré dans la nouvelle photo | Source |
|---|---|---|---|---|---|
| GFT61SWCSE blanc | 8806091276001 | 152 × 465 | 5 000 × 5 000 JPEG | 1 479 × 4 599 ; hauteur ×9,89 | [CEDI, fiche et galerie du produit](https://www.cedi-electromenager.com/produits/famille-congelateur1/91088-GFT61SWCSE/) |
| GFM61MCCSD carbone | 8806091257659 | 152 × 467 | 1 200 × 1 200 JPEG | 289 × 897 ; hauteur ×1,92 | [Darty Martinique, fiche et galerie](https://martinique.darty-dom.com/p/lg-gfm61mccsd) |

Les deux JPEG principaux et les copies originales sont conservés exactement comme téléchargés, sans recadrage, fond ajouté, agrandissement local ni génération d’image. Les variantes WebP de 400, 800 et 1 200 pixels réduisent les fichiers pour l’affichage. La taille de la toile blanche ne doit pas être confondue avec la résolution utile du produit. L’historique des fichiers maîtres en amont des distributeurs reste inconnu ; les détails du logo, de la poignée et de la façade montrent néanmoins un gain réel sur les petites photos précédentes.

## Correspondance vérifiée

Le blanc présente la poignée extérieure droite, le logo haut droit et une façade sans afficheur frontal. Sa galerie ouverte montre un intérieur de congélateur avec commande interne. La page CEDI porte la référence et l’EAN exacts et relie son bouton de galerie, la miniature et le fichier original. Son titre générique « réfrigérateur » est impropre ; aucune description commerciale n’a été importée.

Le carbone présente la même poignée droite, la finition carbone et l’afficheur frontal ThinQ centré que la fiche LG archivée. Le JSON-LD Darty relie la référence, l’EAN et la photo à un même produit ; le lien 1 200 pixels est publié explicitement dans la galerie.

Les fiches LG antérieurement récupérées servent de preuve de comparaison. Le logo LG CEDI et les petits visuels Ubaldi/BUT qui n’apportaient pas de gain utile ont été écartés. Aucun refus HTTP n’a été contourné.

## Contrôles réalisés

- Revue indépendante avant et après installation : deux références acceptées, zéro anomalie.
- Exactement deux entrées de photos modifiées ; les 33 autres remplacements demeurent identiques.
- Index, fiches produit, traductions et données commerciales inchangés. Aucun SQL ou changement de configuration.
- Deux nouvelles sources et six variantes responsives vérifiées ; les 3 691 anciennes entrées du manifeste restent identiques.
- Six fiches HTTP FR/EN/DE : réponses 200 et nouvelles photos présentes. Huit ressources HTTP : deux JPEG et six variantes, réponses 200 et octets conformes.
- Ensemble responsive : 3 693 sources, 10 434 références, 7 912 fichiers uniques, zéro anomalie.
- Présentateur Laravel réel : 3 709 produits avec photo sur 3 709 dans chacune des trois langues.
- Captures du rendu : deux fiches sur ordinateur et le modèle carbone sur mobile, dans `output/qa-photo-quality-20261019/`.
- Syntaxe des scripts Python et différences Git contrôlées. Aucun code d’application modifié ; la recette reste ciblée sur ces médias.

## Preuves et sauvegarde

[Revue indépendante détaillée](QA-PHOTOS-QUALITE-20261019.md).

Les documents privés, plans avant téléchargement, fichiers téléchargés, mesures, SHA-256, acceptations par modèle, état antérieur, reçu et vérifications sont conservés dans `storage/app/private/photo-quality-upgrade-20261019/`. Les fichiers `installation.json`, `http-verification.json`, `presenter-verification.json`, `review/installation-review.json` et `review/source-pixels-review.json` décrivent les résultats observés.

L’utilisation repose sur l’autorisation utilisateur des médias constructeur déjà consignée dans DECISION-016 ; déclaration conservée, contrats non audités. L’attribution des rendus à LG est corroborée par les fiches constructeur, sans revendiquer une nouvelle licence libre.

Sauvegarde complémentaire : `storage/app/private/media-backups/HOMYA-photos-qualite-20261019.tar`, avec inventaire et SHA-256 dans le fichier metadata voisin. Elle complète les sauvegardes du catalogue et des 21 photos déjà restaurées ; conserver également ces archives antérieures et les copier hors de cet ordinateur.
