# Tests locaux et accueil — 19 octobre 2026

Les 10 erreurs précédemment signalées sont corrigées. Le titre complet « Bien chez vous, tout simplement », le paragraphe descriptif et la ligne « Chauffage · Électroménagers · Maison » sont jaunes (#f5c842). La photographie existante du salon est derrière le texte, en pleine largeur, avec voile sombre. Les boutons, crédits et autres sections sont conservés.

## Corrections des tests

- OP06 : référence statique permanente dans tests/Fixtures, indépendante de output/qa-import ignoré par Git. Neuf scénarios figés à partir des sources historiques du commit e41f563, provenance et SHA documentés. L’ancien export n’a pas été retrouvé ; le test ne recalcule jamais ses attendus. Une contre-épreuve avec clé corrompue échoue bien.
- OP05/OP08 : les huit scénarios de schéma créent leurs accès synthétiques dans une fixture temporaire complète, avec les sources revues copiées à l’identique. Garde réelle conservée, permissions 0700/0600, fermeture du socket et nettoyage de la fixture ; doubles sans PDO maintenus. Aucun répertoire réel d’accès ni base MariaDB modifié.
- OP10 : les sous-processus utilisent PHP_BINARY, sans dépendance à /opt/homebrew/bin/php ; le reçu absent utilise un nom temporaire unique.
- PHPUnit : limite de 512 Mo réservée à la suite, car plusieurs catalogues multilingues et rendus PDF sont chargés dans un même processus. Aucun changement de mémoire pour le site ou le PHP système.
- README : commandes adaptées au PHP 8.5 Herd Lite installé.

## Résultats observés

| Contrôle | Résultat |
|---|---|
| Tests ciblés des anciennes erreurs | 16 tests, 162 assertions, réussis |
| Suite complète Unit/Feature | 522 tests, 14 248 assertions, réussis |
| Tests accueil après titre/paragraphe jaunes | 10 tests, 2 492 assertions, réussis |
| Pint sur les quatre fichiers PHP modifiés | Réussi |
| Build Vite après dernier changement de couleur | Réussi |
| git diff --check | Réussi |
| Vue réelle bureau 1265 px, mobile 390 px | Photo chargée derrière le titre, jaune vérifié, aucun débordement |
| Vue FR/EN/DE à 320 px après passage en fond | Titres entiers, hauteur adaptée, aucun débordement |

La dernière modification de couleur de la ligne Chauffage/Électroménagers est purement CSS : build et contrôle visuel réalisés, sans nouvelle relance de la suite complète déjà passée. Aucune publication, opération SQL réelle ni envoi d’email effectué.

## Refaire les tests sur ce poste

Depuis /Applications/XAMPP/xamppfiles/htdocs/HOMECONFORT :

```sh
"$HOME/.config/herd-lite/bin/php" vendor/bin/phpunit
```

Les tests Unit/Feature restent isolés de MariaDB réelle. Les scripts de recette OP et la suite tests/MariaDB conservent leur procédure distincte.

Captures dans output/qa-tests-accueil-20261019 ; plans, fichiers avant modification et journaux complets dans storage/app/private/test-repairs-20261019. Les changements préexistants ont été préservés.
