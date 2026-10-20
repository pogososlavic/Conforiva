# Recette ciblée de l’accueil — 19 octobre 2026

Périmètre : nouvelle présentation de l’accueil et photographies d’ambiance libres. Revue indépendante en lecture seule ; aucune mutation SQL, configuration privée ou donnée commerciale.

## Contrôles indépendants réalisés

- Code examiné : contrôleur d’accueil, configuration des rayons, vue, panneaux de conseil/crédits, traductions FR/EN/DE et bloc CSS éditorial.
- Vérification applicative en lecture seule des cinq rayons dans les trois langues : chaque référence configurée existe, possède une image et appartient effectivement au filtre de destination. Résultats : chauffage 3 086 modèles, mobilier 317, froid 165, café 8 ; lavage 46 en FR et 47 en EN/DE. La variation du lavage relève du catalogue localisé existant ; elle ne change pas la correspondance entre sa vignette et son rayon.
- Les images d’ambiance sont distinguées des modèles vendus. Les crédits affichent les trois sources Wikimedia Commons, auteurs et liens CC0 ; aucune nouvelle livraison, remise, disponibilité ou note client n’est promise.
- Les textes alternatifs sont localisés ; les vignettes de rayons sont décoratives dans des liens portant déjà un libellé. Les photographies secondaires sont chargées progressivement. Les nouveaux styles prévoient grille à une colonne, textes adaptatifs et focus visible.

## Preuves transmises par le pilote

- Build et Pint réussis ; 16 tests HomeMerchandising, HomeNavigation et StorefrontChrome, 3 954 assertions réussies.
- Trois photographies d’ambiance et cinq vignettes de produits vues chargées dans le navigateur local.
- Contrôles visuels bureau, tablette et mobile, dont 320, 390 et 768 px ; FR/EN/DE sans débordement horizontal observé. Captures conservées dans `output/qa-home-20261019/`.
- Rapport de réalisation : [Visuels libres et accueil](ACCUEIL-VISUELS-LIBRES-2026-10-19.md).

## Anomalies et limites

Aucune anomalie bloquante détectée dans ce périmètre. Le raccordement du contrôleur de catalogue à la nouvelle clé `product_slug`, repéré pendant l’intégration, a été corrigé par le pilote et couvert par sa suite.

La tentative HTTP indépendante via curl n’a pas abouti : connexion locale refusée dans le sandbox, puis demande d’accès interrompue. Aucun contrôle HTTP indépendant réussi n’est revendiqué ; les preuves navigateur ci-dessus proviennent du pilote. La revue CSS statique ne constitue pas un audit d’accessibilité complet. Les licences sont documentées dans le rapport médias ; cette recette n’est ni une revue juridique globale ni une validation de la vente, du checkout, des bases, de la production ou d’un service Google.

Le README historique `/Users/akm/Codex-Workspace/.codex/workflows/README.md` est absent de ce poste ; les instructions disponibles et le skill commerce-qa ont été lus.
