> Bilan du passage précédent. [Dernier état : recherches complémentaires du 20 octobre 2026](CATALOGUE-RECHERCHES-COMPLEMENTAIRES-2026-10-20.md). Les CSV liés présentent désormais les chiffres actualisés.

# Autres marques — données officielles installées le 20 octobre 2026

## Résultat

**98 produits supplémentaires identifiés : 24 EAN et 88 références fabricant (MPN).** Le catalogue dispose désormais d’identifiants documentés pour **290 produits**, soit **210 EAN et 245 MPN**. Les champs absents restent absents ; aucun numéro de modèle EPREL, SKU vendeur ou code de sac n’est promu arbitrairement en identifiant de palette/kit.

| Marque | Nouvelles offres identifiées | Nouveaux EAN | Nouveaux MPN |
| --- | ---: | ---: | ---: |
| Justus | 40 | 0 | 40 |
| Wamsler | 17 | 7 | 17 |
| Schiedel | 24 | 0 | 24 |
| Whirlpool | 8 | 8 | 0 |
| Indesit | 1 | 1 | 1 |
| Beko | 2 | 2 | 0 |
| Candy | 2 | 2 | 2 |
| Haier | 3 | 3 | 3 |
| Fagor | 1 | 1 | 1 |

[Identifiants et sources des 290 produits](IDENTIFIANTS-OFFICIELS-2026-10-20.csv).

## Les 88 fiches ciblées

- **31 fiches renseignées** : 24 Schiedel ; cinq combustibles au conditionnement exact (Crépito, Moulin Bois Énergie, Van Roje, TotalEnergies et Starforest) ; deux poêles FIREMATIC BR9 Premium et C12.
- **Huit fiches enrichies partiellement** : sacs HP+ Piveteaubois, Badger et Woodstock ; trois composants de kits Sunex ; poêle inclus dans le pack BR9 ; modèle O9 dont les valeurs contradictoires restent exclues.
- **49 fiches sans nouveau fait public retenu** : 35 combustibles sans producteur identifiable et 14 autres références, dont six modèles FM sans correspondance fabricant prouvée.
- Il reste donc **57 fiches à compléter**, dont les huit déjà enrichies partiellement. Les réserves de ces huit offres sont conservées : les données d’un sac, d’un capteur ou d’un poêle seul ne valident pas une palette, un kit solaire ou un pack avec conduit.
- **179 caractéristiques physiques/techniques** sont ajoutées ou corrigées en FR/EN/DE, dont 104 sur Schiedel, 69 sur les combustibles/poêles/composants retenus et six poids nets Wamsler. Deux classifications d’appareil Haier sont aussi clarifiées séparément.

[Suivi individuel des 88 fiches](SUIVI-88-FICHES-2026-10-20.csv) · [57 fiches restant à vérifier](FICHES-RESTANTES-VERIFICATION-2026-10-20.csv).

## Recherche des fournisseurs des 37 combustibles génériques

Les 37 archives, les photos et leur provenance ont été inspectées. Deux marques ont été confirmées par le nom, l’emballage lisible et une source officielle : **HP+ Piveteaubois (réf.46169)** et **TotalEnergies/Total Pellets Premium (réf.76756)**. Les marques affichées sont corrigées dans les listes et les fiches ; aucune usine ni aucun fournisseur commercial de HOME CONFORT n’est déduit de cette identification de marque.

Pour HP+, seules les propriétés du sac de 15 kg identifié sont ajoutées ; le conditionnement de la palette reste à confirmer. Pour Total, l’offre 66 sacs de 15 kg concorde avec les informations de la marque ; aucun GTIN du sac n’est attribué à la palette.

Pour les **35 autres références**, les images montrent des sacs, filets ou palettes sans identité de producteur vérifiable. Une forme RUF ou Pini Kay, une essence, un calibre ou une photo ressemblante ne permet pas de déterminer le fournisseur. Aucun fabricant, EAN, certification ou caractéristique de bois/charbon n’a été inventé. Une facture/trace d’achat ou une identification lisible de l’emballage devra relier la référence vendeur au producteur et au lot/conditionnement. Les offres restent bloquées dans la préparation Merchant.

[Dossier fournisseur des 37 combustibles](RECHERCHE-FOURNISSEURS-37-COMBUSTIBLES-2026-10-20.csv). Les preuves privées sont aussi conservées par référence.

## Corrections et limites importantes

- **Six Wamsler** : remplacement des anciens champs de poids par le **poids net** explicitement déclaré au code article complet : Methis 152 kg, Montafon S 127 kg, Gron 144 kg, Trion 134 kg, Romantik II 105 kg, Romantik-B II 127 kg. Aucun poids brut/transport calculé.
- **Whirlpool WHK25364W4E** : le titre erroné « encastrable 1 porte » est remplacé par une désignation de réfrigérateur-congélateur 316 L blanc, conformément au produit officiel posable. Le titre est corrigé dans les listes et les détails, sans changer le slug, le prix ou la description déjà correcte. Le titre réécrit/traduit porte la provenance algorithmique appropriée.
- **Haier HTW5618ENMG/ENPW** : les anciennes classifications contradictoires Américain/Combiné sont remplacées par le type documenté de combiné à trois portes. Les autres conflits éventuels restent ouverts. Pour RTG785NHD, aucune dimension n’est importée : les axes divergent entre documents officiels.
- **Schiedel** : les MPN sont les Art.-Nr. des bonnes lignes et colonnes du catalogue 2026. Les dimensions LB90 sont **intérieures** ; diamètre nominal, Di et Da restent distincts. Le poids est donné « par unité », sans supposer net/brut. Aucun tarif du catalogue n’est importé.
- **FIREMATIC BR9** : les 8 kW sont libellés **puissance thermique utile maximale**, conformément à la notice, sans conversion en puissance nominale. Le pack conserve une réserve sur son contenu. C12 indique explicitement une puissance nominale dans sa source. Les autres valeurs O9 discordantes restent omises.
- **Dix Justus exclus** : générations Austin 5 II, Island Aqua II, Texas V, Vegas V ou finitions Mino divergentes. Deux Usedom 5D / 5 D ne diffèrent que par la typographie et gardent le numéro de commande complet identique. Aucun suffixe Wamsler n’est supprimé.
- **Accès et champs manquants** : le portail Oranier refuse certaines lectures et d’anciens PDF redirigent en boucle ; cela ne prouve pas une absence d’EAN. Pour Samsung, les suffixes de pays/versions manquent dans plusieurs références archivées. Deux codes modèle exacts sont retrouvés, mais sans champ EAN/MPN explicite retenu ; ils restent des pistes documentées, sans promotion automatique en MPN.
- **Six modèles FM exclus de l’affichage des nouveaux faits** : les pages consultées sont celles d’un distributeur, sans preuve suffisante de correspondance OEM/génération. Les propositions restent dans les recherches privées. Les propres modèles FIREMATIC de la marque créée par LVZ sont traités séparément, sur la base de ses documents et de son rôle documenté.
- **Conditionnements et versions** : Woodstock 78 sacs dans l’offre contre 72 chez le fabricant, BR12 contre BR12 Premium, BRONPI HYBRID et composants Sunex gardent leurs réserves. Aucun emballage voisin, masse de palette calculée ou version voisine n’est substitué.
- **Starforest** : le PDF primaire Luzeal confirme 70 sacs de 15 kg. Le lecteur web a fourni le texte officiel ; le téléchargement binaire a échoué. La preuve installée est explicitement une **extraction JSON**, avec son propre SHA, pas un original PDF prétendument récupéré.

## Vérifications et données encore nécessaires

**562 tests / 66 693 assertions réussis**, contrôles ciblés 53 / 11 956, Pint et espaces Git conformes. La revue indépendante a contrôlé les originaux, SHA, modèles complets, générations, finitions et alignements des tableaux ; 18 205 assertions supplémentaires portent sur les projections et leurs gardes.

Les 3 709 produits ont été comparés dans les trois langues, soit **11 127 projections** : seuls les **113 profils prévus** changent. Les prix, images, descriptions et **3 714 JSON source sont inchangés**, tout comme le registre énergétique. **27 pages produit** représentatives ont été rendues et leurs faits vérifiés dans les tableaux visibles. Les cartes et détails donnent les mêmes marques/titres corrigés.

L’audit final signale **375 produits avec anomalies** (contre 406), **3 419 lignes produit sans identifiant fabricant documenté**, 317 références avec conflits de caractéristiques, deux titres et un intervalle de puissance restant à contrôler ; les ensembles se chevauchent. Le tableau fournisseur conserve les 92 variantes des 26 parents. Les photos, identifiants et attributs propres à ces variantes restent nécessaires.

[Tableau fournisseur actualisé, 3 801 lignes](INFORMATIONS-FOURNISSEURS-MERCHANT-2026-10-20.csv) · [Identifiants encore à documenter](IDENTIFIANTS-EN-ATTENTE-2026-10-20.csv) · [375 anomalies restantes](CATALOGUE-ANOMALIES-MERCHANT-2026-10-20.csv).

Les 108 réserves Aquaflam, les certifications par module et les autres limites énergétiques sont conservées. La provenance des textes, le domaine HTTPS, l’e-mail définitif, le paiement et les paramètres réels de livraison restent à finaliser avant Merchant Center.

**1 027 dossiers demeurent non approuvés. Zéro offre exportable ou soumise à Google.** Les vérifications ont été réalisées sans requête MariaDB ni modification des stocks/commandes/paiements. Le stock déclaré de 500 unités par produit reste inchangé. La désactivation de l’inventaire dans le rapport concerne uniquement le mode d’audit hors connexion, pas la configuration du site.

Les faits sont appliqués par projection, avec SHA du JSON source complet et référence exacte ; toute source modifiée invalide la règle. Pour les trois corrections de marque/titre, les listes compactes relisent la source complète gardée, puis n’appliquent que le nom/la marque. Les fiches partielles conservent `missing_specifications` et une réserve de portée ; elles ne sont pas marquées complètes pour Google.

## Sources et preuves

Les sources de chaque produit sont dans les CSV et les preuves privées `storage/app/private/other-brands-20261020/`. Originaux HTML/PDF, captures d’extraction identifiées comme telles, tableaux rendus, résultats de tests et SHA sont conservés, avec un index permettant de retrouver les originaux compressés. Les fichiers remplacés sont sauvegardés dans `before/`.

Exemples de sources primaires : [catalogue Schiedel 2026](https://www.schiedel.com/downloads/de-DE/Preislisten/preisliste-schiedel-edelstahl-und-kunststoff-schornsteinsysteme-2026-einseitig.pdf), [Justus Agero W+](https://www.justus.de/de/produkt/kaminofen-agero-w), [fiche Wamsler Methis](https://wamsler.eu/wp-content/uploads/2026/02/Methis-NS-Datenblatt.pdf), [Beko GN1416240XBRN](https://www.beko.fr/froid/refrigerateur/refrigerateur-4-portes/gn1416240xbrn), [fiche Haier HTW5618ENMG](https://docgenerator.candy.it/docgenerator/productdetails?lan=FR&mkt=fr&sku=34005729), [Fagor FSP164EW](https://fagorelectromenager.fr/product.php?slug=fsp164ew).

Règles Google reconsultées : [MPN attribué par le fabricant et variantes](https://support.google.com/merchants/answer/6324482?hl=fr), [GTIN du modèle exact](https://support.google.com/merchants/answer/6324461?hl=fr). Les contrôles locaux ne constituent ni une validation de compte ni une approbation Google.
