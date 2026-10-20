# HOME CONFORT — SEO, Search Console et Merchant Center

Revue locale du 20 octobre 2026. Les sources Google ci-dessous ont été consultées le 20 octobre 2026. Ce document décrit le code et les données disponibles ; il ne constitue ni une approbation Google, ni une promesse de classement.

## Résultat livré

Le socle existant couvre déjà les titres et descriptions localisés, les URL canoniques, les alternatives FR/EN/DE et x-default, la pagination, les facettes de recherche, les fils d’Ariane, les produits et variantes, robots.txt et les sitemaps découpés. Le contenu principal est rendu par Laravel, sans nécessiter JavaScript pour sa lecture par les moteurs.

Trois compléments corrigent des lacunes démontrées :

1. **Identité marchande structurée** : un nœud `OnlineStore`, sous-type d’`Organization`, utilise les mêmes coordonnées légales que les pages du site, ainsi que les contacts validés existants. Le logo réellement affiché, le nom légal, le SIRET, la TVA, l’adresse et la date de création proviennent des configurations ; le nœud `WebSite` désigne cette organisation comme éditeur et les offres ouvertes la désignent comme vendeur. Aucune adresse de magasin, géolocalisation, horaire ou note client n’est inventé.
2. **EPREL cohérent avec Merchant Center** : un produit ou une variante ne reçoit `hasCertification` que si sa revue Merchant est approuvée, correspond à l’empreinte actuelle du produit dans cette langue et contient un code EPREL syntaxiquement valable. Le JSON-LD et le XML utilisent alors le même code. L’identifiant n’est ni fabriqué ni déduit d’une classe énergétique ou d’une photo. Une revue absente, périmée ou contradictoire retire cette certification du balisage.
3. **Langues et variantes** : pour les variantes regroupées sur une fiche unique, les liens `hreflang` pointent désormais vers les pages canoniques de chaque langue. Ils n’envoient plus Google vers des paramètres de sélection dont la canonique désigne la fiche parente. Les liens de sélection des variantes conservent leur fonctionnement pour les visiteurs.

L’indexation locale reste fermée. Les flux Google restent conditionnés par le domaine HTTPS réel, l’environnement de production, les validations commerciales, les données produit revues et l’inventaire. L’ouverture locale de la validation de commande ne transforme pas une demande différée en offre de paiement immédiatement exécutable.

## Contrôles et preuves

| Contrôle | Portée | Résultat et limite |
| --- | --- | --- |
| Syntaxe PHP | `Seo.php`, tests dédiés | Valide avant installation. |
| Tests SEO/Merchant | Classe `GoogleSeoMerchantTest` | 24 méthodes, dont trois nouvelles régressions sur l’identité, les langues des variantes et EPREL. Réussite confirmée par le pilote après installation ; les résultats détaillés de la recette intégrée restent dans son journal. |
| Format et différences | Deux fichiers de code | Pint passé après installation selon le pilote ; `git diff --check` ciblé réussi. |
| Audit catalogue | Archives fichiers FR/EN/DE | 3 709 produits examinés par langue, en lecture seule, sans base, sans modification du catalogue et sans contacter Google. Détail ci-dessous. |
| Examen des comptes | Connecteur GSC Wizard, lecture `list_sites` | Refus `payment_required` / `subscription_required` : essai expiré ou abonnement absent. Aucun état actuel Search Console ou Merchant ne peut être confirmé par cette voie. Aucune souscription engagée. |
| Validation Rich Results | Contrôle local JSON/XML et tests | La validation distante Google d’une URL publiée reste à effectuer une fois le domaine confirmé et la mise en ligne autorisée. |
| Performances | Ce lot SEO | Aucun score Lighthouse ni donnée terrain Core Web Vitals revendiqués dans ce lot. La recette responsive est un chantier parallèle. |

L’audit d’archives a été exécuté à **12:34 UTC le 20 octobre 2026**, avant les derniers ajustements locaux de commande et de stock. Il ne lit pas le stock réel, ne vérifie pas les corrections administratives et ne prouve pas la disponibilité commerciale. Les tests utilisent des données de démonstration isolées, jamais une commande ou un paiement réel.

## Données Merchant encore à compléter

La copie locale ne contient pas le fichier de revues privées `storage/app/private/google/merchant-products.json`. Les mentions historiques de 151 articles LG préparés pour HOMYA et les anciens états Search Console décrivent les opérations d’octobre sous l’ancienne identité ; ils ne prouvent pas que ces revues ou les accès correspondants ont été restaurés pour HOME CONFORT.

L’audit hors connexion donne les mêmes nombres en français, anglais et allemand :

| État de l’archive | Nombre de produits parents | Interprétation |
| --- | ---: | --- |
| Catalogue examiné | 3 709 | Les variantes restent détaillées séparément dans le rapport technique. |
| Revue Merchant actuelle manquante | 3 709 | Aucun fichier de validation privée n’est disponible. Ce n’est pas un refus Google. |
| Attribution des identifiants fabricant à confirmer | 3 709 | Un SKU interne ne remplace pas un GTIN ou un MPN du fabricant. Les faits doivent être rapprochés des sources exactes. |
| Assujettissement énergétique à déclarer dans la revue | 3 709 | Ce nombre ne signifie pas que tous ces produits nécessitent une étiquette, ni que leurs documents récupérés sont absents. |
| Provenance des titres/descriptions à confirmer dans la revue | 3 709 | Le format de flux doit respecter les exigences Google applicables aux contenus générés. |
| Produits avec anomalies catalogue à revoir | 418 | Les signalements existants ne sont pas masqués par l’optimisation SEO. |
| Parents comportant des variantes non documentées pour Merchant | 26 | Les images de chaque finition et ses attributs n’ont pas de revue validée ; cela ne signifie pas que la galerie générale est vide. |
| Offres prêtes à exporter selon cet audit | 0 | Revues absentes et garde globale locale fermée ; aucune soumission effectuée. |

Pour compléter les revues sans fausse approbation, la commande existante prépare les diagnostics et les brouillons :

```sh
php artisan homya:google-audit --offline --locale=all --json
```

Le PHP utilisé doit être compatible Laravel 13 (PHP 8.3 minimum ; PHP 8.5 installé sur ce poste). Le mode `--offline` évite toute lecture de base ou de réglage privé de paiement. Pour enregistrer une liste fournisseur et les brouillons non approuvés, l’option existante `--output=output/qa-google-home-confort` crée ses exports de diagnostic ; leur création ne publie ni n’approuve les articles.

Ordre de reprise recommandé : rétablir les preuves disponibles pour les modèles exacts ; confirmer marque/GTIN/MPN, état, images et variantes ; qualifier l’assujettissement énergétique et les codes EPREL ; résoudre les anomalies ; contrôler prix, stock, devise, livraison et retours sur la fiche, dans le flux et au checkout ; puis valider les empreintes par langue. Ne pas utiliser `identifier_exists=false` pour contourner des identifiants connus.

Les étiquettes et fiches récupérées lors des lots précédents restent conservées. Les dossiers réservés, les contradictions de modèles et les pièces fournisseur encore attendues gardent leurs réserves. Le balisage Google ne corrige pas une preuve énergétique absente ou erronée.

## Passage en production et connexion Google

L’utilisateur a demandé de préparer le SEO localement **sans choisir de domaine maintenant**. Aucun nouveau domaine, adresse mail, jeton de validation, compte ou paramètre de campagne n’est inventé. Les valeurs historiques éventuelles `homya.eu` ne valent pas choix du futur domaine HOME CONFORT.

1. Confirmer le domaine public et la nouvelle adresse mail. Renseigner l’URL HTTPS exacte et cohérente dans `APP_URL` et `HOMYA_PUBLIC_URL`, sans remplacer les identifiants techniques historiques au hasard. Contrôler DNS, certificat, version avec ou sans www et redirections avant d’ouvrir l’indexation. Une éventuelle migration de domaine exige une liste de redirections conservant les chemins utiles et un suivi de l’ancienne propriété.
2. Après autorisation de publication, contrôler sur le serveur les réponses HTTP, canoniques, alternatives linguistiques, images, documents énergétiques, robots, sitemaps, fiches, CGV, livraison, retours et parcours de commande. Le siège en Guyane ne permet pas de déduire le lieu réel d’expédition : confirmer ce fait et les conditions applicables aux pays réellement livrés avant de renseigner les politiques Google.
3. Dans Search Console, valider la propriété du domaine avec le véritable jeton DNS fourni par Google, ou une méthode URL-préfixe adaptée. Aucun tag Analytics ni compte publicitaire n’est requis pour la validation DNS. Une propriété historique n’est pas supprimée automatiquement.
4. Vérifier le sitemap public `/sitemap.xml` et ses enfants, puis le soumettre dans la propriété correcte avec une autorisation distincte d’action externe. Tester des URL représentatives avec l’Inspection d’URL et Rich Results Test. Distinguer un fichier envoyé, un fichier lu, des URL découvertes et des pages effectivement indexées.
5. Dans Merchant Center, faire correspondre identité légale, enseigne, adresse, contacts et domaine réellement revendiqué. Confirmer les marchés/languages réels, les conditions de livraison et retours ainsi que le traitement opérationnel. Les anciens réglages FR/BE/DE ne sont pas une preuve de validation de la nouvelle identité.
6. Publier uniquement les offres complètement revues. Vérifier les URL de flux `/google/merchant/fr.xml` et, après revue linguistique propre, `/google/merchant/de.xml` ou `/google/merchant/en.xml`. Une source en français ne valide pas automatiquement les traductions allemandes ou anglaises.
7. Après import, relever les diagnostics exacts du compte et de chaque produit, la date et les pays concernés. Corriger les causes démontrées avant d’étendre la sélection. Ne demander aucun réexamen de suspension sans diagnostic et correction réels.
8. Après mise en ligne, mesurer les Core Web Vitals disponibles dans Search Console/CrUX et les performances de pages représentatives sur mobile et ordinateur. Les mesures locales et les émulations tablette ne sont pas des données terrain de Google.

Les confirmations de CM2C, de l’adresse de retour et du stock fournies au pilote dans cette session doivent être intégrées au chantier identité/commerce. Elles ne sont pas présentées comme vérifiées dans le compte Google par ce rapport. Le capital et le RCS sont traités par le pilote à partir des pièces remises ; ce lot SEO ne les invente pas.

## Sources officielles consultées le 20 octobre 2026

| Contrôle et nature | Source | Conclusion applicable |
| --- | --- | --- |
| Fondamentaux — recommandation Google | [Guide de démarrage SEO](https://developers.google.com/search/docs/fundamentals/seo-starter-guide) | Architecture lisible, titres utiles, contenu accessible et liens exploitables ; aucun rang garanti. |
| Boutique — recommandation Google | [Documentation e-commerce](https://developers.google.com/search/docs/specialty/ecommerce) | Cohérence des informations produit et parcours compréhensible. |
| Organisation — données structurées | [Organization et OnlineStore](https://developers.google.com/search/docs/appearance/structured-data/organization) | Émettre les faits réels et le logo de la boutique, sans magasin fictif. |
| Produit/Offer/EPREL — données structurées | [Merchant listing et Certification](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing) | Utiliser `hasCertification`, l’autorité européenne et le code EPREL réel ; les résultats enrichis restent à valider par Google. |
| Langues — recommandation Google | [Versions localisées](https://developers.google.com/search/docs/specialty/international/localized-versions) | Alternatives cohérentes avec les pages traduites et les canoniques retenues. |
| Flux — exigence Google conditionnelle | [Spécifications des données produit](https://support.google.com/merchants/answer/7052112?hl=fr) | Attributs requis selon pays et catégorie ; des valeurs syntaxiquement valides ne suffisent pas à prouver leur attribution. |
| Certification — exigence Google conditionnelle | [Attribut certification](https://support.google.com/merchants/answer/13528839?hl=fr) | Certification EPREL de l’offre exacte, cohérente entre fiche et flux. |
| Identité et transaction — règle Google | [Déclarations trompeuses ou déceptives](https://support.google.com/merchants/answer/6150127?hl=fr) | Identité, origine d’expédition, disponibilité, coûts et conditions réels ; ne pas déduire une acceptation d’un simple balisage. |
| Langues et devises — règle Google | [Langues et devises acceptées](https://support.google.com/merchants/answer/160637?hl=fr) | Définir les langues et marchés réellement pris en charge. |
| Propriété — procédure Google | [Validation de propriété Search Console](https://support.google.com/webmasters/answer/9008080?hl=en) | Vérifier le futur domaine avec une preuve authentique. |
| Boutique revendiquée — procédure Google | [Validation des URL dans Merchant Center](https://support.google.com/merchants/answer/11586344?hl=fr) | La validation et la revendication du domaine sont distinctes des corrections locales. |

Aucune publication, connexion de nouveau compte, modification de compte Google, soumission de sitemap/flux, dépense, commande réelle ou garantie d’approbation n’est effectuée dans ce lot.

## Complément de clôture locale

Les confirmations d’adresse de retour, CM2C et stock ont été intégrées. Le capital et le RCS ont été complétés depuis les actes transmis. Le stock local contient 500 unités par produit. `HOMYA_PUBLIC_URL` et sa valeur par défaut sont désormais vides tant que le futur domaine n’est pas choisi ; le JSON-LD et les canoniques contrôlés utilisent 127.0.0.1 avec noindex.

Recette finale : 548 tests / 56 007 assertions réussis, build Vite et 40 vues Chrome contrôlées ; voir le [bilan intégré](RESPONSIVE-SEO-IDENTITE-2026-10-20.md). Ces résultats ne remplacent ni les revues Merchant manquantes ni la validation des comptes et du domaine public.
