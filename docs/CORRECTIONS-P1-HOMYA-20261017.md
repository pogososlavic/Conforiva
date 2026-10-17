# HOMYA — corrections P1 du 17 octobre 2026

Périmètre : corrections locales autorisées après l’audit UE / Merchant. Aucune publication, migration XAMPP, modification du compte Google ou campagne publicitaire. Les sources et pièces non contrôlées restent explicitement ouvertes.

## Changements réalisés

### C11 — facturation distincte

- Adresse de livraison conservée pour le transport et le calcul fiscal : France, Belgique ou Allemagne.
- Facturation différente facultative : nom/raison sociale, adresse, code postal facultatif (certains pays n’en utilisent pas), ville, code pays à deux lettres. Le pays de facturation n’est pas limité aux trois destinations.
- Facturation copiée depuis la livraison si aucune adresse différente n’est sélectionnée ; ancienne commande compatible sans nouveau champ.
- Instantané enregistré dans les données client déjà chiffrées ; aucune colonne ni migration nécessaire. Le PDF récapitulatif et les futurs documents commerciaux reprennent la facturation ; les documents conservent séparément la livraison. Les documents historiques restent inchangés.
- Barre d’adresse avec suggestions IGN pour la France, comme pour la livraison. Les deux recherches utilisent leurs propres champs et listes accessibles ; choisir une suggestion de facturation ne modifie pas la livraison. La saisie manuelle reste possible et les autres pays ne sont pas envoyés à IGN.
- Le parcours complet encaissement / remboursement en production et l’acceptation Google ne sont pas démontrés par ces contrôles.

Source : [Google — checkout incomplet](https://support.google.com/merchants/answer/10249082?hl=en), consultée le 17/10/2026. Google indique de ne pas limiter le pays de facturation aux destinations livrées.

### C06 — notice harmonisée de garantie

- Fichiers SVG couleur officiels FR, EN, DE, issus de l’archive Commission, copiés sans changement dans `public/legal/guarantees/`.
- Notice visible avant le footer commun, donc sur accueil, catalogue, fiches et checkout. Affichage proportionnel, ouverture du fichier intégral en grand, aucun tiers chargé pour l’image.
- Le mot GARAN imprimé dans la notice officielle explique le mécanisme ; aucun label commercial GARAN n’est attribué aux produits HOMYA.
- La notice ne remplace pas l’encadré national français des CGV ni la vérification des garanties commerciales effectives. Cette partie de C06 reste à contrôler.

Sources : [Commission — fichiers officiels et guide](https://commission.europa.eu/publications/practical-guidelines-and-high-resolution-vector-files-eu-notice-and-label-product-guarantees_en), [Your Europe — notice et garanties](https://europa.eu/youreurope/business/selling-in-eu/consumer-contracts-guarantees/eu-legal-guarantee-notice-and-garan-label/index_fr.htm), consultées le 17/10/2026. Archive SVG : `https://commission.europa.eu/document/download/27c45f1f-78a1-47a7-a7cc-adf23afee5ea_en?filename=SVG.zip`.

### C04 — informations Analytics et cookies, correction partielle

- Informations FR/EN/DE : identifiants, pages publiques, identifiants de clic Google Ads après accord et achats réellement encaissés ; destinataire Google ; absence de nom/e-mail/RIB dans les événements.
- Inventaire visible des cookies `homya_analytics_choice`, `_ga` et `_ga_<flux>` ; durée technique de 180 jours distincte de la conservation des événements GA4.
- Durée Analytics configurée sans renouvellement automatique à chaque visite (`cookie_update: false`). Les choix de consentement conservent leur propre expiration de 180 jours.
- Hostinger et Google sont nommés ; les limites concernant contrats, sous-traitants, transferts et réglage GA4 réel sont conservées. La collecte européenne n’est pas présentée comme une garantie d’absence de transfert.
- Tests de refus, retrait, absence de script avant accord et arrêt des événements serveur si l’accord n’est plus valable. Aucun tag ou achat réel envoyé pendant la recette.
- La conservation des commandes, messages et pièces, l’effacement effectif et les contrats de sous-traitance restent à documenter. Aucune suppression de dossier client n’a été exécutée.

Sources : [CNIL — mise en conformité cookies](https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/comment-mettre-mon-site-web-en-conformite), [Google — cookies GA4](https://support.google.com/analytics/answer/11397207?hl=en), [Google — conservation des données](https://support.google.com/analytics/answer/7667196), consultées le 17/10/2026. Les contrôles techniques locaux ne valent pas une vérification des réglages du compte GA4.

### C05 — paiement et périmètre, correction partielle

- Page paiement nomme le virement lorsqu’il est disponible, indique les instructions après enregistrement, distingue preuve de virement et fonds réellement reçus, et précise la préparation après confirmation.
- CGV : remplacement de la formule relative aux commandes « locales » par le fonctionnement réel du parcours lorsque les commandes sont ouvertes.
- Les modalités non connues de déchargement / installation / reprise et de règlement des litiges allemands ne sont pas inventées. C05 reste ouvert pour ces sujets.

## Prix et remises — instruction utilisateur

L’utilisateur confirme les prix et demande expressément de conserver toutes les réductions, prix barrés et pourcentages. Il ne souhaite pas fournir son historique confidentiel. Aucune modification de la politique de prix, des promotions ou de leur affichage dans cette phase. Aucun historique n’est demandé de nouveau ni copié dans le dépôt. C08 reste « justification non vérifiée » ; le refus de transmettre une pièce ne prouve ni sa présence ni son absence. HOMYA conserve elle-même les justificatifs nécessaires.

## Sujets qui nécessitent encore des faits opérationnels

| Point | Reste à obtenir / vérifier | Action concrète de HOMYA |
|---|---|---|
| C04 | Conservation effective GA4 et dossiers, contrats Google/Hostinger, lieux/transferts | Contrôler les réglages GA4, définir et appliquer un calendrier de conservation et les règles de sauvegarde ; archiver les contrats et garanties applicables. |
| C05 | Accès, déchargement, installation, palettes et litiges DE | Confirmer avec le transporteur les prestations réellement incluses, exclusions et coûts ; faire vérifier la déclaration VSBG avant publication. |
| C06 | Encadré français des CGV | Contrôler la version réglementaire applicable et intégrer le texte exact ; la notice UE n’en dispense pas. |
| C07 | Reprise DEEE et filières REP, France/Belgique/Allemagne | Organiser la reprise gratuite applicable avec un opérateur et publier le mode de reprise avant achat ; vérifier éco-contributions/identifiants et rôle de HOMYA par marché. |
| C08 | Preuve du prix de référence | Conservation confidentielle chez HOMYA ; aucune conclusion de conformité sur les prix barrés. |
| C09 | Données modèle exact, fabricant/responsable UE, avertissements | Obtenir du fournisseur coordonnées postales/électroniques, rôles, notices et avertissements FR/DE ; contrôler référence/GTIN avant mise en ligne. Une adresse de siège générale ne démontre pas le responsable du modèle. |
| C10 | Identité, médiation, stocks, sourcing, promesses logistiques | Conserver inscription/RNE, convention CM2C, factures fournisseurs et preuves de livraison/retours/origine. Aucun tiers n’a été contacté. |
| C11 | Cycle réel commande/remboursement et contrôle Merchant | Recette contrôlée avec HOMYA puis diagnostics Merchant ; aucun paiement ou remboursement réel simulé dans les comptes. |

Référence C07 vérifiée : [Service public — DEEE](https://www.service-public.gouv.fr/particuliers/vosdroits/F31955), consultée le 17/10/2026. Une mention générale ou une invitation à contacter HOMYA ne remplace pas l’organisation effective de la reprise. Aucun contrat logistique ou service de reprise n’est créé par les modifications locales.

## Vérification

- 103 tests PHP ciblés / 5 041 assertions réussis : commerce, informations légales, achats encaissés, documents commerciaux, sécurité des e-mails de devis, prix catalogue et promotions.
- 7 tests JavaScript réussis : consentement / attribution et séparation des suggestions livraison / facturation.
- Fixture d’authentification des tests documents actualisée avec l’identité du propriétaire synthétique : deux tests utilisaient une ancienne session sans nom d’utilisateur. Aucun mécanisme d’authentification applicatif modifié.
- Vite, syntaxe JS et compilation Blade réussis ; contrôle des espaces Git réalisé.
- Navigateur local : ouverture de la facturation distincte, saisie d’un pays externe, deux barres indépendantes, notice officielle chargée. Contrôles de largeur à 320 et 1440 px sans dépassement horizontal ; facture non soumise, aucun appel réel IGN nécessaire à la recette.
- Captures locales : `output/qa-p1/billing-mobile.png`, `billing-320.png`, `guarantee-desktop.png`.
- Aucun contrôle de production ni certification globale de conformité UE/Google revendiqué. Les P1 sont partiellement traités ; les dépendances ci-dessus restent ouvertes.

## Phase complémentaire — reprise et informations fabricant

L’utilisateur a confirmé « Oui, reprise lors de la livraison ». Cette confirmation permet de publier les modalités du service et de proposer sa demande dans la commande. Elle n’est pas un audit du transporteur, de ses contrats ou des filières REP.

### C07 — reprise à la livraison, parcours local implémenté

- Information FR/EN/DE sur la fiche des produits électroménagers et téléviseurs, la page Livraison (`#reprise`), les CGV et le checkout.
- Reprise gratuite, un ancien appareil de type et fonction équivalents par appareil acheté, sans restriction de marque ; service annoncé sur les trois destinations de HOMYA. Sélection facultative de la quantité par ligne. Le défaut de sélection ne supprime pas les droits applicables : coordination possible avec HOMYA avant la livraison.
- Consignes de préparation et signalement des difficultés d’accès ; aucune prestation de démontage professionnel ou d’installation inventée.
- Le serveur refuse un article absent, une ligne non éligible, une quantité négative ou supérieure à celle achetée. Aucun montant commercial changé et aucun supplément.
- Demande et texte de politique conservés dans l’instantané client chiffré existant. Visibles sur la confirmation et dans l’administration ; repris dans le PDF récapitulatif et les conditions de livraison des futurs documents. Les anciennes commandes restent compatibles. Aucune migration requise.
- Les CGV adaptées retirent la formulation générale selon laquelle la reprise n’est pas incluse. L’installation et la mise en service restent distinguées de la reprise.
- Le formulaire couvre 254 références de l’archive, classées électroménager/téléviseurs. Les 3 086 références de chauffage ne sont pas automatiquement classées DEEE : l’éligibilité des modèles électriques et les règles du mobilier restent à vérifier. Le texte préserve les droits applicables et oriente vers HOMYA pour les autres catégories.
- Pour clôturer C07 intégralement : vérifier les opérateurs et justificatifs des filières REP dans chaque pays, les éco-contributions et inscriptions applicables, et réaliser une reprise réelle documentée. La déclaration du marchand ne constitue pas cette preuve.

Source : [Service public — DEEE](https://www.service-public.gouv.fr/particuliers/vosdroits/F31955), consultée le 17/10/2026. La portée nationale FR de cette source n’est pas transformée en certification des régimes BE/DE. La couverture commerciale des trois destinations résulte de la politique HOMYA publiée, selon la confirmation de reprise reçue.

### C09 — documents et contacts officiels, couverture partielle

Les informations sont ajoutées dans une section dédiée de la fiche, avec accès depuis la navigation de la fiche. Le registre `config/product-safety.php` exige le slug et la référence exacts ; les variantes ne reprennent pas les coordonnées du parent automatiquement. Aucun nom, adresse ou responsable réglementaire n’est créé par déduction de marque.

| Modèle | Informations vérifiées / affichées | Limite conservée |
|---|---|---|
| LG F14R15WHS | Contact EPREL « EU representative of LG Electronics Inc. », Alfred Herrhausen Allee 3–5, 65760 Eschborn, Allemagne, `eprelpublic@lge.com` ; assistance du modèle ; avertissements et PDF de sécurité liés par LG à ce modèle | Le contact EPREL ne démontre pas à lui seul tous les rôles et obligations sectorielles/GPSR. Nom légal/adresse complets du fabricant et autres pièces doivent être confirmés. |
| Samsung RB33B610ESA | Contact EPREL « Public Contact », PO Box 12987, Dublin, Irlande, `eprel@samsung.com` ; assistance et notices du modèle | Cette boîte postale est présentée comme contact déclaré EPREL ; elle n’est pas étiquetée fabricant, importateur ou personne responsable GPSR. Notices/avertissements précis et rôles restent à compléter. |
| ETNA Fantea FANTEA32EN | Accès à la fiche et au contact officiels ETNA | Les coordonnées légales du fabricant/responsable et la notice du modèle ne sont pas confirmées. La preuve d’approvisionnement reste nécessaire. Aucun tiers contacté. |

Sources exactes contrôlées le 17/10/2026 :

- [LG — fiche modèle et documents de sécurité](https://www.lg.com/fr/lavage/lave-linge/f14r15whs-lave-linge-standards/) ; [assistance du modèle](https://www.lg.com/fr/support/support-produit/cs-F14R15WHS.ABWQPFS).
- [EPREL LG](https://eprel.ec.europa.eu/qr/1673637), données publiques `https://eprel.ec.europa.eu/api/products/washingmachines2019/1673637`, identifiant `F14R15WHS` et coordonnées recoupés.
- [EPREL Samsung](https://eprel.ec.europa.eu/qr/1293184), données publiques `https://eprel.ec.europa.eu/api/products/refrigeratingappliances2019/1293184`, identifiant `RB33B610ESA` recoupé ; [assistance Samsung](https://www.samsung.com/fr/support/model/RB33B610ESA/EF/).
- [ETNA — Fantea](https://www.etnastufe.it/produit/fantea/), [contact ETNA](https://www.etnastufe.it/contact/).
- [GPSR, article 19, texte initial](https://eur-lex.europa.eu/eli/reg/2023/988/oj/eng). Le lien vers la consolidation du 29/05/2026 a été identifié mais sa lecture web a été bloquée par une vérification anti-robot dans cette phase ; l’analyse de la version actualisée reste à compléter.

Le PDF LG (222 pages, langues multiples) est fourni intact dans `public/legal/safety/lg-f14r15whs-safety.pdf`. Il est lié au modèle par la page officielle mais comporte des consignes pour plusieurs familles d’appareils : seules les consignes générales pertinentes sont résumées sur la fiche ; aucun avertissement propre au sèche-linge ou à son réfrigérant n’est attribué au lave-linge. Empreinte SHA-256 : `c77709df45747bf7f71cde2a9989d812dc606d3b521aa8dff50954138a946dc4`.

Le catalogue comporte 3 709 références ; seulement trois modèles ont un supplément officiel contrôlé dans cette phase. C09 ne peut pas être déclaré clos pour tout le catalogue. Prochaine étape : pièces fournisseurs avec modèle/GTIN, identité du fabricant, coordonnées postales/électroniques et rôle du responsable UE lorsque requis, notice et avertissements dans les langues pertinentes, puis ajout au registre modèle par modèle. Aucun prix ou rabais modifié.

### Vérifications de cette phase

- 107 tests PHP / 5 108 assertions réussis : commerce, informations légales, sécurité produit, achats encaissés, documents, sécurité des envois de devis et promotions.
- 7 tests JS réussis : Analytics et recherche d’adresse. Vite, Pint, compilation Blade et contrôle Git réussis.
- Navigateur : choix d’une reprise sur le checkout LG, montant restant à 485,17 €, sans soumission ; section fabricant LG lisible et liens présents ; contrôles 320/1440 px sans débordement horizontal de la fiche. Aucun appel vers une adresse e-mail, envoi de message ou demande de reprise réelle.
- Preuves : `output/qa-p1/take-back-and-manufacturer-evidence.json`, `take-back-checkout.png`, `lg-manufacturer-mobile.png`, `lg-manufacturer-320.png`, `lg-manufacturer-desktop.png`.
- Livraison/collecte réelles, dossiers fournisseurs, réglages Google et compte Merchant non contrôlés par cette phase. Aucune publication ni campagne lancée. Les autres dépendances P1 du tableau précédent restent ouvertes.

## Révision responsive du bouton de commande

Demande complémentaire : bouton final plus compact et libellé sur la même ligne que le montant. Styles partagés : hauteur 40 px sur mobile et 42 px ailleurs ; libellé/prix alignés horizontalement, montant sans compression. Le récapitulatif utilise une requête de conteneur : dans un espace de 360 px ou moins, la mention courte « Commander et payer » (EN/DE équivalents) évite le chevauchement constaté avec la formule longue à 1024 px. La formule complète reste affichée lorsque le récapitulatif offre assez de place. Les états de soumission existants mettent toujours à jour les deux libellés.

Contrôles navigateur locaux : six pages (accueil, catalogue, fiche LG, panier, commande, livraison) à 320/768/1440 px, sans débordement horizontal des éléments principaux ; bouton de commande FR/EN/DE à 320/768/1024/1440 px, sans collision libellé/prix et sans débordement interne. Contrôle initial FR également à 390 px ; capture finale à 390 px. Menu mobile ouvert et fermé, récapitulatif vérifié visuellement sur mobile/tablette/ordinateur. Aucun contrôle exhaustif de toutes les références ou d’autres navigateurs revendiqué.

Vite et `git diff --check` réussis. Aucune soumission de commande, aucun changement de prix/remise, aucun déploiement ou lancement. Preuves : `output/qa-p1/responsive-final.json`, `checkout-mobile-compact.png`, `checkout-tablet-compact.png`, `checkout-desktop-compact.png`.

## Harmonisation des cartes catalogue

Cartes locales : médias compacts de hauteur régulière, espacements resserrés, typographie et actions harmonisées. Grille de lignes partagées (subgrid lorsque pris en charge) pour aligner prix et boutons malgré les titres de longueurs différentes ; présentation flex conservée comme repli. Grille à deux colonnes sur tablette, une colonne sur les mobiles étroits selon le seuil existant, deux au-dessus de 419 px et trois sur ordinateur. Tous les noms restent lisibles, sans troncature ; prix, prix barrés et badges de réduction inchangés. Le composant énergétique est conservé dans un conteneur distinct ; sa marge latérale ne double pas celle de la carte.

31 tests catalogue/UX/prix/énergie, 1 027 assertions, réussis ; Vite, Blade et contrôle Git réussis. Contrôles navigateur à 320/390/430/600/768/1440 px : aucun débordement horizontal observé sur les pages contrôlées, prix et boutons à la même hauteur dans les rangées mesurées. Badges −5/−15 % et prix barrés visibles ; carte Samsung RB33B610ESA et lien de fiche énergétique présents dans le catalogue filtré. Aucune commande réelle soumise ; prix et remises conservés, non déployé.

Preuves locales : `output/qa-p1/product-cards-desktop.png`, `product-cards-mobile.png`.

### Pagination du catalogue — ajustement visuel

Pagination compacte : une seule liste accessible, page active contrastée, flèches et numéros alignés, première et dernière pages conservées avec un voisin de chaque côté. Les libellés précédent/suivant restent accessibles sur mobile. Champ d’accès direct et bouton Aller de même hauteur. Contrôle local de la page 20 à 320, 390, 768 et 1440 px : une ligne de navigation et aucun débordement horizontal. Les tests catalogue et régression UX passent (25 tests, 555 assertions) ; compilation Vite et compilation Blade réussies. Prix et réductions conservés. Aucune publication ni campagne lancée. Capture : output/qa-p1/pagination-mobile.png.

### Contrôle global responsive du catalogue

Contrôle local dans le navigateur intégré : en-tête, fil d’Ariane, introduction, catégories, filtres ouverts/fermés, résultats, cartes, pagination, garantie et pied de page. Espacements entre sections harmonisés et réduits ; titre des résultats et compteur centrés verticalement, marges resserrées. Styles limités à la page catalogue.

À 320, 390, 430, 768, 1024 et 1440 px : aucun débordement horizontal et boutons alignés par rangée sur les 24 cartes. Information d’achat ouverte à 430 px : pas de débordement et boutons voisins alignés. Filtrage Samsung et tri croissant : 23 résultats, étiquette énergétique conservée ; réinitialisation et état sans résultat vérifiés. Les textes de prix et badges de la première page sont identiques avant/après. Tests : 31 réussis, 1017 assertions (CatalogueTest, CatalogueUxRegressionTest, CataloguePromotionPricingTest, ProductEnergyLabelsTest). Build Vite réussi. Captures catalogue-final-mobile.png, catalogue-final-tablet.png et catalogue-final-desktop.png dans output/qa-p1. Aucun déploiement ; contrôle multi-navigateurs Safari/Firefox non réalisé.

### Fiches produits — alignements et responsive

Styles limités à .product-page : navigation de sections compacte, galerie et miniatures dimensionnées, résumé et informations d’achat resserrés, contrôles quantité/ajout/achat de 40 px, sections techniques espacées régulièrement. Les fiches passent en une colonne sous 960 px afin de rendre les informations lisibles sur tablette ; deux colonnes conservées sur ordinateur. Aucun contenu commercial, prix, remise ni logique de panier modifié.

Contrôles locaux : Ensol KS3910 à 320, 390, 768, 1024 et 1440 px sans débordement ; prix et badge identiques avant/après ; galerie passée à image 2/6 ; quantité passée de 1 à 2 puis revenue à 1. Samsung RB33B610ESA à 320, 768, 1024 et 1440 px : étiquette E, fiche produit, informations fabricant et reprise conservées. Palazzetti Ecofire Ginger 9 à 320 et 768 px : sélection Acier noir et largeur du champ vérifiées. Rubrique informations d’achat ouverte/fermée, description et caractéristiques contrôlées. Tests catalogue/UX/prix/énergie/sécurité produit/commerce : 77 réussis, 1925 assertions. Build Vite et git diff --check réussis. Captures product-final-desktop.png et product-final-mobile.png dans output/qa-p1. Vérification par échantillon dans le navigateur intégré, pas une inspection de chaque référence ni une vérification Safari/Firefox. Aucun déploiement, achat réel ou lancement publicitaire.

### Panier et commande — alignement et espacements

Styles limités à cart-page/checkout-page : titres et sections resserrés, contrôles quantité/Actualiser de 40 px, prix et énergie sous les informations produit pour conserver une largeur utile sur tablette. Le nom produit de la rubrique achat reste accessible mais est masqué visuellement pour éviter un bouton démesuré. Champs de commande uniformes à 44 px, coordonnées/facturation et consentements espacés régulièrement, récapitulatif compact. Prix et logique commerciale inchangés.

Contrôle local à 320, 390, 768, 1024 et 1440 px : aucun débordement, contrôles du panier alignés ; texte du bouton de commande et prix sur la même ligne sans chevauchement. Facturation différente ouverte, reprise et informations d’achat contrôlées sans envoyer de commande. Comparaison des montants panier et commande : inchangés (LG F14R15WHS 485,17 €). Tests CommerceTest, CataloguePromotionPricingTest et ProductEnergyLabelsTest : 50 réussis, 1344 assertions ; build et git diff --check réussis. Captures cart-final-mobile.png, cart-final-desktop.png, checkout-final-mobile.png et checkout-final-desktop.png dans output/qa-p1. Vérification navigateur par échantillon ; pas de validation de commande réelle, paiement, déploiement ou lancement de campagne.

### Accueil — contrôle et harmonisation responsive

Cartes de l’accueil réutilisant les styles compacts du catalogue et les lignes subgrid : images, titres, références, prix et boutons alignés par rangée, y compris Samsung avec étiquette E. Prix et badges identiques avant/après sur les 20 produits présentés. Sections resserrées, cartes éditoriales et univers plus compactes, liens Découvrir alignés, bannière finale empilée sur mobile ; produits en deux colonnes sur tablette, une sous 420 px.

Contrôle local à 320, 390, 430, 768, 1024 et 1440 px : aucun débordement et aucun décalage des prix/boutons par rangée. Bandeau principal, univers, espace conseil et cartes ouvertes contrôlés visuellement. Informations d’achat ouvertes à 768 px : boutons alignés et aucun débordement. Test HomeNavigationTest adapté pour vérifier l’existence des PDF énergétiques et SVG officiels de garantie plutôt que de les traiter comme des routes Laravel ; autres validations de liens conservées. HomeMerchandisingTest, HomeNavigationTest, CatalogueUxRegressionTest, CataloguePromotionPricingTest et ProductEnergyLabelsTest : 20 tests réussis, 3203 assertions. Build Vite et diff check réussis. Captures home-final-desktop.png, home-final-mobile.png et home-final-hero.png dans output/qa-p1. Aucun déploiement ni changement de prix, réduction ou campagne.

### Promotions — contrôle responsive

Introduction harmonisée avec le catalogue (bannière pleine largeur, marges compactes), recherche et bouton de même hauteur 40 px, espacements et état sans résultat resserrés. Styles des cartes et pagination partagés conservés. À 320, 390, 430, 768, 1024 et 1440 px : aucun débordement, prix et zones d’achat alignés par rangée. Prix, prix barrés et badges des 24 produits identiques avant/après. Recherche Citrin : 2 résultats ; recherche sans résultat et réinitialisation fonctionnelles ; accès direct page 20 sur mobile : une seule ligne de pagination, pas de débordement. Tests PromotionsTest, CataloguePromotionPricingTest et CatalogueUxRegressionTest : 18 réussis, 940 assertions. Build Vite et diff check réussis. Captures promotions-final-mobile.png et promotions-final-desktop.png dans output/qa-p1. Aucun déploiement ou lancement publicitaire.

### Bien choisir — responsive et compacité

Classe advice-page appliquée uniquement à la page conseiller. Galerie de conseil limitée à 360/240/200 px selon écran, espacements resserrés, titres et texte harmonisés, contact, FAQ et liens d’aide compacts avec cibles de 40–44 px. Mise en colonne unique sous 960 px pour les conseils et la zone FAQ/aide. Contenu et destinations conservés ; composant partagé de l’accueil non modifié. Contrôle local à 320, 390, 768, 1024 et 1440 px : aucun débordement. Première question ouverte sur mobile : réponse visible et liens correctement placés. InformationPagesTest : 3 tests, 444 assertions réussies ; build Vite et diff check réussis. Captures advice-final-mobile.png et advice-final-desktop.png dans output/qa-p1. Aucun déploiement.

### Contact — contrôle responsive

Classe contact-page limitée à Contact. Titres, paragraphes et actions resserrés ; tableau entreprise compact avec libellés/valeurs en colonnes sur ordinateur/tablette et empilés sur mobile. Liens de contact regroupés et liens utiles de 44 px ; colonne latérale placée sous le contenu sous 960 px. Coordonnées et 13 informations entreprise conservées. Contrôle local à 320, 390, 768, 1024 et 1440 px : aucun débordement ni élément hors écran ; destinations mailto et WhatsApp conservées, sans ouverture ou envoi de message. InformationPagesTest : 3 tests, 444 assertions réussies ; build et diff check réussis. Captures contact-final-mobile.png et contact-final-desktop.png dans output/qa-p1. Aucun déploiement.

### Livraison — contrôle responsive

Classe delivery-page avec rythme partagé Contact/Livraison : titres et paragraphes compacts, liens utiles de 44 px, bloc reprise espacé et lien de contact de 40 px. Colonne latérale placée sous le contenu sous 960 px, ancre reprise adaptée à l’en-tête. Comparaison des textes livraison/reprise avant-après : identiques. Contrôle à 320, 390, 768, 1024 et 1440 px : aucun débordement ni élément hors écran ; lien reprise vers Contact conservé. InformationPagesTest : 3 tests, 444 assertions réussies ; build Vite et diff check réussis. Captures delivery-final-mobile.png et delivery-final-desktop.png dans output/qa-p1. Aucun déploiement ni modification des conditions commerciales.

### Paiement — contrôle responsive

Classe payment-information-page appliquée à la page Paiement, avec styles compacts partagés Contact/Livraison : titre, paragraphes, actions et liens utiles harmonisés. Colonne latérale sous le contenu sous 960 px. Texte affiché identique avant/après. Contrôle local à 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran ; captures payment-information-final-mobile.png et payment-information-final-desktop.png dans output/qa-p1. InformationPagesTest : 3 tests, 444 assertions réussies ; build et diff check réussis. Environnement local en mode coordonnées bancaires non configurées ; aucune modification des modalités, aucune transaction ni aucun déploiement.

### Retours & rétractation — contrôle responsive

Classe returns-information-page : rythme partagé des pages information, résumé et faits de retour compacts, sommaire et sections légales resserrés sans suppression de contenu. Comparaison du résumé et document légal avant/après : textes identiques. Contrôle à 320, 390, 768, 1024 et 1440 px : 12 sections conservées, aucun débordement ou élément hors écran. Sommaire Renvoi et remboursement : ancre atteinte sous l’en-tête (220 px en mobile) ; sources officielles ouvertes. Lien de déclaration conservé sans enregistrement réel. LegalInformationTest et ReturnRequestsTest : 22 tests, 3096 assertions réussis ; build Vite et diff check réussis. Captures returns-final-mobile.png et returns-final-desktop.png dans output/qa-p1. Aucun déploiement ni modification des modalités légales.

### CGV — contrôle responsive

Classe terms-information-page réutilisant le rythme compact des pages information et des sections légales Retours. Sommaire en colonne unique sur mobile ; numéros non sécables, titres et marges harmonisés, informations entreprise compactes. Texte du document légal strictement identique avant/après ; 12 sections conservées. À 320, 390, 768, 1024 et 1440 px : aucun débordement ni élément hors écran. Ancre Prix, commande et paiement atteinte sous l’en-tête à 220 px sur mobile ; numéros du sommaire sur une seule ligne vérifiés après correction. LegalInformationTest : 17 tests, 3041 assertions réussis ; build Vite et diff check réussis. Captures cgv-final-mobile.png et cgv-final-desktop.png dans output/qa-p1. Aucun changement du contenu légal ni déploiement.

### Confidentialité — contrôle responsive

Classe privacy-information-page réutilisant les styles compacts des pages information et des sections légales. Sommaire, titres, coordonnées et marges harmonisés ; numéros du sommaire non sécables. Comparaison du document légal avant/après : texte strictement identique, 15 sections conservées. Contrôle à 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. Sommaire Vos droits : ancre atteinte sous l’en-tête à 220 px sur mobile. LegalInformationTest : 17 tests, 3041 assertions réussis ; build et diff check réussis. Captures privacy-final-mobile.png et privacy-final-desktop.png dans output/qa-p1. Aucun changement de texte ou de traitement de données ni déploiement.

### Cookies — contrôle responsive

Classe cookies-information-page utilisant les styles compacts partagés des pages légales : titres, sommaire et sections harmonisés ; inventaire des cookies resserré et noms longs pouvant revenir à la ligne. Texte légal strictement identique avant/après, 7 sections conservées. À 320, 390, 768, 1024 et 1440 px : aucun débordement ni élément hors écran. Sommaire Choix et évolutions atteint sous l’en-tête à 220 px sur mobile ; inventaire technique conservé. LegalInformationTest : 17 tests, 3041 assertions réussis ; build et diff check réussis. Captures cookies-final-mobile.png et cookies-final-desktop.png dans output/qa-p1. Aucun changement des cookies, du consentement, du tracking ou du contenu légal ; aucun déploiement.

### Mentions légales — contrôle responsive

Classe notices-information-page utilisant les styles légaux compacts partagés : titres, sommaire, paragraphes et coordonnées harmonisés. Texte du document légal strictement identique avant/après, 8 sections conservées. À 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. Sommaire Hébergeur du site : ancre atteinte sous l’en-tête à 220 px sur mobile. LegalInformationTest : 17 tests, 3041 assertions réussis ; build Vite et diff check réussis. Captures notices-final-mobile.png et notices-final-desktop.png dans output/qa-p1. Aucun changement de contenu légal ni déploiement.

### À propos — contrôle responsive

Classe about-information-page avec rythme partagé des pages information ; image bornée de 130 à 260 px, espace sous l’image, sections, titres et listes harmonisés, coordonnées compactes. Texte de l’article strictement identique avant/après, 7 sections conservées. À 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. InformationPagesTest : 3 tests, 444 assertions réussies ; build et diff check réussis. Captures about-final-mobile.png et about-final-desktop.png dans output/qa-p1. Aucun changement de contenu ni déploiement.

### Nos produits — contrôle responsive

Classe products-information-page : rythme compact des pages information et FAQ partagé avec Bien choisir, sans modifier les textes ou destinations. Contenu de l’article strictement identique avant/après ; 4 questions conservées. À 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. Première question ouverte en mobile : réponse visible et disposition correcte. InformationPagesTest : 3 tests, 444 assertions réussies ; build et diff check réussis. Captures products-information-final-mobile.png et products-information-final-desktop.png dans output/qa-p1. Aucun déploiement.

### Suivi de commande — contrôle responsive

Classe tracking-page limitée au suivi : titre, formulaire et carte assistance compacts, référence à 44 px, bouton suivi à 40 px, liens contact et espacements harmonisés. Une colonne sous 960 px. Contrôle à 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. Champ vide bloqué par validation HTML native ; aucun dossier réel consulté. OrderTrackingTest : 4 tests, 136 assertions réussies ; build Vite et diff check réussis. Captures tracking-final-mobile.png et tracking-final-desktop.png dans output/qa-p1. Logique du suivi inchangée, aucun envoi de message ni déploiement.

### Formulaire de rétractation — contrôle responsive

Styles limités à returns-page : titres, marges du formulaire et labels compacts ; champs et listes à 44 px, zones de texte à 90 px minimum, aides et bouton harmonisés. Aucun champ ou texte retiré, obligations et traitement inchangés. À 320, 390, 768, 1024 et 1440 px : aucun débordement ni élément hors écran. Champ Nom obligatoire contrôlé avec formulaire vide ; aucune déclaration réelle enregistrée. ReturnRequestsTest : 5 tests, 55 assertions réussies ; build et diff check réussis. Captures withdrawal-form-final-mobile.png et withdrawal-form-final-desktop.png dans output/qa-p1. Aucun déploiement.

### Confirmation de commande — contrôle responsive

Styles limités à order-page : statut/référence, étapes suivantes, résumé, montants et actions resserrés. Total et libellé sur une même ligne mobile ; référence sur ligne dédiée sous 960 px ; titres du résumé pouvant revenir à la ligne. Aperçu Blade avec objets fictifs non enregistrés, sans base ni notification, fichier public temporaire supprimé après contrôle. État commande enregistrée à 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran ; état payé fictif contrôlé sur mobile sans débordement. Montant de démonstration conservé à 1836,98 €. CommerceTest : 44 tests, 882 assertions réussis ; build et diff check réussis. Captures confirmation-final-mobile.png et confirmation-final-desktop.png dans output/qa-p1. Aucun changement de statut réel, transaction ou déploiement.

### Instructions de virement — contrôle responsive

Styles limités à bank-confirmation : titre, montant, références, accordéons, coordonnées et actions compactés ; boutons Copier uniformes et liens PDF lisibles. Aperçu Blade avec coordonnées explicitement fictives, aucun IBAN réel utilisé, aucune commande créée. À 320, 390, 768, 1024 et 1440 px : aucun débordement ou élément hors écran. Justificatif ouvert à 320 px : champ fichier contenu (213 px), aucun document envoyé. CommerceTest : 44 tests, 882 assertions réussis ; build et diff check réussis. Captures bank-final-mobile.png et bank-final-desktop.png dans output/qa-p1. Fichier public temporaire supprimé ; aucun changement de paiement, transaction ou déploiement.

### Vérification générale responsive du site

Contrôle transversal local : 19 pages publiques françaises (accueil, catalogue, promotions, fiche Fantea, panier, commande, conseil, contact, livraison, paiement, retours, CGV, confidentialité, cookies, mentions, à propos, provenance, suivi, déclaration) à 320/768/1440 px : 57 contrôles, aucun débordement horizontal ni élément principal hors écran. 6 pages des parcours essentiels en anglais et allemand à 320/1024 px : 24 contrôles, aucun débordement ni chevauchement du libellé de commande avec son montant. Menu mobile allemand à 320x700 : contenu défilable et navigation utilisable. Pied de page contrôlé visuellement.

Corrections finales CSS : grille des produits associés partageant les lignes alignées du catalogue/accueil ; retest à 320, 430, 768, 1024 et 1440 px sans décalage des prix/zones d’achat par rangée. Panier mobile : suppression de l’espace réservé à une ancienne barre fixe ; bouton Passer à la commande sans ancien décalage gauche/bas, prenant la largeur utile du récapitulatif. Capture après correction : general-cart-mobile.png ; produits associés : general-related-desktop.png, dans output/qa-p1.

Régression : 125 tests réussis, 8691 assertions (accueil/navigation/catalogue/UX/promotions/prix/énergie/sécurité produit/information/légal/commerce/suivi/retours). Build Vite, compilation Blade et git diff --check réussis. Prix, réductions, données et contenu légal non modifiés par cette phase (CSS uniquement). Les confirmations et instructions de virement ont été contrôlées auparavant avec des objets fictifs sans enregistrement ; leurs fichiers publics temporaires ont été supprimés.

Limites : contrôle de l’environnement local et d’échantillons de produits, pas inspection visuelle de chacune des 3709 références ; Safari/Firefox, administration privée et données de commandes réelles non contrôlés dans cette phase. Aucun déploiement, envoi de message, commande ou paiement réel, migration ou lancement publicitaire.

### Boutons fixes mobiles — panier, commande et fiche produit

Actions fixes sous 600 px, avec le bouton existant de chaque formulaire (aucune duplication de soumission). Prix produit rendu depuis la référence sélectionnée ; total du panier ajouté au bouton mobile depuis le devis serveur, sans modifier les calculs. Libellé et montant centrés verticalement. La commande conserve ses libellés et validations existants. WhatsApp est placé au-dessus des actions. Espace terminal et marges de défilement/focus de 140 px, plus zone sûre du téléphone, pour rendre les derniers liens accessibles au-dessus des éléments flottants.

Contrôle local des trois pages à 320/390/768/1440 px, avec défilement initial/intermédiaire/final : boutons mobiles stables, montants alignés, aucun débordement horizontal, pied de page entièrement accessible en fin de défilement sur mobile. Boutons dans le flux normal sur tablette/ordinateur. Navigation clavier dans la commande : champs et case de prise de connaissance des données visibles au-dessus de l’action, sans cocher ni soumettre. Capture : output/qa-p1/fixed-actions-footer-mobile.png. Build et diff check réussis ; CommerceTest et CataloguePromotionPricingTest : 47 tests, 1297 assertions réussis. Prix, remises et textes légaux conservés. Pas de déploiement ni commande réelle. Clavier virtuel et navigateur sur téléphone physique non testés.
