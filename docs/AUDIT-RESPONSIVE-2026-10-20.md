# Audit responsive HOME CONFORT — 20 octobre 2026

## Périmètre et méthode

Audit local après les ajustements d’en-tête et de boutons. Revue du code public et administratif, contrôles dans le navigateur, mesures du DOM, captures et tests existants. Aucun déploiement ni envoi de formulaire commercial. Aucun changement de base.

60 affichages : 12 routes à 320, 375, 768, 1024 et 1440 px (hauteur 900 px). Routes : accueil, catalogue, promotions, fiche Fantea, panier, commande, suivi, déclaration de retour, assistance, à-propos, mentions légales et connexion administration. La route commande redirige vers le panier vide : elle ne valide donc pas l’écran de paiement rempli.

Compléments : huit affichages à 320 px pour les libellés FR/EN/DE ; cinq pages en paysage 844 × 390 ; menu allemand ouvert à 320 × 568. Mesures de débordement global et éléments sortant du cadre hors zones de défilement prévues ; contrôles ponctuels des libellés de boutons tronqués. Ces mesures ne remplacent pas une revue exhaustive de tous les états.

## Résultats et corrections

| Contrôle | Statut | Preuve / correction |
| --- | --- | --- |
| Débordement horizontal des 60 affichages | Conforme observé | Aucun débordement global ni élément sortant du cadre détecté hors zones de défilement locales |
| Libellés anglais/allemands sur petit écran | Conforme observé, échantillon | Huit affichages complémentaires sans débordement ni texte de bouton tronqué détecté |
| Paysage court | Conforme observé, échantillon | Cinq pages sans débordement ; en-tête public 112 px sur 390 px de hauteur |
| Menu allemand ouvert sur petit mobile | Conforme observé | En-tête limité à 568 px ; navigation interne 368 px pour un contenu de 557 px, donc défilable |
| Contrôles de quantité sur tablette tactile large | Défaut corrigé dans le code | Extension des dimensions 44 × 44 px aux périphériques `pointer:coarse`, même au-delà de 959 px ; périphérique tactile physique non exercé |
| Récapitulatif et rubriques facultatives du checkout | Défaut corrigé dans le code | Cibles restaurées à 44 px sur mobile/tactile ; écran rempli non accessible avec le panier vide |
| Liens de retour dans les formulaires | Défaut corrigé et mesuré | Minimum 44 px ; lien de retour de déclaration mesuré à 44 px dans le navigateur |
| Volets administratifs | Défaut corrigé dans le code | Résumés de volets et récapitulatif de document à 44 px sur mobile/tactile ; écrans authentifiés non observés |
| Champs des formulaires administratifs | Prévention appliquée | Police 16 px sur mobile/tactile pour éviter la réduction par héritage et le zoom automatique associé sur certains navigateurs |
| Compilation et tests | Conforme observé | Vite réussi ; 22 tests, 3 869 assertions ; `git diff --check` réussi |

Tests exécutés : StorefrontChromeTest, HomeNavigationTest, CatalogueUxRegressionTest, ResponsiveMediaTest et deux tests d’achat immédiat/variantes. Suite configurée pour le catalogue fichiers, sans migrations ni écriture sur la base du client.

## Limites et suite de recette

L’administration après connexion a fait l’objet d’une revue du code ; seule sa connexion a été observée dans le navigateur. Les tableaux, fiches de commande, documents et états privés avec données réelles restent à contrôler visuellement dans une session autorisée ouverte. Le panier rempli, le checkout, les confirmations et le virement ne sont pas validés visuellement par cet audit. Aucun paiement ou achat réel effectué.

Navigateurs physiques Safari/iOS et Android, zoom navigateur 200 %, clavier virtuel, lecteurs d’écran, tous les produits/variantes et tous les états d’erreur : non exercés. Les changements de couleur et l’identité jaune ont été conservés. Aucun engagement de conformité responsive exhaustive ou d’accessibilité complète.

## Preuves

Mesures détaillées : `/Users/akm/.codex/visualizations/2026/10/20/01a15a4b-39af-7312-9649-e79b1bcb8bfb/audit-responsive-mesures.json`.

Captures : `audit-responsive-produit-mobile.jpg` et `audit-responsive-catalogue-tablette.jpg`, dans le même dossier de visualisations.

Responsable des corrections et contrôles : Codex ; revue statique complémentaire par le rôle commerce_qa.
