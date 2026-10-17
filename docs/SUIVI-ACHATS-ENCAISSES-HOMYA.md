# Suivi des achats encaissés HOMYA

17 octobre 2026. Implémentation locale ; activation en production et transmission Google non effectuées.

## Règle confirmée

La demande utilisateur autorise la mise en place et les tests d'un suivi comptant uniquement les virements effectivement reçus. La réception bancaire reste vérifiée par HOMYA via l'administration ou la commande privée existante. Aucun accès automatique à la banque n'est ajouté et un justificatif client ne constitue pas une réception.

Le suivi vérifie simultanément le statut `paid`, `paid_at`, le montant exact et la devise EUR dans la réception privée chiffrée, ainsi que l'événement `bank_paid` correspondant et son empreinte. Une simple modification du statut sans preuve concordante ne suffit pas. Les commandes créées, virements attendus, justificatifs transmis, refus et expirations ne sont pas des achats.

Le rapprochement serveur utilise la date de confirmation de réception (`paid_at`) en UTC ; il ne dépend pas d'un retour du client sur la page de commande. Les règles de rapprochement manuel des virements tardifs ou partiels restent inchangées. Les paiements carte ne sont pas activés dans le parcours actuel et ne sont pas simulés dans ce suivi.

## Mesure interne et données préparées pour Google

`PaidPurchases` calcule le nombre de réceptions vérifiées et leur montant TTC en centimes. Il prépare séparément les événements utilisables pour la mesure e-commerce : `purchase`, identifiant unique de commande, devise, valeur HT, TVA issue du snapshot de commande, livraison et articles. Le calcul HT suit l'arrondi entier utilisé par les documents commerciaux. Les identifiants produit suivent le flux Merchant Center, y compris les variantes.

La mesure financière reste disponible si le snapshot TVA est incomplet ; l'événement à transmettre est alors signalé comme incomplet, sans inventer un taux. Une livraison payante nécessiterait de préciser son traitement TVA ; elle n'est pas supposée prête par ce lot.

La table dédiée `commerce_paid_purchases` conserve un snapshot chiffré et une seule ligne par commande grâce à sa clé primaire. Les reprises et passages simultanés ne créent pas de second achat. Les écritures se font par rapprochement séparé ; un problème de mesure ne bloque pas la confirmation du paiement. Aucun événement n'est émis depuis la page de confirmation client ou par le navigateur.

Les événements ne contiennent ni nom client, adresse email, téléphone, adresse de livraison, IBAN, justificatif ou référence bancaire. Les résultats de commande affichent uniquement des agrégats. Ce registre interne n'est pas une preuve de réception dans GA4 ni d'attribution Google Ads.

## Commandes

Lecture seule, sans création de table ou de snapshot :

```sh
php artisan homya:measure-paid-purchases --from=2026-10-17 --to=2026-10-18
```

`--from` est inclusif et `--to` exclusif, tous deux en UTC. `--apply` ajoute les snapshots dans la table existante ; cette option n'exécute aucune migration et ne transmet rien à Google. `verified_purchases` et `received_cents` décrivent les encaissements concordants ; `measurement_ready` décrit les snapshots utilisables, `unverified_or_incomplete` les dossiers à examiner, et `new_records` les nouvelles lignes ajoutées. La réexécution du même intervalle ne multiplie pas les achats : les chiffres sont un état de la période, pas des incréments à additionner entre exécutions.

Le planificateur existant possède une tâche de rapprochement par minute, désactivée par défaut. `HOMYA_PAID_PURCHASE_MEASUREMENT_ENABLED=true` et une date explicite `HOMYA_PAID_PURCHASE_MEASUREMENT_START_DATE=YYYY-MM-DD` sont nécessaires après validation du schéma. Le rapprochement reprend depuis cette date après une interruption ; il n'impose pas de reprise de l'historique antérieur. Le fonctionnement effectif du cron en production reste à vérifier.

## Plan d'activation à valider

1. Vérifier la cible et les droits de la connexion `commerce`, la sauvegarde disponible, la présence des tables commerce existantes et l'absence de `commerce_paid_purchases`.
2. Appliquer uniquement `database/migrations/commerce/2026_10_17_000005_create_paid_purchase_measurement.php`. Il s'agit d'une nouvelle table avec FK restrictive, sans modification ou suppression des commandes. Les DDL MariaDB ne sont pas transactionnels : inspecter tout résultat partiel avant une reprise. Aucun rollback destructif automatique.
3. Effectuer une mesure en lecture seule sur la période convenue ; rapprocher les agrégats de la source bancaire privée et examiner les dossiers incomplets.
4. Activer l'enregistrement et le planificateur avec une date de départ validée ; vérifier le premier rapprochement puis sa réexécution idempotente. Ne pas confirmer une commande réelle uniquement pour tester.
5. Déployer après autorisation spécifique, puis vérifier le cron et les résultats internes sur l'hôte réel.

Aucune migration XAMPP/production, activation `.env`, confirmation bancaire réelle ou déploiement n'a été effectué pendant l'implémentation.

## Dépendances Google non résolues

Le connecteur Google Analytics a retourné une liste de comptes vide. La console `analytics.google.com` n'est pas accessible dans Chrome (`ERR_NAME_NOT_RESOLVED`). Aucun identifiant GA4/GTM n'est renseigné dans les variables locales inspectées. Cela ne prouve pas qu'aucune propriété existe dans le compte Google.

Le raccordement externe n'est pas implémenté ni activé : il faut identifier le flux HOMYA réel, préparer le consentement et les identifiants de visite/attribution, puis le transport serveur adapté aux paiements différés. Ne pas inventer de `client_id`, d'identifiant de flux, de consentement ou d'attribution. Les événements historiques internes ne sont pas envoyés rétroactivement à Google.

Pour GA4, le Measurement Protocol complète le marquage web et exige notamment le `client_id` ; un succès HTTP ne démontre pas l'ingestion. Il faudra vérifier le payload avec le serveur de validation puis la réception dans la propriété, et contrôler ensuite l'import d'une unique source d'achat dans Google Ads. Les délais d'attribution des paiements différés devront être testés et documentés.

Sources officielles consultées : [Measurement Protocol](https://developers.google.com/analytics/devguides/collection/protocol/ga4/reference), [vérification de l'implémentation](https://developers.google.com/analytics/devguides/collection/protocol/ga4/verify-implementation), [déduplication par transaction](https://support.google.com/analytics/answer/12313109).

## Vérification

Tests dédiés SQLite en mémoire, HTTP externe interdit : réceptions exactes, statuts falsifiés, empreinte incorrecte, montants incorrects, expiration, absence de TVA, cohérence HT/TVA/TTC, identifiants Merchant, absence de données personnelles, reprises, périodes UTC et activation planifiée. Ces tests ne valent pas validation MariaDB ou GA4.
