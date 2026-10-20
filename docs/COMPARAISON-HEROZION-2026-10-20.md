# Comparaison Herozion — 20 octobre 2026

Scan local effectué, sans push, liveness-check ni achat. Aucun code applicatif modifié pendant cette vérification.

## Périmètre validé

Commande finale :

```sh
herozion scan . --offline --exclude vendor --exclude node_modules --exclude storage -o json
```

Les noms de dossiers simples sont nécessaires pour cette version de l’outil. Une fixture contenant un fichier dans app/ et un dans chacun des trois dossiers exclus renvoie exactement un fichier analysé. La première tentative avec des motifs `vendor/**`, `node_modules/**`, `storage/**` incluait encore des résultats sous storage/ : ses 852 alertes ne constituent donc pas le scan final demandé. Cette tentative est conservée dans output/security/herozion-project-glob-attempt-20261020.json. Aucun résultat n’a été modifié pour cacher des alertes.

## Résultats comparés

| Indicateur | Rapport initial fourni | Nouveau scan avec exclusions |
| --- | ---: | ---: |
| Herozion | 2.0.14 | 2.0.16 |
| Fichiers analysés | 3 395 | 559 |
| Score | 0/100 | 0/100 |
| Alertes totales | 834 | 78 |
| Critiques | 223 | 57 |
| Élevées | 353 | 10 |
| Moyennes | 257 | 11 |
| Information | 1 | 0 |
| Alertes détaillées accessibles | 5 | 5 |
| Alertes masquées | 829 | 73 |
| Durée | 634,42 s | 37,51 s |

La différence est de 756 signalements, soit environ 90,6 %. Elle ne mesure pas le nombre de failles corrigées : exclusions effectives, code courant et version du moteur diffèrent. La durée peut aussi bénéficier des caches. Le scan limité à app/ effectué auparavant (112 fichiers, 9 alertes) reste un troisième périmètre distinct.

## Qualification des cinq alertes visibles

| Chemin actuel | Signalement | Vérification actuelle |
| --- | --- | --- |
| app/Services/Admin/Messages.php:97 | Injection SQL | Faux positif : where/max/insert du Query Builder, aucune requête SQL interpolée. Mail::raw ne constitue pas un appel SQL. |
| resources/views/admin/documents/edit.blade.php:21 | Injection SQL | Faux positif : formulaire et rendu Blade avec config() ; aucune requête SQL. |
| scripts/install_admin_management.py:46–47 | Commande issue d’une réponse LLM | Non confirmé : listes d’arguments internes, shell=False, délai borné et environnement réduit. Aucun flux de réponse LLM identifié. |
| scripts/install_commerce_preview.py:33–35 | Même signalement | Non confirmé : arguments Git internes en liste et shell=False. Les gardes locaux et de chemins sont présents. |
| scripts/op06_xampp_admin.py:148 | Injection SQL | Aucun chemin injectable confirmé après renforcement : deux identifiants int stricts, positifs et distincts validés aux lignes 142–145 avant composition SQL. Clés de lot fichier validées hex64 avant interpolation ; noms de comptes/tables issus de constantes internes. |

Relecture indépendante de ces chemins et de leurs gardes effectuée en lecture seule. Les contrôles de session des conversations et les validations message/nonce ont aussi été relus. Aucune installation, requête SQL ni migration exécutée.

Les 73 alertes restantes ne sont pas détaillées dans l’export gratuit : **52 critiques, 10 élevées et 11 moyennes** par différence avec les cinq critiques visibles. Leur statut reste **inconnu/non vérifié**. Les compteurs ne permettent ni de les confirmer ni de les déclarer faux positifs. Leur qualification exhaustive exige un export détaillé ou leurs chemins, règles et extraits ; aucun accès payant n’a été contourné. Aucun correctif supplémentaire n’est justifié par les cinq cas accessibles.

Le mode offline ne constitue pas une nouvelle interrogation des bases de vulnérabilités des dépendances ; les audits Composer/npm sans vulnérabilité signalée plus tôt dans la journée restent des contrôles distincts. La dernière suite complète de tests conservée rapporte 621 tests réussis, 95 589 assertions ; le scan ne remplace pas une recette de production.

## Preuves

- Export final : output/security/herozion-project-excluded-20261020.json.
- Validation de la syntaxe d’exclusion : output/security/herozion-exclusion-fixture-20261020.json.
- Scan ID : ba1d0e3831ba40d8b04b0da249f77d15.
- Début UTC : 2026-10-20T20:35:41.871850+00:00.
- SHA256 de l’export final : 9c26a0b74aea1eada75dfbd76986e9f94d9deb154e0a989ffacb239dc7d71688.
- Revue et correctifs précédents : docs/AUDIT-HEROZION-2026-10-20.md.

Aucun déploiement effectué. Le score nul et les 73 alertes non détaillées ne permettent pas de déclarer l’ensemble du projet sûr ou prêt à publier.
