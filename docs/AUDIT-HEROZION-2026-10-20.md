# Vérification ciblée Herozion — 20 octobre 2026

Périmètre : les cinq alertes détaillées du rapport fourni, recherche statique ciblée des secrets dans app/config/routes/resources/scripts et vérifications de dépendances. Ce contrôle ne valide pas les 829 alertes masquées ni une mise en production.

| Signalement | Résultat observé |
| --- | --- |
| Messages.php:97, SQL | Faux positif sur le code actuel : insert/where/max du Query Builder, sans SQL interpolé. Mail::raw envoie du texte, sans exécuter de SQL. |
| admin/documents/edit.blade.php:21, SQL | Faux positif : formulaire, configuration et affichage échappé ; aucune requête SQL. |
| install_admin_management.py:46 | subprocess.run avec liste d’arguments et shell=False déjà présent ; aucune réponse LLM identifiée sur ce chemin. |
| install_commerce_preview.py:33 | Même constat : commande Git sous forme de liste, shell=False. |
| op06_xampp_admin.py:147 | Identifiants issus des arguments CLI numériques. Renforcement : type int strict avant composition SQL ; clés de lot provenant du fichier de fixtures limitées à 64 caractères hexadécimaux minuscules avant insertion dans SQL. Le client mysql natif utilisé ici ne propose pas cursor.execute avec bindings. |

Secrets : aucun secret réel en dur identifié par la recherche ciblée. Correspondances observées : fixtures synthétiques, règles de validation et génération aléatoire. Aucun appel env() trouvé hors config dans le code applicatif examiné. .env est ignoré, non suivi et aucun commit n’est trouvé par git log --all --full-history -- .env. Son contenu n’a pas été affiché. Les champs secrets de .env.example sont vides ou utilisent null. Cela n’exclut pas une fuite sous un autre nom ou hors Git ; aucune rotation effectuée.

Le préparateur scripts/prepare_hostinger_release.py sélectionne explicitement les dossiers applicatifs : scripts/ n’est pas inclus dans l’archive d’application. Aucun déploiement exécuté.

## Vérifications

- Deux nouveaux tests Python Op06SqlInputTests passent : identifiants non entiers et clés de lot malformées rejetés sans SQL réel.
- 51 tests Laravel passent, 705 assertions : Op06PreparationTest, Op06ProtocolTest, AdminManagementTest, CommercialDocumentsTest, DeploymentPreparationTest et SecurityHardeningTest.
- Exécution élargie avec SecurityHeadersTest : 48 tests passent sur 49, un test en erreur et un statut risky. Le test test_source_failure_is_logged_without_exception_context_or_secret_disclosure attend un avertissement unique, appelé trois fois. Non modifié dans ce correctif.
- Suite Python historique OP-06 : 13 erreurs dues à l’absence de output/qa-import/op05-reprise-controle-independant.json ; les deux nouveaux tests passent indépendamment. Ne pas recréer une preuve d’exécution fictive pour satisfaire ces tests.
- composer audit : aucune advisory et aucun paquet abandonné signalés ; npm audit : zéro vulnérabilité. Vérifications réseau exécutées après autorisation.
- Nouveau scan Herozion hors ligne sur app/, avec exclusions explicites vendor/node_modules/storage : 112 fichiers, 9 alertes, score 37/100, 6 alertes détaillées. Résultat local : output/security/herozion-app-20261020.json. Les autres alertes du nouveau scan ne sont pas intégralement qualifiées. Ce périmètre diffère du rapport global ; aucun gain de sécurité chiffré n’en est déduit.

Référence : [Query Builder Laravel 13](https://laravel.com/framework/docs/13.x/queries), paramètres liés et restrictions sur les noms de colonnes.

Limite d’environnement : le fichier de workflow référencé /Users/akm/Codex-Workspace/.codex/workflows/README.md est absent. Les skills commerce-pilot, commerce-security et commerce-laravel disponibles ont été consultés. Aucune modification de service, migration, rotation, publication ou transmission du rapport au cloud.

Les modifications de ce correctif concernent uniquement scripts/op06_xampp_admin.py, scripts/test_op06_xampp_admin.py et le présent rapport ; les nombreux changements préexistants sont conservés.

## Suite de la qualification — 20 octobre 2026

Les six alertes détaillées sur neuf sont désormais qualifiées sur le code actuel. Les trois alertes masquées restent inconnues ; aucun contournement du verrouillage ni achat effectué.

| Alerte restante visible | Qualification et preuve |
| --- | --- |
| AdminManagementController:163, montant insuffisamment validé | Faux positif sécurité : regex sans signe négatif ; OrderStore::confirmBankReceipt rejette amount < 1 avant connection(), puis impose montant exact, EUR, statut et échéance dans la transaction. Trois tests unitaires ajoutés vérifient 0, -1 et PHP_INT_MIN sans conteneur applicatif ni base. |
| CheckoutController:182, BOLA | Faux positif : session propriétaire exigée, owned(id, ownerHash) avant find(document), puis document émis et lié à la commande exigé. Test test_order_snapshot_cannot_be_changed_and_client_download_is_owned : 1 test, 9 assertions réussis, dont accès d’un autre propriétaire et document non lié refusés. |
| OrderTrackingController:19, BOLA | Fonction publique intentionnelle sur UUIDv4 complet aléatoire ; seules reference/stage sont renvoyées, sans identité, paiement ni numéro transporteur. Throttle 10/min ; aucun droit d’accès à la commande accordé. |
| ReturnController:61 et :69, BOLA | Faux positifs : returns.access.<id> strictement true en session exigé avant find() pour récépissé et téléchargement. OrderTrackingTest et ReturnRequestsTest : 11 tests, 207 assertions réussis. |

Correction du test de journalisation : Catalogue journalise un signal fixe une seule fois par instance, mais les composants de page peuvent résoudre plusieurs instances. Le test HTTP exige désormais au moins un appel avec exactement le message fixe, sans contexte ni donnée supplémentaire. Le test séparé garde once() pour vérifier la déduplication par instance et la sûreté en cas d’échec du logger. Aucun changement de durée de vie du catalogue n’est conservé : une tentative de portée partagée a été retirée après détection de régressions dans les tests de changement de source et de médias entre requêtes.

Trois tests unitaires ajoutés pour les montants bancaires nuls et négatifs, sans conteneur ni base. Les tests de non-divulgation des sentinelles et les contrôles de propriété existants sont conservés.

Le test de panne des promotions utilisait la même hypothèse d’un appel unique à la source pour une page entière ; son attente est également remplacée par au moins un appel. Les assertions HTTP 503, absence de sentinelles privées et absence de repli catalogue sont conservées dans les trois langues.

Validation finale ciblée : 86 tests, 1 737 assertions, tous réussis (SecurityHeadersTest, BankReceiptAmountValidationTest, CatalogueTest, PromotionsTest, AdminManagementTest, CommercialDocumentsTest, OrderTrackingTest, ReturnRequestsTest). git diff --check ne signale aucune erreur sur les fichiers du correctif.

Suite complète hors bac à sable : 608 tests, 603 réussis, 92 865 assertions, 1 échec et 4 erreurs. Le processus avait chargé PromotionsTest avant sa correction finale ; ce test passe dans la vérification ciblée finale. Quatre autres défauts restent hors du correctif demandé : HomeNavigationTest::test_home_navigation_has_real_destinations_in_every_language_without_database (lien de crédit Wikimedia attendu absent), CataloguePresentationTest::test_source_failure_during_related_loading_returns_the_safe_unavailable_page, HomeMerchandisingTest::test_unavailable_source_exposes_no_private_failure_or_product_data et HomeMerchandisingTest::test_home_asks_for_one_shared_catalogue_projection (attentes d’un appel, trois observés). Le test de journalisation demandé est réussi ; la suite globale n’est pas déclarée verte. Aucun contrôle de production/MariaDB réelle déduit de ces résultats.

## Correction des quatre défauts restants

Le menu commun relisait deux fois les produits pour construire les catégories et marques, en plus du chargement du contrôleur. CategoryNavigation accepte maintenant une projection déjà chargée, partagée entre les deux listes. HomeController transmet sa projection à la vue puis au layout, avec une collection vide en cas d’indisponibilité ; la vue catalogue/unavailable fournit aussi une collection vide pour ne pas relancer une source défaillante. Les autres pages conservent leur menu dynamique avec une seule lecture pour les deux listes. Aucun cache global ni changement de durée de vie du catalogue. Les attentes exactes des trois tests en erreur sont conservées.

HomeNavigationTest autorise maintenant le lien du quatrième crédit photo (Cozy interior with a sofa), présent dans le composant et documenté dans ADVICE-IMAGE-CREDITS-2026-10-20.json. Aucun crédit ou média supprimé.

Premiers contrôles : 39 tests ciblés, 4 628 assertions réussis ; CategoryDropdownNavigationTest : 4 tests, 16 assertions réussis. Le nouveau test interdit toute relecture de Catalogue lors de la construction du menu à partir d’une projection fournie, y compris vide.

Validation finale de la version corrigée : **621 tests sur 621 réussis, 95 589 assertions**, durée 169,025 secondes. Suite complète exécutée hors bac à sable pour autoriser les sockets Unix synthétiques. Résultat : `output/security/phpunit-four-defects-20261020.json`. `git diff --check` est réussi sur les fichiers du correctif. Les quatre défauts demandés sont résolus ; les résultats antérieurs ci-dessus restent conservés comme étapes de diagnostic. Aucun déploiement ni migration de base réelle.

## Scan complet avec exclusions validées

559 fichiers, 78 alertes (57 critiques, 10 élevées, 11 moyennes), score 0/100. Cinq cas accessibles requalifiés sans faille exploitable confirmée ; 73 cas masqués restent inconnus. Comparaison, syntaxe d’exclusion vérifiée et preuves dans [COMPARAISON-HEROZION-2026-10-20.md](COMPARAISON-HEROZION-2026-10-20.md). La tentative précédente avec motifs glob ne respectait pas le périmètre demandé et reste conservée uniquement pour traçabilité.
