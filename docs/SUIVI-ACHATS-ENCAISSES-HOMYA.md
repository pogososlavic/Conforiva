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

### Création GA4 effectuée le 17 octobre 2026

Après autorisation explicite de création et confirmation distincte d'acceptation des conditions Analytics françaises et de traitement des données, le compte et la propriété ont été créés dans la session Google HOMYA. L'interface confirme la création et affiche le flux Web :

- Compte HOMYA : `412335293`.
- Propriété HOMYA — homya.eu : `559384141`.
- Flux HOMYA — site Web, URL `https://homya.eu` : `16146111479`.
- Identifiant de mesure : `G-MW0CXWV7F9`.
- Paramètres saisis : euros, fuseau France correspondant à Paris, Maison et jardinage, 1 à 10 employés, objectifs ventes et trafic.
- Partages de données facultatifs désactivés ; mesures améliorées désactivées pour éviter une collecte automatique non maîtrisée, notamment des formulaires.

La page d'accueil de cette propriété affiche encore « Aucune donnée reçue ». La création n'installe pas la balise sur Laravel et ne raccorde pas le registre des paiements. Aucun secret Measurement Protocol n'a été créé, aucun identifiant client ou consentement n'a été inventé et aucun achat de test n'a été transmis.

Le déploiement précédent du registre interne est déclaré effectué par l'utilisateur. Le raccordement GA4 nécessite encore le marquage Web avec consentement, la capture d'un identifiant de visite autorisé pour la commande, le respect du retrait du consentement, puis le transport serveur après réception bancaire et son contrôle de déduplication. L'accès à hPanel demandé pour ce raccordement aboutit à un écran de connexion ; aucune session Hostinger authentifiée n'est actuellement disponible. Aucun déploiement supplémentaire effectué dans cette phase.

Les paragraphes suivants décrivent le blocage d'accès antérieur, désormais résolu pour la console Analytics :

Le connecteur Google Analytics a retourné une liste de comptes vide. La console `analytics.google.com` n'est pas accessible dans Chrome (`ERR_NAME_NOT_RESOLVED`). Aucun identifiant GA4/GTM n'est renseigné dans les variables locales inspectées. Cela ne prouve pas qu'aucune propriété existe dans le compte Google.

Le raccordement externe n'est pas implémenté ni activé : le flux HOMYA est maintenant identifié, mais le consentement et les identifiants de visite/attribution, puis le transport serveur adapté aux paiements différés, restent à préparer. Ne pas inventer de `client_id`, de consentement ou d'attribution. Les événements historiques internes ne sont pas envoyés rétroactivement à Google.

Pour GA4, le Measurement Protocol complète le marquage web et exige notamment le `client_id` ; un succès HTTP ne démontre pas l'ingestion. Il faudra vérifier le payload avec le serveur de validation puis la réception dans la propriété, et contrôler ensuite l'import d'une unique source d'achat dans Google Ads. Les délais d'attribution des paiements différés devront être testés et documentés.

Sources officielles consultées : [Measurement Protocol](https://developers.google.com/analytics/devguides/collection/protocol/ga4/reference), [vérification de l'implémentation](https://developers.google.com/analytics/devguides/collection/protocol/ga4/verify-implementation), [déduplication par transaction](https://support.google.com/analytics/answer/12313109).

## Vérification

### Raccordement GA4 préparé dans le dépôt

Le lot ajoute une interface FR/EN/DE d'acceptation/refus/retrait, un cookie de choix chiffré et HttpOnly valable 180 jours, une preuve serveur du consentement avec identifiant de visite chiffré et un historique d'envoi unique par commande. La balise n'est chargée qu'après consentement explicite. Les URL privées, références de commandes dans les URL, paramètres de recherche et valeurs de formulaire ne sont pas envoyés. Les signaux Google, le stockage publicitaire et la personnalisation restent désactivés.

Les futurs dossiers de commande associent uniquement une identité de visite effectivement reçue et autorisée ; aucun rapprochement d'anciennes commandes avec une nouvelle identité n'est créé. Le retrait révoque l'identité liée aux anciennes commandes, même si le paiement intervient ensuite. Le site doit fonctionner si la capture Analytics échoue. L'historique de consentement reste interne et l'identifiant Google est neutralisé après expiration lors d'un traitement d'envoi.

L'envoi exige le registre interne `commerce_paid_purchases`, la migration dédiée `2026_10_17_000006_create_ga4_consent_and_deliveries.php` et les variables privées `HOMYA_GA4_ENABLED`, `HOMYA_GA4_MEASUREMENT_ID`, `HOMYA_GA4_API_SECRET`. Aucun secret n'est inscrit dans le dépôt ou le navigateur. Le serveur transmet au Measurement Protocol européen de Google uniquement les achats encaissés et consentis de moins de 72 heures. Aucun identifiant de session artificiel n'est ajouté : l'attribution d'un virement tardif à une session/publicité n'est pas garantie par ce lot.

`php artisan homya:ga4-purchases` est un aperçu sans requête Google ; `--validate` utilise le serveur de validation sans enregistrer de conversion ; `--apply` transmet réellement les conversions admissibles. Les options validate/apply sont exclusives. Un résultat `submitted` signifie que Google a accepté le HTTP, pas que l'événement est visible dans GA4. Le résultat final doit être vérifié dans la propriété. Une livraison en état `sending` ou `unknown` nécessite une vérification manuelle avant toute reprise ; le programme ne la renvoie pas automatiquement. L'identifiant de transaction reste identique pour toute reprise explicitement examinée.

Le planificateur existant comporte une tâche d'envoi par minute, inactive sans configuration complète. Le rapprochement du registre et l'envoi sont séparés ; un intervalle supplémentaire d'une minute peut être nécessaire. L'activation doit vérifier le cron réel et les droits DB. Une création partielle des deux tables doit être inspectée avant reprise ; aucun rollback destructif ou lancement de toutes les migrations n'est prévu.

Contrôles du lot : 10 tests du suivi (66 assertions), 38 tests commerce, 39 tests des en-têtes/confidentialité/sécurité bancaire, 3 tests JavaScript du consentement, Pint et compilation Vite réussis. Le test commerce déjà signalé concernant un texte de délai dans le pied de page reste exclu du contrôle ciblé ; aucun correctif de ce texte dans ce lot. Les appels Google sont simulés et aucune commande réelle n'est payée pour tester.

Autorisation explicite de création du secret Measurement Protocol reçue le 17 octobre 2026. Sa création et la validation Google restent à faire : la console affiche à nouveau `ERR_NAME_NOT_RESOLVED`, après des échecs d'enregistrement des préférences email. La session Hostinger est authentifiée sur homya.eu, déploiement Git `main` au commit `c290f701`. L'accès SSH est actif, mais aucune identité SSH correspondante n'est disponible localement ; aucun nouvel accès SSH n'a été créé sans confirmation.

Tests dédiés SQLite en mémoire, HTTP externe interdit : réceptions exactes, statuts falsifiés, empreinte incorrecte, montants incorrects, expiration, absence de TVA, cohérence HT/TVA/TTC, identifiants Merchant, absence de données personnelles, reprises, périodes UTC et activation planifiée. Ces tests ne valent pas validation MariaDB ou GA4.
