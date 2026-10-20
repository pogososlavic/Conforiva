# Documents énergétiques — deuxième complément du 20 octobre 2026

## Résultat local

**134 références supplémentaires de chauffage** reçoivent les documents originaux correspondant aux modèles vérifiés : **26 dossiers utilisables pour l’affichage près du prix en français**, et **108 dossiers Aquaflam consultables avec une réserve visible**, sans flèche près du prix.

| Marque | Références ajoutées | Décision |
| --- | ---: | --- |
| Haas + Sohn | 20 | Étiquettes et fiches FR/EN/DE |
| Dovre | 4 | Étiquettes et fiches FR/EN ; bloc DE du document réservé |
| SUPRA | 1 | OSCAR, EPREL 1444805, FR/EN/DE |
| Panadero | 1 | 18315, OVAL 3 STONES 4, EPREL 2608326, FR/EN/DE |
| Aquaflam | 108 | Originaux FR/EN/DE avec avertissement ; affichage au prix désactivé |

Le registre atteint **904 produits / 909 étiquettes**, dont **585 dossiers utilisables près du prix en français**. Les **770 associations préexistantes sont conservées à l’identique**. Les **906 modèles** ayant au moins une caractéristique vérifiée représentent **4729 valeurs vérifiées** ; cette validation de valeurs ne vaut pas validation réglementaire de tout le dossier.

## Correspondances exactes et réserves

- **Aquaflam** : six familles (7, 12, 17, 25, VARIO 11/5, VARIO 11/7), 108 références exactes reliées par le fabricant aux documents. Les étiquettes autonomes indiquent correctement 2015/1186. Les fiches contiennent les véritables rubriques FR/EN/DE et une échelle A++–G correcte, mais l’illustration incluse porte la référence erronée « 2018/1186 ». Les originaux sont donc accessibles avec un message explicite dans chaque langue, `price_display:false`, sans présenter ces dossiers comme prêts pour l’affichage réglementaire près du prix. Il faut demander des fiches corrigées au fabricant. Aucun PDF n’est retouché. L’hypothèse initiale de mauvaise échelle dans la note de recherche est rectifiée par la revue indépendante : le défaut constaté est la référence réglementaire imprimée. Les rendements divergents entre page et fiche ne sont pas validés ; les ensembles KS, Vario air et Peru à classe contradictoire sont exclus.
- **Haas + Sohn** : références complètes et génération contrôlées, fiches réellement multilingues. Les rendements bornés « ≥75 % » ne sont pas transformés en valeurs exactes. AQUA, Tirol, Montegrotto III, Prunus II, Treviso II, Kuopio II et Pinus II Eck avec données ou générations divergentes restent exclus. Schmitzker demeure en attente faute de preuves concordantes.
- **Dovre** : 100 CBS (01.90108.021), 525 CB (01.93670.021), ASTRO 2/L (01.94395.021) et ASTRO 4CB/P (01.97046.021). Le tarif OEM historique relie explicitement la dernière référence à ASTRO 4CB/P ; le manuel ASTRO4CBP LEON confirme 8 kW / 140 kg / 76,4 %. La référence actuelle différente n’est pas substituée. Les fiches originales comportent des rubriques FR/EN correctes, mais le titre du bloc allemand imprime « 1185/2015 » : aucune association DE n’est créée. Ce bloc reste physiquement présent dans l’original multilingue ; aucune page n’est modifiée.
- **SUPRA OSCAR** : l’étiquette correcte vient d’EPREL 1444805 ; le téléchargement fabricant nommé « étiquette énergétique » contenait seulement une plaque CE et n’a pas été utilisé. Modèle, visuel, puissance 4,8 kW et rendement 77,6 % concordent. La référence 44294 est une référence catalogue vendeur. Le titre catalogue décrit la fonte alors que le fabricant décrit une structure acier avec pièces internes en fonte : cette réserve commerciale est tracée, sans modifier le titre dans cette intervention documentaire.
- **Panadero** : le fabricant relie explicitement le SKU 18315 à l’EPREL 2608326 « OVAL 3 STONES 4 » ; 8,7 kW / 80,7 % / 1048 × 605 × 490 mm concordent. Les anciennes références réutilisées avec puissances différentes ne sont pas assimilées aux générations actuelles. Les 16 Panadero déjà documentés sont préservés.
- **Godin** : aucun des dossiers examinés n’offre encore une paire étiquette/fiche suffisamment certaine pour les anciennes références vendues. Les fiches de versions voisines ou actuelles ne sont pas associées par analogie.

## Preuves et contrôles

Les originaux fabricant / EPREL sont inchangés, les extraits ZIP sont contrôlés par SHA-256, les langues réelles et les pages PDF vérifiées. Les aperçus proviennent des étiquettes originales, sans création de classe ni traduction de fiche. Les sources, décisions, sauvegardes et revue indépendante sont dans `storage/app/private/energy-documents-20261020-more/`. Le résultat final des tests, contrôles HTTP et navigateur est consigné dans `verification.json`.

[Suivi du catalogue](SUIVI-ENERGIE-COMPLEMENT-2-2026-10-20.csv) · [Chauffage restant à qualifier](ENERGIE-CHAUFFAGE-RESTANT-COMPLEMENT-2-2026-10-20.csv). Cette liste comprend accessoires, ensembles et cas à qualifier ; elle ne constitue pas un décompte juridique d’appareils assujettis.

Intervention locale, sans publication. Catalogue source, prix, stocks et variantes conservés. Les contradictions et documents manquants restent à résoudre ; aucun feu vert global de lancement n’est donné.

## Recette après installation

- **540 tests / 55 845 assertions réussis** ; ciblage énergie : **34 tests / 42 054 assertions**. Pint et vérification des espaces Git réussis.
- **Neuf pages vérifiées dans Chrome** : Aquaflam FR/EN/DE (originaux accessibles, avertissement traduit, aucune flèche au prix), Haas + Sohn FR/DE, Dovre FR/DE, SUPRA FR, Panadero FR. Ouverture de l’étiquette au clavier et aperçu chargé, réponses PDF HTTP 200, absence de débordement mobile sur le cas Aquaflam exercé.
- **3 714 fichiers JSON source identiques**. Les **770 associations préexistantes** restent identiques champ par champ.
- Captures relues : [Haas + Sohn bureau](../output/qa-energy-20261020-more/haas-desktop.png), [Dovre en allemand](../output/qa-energy-20261020-more/dovre-de-documents.png), [réserve Aquaflam mobile](../output/qa-energy-20261020-more/aquaflam-fr-mobile.png).

Les réserves documentaires précédentes demeurent. La recette de ces pages ne vaut pas validation des parcours de paiement, emails, stock ou de tous les lecteurs PDF natifs. Aucun CSS/JavaScript applicatif modifié ; pas de reconstruction d’assets nécessaire.
