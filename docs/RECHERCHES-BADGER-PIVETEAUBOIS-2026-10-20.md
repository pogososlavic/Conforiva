# HOME CONFORT — complément Badger et Piveteaubois

Recherche du 20 octobre 2026 sur les deux réserves de la sélection Shopping. **Aucun nouvel identifiant ou visuel suffisamment vérifié pour être installé n’a été trouvé. Les deux références restent en réserve.** Cette recherche n’établit pas que ces éléments n’existent pas chez les fabricants.

## Badger : identifiant du sac papier de 15 kg

Offre 48531, lot constitué par HOME CONFORT de 65 sacs, soit 975 kg nets. La [fiche fabricant](https://www.badgerpellets.com/produit/en-sac/) confirme le conditionnement mais ne publie pas d’EAN/MPN exploitable.

Nouvelles pièces effectivement examinées : brochure officielle 2022, deux pages rendues et lues visuellement ; [actualité fabricant du 17 septembre 2026 sur l’origine imprimée sur les sacs](https://www.badgerpellets.com/lorigine-de-la-matiere-comme-mention-obligatoire-sur-les-sacs-de-pellets/) ; réponse publique des médias contenant cinq entrées « sac » ; les cinq originaux correspondants, téléchargés et examinés. La photographie du sac papier en 1221 × 1920 ne montre pas de code-barres complet. Le seul code-barres visible dans ces cinq images appartient à un autre sac plastique de 14 kg, qui est exclu.

Les pistes tierces 5420044090013 et 0540044090013 ne sont pas importées : même un chiffre de contrôle valide ne prouve pas l’attribution officielle au conditionnement vendu. Un autre code de palette commençant par 2 est également exclu. BE010, LU001 et 7A072 sont des références de certification ou de sites, pas des MPN commerciaux du sac. Aucune exemption `identifier_exists=false` n’est déduite de l’absence de résultat.

Limites : le registre ENplus est partiellement lisible dans l’outil web ; son téléchargement HTTP retourne 403. Aucun contournement ni conclusion d’absence de certification. Le résultat GS1 du candidat n’a pas été accessible ; aucune attribution GS1 n’est revendiquée. Les photographies Badger récupérées sont conservées comme preuves privées, sans publication sur le site.

**Pièce nécessaire :** photo nette du code-barres, avec ses chiffres, du sac papier Badger de 15 kg réellement fourni, ou fiche/confirmation écrite du producteur reliant explicitement l’EAN/MPN à ce sac.

## Piveteaubois : original du sac HP+ de 15 kg

Offre 46169, référence fabricant du sac G00003249, lot constitué par HOME CONFORT de 72 sacs, soit 1080 kg nets. L’autorisation d’utiliser les visuels fabricant repose sur la confirmation directe du client ; aucun contrat supplémentaire n’a été audité.

Les pages officielles FR/EN, les anciennes adresses de fiches et les métadonnées déjà archivées ont été relues. L’ancienne fiche anglaise indexée redirige désormais vers l’accueil HTML ; aucun PDF récupéré n’est prétendu. Les images des distributeurs ne sont pas assimilées automatiquement à des visuels fabricant autorisés.

Le dernier contrôle du pilote a réellement récupéré la [bibliothèque publique filtrée sur « sac »](https://www.piveteaubois.com/wp-json/wp/v2/media?search=sac&per_page=100) : 27 entrées, 17 URLs distinctes, aucune clé `original_image` révélant un original plus grand. Après exclusion des formats 10 kg, des dimensions insuffisantes et d’un ancien emballage déjà examiné, deux PNG 1000 × 750 ont été téléchargés depuis leurs URLs officielles de 2016 et 2017. Leurs octets sont strictement identiques à l’archive précédente : SHA-256 `9eb4c2a81d26c51990de2af838d0150ed84f8e274f3fc87f49d931f5f1bcb488`. Ils montrent un ancien sac « Spécial poêles » et ne permettent pas d’établir la correspondance exacte HP+ 15 kg / G00003249. Ils sont exclus. Le dernier accès réussi remplace la limite réseau de la première tentative interrompue par le pilote ; aucune décision de refus humain n’est affirmée.

La photo exacte de la [fiche fabricant du 18 mars 2026](https://www.piveteaubois.com/web/documents-techniques/fiches-techniques/pellets/PIVETEAUBOIS_FT_G00003249_Pellets_Sac_HP+_15-kg_18-03-2026.pdf) reste donc en place : JPEG natif **473 × 473**, flux DCT du PDF conservé sans réencodage, SHA-256 `1f865da264a3a61bb8c6e95f2bece1aadd119eb788f81370dab2899161ffc088`. Aucun agrandissement, fond ajouté ou génération d’image n’est utilisé pour simuler une meilleure définition.

**Pièce nécessaire :** packshot original propre du sac exact G00003249, au moins 500 × 500 pixels réels, idéalement 1500 × 1500, avec confirmation de la version d’emballage. Le seuil 500 est conservé pour cette préparation durable ; [Google en annonce l’application à partir du 31 janvier 2027](https://support.google.com/merchants/answer/6324350?hl=fr).

## Résultat et contrôles

| Contrôle | Résultat observé |
| --- | --- |
| Badger : EAN/MPN officiel exact | Inconnu ; aucun code importé |
| Piveteaubois : photo exacte de meilleure définition | Non trouvée dans les sources examinées ; photo native 473 conservée pour le site, liste Google vide |
| Préparation Shopping | Deux réserves produit maintenues ; aucun changement de statut prêt/approuvé |
| Intégrité | 3 709 JSON produits bruts et quatre registres de données/images inchangés, vérifiés par SHA |
| Recette | Sources, types/dimensions, comparaison des fichiers et revue visuelle indépendante ; aucune modification fonctionnelle nécessitant une nouvelle suite applicative ou un build |
| Base et commerce | Aucune requête SQL, commande de recette, modification de prix/stock ou paiement dans ce complément |
| Publication et messages | Aucun push, déploiement, contact fournisseur ni soumission Google |

La sélection de 150 reste celle préparée précédemment : 148 sans blocage technique local propre au produit, deux en réserve ; toutes restent soumises aux contrôles globaux du domaine, de l’email, du paiement, du site public et des comptes Google. Ce complément ne constitue pas une approbation Google.

[Brouillon de demande aux fabricants](DEMANDE-BADGER-PIVETEAUBOIS-2026-10-20.txt), préparé sans envoi. Les originaux et audits sont conservés dans `storage/app/private/remaining2-20261020/`. Les rapports intermédiaires décrivent leurs propres étapes ; le résultat final de la bibliothèque Piveteaubois est documenté dans `root/piveteau-final-result.json`.
