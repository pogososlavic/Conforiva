# Caractéristiques énergétiques — HOME CONFORT — 19 octobre 2026

Suite : [complément documentaire et bilan actualisé](ENERGIE-DOCUMENTS-COMPLEMENT-2026-10-19.md). Les nombres ci-dessous décrivent la première passe.

## Résultat local

- Catalogue examiné : **3,709 références**.
- Rubrique énergétique dans **2,285 fiches**, en français, anglais et allemand.
- **439 modèles** possèdent au moins une valeur confirmée par EPREL ou une documentation officielle du fabricant ; **2,640 valeurs confirmées**.
- **299 produits** disposent d’étiquettes et fiches locales téléchargeables : 235 références électroménager/TV et 64 chauffages, **304 étiquettes** avec les deux modules distincts des cinq WashTower.
- Profils : 232 entièrement vérifiés dans les valeurs affichées, 207 mêlant valeurs confirmées et récupérées, 1846 uniquement récupérés. « Entièrement vérifié » ne signifie pas que toutes les caractéristiques ou toutes les obligations documentaires sont renseignées.

Les valeurs récupérées sont identifiées sur le site. Aucune classe n’est calculée à partir du rendement, de la catégorie ou d’une image. Les valeurs HTML LG décalées, les références voisines et les générations de poêles contradictoires sont exclues des enrichissements officiels. Les anciennes données du catalogue demeurent intactes ; les lignes remplacées ne sont plus répétées dans les tableaux affichés. Une classe explicitement mentionnée dans la description de la fiche est corrigée seulement si un unique classement officiel de l’appareil est disponible. Les programmes lavage seul / lavage et séchage, SDR/HDR et modules lavage/séchage restent distincts.

## Limites précises

Cette intervention ne complète pas tous les dossiers fournisseur du catalogue. Les documents individuels manquent encore pour de nombreux chauffages ; **67 classes La Nordica** sont confirmées par le catalogue officiel, sans étiquette individuelle récupérée. **72 Aquaflam Vario** ont une classe confirmée mais leurs documents et versions présentent des divergences de rendement : ils ne reçoivent pas de nouvelle flèche documentaire.

La liste chauffage contient **1458 références candidates sans classe récupérée ou confirmée**, à qualifier selon le modèle, la puissance et le contenu des ensembles. Ce nombre inclut des kits/accessoires à trier et ne constitue pas un décompte juridique définitif des appareils assujettis.

Électroménager/TV : **11 références** restent sans dossier documentaire exact installé : ADN203/1BP, B7RCNE367HG, HCR38S18ENPT, KGE49AICA-1, RB38C7B5DS9/EF, WHK25404XP5E, WHK26362XP5E, WHK26412X5EE, F954B15WHS, GFM61MCCSD.GLM71MC, GTF8659PS. Le pack réfrigérateur/congélateur nécessite les documents identifiés de ses deux composants ; aucune classe unique n’est inventée. Les suffixes B7RCNE367HG et WHK26412X5EE ne sont pas assimilés sans preuve fabricant supplémentaire.

Les accessoires, conduits, combustibles, meubles et machines à café ne reçoivent pas automatiquement une étiquette d’appareil. Les cheminées au bioéthanol, réservoirs et ensembles génériques nécessitent une qualification spécifique.

- [Suivi des 3 709 références](SUIVI-ENERGIE-2026-10-19.csv).
- [Documents et qualifications restant à compléter](ENERGIE-DOCUMENTS-A-COMPLETER-2026-10-19.csv).
- Preuves et sauvegardes : `storage/app/private/energy-update-20261019/` ; aucun accès SQL/migration, publication ou soumission Google réalisé dans cette intervention.

## Sources

Les sources du modèle sont liées sur chaque fiche enrichie. Étiquettes et fiches EPREL : API publique, documents d’origine conservés avec empreinte SHA-256 et identité du modèle vérifiée. Trois correspondances commerciales explicites sont prouvées par les fabricants (deux Beko avec numéro SAP et un Samsung avec suffixe commercial).

Règles de périmètre vérifiées : [informations aux revendeurs](https://energy-efficient-products.ec.europa.eu/dealers_en), [appareils de chauffage localisés](https://energy-efficient-products.ec.europa.eu/product-list/local-space-heaters_en), [chaudières à combustible solide](https://energy-efficient-products.ec.europa.eu/product-list/solid-fuel-boilers_en), [sèche-linge](https://energy-efficient-products.ec.europa.eu/product-list/tumble-dryers_en). Nouvelle échelle A–G des sèche-linge depuis juillet 2025 ; aucune conversion d’ancienne classe A++.

## Vérification

Audit données final : 2285 profils, 2640 valeurs officielles, 240 enregistrements EPREL : aucune erreur relevée par le contrôleur de correspondance. Vérification indépendante du texte du modèle sur les 720 fiches EPREL (trois langues, 240 enregistrements) : aucune divergence. Validation service : 912 étiquettes localisées dans les trois langues et gardes références/variantes.

Les résultats définitifs des tests et contrôles visuels sont conservés dans le fichier `final-checks.json` du dossier de preuves.

Résultats définitifs : **534 tests, 23 118 assertions réussis**, Pint réussi, compilation Vite réussie, contrôle de diff sans erreur. Les 3 714 JSON sources du catalogue sont inchangés (dont 3 709 fiches produit). Contrôles visuels sur ordinateur et mobile dans les trois langues ; ouverture au clavier de l’étiquette validée. Le téléchargement PDF vérifié répond HTTP 200 / application/pdf. L’aperçu PDF intégré à Codex est resté vide ; les originaux sont conservés et leur contenu a été contrôlé par extraction/rendu local.

Captures : `output/qa-energy-20261019/energy-fr-desktop.png` et `energy-washtower-mobile.png`. Bilan structuré : `storage/app/private/energy-update-20261019/final-verification.json`.
