# Vérification des images et logos HOMYA — 19 octobre 2026

> Mise à jour qualité : les deux photos de faible définition ont été remplacées par des visuels plus détaillés du modèle exact. Voir [le rapport des deux améliorations](AMELIORATION-2-PHOTOS-2026-10-19.md). Les réserves ci-dessous décrivent leur état antérieur.

> Mise à jour : les 21 photos alors manquantes ont été installées et vérifiées. Couverture actuelle : 3 709/3 709. Voir [le complément documenté](PHOTOS-COMPLEMENTAIRES-2026-10-19.md). Les chiffres ci-dessous décrivent l’audit historique avant ce complément.

Demande : rechercher les autres images et logos manquants et les réinstaller
dans le périmètre du site local, en préservant les sources et données restaurées.

## Correctif

L'image de la page **À propos** pointait encore vers
`/media/home/interieur-display.webp`, disparu après la réinitialisation.
La vue utilise maintenant la photographie de salon CC0 déjà installée sur
l'accueil, avec variantes 960/1 600 px et dimensions correctes. C'est un
remplacement documenté, pas la récupération de l'ancienne illustration.
Source et licence : [manifeste des photos libres](HOME-IMAGE-CREDITS-2026-10-19.json).
Aucune référence à l'ancien fichier ne reste dans les vues.

Le pictogramme de virement rétabli lors de l'intervention précédente est bien
présent et servi. Aucun autre logo manquant n'a été identifié dans les contrôles
ci-dessous ; aucun logo d'une banque ou d'un prestataire tiers n'a été inventé.

## Contrôles exécutés

| Contrôle | Résultat observé | Preuve |
|---|---|---|
| Références statiques | 235 fichiers examinés, 27 références résolues. Seule absence initiale : l'ancienne illustration À propos, corrigée. | `output/qa-site-media-20261019/static-audit.json` |
| Pages publiques FR/EN/DE | 99 URLs contrôlées : accueil, informations, catalogue général et ses cinq univers, promotions, panier, commande, déclaration de retour, assistance et neuf fiches représentatives par langue. | `http-audit.json` dans le même dossier |
| Ressources locales publiées | 827 ressources distinctes accessibles ; aucune ressource manquante ou illisible détectée. Images, `srcset`, galeries, logos, favicons, garanties, documents liés, CSS, JavaScript et police contrôlés. | `http-audit.json` |
| Originaux catalogue | 18 454 images : tailles et SHA-256 conformes, 3 450 463 473 octets logiques. Les JSON archivés restent inchangés. | `storage/app/private/media-recovery-20261019/verification.json` |
| Variantes responsive | 3 670 sources ; 10 384 références correspondant à 7 862 fichiers uniques. Existence, format, dimensions, taille et SHA-256 conformes. | rubrique `responsive` de `http-audit.json` |
| Identité et pictogrammes | Logos HOMYA web/PDF, favicons, icône Apple, virement, trois notices de garantie FR/EN/DE et ressources énergétiques présents et valides. Drapeaux et icônes publiques intégrés en SVG ; police et six icônes admin présentes dans le build. | Audit statique indépendant |
| Correction À propos | Image observée chargée dans le navigateur mobile ; fichier 960 px sélectionné via `srcset`. | `about-mobile.png` et `about-restored.png` dans le dossier de preuves |
| Régression des pages d'information | 3 tests existants, 444 assertions réussies, sans base. Syntaxe Python et `git diff --check` réussis. | Sorties de la session |

Sur les 99 URLs, 93 répondent 200. La commande et l'assistance répondent 503 dans
chacune des trois langues avec la configuration actuelle. Les ressources de leurs
pages d'indisponibilité ont également été inspectées ; elles n'ajoutent aucune
image manquante. Leur activation fonctionnelle ne fait pas partie de cette
restauration des médias.

## Limites qui subsistent

**3 688 produits sur 3 709 disposent d'une photo affichable.** Les 21 exceptions
connues n'ont pas été masquées par une image générique présentée comme réelle :
20 modèles LG dont les sources constructeur refusent l'accès, et Bronpi NICOL,
dont l'ancienne capture ne peut pas être reproduite exactement avec les éléments
retrouvés. Liste complète :
`storage/app/private/media-recovery-20261019/verification.json`, rubrique
`products_without_display_photo`. Voir aussi le
[rapport de restauration des photos](RESTAURATION-PHOTOS-2026-10-19.md).

Les 3 709 fiches dans les trois langues n'ont pas toutes été rendues par HTTP :
l'intégralité des fichiers du catalogue et des variantes est vérifiée sur disque,
complétée par les pages représentatives ci-dessus. Les écrans privés
d'administration et les PDF générés n'ont pas été ouverts ; leurs références
statiques ont été contrôlées. Les liens de crédit, documents externes et images
Open Graph utilisant l'origine de production ne sont pas téléchargés depuis
Internet ; les photos correspondantes ont été vérifiées localement.

Script réutilisable : `scripts/audit_site_media.py`, limité à une origine HTTP
locale et aux GET, sans soumission de formulaire. `--offline` vérifie uniquement
les variantes. L'audit ne change ni prix, ni stocks, ni comptes, ni secrets,
ni bases SQL. Les GET publics peuvent créer une session technique locale.

## Sauvegardes

- Photos du catalogue : `storage/app/private/media-backups/HOMYA-photos-20261019.tar`.
- Complément à jour des médias statiques, références corrigées et preuves :
  `storage/app/private/media-backups/HOMYA-medias-verifies-20261019.tar`.

Les archives sont locales : les copier sur un autre support pour les préserver
d'une nouvelle réinitialisation du disque.
