# Documents énergétiques — complément du 20 octobre 2026

## Résultat installé en local

**134 nouvelles références de chauffage** documentées par des originaux fabricant ou EPREL correspondant aux modèles vérifiés. Le registre atteint **770 produits et 775 étiquettes**, les cinq appareils à deux modules étant comptés séparément dans les étiquettes.

| Marque | Références ajoutées |
| --- | ---: |
| Bronpi | 48 |
| Atmos | 16 |
| Olsberg | 17 |
| Königshütte | 2 |
| TermaTech | 33 |
| Lotus | 9 |
| Invicta | 9 |

**83 nouveaux dossiers** possèdent une étiquette et une fiche réellement en français. Le total d’affichage près du prix en français atteint **559 produits**. Les autres documents sont accessibles avec leur langue réelle ; aucune traduction de fiche officielle n’est fabriquée.

Le catalogue compte désormais **844 modèles comportant au moins une caractéristique officiellement vérifiée**, pour **4222 caractéristiques vérifiées**. Les autres données récupérées restent signalées comme non confirmées. Les fichiers source du catalogue, prix, stocks et variantes sont conservés.

## Correspondances et limites

- Invicta : neuf modèles exacts ; fiche réglementaire Annexe IV située dans le manuel original FR/EN. L’ancien Fifty 6480-44 n’a pas été assimilé aux versions actuelles Fifty Plus.
- Bronpi : étiquette et fiche FR/EN exactes ; les rendements dont la page et la fiche divergent ne sont pas importés comme valeurs vérifiées.
- Atmos : chaudières nues GSE/GS/KC, échelle A+++–D du recueil original de 180 pages, page exacte et aperçu distinct pour chaque modèle. Vraies fiches EN/CS, proposées en EN ; aucun ensemble Komfort/Dual ou brûleur ajouté par analogie.
- Olsberg : les fiches réglementaires sont conservées dans les manuels complets ; les liens ouvrent les pages vérifiées de chaque langue, jusqu’à la page 143 de l’original de 156 pages. Les valeurs saisonnières contradictoires Levana 8 sont omises. Les générations bois .8900 non établies restent en attente.
- Königshütte : seuls les dossiers concordants sont retenus. Les trois Draco/Draco M à classe contradictoire entre page fabricant et documents sont exclus.
- TermaTech : les regroupements sont acceptés uniquement quand le fabricant les relie explicitement à la version vendue. Un chemin /fr/ ou /de/ ne prouve pas la langue du PDF. Les TT30 dont l’étiquette et la fiche donnent une classe différente sont exclus.
- Lotus : modèle exact, rapprochement depuis le site fabricant vers EPREL, contrôles des puissances et des langues. Les PDF reçus dans un ZIP sont extraits sans réécriture et leur archive/membre est tracé. Les dossiers indiquant une puissance indirecte incompatible avec le poêle sec restent exclus.

## Rectification du lot précédent

**35 références HS Flamingo** sont retirées des associations publiques : l’étiquette et la fiche anciennes donnent A, tandis que les pages fabricant conservées indiquent A+. L’étiquette présente aussi une référence réglementaire erronée. Les profils antérieurs sont rétablis, sans marquer cette classe incertaine comme vérifiée. Les originaux et preuves contradictoires sont conservés à titre de justificatifs ; aucun document n’est retouché pour résoudre artificiellement le conflit.

Le lot du 19 octobre représente donc **337 références supplémentaires confirmées** après ce retrait, puis le présent complément ajoute **134 références**. Les 299 dossiers antérieurs sont préservés.

## Contrôles et suivi

Les références complètes, générations, suffixes, puissances, classes, langues, pagesPDF et empreintes SHA-256 sont contrôlés. Les PDF restent inchangés ; les aperçus sont rendus depuis la page du modèle. Les chemins locaux restent distincts des fragments #page=N utilisés dans les liens. Aucun héritage vers un parent à variantes.

Les preuves et sauvegardes sont dans `storage/app/private/energy-documents-20261020/` : manifestes, URL précises, SHA, décisions d’exclusion et recette indépendante. Les résultats finaux des tests et contrôles HTTP/navigateur sont consignés dans `verification.json`.

[Suivi des 3 709 références](SUIVI-ENERGIE-2026-10-20.csv) · [Chauffage restant à qualifier/compléter](ENERGIE-CHAUFFAGE-RESTANT-2026-10-20.csv). La liste chauffage comprend aussi accessoires, ensembles et cas à qualifier ; elle ne constitue pas un décompte juridique des appareils assujettis.

Cette intervention reste locale. Aucun déploiement, push Git, envoi Google, migration ou écriture SQL. Les dossiers manquants et contradictions fournisseur restent des réserves ; aucun feu vert global de publication n’est donné.

## Résultats de la recette après installation

- Suite complète : **539 tests et 49 538 assertions réussis**. Ciblage énergie :33tests,35 747assertions. Pint et vérification des espaces Git réussis.
- Dix contrôles navigateur Chrome : produits FR/EN/DE, liensPDF HTTP200, aperçus chargés, ouverture au clavier, document EN explicitement indiqué en FR, absence de débordement horizontal mobile et absence du classement retiré sur le produit Flamingo témoin.
- Catalogue : **3 714 fichiersJSON source contrôlés, aucun modifié**.
- Captures relues : [Invicta bureau](../output/qa-energy-20261020/invicta-desktop.png), [Atmos bureau EN](../output/qa-energy-20261020/atmos-en-desktop.png), [TermaTech mobile FR](../output/qa-energy-20261020/termatech-fr-mobile.png).

Les aperçus et les pages réglementaires ont été rendus et vérifiés ; le fonctionnement de tous les lecteursPDF natifs n’a pas été testé exhaustivement. Les parcours de commande, paiement, emails et stock n’ont pas été exercés dans cette recette documentaire. Aucun changement CSS/JavaScript applicatif n’impose de reconstruire les assets.
