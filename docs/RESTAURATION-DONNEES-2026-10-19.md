# Reprise des données locales HOMYA — 19 octobre 2026

Autorisation utilisateur : démarrer MariaDB XAMPP, restaurer le catalogue récupéré
et recréer les tables manquantes à partir du projet pour reprendre en local.
Le mot de passe SQL root n'est pas connu. La récupération utilise l'authentification
administrateur macOS pour créer des comptes HOMYA, sans changer root.

## Source contrôlée

`u429557141_catalogue.20261018180146.sql` : 31 763 885 octets,
SHA-256 `b542eb33f2d5e855dbf066f3aff3fba389181081838e5e5b71b824bf129ac854`.
Le dump contient cinq tables : 5 lots, 67 catégories, 3 711 produits,
23 890 traductions et 4 entrées de migrations. Il correspond au contenu de recette
décrit par OP-06/07, qui conserve également le lot métier réel.

Le lot métier `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`
contient 1 lot, 65 catégories, 3 709 produits et 23 878 traductions.
Les autres lots sont conservés dans la copie complète ; ils ne sont pas publiés
dans le catalogue de travail.

## Plan de récupération

1. Préserver et hacher le SQL original. Analyser les instructions hors connexion,
   n'extraire que les cinq CREATE et les valeurs littérales des INSERT. Les DROP,
   commentaires exécutables, réglages de session et COMMIT d'origine ne sont pas rejoués.
2. Arrêter proprement MariaDB et sauvegarder à froid son répertoire complet ainsi que
   `my.cnf`. Vérifier l'archive et conserver ses empreintes dans le dossier privé de reprise.
3. Démarrer une instance temporaire sur le même répertoire, sans réseau, avec une socket
   dans un dossier réservé à `_mysql`. Vérifier l'absence des noms cibles et comptes.
   Créer les trois schémas et les quatre comptes HOMYA bornés à ces schémas.
   Les droits de root sont comparés par empreinte, sans afficher ses informations privées.
4. Fermer la maintenance et redémarrer normalement XAMPP. Conserver son port existant,
   limiter l'écoute TCP à 127.0.0.1 et porter `max_allowed_packet` à 16 Mio, nécessaire
   aux documents d'archive. La configuration précédente est sauvegardée.
5. Restaurer intégralement le dump dans `homya_restore_op07`, recréer un schéma de
   recette vide `homya_test`, puis restaurer le seul lot métier dans `homya_local`.
   Ces noms sont reconstruits aujourd'hui ; cela ne prétend pas récupérer les anciens fichiers physiques.
6. Dans `homya_local`, exécuter les six migrations commerce et les trois migrations
   Laravel génériques, sans rejouer les migrations catalogue déjà couvertes par le dump.
   Résultat prévu : 27 tables locales, 5 tables d'archive de recette vide et 5 tables
   dans la copie complète récupérée. Aucune commande ni quantité de stock n'est inventée.
7. Vérifier les lignes restaurées, les clés étrangères, les documents JSON et la preuve
   sémantique complète du lot métier avec `CatalogueArchivePayload`. Reconstituer les
   fichiers catalogue et dictionnaires depuis ces données pour le mode local `files`.
8. Configurer les accès applicatifs privés et vérifier les pages FR/EN/DE et les tables.
   Conserver le checkout et les intégrations externes désactivés.

Tout nom déjà présent ou erreur arrête les écritures. Aucun DROP, purge, migration
fresh ou remplacement d'une base rencontrée. Les comptes applicatifs n'ont aucun droit DDL.

## Limites de récupération

Les tables commerciales recréées sont vides : commandes, messages, stocks validés,
modifications d'administration et événements non sauvegardés ne sont pas récupérés.
Le dump contient les références aux images, pas leurs fichiers. Les anciens comptes
administrateurs et fichiers privés ne sont pas restaurés à partir du schéma SQL.

La maintenance suit les options documentées par [MariaDB](https://mariadb.com/docs/server/server-management/starting-and-stopping-mariadb/mariadbd-options)
et la [procédure d'authentification 10.4](https://mariadb.com/docs/server/security/user-account-management/authentication-from-mariadb-10-4).

## Résultat

Restauration terminée et vérifiée :

| Base | Tables | Données |
| --- | ---: | --- |
| `homya_local` | 27 | 1 lot, 65 catégories, 3 709 produits, 23 878 traductions ; 13 migrations ; 22 tables applicatives vides |
| `homya_test` | 5 | Schéma d'archive vide, journal de 4 migrations |
| `homya_restore_op07` | 5 | Dump complet : 5 lots, 67 catégories, 3 711 produits, 23 890 traductions, journal de 4 migrations |

Toutes les valeurs SQL ont été comparées au dump avant commit. Le payload métier
reconstruit conserve l'empreinte historique
`49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1`.
Les 27 contraintes CHECK d'archive sont présentes. Aucun produit de recette n'est
dans le catalogue du site.

MariaDB XAMPP 10.4.28, port existant **3306**, socket XAMPP, 16 Mio, écoute locale.
Les anciennes procédures OP-xx décrivent le poste précédent sur 3307. Le catalogue
local utilise les fichiers JSON reconstruits ; les gardes de lecture SQL historiques,
qui exigent 3307 et un profil privé disparu, restent intactes.

Le compte applicatif courant est `homya_local_app`, le commerce utilise
`homya_local_commerce`. Le lecteur possède seulement les quatre SELECT d'archive :
un SELECT sur les commandes a bien été refusé. Aucun compte applicatif n'est root.
Un essai avec un mauvais mot de passe confirme le retour à l'authentification normale.
Les empreintes des lignes de comptes root avant/après création sont identiques.

Le premier essai de sauvegarde n'était pas à froid : un processus XAMPP retenait
les fichiers malgré l'annonce d'arrêt. Il n'est pas considéré comme sauvegarde
cohérente. L'arrêt a ensuite été contrôlé par les processus exacts et les fichiers
ouverts avant les nouvelles sauvegardes à froid conservées.

Une migration a été corrigée : la seconde colonne TIMESTAMP de
`commerce_paid_purchases` avait un défaut zéro implicite refusé sous NO_ZERO_DATE.
`created_at` possède maintenant un défaut explicite CURRENT_TIMESTAMP, NOT NULL ;
le service continue de fournir sa date à l'insertion. Aucun changement de prix/stock.
Les migrations déjà réussies ont été conservées, les autres ont été reprises seules.

Vérifications : 10 tests PaidPurchasesTest / 66 assertions réussis, contrôles du
parseur hors ligne, égalité exhaustive des lignes restaurées, reconstruction du
payload métier, trois catalogues CLI de 3 709 produits, neuf GET 200 réels
(accueil/catalogue/fiche FR/EN/DE), trois chemins privés refusés 404 et catalogue
observé dans le navigateur. Images indisponibles observées : leurs fichiers restent absents.

Preuves et accès privés : `storage/app/private/database-backups/recovery-20261019/`.
Fichiers 0600, dossier 0700, ignorés par Git ; ne pas les publier. `access-recovered.json`
identifie la sauvegarde à froid retenue. `restore-result.json`, `verification.json`
et `http-verification.json` détaillent les contrôles sans secret. Une nouvelle
sauvegarde SQL après restauration est conservée sous `homya_local-restored.sql` ;
sa propre restauration n'a pas été rejouée.

## Continuer dans VS Code

Ouvrir le dossier HOMYA. Le site est disponible sur
http://127.0.0.1:8000/fr/catalogue. Après redémarrage du Mac, démarrer MySQL dans
XAMPP et lancer `demarrer-local.command`, ou la tâche VS Code « HOMYA : lancer le site local ».

Les accès de l'application sont déjà dans `.env`. Ne pas remplacer par root et
ne pas réexécuter les scripts ponctuels `recover_*` / `restore_recovered_database.php`.
Pour de nouvelles migrations dans le développement local :

```sh
/Users/akm/.config/herd-lite/bin/php scripts/migrate_local_database.php
/Users/akm/.config/herd-lite/bin/php scripts/migrate_local_database.php --apply
```

La première commande simule ; la seconde applique les migrations en attente avec
le compte de schéma privé. Les migrations catalogue sont exclues de cette commande,
puisque l'archive est déjà restaurée. Le compte de schéma n'a pas le droit DROP.
