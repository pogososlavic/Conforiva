# HOME CONFORT — cohérence des 150 produits : site, JSON-LD et flux

Audit du **20 octobre 2026**, sélection `shopping150-lifecycle-20261020`. **Aucun écart de données nécessitant une correction n’a été constaté sur les 150 produits.** Aucun code applicatif, prix, stock, texte produit, image, identifiant, étiquette ou statut d’approbation n’a été modifié par cet audit. Les livrables de ce travail sont le présent rapport, le [relevé produit par produit](AUDIT-COHERENCE-150-SITE-JSONLD-FLUX-2026-10-20.csv) et les preuves privées.

## Résultat et périmètre

- **150 offres × 3 langues : 450 fiches locales**, puis les mêmes 450 fiches en simulation de publication ; **900 rendus HTML** contrôlés via les vraies routes, contrôleurs et vues Blade.
- **31 768 comparaisons** du rendu/JSON-LD/XML : PASS. Les 3 flux simulés sont produits par `MerchantFeedController`, `entries()` et `xml()`, avec 150 identifiants uniques par langue.
- **Recette indépendante : 12 012 contrôles supplémentaires**, zéro anomalie ; 916 images de galerie et 148 images d’étiquette ouvertes, 592 PDF effectivement liés structurellement lisibles. Les 150 images Google retenues sont natives d’au moins 500 × 500 pixels.
- **44 promotions**, **2 lots vendeur**, **148 modèles EPREL**, **143 EAN** et **109 références fabricant** sont concordants. Aucune variante n’est sélectionnée.
- Les faits produits, l’énergie, les médias, la sélection, les 1 075 revues Merchant et les services métier sont inchangés ; les changements simultanés de menu restent bornés à la navigation, hors du contenu et des métadonnées produit. **Aucune revue réelle approuvée**.

## Contrôles effectués

| Contrôle | Type / périmètre | Résultat observé | Preuve / suite |
| --- | --- | --- | --- |
| Nom, description, marque | Cohérence Google ; 450 offres par phase | Conforme : titre H1, texte visible, JSON-LD et contenus structurés XML identiques | `root/rendered-audit.json`, 900 HTML et trois XML privés |
| Identifiant stable et variante | Cohérence Google ; 150 sans variante | Conforme : 150 identifiants uniques dans chaque XML et SKU JSON-LD correspondant | IDs techniques `homya-*` conservés ; aucun identifiant fabricant inventé |
| Prix payable et devise | Exigence Google ; 450 offres et 44 promotions | Conforme au centime : prix visible, Offer JSON-LD et XML ; EUR partout | Prix d’origine barrés également comparés ; aucun calcul flottant ni prix fabricant importé |
| Disponibilité | Exigence Google ; photographie XAMPP des 150 stocks | Conforme : 150 × 500 disponibles, références/prix validés, réservations nulles ; InStock / in_stock concordants | SELECT seul ; pas de comptage physique ni d’expiration des commandes |
| Images | Cohérence Google ; image principale et images retenues | Conforme : image principale du site, liste revue JSON-LD et XML concordantes ; images retenues présentes dans la galerie | Recette indépendante des fichiers et dimensions ; les images écartées pour Google peuvent rester dans la galerie du site |
| GTIN / MPN et état | Attributs produits ; 143 EAN, 109 MPN, tous neufs | Conforme entre faits fabricant, revues et XML/JSON-LD simulés ; checksums GTIN valides | Sept offres sans EAN documenté possèdent un MPN vérifié ; aucune exemption d’identifiant inventée |
| Énergie | Cohérence Google et information produit UE ; 148 modèles | Conforme : EPREL unique par modèle, classe affichée complète, liens exacts vers étiquette et fiche de la bonne langue | 592 PDF locaux présents et lisibles ; pas de nouvelle attribution énergétique ni d’audit juridique exhaustif |
| Lots de granulés | Attributs conditionnels ; deux lots constitués par HOME CONFORT | Conforme : 72 et 65 sacs, identifiants du sac, quantités et masses du lot, unités du flux et propriété JSON-LD | Prix et stock portent sur le lot complet ; aucun GTIN de palette inventé |
| URL canonique et langues | SEO ; 450 pages par phase | Conforme : produit/langue exacts, alternates FR/EN/DE cohérents et URL Offer correspondant à la fiche | Origine localhost pour la phase locale, origine réservée pour la simulation |
| Provenance des textes | Spécifications Google ; 450 entrées XML | Conforme : titres et descriptions déclarés dans les attributs structurés avec leur provenance | Aucun texte ni identifiant réécrit pendant l’audit |

Ces contrôles suivent les [spécifications produit Google](https://support.google.com/merchants/answer/7052112?hl=fr), les exigences de [prix](https://support.google.com/merchants/answer/6324371?hl=fr), de [disponibilité](https://support.google.com/merchants/answer/6324448?hl=fr) et de [données structurées marchand](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing), consultées le 20/10/2026. Les [guides e-commerce](https://developers.google.com/search/docs/specialty/ecommerce?hl=fr) et [SEO](https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=fr) restent la référence pour les contrôles publics ultérieurs.

### Promotions

Le flux transmet le prix effectivement payable dans `g:price`. L’absence de `g:sale_price` n’est pas une anomalie : Google permet le prix réduit dans `price` lorsque `sale_price` n’est pas transmis. Aucun prix d’origine n’a été ajouté artificiellement au flux, aucune annotation promotionnelle Google n’est promise. [Règle officielle des prix soldés](https://support.google.com/merchants/answer/6324471?hl=fr), consultée le 20/10/2026.

## Fonctionnement local et simulation privée

**Le flux public actuel reste désactivé.** Les trois vrais GET locaux `/google/merchant/fr.xml`, `/en.xml` et `/de.xml` retournent 404, comme prévu. La configuration réelle observée est : environnement local, indexation désactivée, flux désactivé, domaine public absent, validation commerciale/juridique globale désactivée et zéro revue approuvée. Le contrôle de disponibilité des moyens de paiement n’a pas été exécuté et aucune donnée bancaire privée n’a été lue.

Dans ce mode local, les fiches conservent leur JSON-LD descriptif ; l’offre transactionnelle, les identifiants et la certification conditionnés par les revues ne sont pas publiés comme s’ils étaient approuvés. Leur absence locale est un verrou attendu, pas un écart produit masqué.

Pour tester le futur résultat sans ouvrir ces verrous, un processus de test isolé utilise **l’origine réservée `https://example.org`**, une copie temporaire des revues, des empreintes locales et un double de paiement. Les stocks viennent de la photographie SELECT XAMPP et sont servis par des doubles en mémoire. Le service ordinaire de disponibilité pouvant expirer des virements, il n’a pas été utilisé pendant le rendu audité ; aucune commande ni réservation n’a été écrite ou expirée. Les routes, vues, contrôleurs, validation des stocks et sérialiseurs restent ceux de l’application.

**Les XML `preview-fr/en/de.xml` sont des preuves privées de simulation. Ne pas les importer dans Merchant Center : leur domaine est réservé au test.** Aucun domaine réel, paiement, indexation ou statut d’approbation n’a été activé.

## Ajustements de l’audit et risques non observés

La première passe du comparateur a signalé 82 différences purement typographiques : elle normalisait l’espace fine insécable des milliers français d’un seul côté de la comparaison. Le comparateur a été corrigé pour confronter le texte brut des deux côtés ; les prix du site étaient déjà corrects. Cette fausse alerte n’est pas présentée comme une correction de 82 produits. Première passe, ajustement et dernière passe réussie sont conservés.

La description visible passe par le formateur énergétique, alors que le flux lit la description canonique. Les **450 descriptions actuelles sont identiques** après ce traitement. Un futur changement de contenu devra refaire ce contrôle et invalider les empreintes de revue si nécessaire ; aucun défaut actuel n’a été inventé pour justifier une modification.

La navigation a été modifiée en parallèle : retrait d’une flèche et ajout d’une liste de marques avec sa méthode de service. Ces changements ont été préservés. Une passe a été interrompue lorsque la nouvelle vue a appelé une méthode ajoutée après le chargement de l’ancienne classe dans le processus de test ; le processus a été redémarré et les 900 rendus ont été revérifiés sur la version cohérente courante. Un dernier réordonnancement des liens du menu a ensuite été conservé : un contrôle indépendant vérifie que tout ce qui précède et suit cette navigation, ainsi que les dix autres fichiers contrôlés, reste strictement identique. Il n’a pas justifié une nouvelle répétition des rendus produit. Les empreintes initiales et ces incidents sont archivés ; les cinq données JSON de catalogue/revues/énergie/sélection/médias sont restées identiques au gel initial. Aucun contenu produit n’a changé.

## Contrôles encore impossibles ou à finaliser

| Contrôle | Statut et conséquence |
| --- | --- |
| Domaine public HTTPS, nouvel e-mail et URL définitives | Informations encore attendues ; les véritables liens/image_link ne sont pas validés sur Hostinger |
| Paiement, e-mails, validation commerciale/juridique globale et livraison réelle | À configurer et recetter avant activation ; aucune preuve de transaction réelle ou conformité juridique exhaustive dans cet audit |
| Revues Merchant | 1 075 revues réelles non approuvées ; les 150 fiches restent préparées et ne sont pas déclarées prêtes à soumettre |
| Exploration Google et accès aux images/PDF | Non testés en production ; contrôle des robots, réponses publiques et accès par pays à réaliser après déploiement |
| Rich Results Test, Search Console et Merchant Center | Aucun test distant, accès au compte, soumission ou résultat Google confirmé ; le JSON-LD local n’équivaut pas à une validation Google |
| Synchronisation future des prix et réservations | Photographie valable au moment de l’audit ; refaire la comparaison lorsque les données changent |

Dossier privé : `storage/app/private/consistency150-20261020/`. Les rapports des rôles SEO, Merchant et QA sont conservés avec les résultats, les réponses HTML compressées, les XML de simulation et les empreintes des sources. Aucun changement de base, de catalogue, de code applicatif, de Git distant ou de publication n’a été effectué.
