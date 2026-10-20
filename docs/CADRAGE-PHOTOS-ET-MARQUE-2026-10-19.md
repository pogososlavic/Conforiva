# HOME CONFORT et cadrages du catalogue — 19 octobre 2026

## Marque appliquée

HOME CONFORT remplace la marque HOMYA dans les pages FR/EN/DE, titres, descriptions, textes accessibles, administration, messages transactionnels, flux et nouveaux PDF. Logo partagé SVG et PNG PDF 720 × 240 RGB, licence conservée. Les anciens dictionnaires de traduction restent compatibles ; la marque se normalise après traduction. Consigne persistante dans AGENTS.md et docs/IDENTITE-HOME-CONFORT.md.

La raison sociale HOMYA confirmée, les coordonnées réelles (support@homya.eu), préfixes de documents historiques, identifiants techniques et archives sont conservés. Aucun nouveau domaine, email, stock ou prix inventé. Aucune base SQL modifiée, publication ou push effectué.

## Photos

Catalogue entier analysé : 3709 produits,18489 URLsource,12650 contenus uniques.1788 propositions de cadrage examinées : 1503 acceptées (2045 URL),285 écartées par prudence (schémas, étiquettes, textes, cas ambigus).912 propositions vues indépendamment par QA et 876 par le designer ayant conçu la détection ; cette seconde moitié ne constitue pas une seconde revue visuelle indépendante.

Cadrage d'affichage uniquement sur cartes et galeries, proportion uniforme, marge utile conservée ; aucune modification des pixels originaux ni réencodage. Plafond tenant compte de la définition native et de la densité d'écran :  les 1503 cadrages ne s'activent pas tous à toutes les tailles. Les deux congélateurs déjà traités gardent leur cadrage spécifique. Repli entier si métadonnées absentes, invalides ou périmées ; chaque photo de galerie possède son cadrage.

## Vérifications observées

- Build Vite : réussi ;3 tests JavaScript et 22 testsPHP/468 assertions de cadrage/présentation : réussis.
-63 tests ciblés marque/catalogue/admin/documents sous 128 Mo : réussis,1041 assertions. FacturesFR/EN/DE : rendu sous 128 Mo, pic 50 Mo. PNG PDF optimisé après le premier dépassement mémoire.
- Suite générale sous 512 Mo : 522 tests exécutés,512 réussis,10 erreurs de préparation hors modification : fixture op 06 manquante, dossiers de fixtures de schéma absents et chemin /opt/homebrew/bin/php absent. Ne pas qualifier la suite entière de réussie ; ces outils historiques restent à réparer séparément.
- Intégrité indépendante après application : 18489 URL/12650 SHA etJSONcatalogue inchangés ; manifeste conforme aux 1503 acceptations,0 erreur.
-22545 configurations géométriques vérifiées (1503 × 5 tailles× 3 densités) : 20399 cadrages actifs,2146 replis,0 déformation/troncature/dépassement natif.
- Navigateur réel : accueil et logo chargés ; fiche Fantea FR/EN/DE ; mobile 390 × 844 sans débordement ; passage photo 1 cadrée→schéma 4 entier→photo 1 cadrée réussi. Aperçus dans output/qa-catalogue-framing-brand-20261019. Le fichier product-desktop est une capture de contexte défilée ; homepage et product-mobile sont les preuves visuelles principales.
- git diff --check : réussi.

Périmètre local uniquement. Paiement réel, envoi d'emails, modifications SQL, déploiement et soumissions Google non exécutés.

## Retour arrière et preuves

storage/app/private/catalogue-framing-brand-20261019 contient les plans SHA, fichiers avant modification, preuvesQA, journaux de tests et copie finale des sources concernées. Archive privée HOME-CONFORT-catalogue-marque-20261019.tar.gz avec SHA 256 à côté. Aucun secret.env ni exportSQL ajouté.


## Mise à jour après correction des tests

Les 10 erreurs mentionnées dans le relevé ci-dessus sont désormais corrigées. Suite complète : 522 tests et 14 248 assertions réussis. Voir [le rapport de correction et du nouvel accueil](CORRECTION-TESTS-ET-ACCUEIL-2026-10-19.md).
