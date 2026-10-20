# Cadrage des deux congélateurs — 19 octobre 2026

Les fiches GFT61SWCSE blanc et GFM61MCCSD carbone affichent désormais les congélateurs plus grands. Le cadrage est réalisé dans l’interface : seules les marges blanches débordantes sont masquées. Les JPEG haute définition et leurs originaux restent strictement inchangés, sans recadrage destructif, réencodage ou déformation.

La zone photo passe à 480 pixels de haut sur ordinateur, 440 sur tablette et 340 sur mobile. Les deux produits occupent respectivement environ 446, 406 et 306 pixels de haut, avec environ 17 pixels de marge au-dessus et au-dessous. Logo, poignée et pieds restent entièrement visibles. Les proportions sont conservées ; aucune partie du congélateur n’est coupée.

Le cadrage est associé aux deux photos exactes, pour une galerie à image unique. Une autre photo ou une future galerie à plusieurs vues garde le cadrage normal tant que sa composition n’a pas été revue. Les tailles de chargement responsive ont été ajustées pour tenir compte de la largeur réelle de l’image avant masquage des marges, y compris sur petit mobile.

## Vérifications

- Build Vite réussi ; 18 tests ciblés ResponsiveMedia/CataloguePresentation réussis, 458 assertions.
- Deux produits contrôlés dans le navigateur à 1 265, 768, 390 et 320 pixels de largeur, soit huit compositions vérifiées.
- Produits entiers, ratios inchangés et marges au moins égales à 16 pixels dans les huit cas ; variantes sélectionnées au moins aussi larges que l’image rendue à la densité observée.
- Cadrage présent sur les deux fiches dans les trois langues ; une autre fiche de réfrigérateur contrôlée conserve son affichage normal.
- SHA-256 des deux JPEG publics et des deux originaux inchangés, ainsi que `media-overrides.json` et le manifeste des images responsives.
- Aucun changement SQL, de configuration ou de données commerciales ; aucun nouveau fichier image produit créé.

Preuves : captures et mesures dans `output/qa-freezer-framing-20261019/`, état antérieur et vérification dans `storage/app/private/freezer-framing-20261019/`.

Sauvegarde complémentaire : `storage/app/private/media-backups/HOMYA-recadrage-congelateurs-20261019.tar`, avec inventaire et SHA-256 dans le fichier metadata voisin. Elle conserve les fichiers avant/après, les images sources intactes et les captures.
