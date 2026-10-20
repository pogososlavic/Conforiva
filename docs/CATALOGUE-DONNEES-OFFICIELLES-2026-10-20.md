> Lot antérieur conservé. Le [bilan des autres marques](CATALOGUE-AUTRES-MARQUES-2026-10-20.md) porte désormais le total à 210 EAN / 245 MPN, 57 fiches à compléter et 375 anomalies. Les CSV sont actualisés.

# Données officielles du catalogue — lot du 20 octobre 2026

## Résultat installé

**188 produits supplémentaires documentés : 182 EAN/GTIN et 153 références fabricant (MPN).** Les identifiants sont présents sur les fiches FR/EN/DE et dans les dossiers de préparation Merchant. Les références vendeur restent distinctes des codes fabricant.

| Fabricant | Produits du nouveau lot | EAN ajoutés/vérifiés officiellement | MPN explicites ajoutés |
| --- | ---: | ---: | ---: |
| LG | 146 | 146 | 146 |
| Bosch | 36 | 36 | 1 |
| Philips | 6 | 0 | 6 |

Le total documenté est maintenant **192 produits**, avec **186 EAN et 157 MPN**, en incluant les quatre dossiers du lot précédent. Ces nombres comptent des champs vérifiés et non des validations Google. Pour 35 Bosch, la fiche donne le modèle et son EAN, mais aucun champ explicitement intitulé référence commerciale n’a été converti arbitrairement en MPN. Les suffixes Philips `/00`, `/20` et `/60` sont conservés.

[Tableau des 192 références et sources officielles](IDENTIFIANTS-OFFICIELS-2026-10-20.csv).

## Anomalies corrigées

- LG GTBV20PYGKD : EAN de l’archive 8806096521663 remplacé en affichage par **8806084813329**, publié dans le produit principal de la page officielle du modèle exact.
- LG GTBV20SWGKD : 8806096521656 remplacé en affichage par **8806084813466**, selon la même vérification.
- LG RH9V50WH : la ligne EAN secondaire tronquée `880609138660` est supprimée ; la valeur complète **8806091386601** reste affichée une seule fois.
- Douze lignes EAN secondaires sont regroupées, dont celle de RH9V50WH et onze doublons stricts. Seuls les libellés EAN exacts sont concernés ; les programmes Jeans, la disponibilité des pièces et les champs Modèle restent conservés.
- Neuf fiches chauffage sans caractéristiques reçoivent **46 faits vérifiés**, traduits en français, anglais et allemand. Poids net et poids emballé ne sont pas assimilés ; les axes des dimensions sont précisés.

| Modèle fabricant | Référence vendeur | Faits ajoutés | Source |
| --- | --- | ---: | --- |
| MONZA | 47298 | 5 | [Fabricant](https://bronpi.com/producto/monza/) |
| LORETO | 94723 | 5 | [Fabricant](https://bronpi.com/producto/loreto/) |
| LIRA | 81363 | 5 | [Fabricant](https://bronpi.com/producto/lira/) |
| ARUS | 32578 | 5 | [Fabricant](https://bronpi.com/producto/arus/) |
| FREE-11 | 64956 | 5 | [Fabricant](https://bronpi.com/producto/free-11/) |
| FREE-6 | 93356 | 5 | [Fabricant](https://bronpi.com/producto/free-6/) |
| BRIGITTA.16 | 11238 | 5 | [Fabricant](https://www.lanordica-extraflame.com/en/wood/wood-burning-stove/brigitta16) |
| JENNIFER.16 | 42265 | 5 | [Fabricant](https://www.lanordica-extraflame.com/en/wood/wood-burning-stove/jennifer16) |
| TEA.16 | 45945 | 6 | [Fabricant](https://www.lanordica-extraflame.com/en/wood/wood-burning-stoves/tea16) |

Les fiches sans caractéristiques passent de **97 à 88** et les produits avec anomalies de **415 à 406**. Les 317 conflits de caractéristiques, deux titres et un intervalle de puissance incohérent restant ne sont pas effacés sans preuve ; les ensembles peuvent se chevaucher.

## Informations encore manquantes et réserves

1. **3 517 lignes produit sans identifiant fabricant documenté**, dont des parents à variantes. Fournir EAN/GTIN attribués, MPN et marque exacts, avec une association par finition/variante. Le tableau fournisseur conserve aussi les 92 variantes des 26 parents ; une référence de famille ne remplace pas celle de chaque option. [Liste des lignes encore à documenter](IDENTIFIANTS-EN-ATTENTE-2026-10-20.csv).
2. **LG : trois références exclues** : le pack `GFM61MCCSD.GLM71MC` ne reçoit pas l’EAN du congélateur seul ; `GMG960EVEE` n’a pas de page officielle exacte retrouvée ; `GTF8659PS` renvoie une page générique sans identité de produit vérifiable.
3. **Bosch/Siemens** : `KGE49AICA-1` reste sans document exact ; le suffixe `-1` n’est pas retiré pour utiliser un modèle voisin. La référence catalogue Siemens `KSN36VLDG` renvoie à une fiche qui déclare explicitement Bosch : association mise en réserve, marque/titre/photo conservés en attendant confirmation du produit réellement vendu.
4. **Philips** : aucun EAN officiel retrouvé sur les six pages exploitées. `BAR500/00` et `BAR700/00` renvoient 404. Le titre fabricant de `BAR307/20` contient lui-même « Black » et « Blanche » ; la couleur n’est pas arbitrée à partir de cette contradiction.
5. **Chauffage** : 88 fiches restent sans caractéristiques structurées. BRONPI HYBRID présente une différence de version et de poids (150 contre 122 kg) ; aucun profil n’est installé. Les dimensions de Loreto divergent selon les documents ; elles sont omises. Pour Tea.16, la hauteur du dessin incluant le conduit n’est pas utilisée comme hauteur de l’appareil.
6. **Autres données officielles contradictoires** : cinq pages LG contiennent des poids emballés aberrants (1 860 ou 2 030 kg), et F964X71WHST présente deux capacités de lavage. Les 11 619 champs extraits ne sont pas importés en bloc. Aucune de ces valeurs douteuses n’est ajoutée.
7. **Énergie et variantes** : les 108 réserves Aquaflam demeurent bloquantes. Les cinq appareils à deux modules conservent leurs certifications séparées. L’audit signale encore 2805 dossiers dont l’assujettissement reste à qualifier ; ce nombre ne désigne pas autant de produits obligatoirement assujettis. Les photos et identifiants propres aux variantes restent à confirmer.
8. **Avant Merchant Center** : compléter la provenance des titres/descriptions, la revue des caractéristiques et variantes, puis le domaine public HTTPS, l’e-mail définitif, le paiement HOME CONFORT et les paramètres réels d’expédition/livraison. Aucune nouvelle identité, aucun stock ni délai n’a été supposé.

[Tableau fournisseur actualisé, 3 801 lignes](INFORMATIONS-FOURNISSEURS-MERCHANT-2026-10-20.csv) · [406 produits présentant encore une anomalie](CATALOGUE-ANOMALIES-MERCHANT-2026-10-20.csv).

## Vérifications

- **559 tests / 63 795 assertions réussis**, tests ciblés 55 / 9 155, Pint et espaces Git du lot conformes.
- Revue indépendante : modèles exacts, originaux HTML/PDF et SHA, chiffres de contrôle GTIN, absence de collision et exclusion de l’association Bosch/Siemens erronée. 11 387 assertions indépendantes sur le service et les trois langues.
- Comparaison de **11 127 projections produit** : seuls les 197 profils prévus changent par langue ; prix, descriptions, noms et images identiques. **3 714 JSON source inchangés**, registre énergétique inchangé.
- Rendu de **12 pages produit** en FR/EN/DE : caractéristiques et identifiants présents dans les tableaux visibles, sans accès à MariaDB.
- Audit final de 3 709 produits dans chaque langue, avec limite mémoire 512 Mo, sans SQL, contrôle du stock ou vérification du paiement. Les valeurs de configuration « inventaire désactivé » du rapport concernent seulement ce mode hors connexion ; elles ne modifient pas les réglages du site.
- **968 dossiers non approuvés**, empreintes conservées non approuvées. **Zéro offre exportable ou envoyée à Google.** Le stock déclaré de 500 unités par produit, la base et les moyens de paiement n’ont pas été modifiés.
- Service séparé de l’archive : chaque règle exige le SHA du JSON source complet, la référence exacte, une fiche complète sans variantes. Toute modification de la source invalide la règle plutôt que de réutiliser silencieusement une ancienne preuve.

Les originaux et preuves compressés sont archivés dans `storage/app/private/official-catalogue-20261020/`, avec correspondance des chemins et SHA d’origine. Les sauvegardes des fichiers remplacés sont conservées dans `before/`. Le rapport d’audit final est aussi dans `output/merchant-official-identifiers-20261020/`.

## Sources et portée

Sources fabricant détaillées dans le CSV : [LG France — exemple F14R33WHS](https://www.lg.com/fr/lavage/lave-linge/f14r33whs-lave-linge-standards/), [Bosch KAD93AIDP — fiche officielle](https://media3.bosch-home.com/Documents/specsheet/fr-BE/KAD93AIDP.pdf), [Philips BAR320/00](https://www.philips.fr/c-p/BAR320_00/baristina_espresso_machine), et notices fabricant du tableau chauffage.

Règles Google reconsultées le 20 octobre 2026 : [GTIN du modèle et des variantes](https://support.google.com/merchants/answer/6324461?hl=fr), [spécifications produit](https://support.google.com/merchants/answer/7052112?hl=fr). Les identifiants ne sont ni inventés ni remplacés par une fausse déclaration d’absence. Une correspondance exacte de modèle vérifiée ne constitue pas une décision d’approbation de Google ; les contrôles du compte et du domaine public restent à effectuer.
