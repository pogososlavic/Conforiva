# HOME CONFORT — responsive, identité, commandes et SEO

Intervention locale vérifiée le 20 octobre 2026.

## Ce qui est installé

- Accueil, catalogue, fiches, panier et formulaire de commande contrôlés sur ordinateur, tablette et mobile. Actions tactiles principales agrandies à 44 px, champs de commande à 16 px, navigation intermédiaire ajustée et barre d’achat mobile pouvant se replier. Les captures et tests portent sur les cas réellement exercés, sans promesse de conformité complète d’accessibilité.
- La tuile Télévisions de l’accueil est remplacée par le congélateur réel **LG GFM61MCCSD**, photo locale et lien vers son modèle exact, en FR/EN/DE. Le rayon TV reste accessible dans la navigation. Les prix et références ne changent pas.
- Identité légale centralisée : HOME CONFORT, SAS, siège 1010 A ROUTE DU MAHURY, 97354 RÉMIRE-MONTJOLY ; SIREN 934757311, SIRET 93475731100014, TVA FR05934757311, création 29/10/2024, dirigeant RAPHAEL RUIZ. Capital **500,00 €** et **934757311 R.C.S. Cayenne** relevés sur l’acte remis, page 1 du document de juin 2026 ; ce contrôle documentaire ne vaut pas certification d’un registre en temps réel.
- L’adresse de retour, y compris pour les appareils volumineux, et l’adhésion CM2C de HOME CONFORT sont confirmées par le propriétaire dans cette session. Les pages et futurs documents utilisent les nouvelles coordonnées ; les archives déjà émises ne sont pas réécrites.
- Téléphone **+33756894951** conservé. L’adresse **support@homya.eu** reste utilisée en attendant la nouvelle adresse communiquée par le propriétaire. Les contacts personnels présents dans les signatures des PDF ne sont pas repris.
- **3 709 produits à 500 unités chacun**, soit 1 854 500 unités déclarées par le propriétaire, importés dans l’inventaire initialement vide de MariaDB XAMPP. Les variantes d’un produit partagent ses 500 unités ; l’import ne crée pas 500 unités supplémentaires par finition. Quantités et réservations ne sont jamais réapprovisionnées par une réexécution de l’import initial.
- Validation de commande ouverte **en local**, en mode différé : saisie, consentements, récapitulatif et enregistrement en attente des instructions de paiement. La réservation et les limites de stock restent vérifiées côté serveur. Le moyen de paiement effectif attend le RIB HOME CONFORT ; aucune carte n’est activée. Les libellés avant validation n’affirment plus qu’une commande est déjà enregistrée.

## SEO et Google

Le socle existant de titres/descriptions localisés, canoniques, hreflang, pagination, facettes, robots et sitemaps est conservé. Les corrections ajoutent l’organisation `OnlineStore`, les liens éditeur/vendeur, des alternates de variantes cohérents avec leur canonique et la certification EPREL uniquement lorsqu’une revue Merchant actuelle l’établit. Les mêmes configurations d’identité alimentent le contenu visible et le JSON-LD.

Le propriétaire demande une préparation locale : **aucun nouveau domaine choisi**, `HOMYA_PUBLIC_URL` vide, liens canoniques locaux et **noindex, nofollow** conservés. L’ancien domaine n’est plus attribué implicitement à HOME CONFORT. Le futur domaine HTTPS devra être configuré et contrôlé avant d’ouvrir l’indexation.

Le catalogue n’est pas déclaré prêt à être envoyé à Google : fichier des revues Merchant privées non restauré, 418 signalements catalogue et 26 parents à variantes à revoir dans l’audit hors connexion. Ces nombres ne constituent ni des refus Google ni un décompte d’étiquettes manquantes. Les documents énergétiques récupérés restent inchangés. Aucun compte, sitemap ou flux n’a été soumis.

Le [rapport SEO/Merchant](SEO-MERCHANT-HOME-CONFORT-2026-10-20.md) détaille les sources Google consultées, les contrôles, les données fournisseur nécessaires et les étapes Search Console/Merchant sur le futur domaine. Le connecteur Search Console n’a pas permis de confirmer un état de compte actuel ; aucune dépense ni connexion de nouveau compte n’a été engagée.

## Résultats observés

| Contrôle | Résultat | Preuve |
| --- | --- | --- |
| Suite PHP complète sur le résultat final | **548 tests / 56 007 assertions réussis** | full-final.log |
| Ciblage initial intégré | 120 tests / 7 862 assertions réussis | targeted-all.log |
| Retest après les derniers libellés et URL locales | 87 tests / 4 858 assertions réussis | final-targeted.log |
| Build Vite | Réussi | vite-build.log |
| Pint et espaces Git | Réussis sur les fichiers concernés | journaux et recette |
| Chrome | **40 pages/configurations** à 320, 360, 390, 768, 1024, 1440 px, FR/EN/DE ; aucune image visible cassée, aucun débordement ni champ sans label dans ces cas | qa/browser-final.json |
| Formulaire et focus | Champs/selects 44 px et police 16 px ; focus des champs et cases non masqué par la barre fixe dans les cas contrôlés | qa/touch-focus.json |
| Identité et SEO final | OnlineStore cohérent, canonical 127.0.0.1, noindex local | qa/seo-final.json |
| Stock post-import | 3 709 lignes, 500 par produit, 0 réservé et 0 commande au contrôle immédiatement après import | inventory-after.json |
| Catalogue source | **3 714 fichiers JSON inchangés** ; registre énergétique inchangé | verification.json |
| Dépendances | npm : aucune vulnérabilité signalée ; Composer : non concluant, délai réseau Packagist dépassé après relance hors sandbox | scripts/*audit* |
| Fichiers privés | Noms/liens symboliques publics : aucune alerte ; chemins .env/.git/log HTTP 404 dans la recette initiale ; sauvegardes privées ignorées par Git | qa/initial-review.md |

Les tests de commandes utilisent des bases SQLite isolées et des transports simulés. Le navigateur ajoute seulement au panier de sa session et visite le formulaire, sans POST de commande, sans paiement et sans e-mail. La nouvelle relecture SQL finale demandée par la recette n’a pas été exécutée après son refus ; le contrôle post-import ci-dessus reste le dernier constat SQL effectivement réalisé. Aucune nouvelle tentative n’a été faite.

Les appareils physiques, tous les lecteurs PDF, le paiement réel, la délivrance SMTP et la connexion des comptes Google ne sont pas validés par cette recette. Aucun score Lighthouse, classement SEO, acceptation Merchant ou validation juridique globale n’est promis.

## Captures et preuves

[Congélateur sur ordinateur](../output/qa-responsive-seo-20261020/final-freezer-1440-fr.png) · [tablette](../output/qa-responsive-seo-20261020/final-freezer-768-fr.png) · [mobile](../output/qa-responsive-seo-20261020/final-freezer-390-fr.png) · [commande mobile](../output/qa-responsive-seo-20261020/checkout-viewport-390.png).

Sauvegardes et preuves privées : `storage/app/private/site-quality-20261020/`. Les PDF source sont conservés à leur emplacement externe d’origine ; leurs empreintes et les faits nécessaires sont tracés sans publier les signatures ou coordonnées personnelles. Aucune publication, aucun push Git, aucune migration ni reconfiguration de XAMPP.
