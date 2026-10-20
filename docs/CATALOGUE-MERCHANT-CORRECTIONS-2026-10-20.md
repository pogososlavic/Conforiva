> Lot initial conservé pour historique. Un lot ultérieur a porté le total à 186 EAN / 157 MPN et réduit les anomalies à 406 : voir [le bilan actualisé](CATALOGUE-DONNEES-OFFICIELLES-2026-10-20.md). Les CSV liés sont actualisés.

# Catalogue Merchant Center — corrections du 20 octobre 2026

## Résultat installé

- Deux modèles Olympia : les équipements cumulatifs « four / plaque de cuisson », explicitement présents dans leurs titres et données source, ne sont plus traités comme deux valeurs incompatibles. Correction structurelle, sans validation générale du fabricant.
- SUPRA OSCAR, référence vendeur 44294 : retrait de l’allégation globale « en fonte » en FR/EN/DE. La fiche technique officielle donne une matière principale acier, une grille et un sol de foyer en fonte. Quatre caractéristiques documentées sont ajoutées : acier, poids net 75 kg, poids brut 85 kg, dimensions largeur × hauteur × profondeur 44,7 × 63,6 × 36,9 cm. La référence EPREL 1444805 omise dans le registre est rétablie. Les slugs et archives restent identiques.
- **418 → 415 références avec anomalies**, **98 → 97 fiches sans caractéristiques structurées**. Il reste 317 références avec une contradiction de caractéristiques, deux titres à vérifier et un intervalle de puissance incohérent ; les ensembles se chevauchent.
- **961 dossiers de contrôle préremplis**, tous **non approuvés**, avec 904 assujettissements documentés par des étiquettes du modèle exact. Aucun faux `identifier_exists=false`, aucune provenance de texte devinée, aucune empreinte vide transformée en approbation.
- **262 produits / 267 associations de certification EPREL** documentées : 257 dossiers simples et cinq appareils à deux modules. Les deux codes de ces cinq appareils restent distincts ; un module n’est pas présenté comme le certificat du produit complet.
- **Quatre couples GTIN/MPN fabricant vérifiés** : trois pages officielles LG et la fiche technique SUPRA. Le nom de modèle EPREL n’est pas automatiquement converti en référence fabricant.
- Les 108 dossiers Aquaflam réservés restent consultables sur le site avec leur avertissement. Un nouveau contrôle empêche qu’une future revue Merchant approuvée fasse ignorer la réserve sur les documents du modèle exact.

## Identifiants vérifiés

| Produit | GTIN/EAN | MPN déclaré par le fabricant | Source |
| --- | --- | --- | --- |
| LG F14R15WHS | 8806084778222 | LG F14R15WHS | [LG France](https://www.lg.com/fr/lavage/lave-linge/f14r15whs-lave-linge-standards/) |
| LG 27LX6TDGA | 8806096527979 | LG 27LX6TDGA | [LG France](https://www.lg.com/fr/televiseurs-lifestyle/stanbyme/lg-27lx6tdga-stanbyme-tv/) |
| LG GFM61MCCSD | 8806091257659 | LG GFM61MCCSD | [LG France](https://www.lg.com/fr/refrigerateur/tall-fridge-freezers/lg-gfm61mccsd-congelateur-1-porte/) |
| SUPRA OSCAR | 3760075413286 | FR9016070B | [Fiche technique SUPRA, page 1](https://supra.fr/?jet_download=23545) |

Le SKU vendeur 44294 n’est pas assimilé au code fabricant FR9016070B. La référence du pack GFM61MCCSD.GLM71MC n’est pas associée au congélateur vendu seul. Les originaux HTML/PDF, chemins d’extraction et SHA sont archivés dans les preuves privées.

## Informations encore nécessaires

1. **Données fabricant par modèle et par variante** : GTIN/EAN réellement attribués, MPN et marque fabricant exacte. Le tableau préremplit les quatre couples confirmés. Ne pas fabriquer d’identifiant ni déclarer qu’il n’existe pas pour contourner une absence d’information.
2. **Caractéristiques sûres pour les 415 références signalées** : notamment 97 fiches sans données structurées, 227 groupes de poids contradictoires et 48 listes de zones d’installation à rapprocher des notices et de leurs conditions. Distinguer poids net et emballé, dimensions et unités. Les deux titres restants concernent Uovo réf.1597 et Infinity réf.6200 ; l’intervalle incohérent concerne Rimbaud réf.111700002.
3. **92 variantes de 26 produits** : photo exacte de chaque finition/couleur, attributs qui les distinguent et identifiants fabricant propres. Une galerie générale n’est pas une preuve de correspondance par option. Les 500 unités déclarées par produit restent partagées par ses variantes.
4. **Énergie** : qualifier les références restantes selon le type et le modèle, fournir les codes/documents manquants lorsqu’ils sont exigés et obtenir les fiches Aquaflam corrigées. Le contrôle interne signale encore 2805 dossiers à qualifier ; cela ne signifie pas autant d’appareils obligatoirement assujettis. Des meubles ou combustibles ne sont pas déclarés assujettis par analogie. Les cinq appareils à deux modules restent à traiter comme tels.
5. **Origine des titres et descriptions FR/EN/DE** : confirmer les textes originaux et ceux créés/traduits par IA ; le code utilise déjà les attributs structurés adaptés quand cette provenance est connue. La provenance n’est préremplie que lorsqu’elle est explicitement établie dans les trois langues.
6. **Avant une publication Google** : domaine public HTTPS, nouvelle adresse e-mail attendue, paiement opérationnel avec RIB HOME CONFORT, lieu réel d’expédition et confirmation des paramètres de livraison pour la nouvelle société, propriété/revendication du domaine et accès au bon compte Merchant Center. L’identité, le téléphone, le stock déclaré de 500, les retours au siège et CM2C déjà confirmés ne sont pas redemandés.

[Tableau fournisseur par produit et variante](INFORMATIONS-FOURNISSEURS-MERCHANT-2026-10-20.csv) — 3801 lignes, dont les 26 lignes de regroupement parent et les 92 variantes. Ce fichier est un document de travail ; ce n’est pas un flux prêt à transmettre.

[Liste des 415 anomalies et des valeurs en conflit](CATALOGUE-ANOMALIES-MERCHANT-2026-10-20.csv).

## Contrôles et limites

- **556 tests / 56 143 assertions réussis**, avec tests négatifs sur les autres modèles, références et variantes. Pint et espaces Git contrôlés sur le lot.
- Audit hors connexion des **3 709 produits dans les trois langues**, sans requête MariaDB, ni modification du stock, des commandes ou du paiement. Export final exécuté avec `php -d memory_limit=512M artisan homya:google-audit --offline --locale=all` : la première tentative à 128 Mo était incomplète et ne constitue pas le livrable.
- Contrôle des **3 709 images principales** : fichiers lisibles, formats/extensions cohérents, dimensions d’au moins 500 × 500, limites de taille vérifiées. Ce contrôle technique ne certifie pas les droits ni la correspondance visuelle de chacune des photos ; les photos par variante restent à vérifier.
- **3714 JSON source inchangés**. Seules trois projections publiques changent et l’ajout du code EPREL SUPRA modifie le registre énergétique ; les autres étiquettes/documents ne sont pas remplacés.
- Revue indépendante des preuves, identifiants et réserves ; tout reste `approved:false`. **Zéro offre déclarée approuvée ou envoyée à Google**. Le flux public et l’indexation locale restent fermés.
- Preuves privées : `storage/app/private/merchant-corrections-20261020/`. Les exports techniques sont aussi dans `output/merchant-catalogue-corrections-20261020-final/`.

## Sources Google reconsultées le 20 octobre 2026

[Spécifications produit](https://support.google.com/merchants/answer/7052112?hl=fr), [codes GTIN](https://support.google.com/merchants/answer/6324461?hl=fr), [certification EPREL](https://support.google.com/merchants/answer/13528839?hl=fr), [images et variantes](https://support.google.com/merchants/answer/6324350?hl=fr). La nouvelle exigence d’images 500 × 500 est annoncée au 31 janvier 2027 ; les images principales contrôlées l’atteignent déjà.

Les statuts du compte Google, les diagnostics réels de refus et l’exploration publique ne sont pas vérifiés par un audit local. Les règles conditionnelles par pays/produit et les données fournisseur devront être confirmées avant soumission.
