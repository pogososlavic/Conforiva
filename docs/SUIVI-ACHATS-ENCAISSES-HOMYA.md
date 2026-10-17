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

### Contrôle du serveur — 17 octobre 2026

- Déploiement Hostinger vérifié au commit `9ebe040`, PHP CLI 8.5.11.
- Sauvegarde privée pré-migration de la base commerce et de la configuration dans `/home/u429557141/homya-private-backups/ga4-20261017` (répertoire 0700, fichiers 0600) : 3 944 lignes, 1 233 927 octets JSONL. Sauvegarde créée ; restauration non testée.
- Migrations ciblées `000005` et `000006` appliquées avec succès ; trois tables de mesure présentes. Cache de configuration et cache des routes actualisés.
- Registre des encaissements activé, date de début UTC `2026-10-17`. Première réconciliation : zéro achat vérifié et zéro transmission externe.
- Consentement GA4 activé sur le site. Acceptation et retrait testés : choix persistés, balise ajoutée après acceptation et absente après retrait ; aucun consentement encore accordé après le test. Aucun identifiant Google n'a été reçu lors du test.
- Cron Hostinger existant vérifié dans hPanel : `* * * * * /usr/bin/php /home/u429557141/domains/homya.eu/public_html/artisan schedule:run`. Aucun doublon ajouté. L'outil `crontab` est absent en SSH : son absence ne signifie pas absence de tâche hPanel.
- Console Analytics toujours bloquée par `ERR_NAME_NOT_RESOLVED`. Secret Measurement Protocol non créé ; aucune réception GA4 ou conversion réelle confirmée. Les tests de virements sont ceux des fixtures locales, pas un encaissement réel de production.
- Clé SSH temporaire utilisée uniquement pour ces opérations puis révoquée ; clé privée locale supprimée. L'accès préexistant est conservé.

### Reprise du raccordement Google — 17 octobre 2026

La confirmation de collecte a été acceptée après accord explicite. Une ligne de secret « HOMYA — achats encaissés serveur » a été observée après création, sans divulguer sa valeur. Lors de la reprise pour installation, le panneau Google est resté en chargement puis le rechargement a échoué avec `ERR_NAME_NOT_RESOLVED`. La valeur n'a pas été récupérée ni installée. Le contrôle SSH confirme que le secret n'est toujours pas configuré en production. Le nouvel accès SSH temporaire autorisé a été retiré ; sa clé privée locale est supprimée. Aucune conversion Google envoyée dans cette reprise.

### Secret installé — 17 octobre 2026

Secret existant du flux récupéré sans divulgation, installé dans `.env` de production puis cache de configuration actualisé. Contrôle des indicateurs : GA4 et registre activés, secret présent, trois tables présentes. Validation serveur sur `/debug/mp/collect` avec fixture explicitement technique, sans collecte : HTTP 200 et zéro message de validation stricte. Cet endpoint ne vérifie pas l'authentification du secret ; ce résultat ne prouve donc pas l'ingestion dans les rapports GA4. Aucun achat réel éligible : commande `--validate` retourne zéro événement. Vérification de réception réelle et attribution restent à effectuer après visite consentie et prochain encaissement éligible. Clé SSH temporaire retirée et fichiers locaux de transport du secret supprimés. Aucun secret dans Git.

### Réception des visites confirmée — 17 octobre 2026

Visite consentie sur `/fr/a-propos`, puis observation du rapport en temps réel de la propriété HOMYA `559384141` : un utilisateur actif dans les fenêtres de cinq et trente minutes, une vue HOMYA, `page_view` = 1 et `first_visit` = 1. Réception navigateur GA4 confirmée. Consentement du navigateur de test retiré ensuite. Aucun achat ou encaissement fictif effectué. Installation terminée pour les visites ; réception d'un achat serveur et attribution à vérifier au prochain encaissement réellement éligible et consenti. Preuve locale : `/private/tmp/homya-ga4-visits-confirmed.jpg`.

### Association Google Ads et préparation au lancement — 17 octobre 2026

Compte Google Ads confirmé : `524-611-8848`, « Compte Google Ads HOMYA ». Association à la propriété GA4 `559384141` créée après confirmation explicite, état « Association créée », puis tableau « Associations terminées (1) ». Publicité personnalisée désactivée, accès aux fonctionnalités Analytics depuis Ads désactivé ; activation du taggage automatique sélectionnée lors de l'association. Google annonce un délai maximal de 24 heures pour la circulation des données. Preuve : `/private/tmp/homya-ga4-ads-linked.jpg`.

Contrôle de préparation, sans lancement ni budget modifié :
- Facturation : navigation renvoie à « Nouvelle configuration de facturation », profil de paiement à créer. Aucune information de paiement saisie.
- Campagnes : vue toutes les campagnes, toute la période, affiche aucune campagne activée, zéro brouillon, budget total 0 EUR/jour. Les paramètres de budget, annonces, ciblage et stratégie ne sont donc pas validés pour un lancement.
- Conversions : écran « Configurer le suivi des conversions », aucune action d'achat visible ; Google Ads affiche aussi un avertissement sur un bloqueur de publicité, ce qui limite la fiabilité du chargement. Aucun import/modification de conversion exécuté.
- Merchant Center HOMYA `5874312343` : compte Ads ci-dessus connecté avec état Actifs. Vue toutes méthodes : 151 produits, 119 approuvés, 14 limités, 2 refusés, 16 en examen. Diagnostic des deux refus : « Image non traitée », retraitement automatique annoncé sous trois jours, aucune action requise selon Google. L'éligibilité Shopping de la sélection de campagne reste à vérifier par destination.
- Attribution : la balise actuelle remplace `page_location` par origine/chemin sans query et le serveur n'envoie pas de `session_id`. Réception de visites confirmée, mais attribution des clics publicitaires et des achats différés NON validée. Ne pas assimiler l'association ou la validation du payload à une preuve d'attribution Ads. Référence officielle Google : https://developers.google.com/analytics/devguides/collection/protocol/ga4/use-cases ; les règles d'export Ads et celles d'attribution de session sont distinctes.

Conclusion : association réalisée ; lancement non prêt. Restent facturation, conversion unique d'achat encaissé/import et validation réelle de l'attribution, campagne et budget à faire approuver. Aucun lancement, aucune dépense, aucun ajout de droit d'édition Analytics via Ads.

### Import de la conversion Google Ads — 17 octobre 2026

Import de `purchase` de GA4 559384141 autorisé puis réalisé. Une seule action active observée : « HOMYA — achat réellement encaissé (GA4) », source GA4 Web, principale, toutes les conversions, fenêtre après clic 90 jours, état en attente de conversions. Valeur GA4 avec repli zéro et modèle basé sur les données choisis. Aucun faux achat envoyé. Préparation Shopping à 16 EUR/jour et FR/BE/DE conservée dans `CAMPAGNE-HOMYA-BROUILLON.md` ; formulaire non publié, non enregistré en campagne persistante car Google exige la finalisation pour sauvegarder. Attribution réelle non vérifiable sans achat lié à un clic ; collecte des paramètres publicitaires non validée, synthèse géographique incohérente et facturation non finalisée. Aucun lancement.

### Correction des identifiants de clic — 17 octobre 2026

Commit `2ca51ed` déployé : conservation ciblée de gclid/gbraid/wbraid dans page_location après consentement, exclusion des autres paramètres et chemins privés. Cinq tests JS et build réussis ; nouveau bundle observé en production. Attribution commerciale toujours non vérifiée faute d'encaissement relié à un clic réel. L'absence de session_id ne bloque pas en elle-même l'export publicitaire Google ; ne pas confondre ce point avec l'attribution à une session. Le blocage restant du formulaire de campagne est décrit dans `CAMPAGNE-HOMYA-BROUILLON.md`.
