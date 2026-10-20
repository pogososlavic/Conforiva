# Vecteurs de référence OP-06

`op06-offline-programme-v1.json` est une référence statique destinée à `Op06PreparationTest`. Les neuf scénarios et leurs clés de lot proviennent du programme OP-06 documenté dans `docs/OP-06-RECETTE-XAMPP-PREPARATION.md`, section 5.

L’ancien export `output/qa-import/op06-fixtures-proposees.json` n’a pas été récupéré après la réinitialisation. Le dossier `output/` est ignoré par Git ; ce fichier ne doit donc plus être une dépendance de la suite de tests.

Les valeurs ont été reconstruites le 19 octobre 2026, une seule fois hors PHPUnit, à partir d’un export Git du commit immuable `e41f563e157939cb2bc5c9c74ab59d3dd9a27ec0` du 11 octobre 2026. Ce sont les sources historiques `CatalogueArchiveFixture`, `Op06Recipe`, `CatalogueArchivePayload`, `CatalogueCapsule` et `StrictJson`, dont les SHA-256 figurent dans le JSON. Aucun code actuel du checkout n’a fourni les résultats attendus. Les neuf payloads utilisent le marqueur `OP06-v01-<scenario>` et contiennent chacun un lot, une catégorie, un produit fictif et six traductions.

Le test charge ces valeurs figées puis les compare au programme actuel. Il ne régénère pas les résultats attendus et vérifie la couverture exacte des neuf scénarios. Un changement de sérialisation ou de contenu de fixture doit provoquer un échec à examiner ; ne pas réécrire automatiquement ce JSON pour faire passer le test.

Cette reconstruction ne prétend pas restituer les octets de l’ancien export ni prouver une exécution MariaDB historique. Aucun accès, boot Laravel ou SQL n’est nécessaire pour ce contrôle unitaire. Les scénarios SQL et leurs autorisations demeurent distincts.
