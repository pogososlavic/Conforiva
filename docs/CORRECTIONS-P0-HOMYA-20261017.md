# HOMYA — corrections P0 du 17 octobre 2026

Demande : corriger les points P0 de l'audit public, rechercher les informations fiables et vérifier sans lancer la campagne. Travail dans le dépôt HOMYA ; aucune migration, commande réelle, modification de compte Google ni mise en production effectuée par cette phase.

## Commande

- Bouton final FR : « Commander avec obligation de paiement » ; EN : « Place order with obligation to pay » ; DE : « Zahlungspflichtig bestellen ».
- Les CGV affichées précisent l'engagement correspondant au clic et distinguent le PDF récapitulatif d'une facture ou preuve d'encaissement. Les conditions de réception réelle des virements sont conservées.
- La projection des CGV participe au hash des conditions enregistré par le parcours existant ; les anciennes commandes ne sont pas réécrites.
- La commande n'a pas été soumise dans le navigateur. Les tests existants de commande/paiement s'exécutent dans SQLite en mémoire, sans base XAMPP ou production.
- Source : [DGCCRF — e-commerce et obligation de paiement](https://www.economie.gouv.fr/dgccrf/les-fiches-pratiques/e-commerce-les-regles-entre-professionnels-et-consommateurs), consultée le 17/10/2026.

## Étiquettes énergétiques

Composant Blade partagé utilisable sans JavaScript : flèche de classe avec échelle A–G, ouverture de l'étiquette complète, lien PDF et fiche d'information. Documents servis localement ; aucun chargement tiers n'est nécessaire. Affichage sur fiche, carte catalogue/accueil, panier et récapitulatif checkout. Les liens des cartes ne sont pas imbriqués.

Les correspondances sont contrôlées par slug ET référence ; les variantes ne reprennent pas automatiquement l'étiquette de leur parent. Si un document du modèle/langue manque, aucun lien de remplacement vers un autre modèle n'est affiché. Le registre public contient uniquement des documents confirmés.

| Modèle | Classe / EPREL | Documents vérifiés |
|---|---|---|
| Samsung RB33B610ESA | E / 1293184 | Étiquette officielle, fiches FR/EN/DE |
| LG F14R15WHS | A / 1673637 | Étiquette officielle, fiches FR/EN/DE |

Sources EPREL consultées/téléchargées le 17/10/2026 :

- [Samsung — étiquette](https://eprel.ec.europa.eu/labels/refrigeratingappliances2019/Label_1293184.pdf).
- [Samsung — fiche FR](https://eprel.ec.europa.eu/informationsheet/Fiche_1293184_FR.pdf), mêmes documents linguistiques avec suffixes EN et DE.
- [LG — étiquette](https://eprel.ec.europa.eu/labels/washingmachines2019/Label_1673637.pdf).
- [LG — fiche FR](https://eprel.ec.europa.eu/informationsheet/Fiche_1673637_FR.pdf), mêmes documents linguistiques avec suffixes EN et DE.
- [LG — page fabricant du modèle](https://www.lg.com/fr/lavage/lave-linge/f14r15whs-lave-linge-standards/) : documents du fabricant et modèle/EAN recoupés. Les fichiers EPREL ont été retenus, plutôt que le PDF fabricant comportant des repères d'impression.
- [Commission européenne — information énergétique des revendeurs](https://energy-efficient-products.ec.europa.eu/dealers_en).

Les huit PDF contiennent leur référence exacte, vérifiée par extraction de texte ; les deux étiquettes ont été rendues en PNG pour l'affichage. Les copies sources PDF restent intactes. Les classes proviennent des documents officiels, pas d'une déduction de catégorie.

**Limite : cette phase couvre les deux modèles énergétiques constatés dans l'audit P0. Elle ne clôture pas l'étiquetage de tous les autres appareils du catalogue.** Aucune nouvelle correspondance EPREL n'est inventée. La configuration et les diagnostics du compte Merchant ne sont pas modifiés ; les données énergétiques effectivement utilisées par Google restent à contrôler séparément.

## Fantea FANTEA32EN

Source primaire trouvée : [ETNA — Fantea](https://www.etnastufe.it/produit/fantea/), consultée le 17/10/2026. La marque décrit la Fantea comme chaudière à granulés de 32 kW, réservoir granulés 85 kg, eau 60 L, rendement 90,6 %, consommation 1,4 kg/h, poids 305 kg. Dimensions et désignation recoupent la référence fournisseur [Top Chaleur — FANTEA32EN](https://www.topchaleur.com/447-chaudiere-a-granules-fantea.html).

- Description « poêle à bois » remplacée par chaudière à granulés, dans les trois langues.
- Ligne « Réservoir eau (L) : Non » retirée ; capacité 60 L conservée comme valeur unique.
- Consommation de granulés corrigée de 1 à 1,4 kg/h ; rendement aligné sur 90,6 % publié par ETNA.
- Poids 305 kg déjà concordant conservé ; prix, stock, images, options et archive source inchangés.
- Aucun blocage de commande technique n'est laissé après confirmation des valeurs ; le contrôle provisoire préparé avant découverte de la source a été retiré.

Une revue locale modifie le contenu/fingerprint calculé de la référence ; elle ne vaut pas nouvelle approbation Merchant. Le mécanisme existant de revue des offres conserve ses contrôles.

Point commercial à documenter : ETNA annonce sur son site une distribution exclusive par Top Chaleur. Cette déclaration n'est pas une conclusion juridique sur la revente ; HOMYA doit faire confirmer son approvisionnement et les droits/conditions de commercialisation avec ses pièces fournisseurs avant la publicité. Aucun fournisseur n'a été contacté.

## Vérification

- 101 tests ciblés, 5 328 assertions : réussis (85 tests commerce/catalogue/énergie/Merchant, puis 16 tests d’informations légales).
- Test historique panier actualisé : il excluait le délai de livraison « 7 jours ouvrés » alors que le site annonce déjà « 3 à 7 jours ouvrés » ; l'assertion vérifie désormais cette promesse existante. Aucune promesse de livraison n'a été modifiée.
- Pint ciblé : réussi ; compilation Blade : réussie ; build Vite : réussi ; `git diff --check` : réussi.
- Navigateur local avec catalogue fichier, gestion privée et inventaire désactivés, GA4 désactivé ; aucun secret ou service de production utilisé pour la simulation.
- Contrôles visuels ordinateur 1440 px, mobile 390 px, petit écran 320 px ; aucun débordement horizontal observé sur les fiches contrôlées, étiquette Samsung ouverte comprise.
- Ouverture de l'étiquette LG complète et chargement de l'image officielle confirmés ; lien de fiche linguistique présent.
- Bouton final et montant lisibles sur mobile ; entrée dans le checkout vérifiée sans soumission finale.
- Projection Fantea FR/EN/DE : nouvelle description présente, 60 L présent, ancienne expression de poêle à bois absente.

Les captures et le registre de documents sont listés dans les preuves ci-dessous. Ces corrections ne certifient pas la conformité globale UE/Google ; les points P1/P2 de l'audit restent ouverts.

## Preuves locales

- `output/qa-p0/homya-p0-regression.json` : résultats des tests.
- `output/qa-p0/homya-energy-documents.json` : références et SHA-256 des PDF.
- `output/qa-p0/homya-p0-energy-desktop.png` : étiquette LG ouverte.
- `output/qa-p0/homya-p0-energy-mobile.png` : classe et liens près du prix.
- `output/qa-p0/homya-p0-checkout-mobile.png` : bouton et montant.

Les preuves générées restent locales et ignorées par Git. Les huit PDF et deux PNG publics sont explicitement inclus dans Git pour le futur déploiement ; les autres archives média restent ignorées.

## Ajustement mobile des boutons — demande complémentaire

Boutons d’achat réduits à 44 px, commande à 46 px ; quantité et ajout au panier alignés. Sur mobile, le bouton de commande affiche « Commander et payer » (EN « Order and pay », DE « Bestellen und bezahlen ») à côté du montant ; la formule longue reste sur ordinateur. Les CGV reconnaissent les deux libellés. Les états d’attente et de retour navigateur restaurent chacun des libellés. Aucun changement de paiement ou de commande. Contrôles à 320/390 px FR/EN/DE, sans débordement ; 39 tests commerce, 825 assertions, build et syntaxe JS réussis. Preuves locales : `output/qa-mobile-buttons/`. Non déployé par cette phase.

## Harmonisation visuelle complémentaire

« Nous écrire » remplace le texte WhatsApp FR, avec équivalents EN/DE et noms accessibles alignés. Boutons principaux et secondaires harmonisés : angles 9–10 px, bordures plus légères, ombres discrètes et focus clavier conservé. Action WhatsApp en vert contrasté. Commande mobile réduite à 44 px, libellé et montant en ligne. Contrôles 320/390/1440 px ; quatre tests contact et 254 assertions réussis, build et compilation Blade réussis. Aucune commande réelle, publication ou campagne lancée.
