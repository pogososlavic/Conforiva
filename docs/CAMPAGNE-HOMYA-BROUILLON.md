# HOMYA — préparation publicitaire sans lancement

Date : 17 octobre 2026. Statut : préparation locale conservée ; formulaire Shopping ouvert, non publié et non enregistré comme campagne Google Ads.

## Choix confirmés par l’utilisateur

- Budget quotidien moyen : 16 EUR.
- France, Belgique, Allemagne.
- Produits approuvés.
- Aucun lancement.

## Préparation du formulaire Google Ads

| Réglage | Valeur préparée |
| --- | --- |
| Compte Ads | HOMYA 524-611-8848 |
| Nom | HOMYA — Shopping — FR BE DE — Brouillon |
| Type proposé | Shopping |
| Objectif | Achat uniquement |
| Conversion principale | HOMYA — achat réellement encaissé (GA4) |
| Merchant Center | HOMYA 5874312343 |
| Libellé du flux | HOMYA-FR |
| Budget | 16 EUR/jour moyen |
| Enchères proposées, à revoir avant lancement | Maximiser la valeur de conversion, sans ROAS cible |
| Ciblage | France, Belgique, Allemagne ; présence dans les zones, sans intérêt seul |
| Groupe | HOMYA — catalogue éligible |
| Produits dans le formulaire | Tous les produits du flux ; seuls les produits éligibles selon Google peuvent diffuser. Aucune sélection manuelle figée des 119 produits approuvés n’a été sauvegardée. |
| Logo | Non ajouté ; accès/vérification annonceur à examiner |
| Politique UE | Campagne commerciale sans annonces politiques |

Limite concrète : le formulaire Shopping avertit « Campagne non enregistrée » lorsque l’on quitte, avec invitation à finaliser sa création. Aucun clic sur « Publier la campagne ». La page est conservée ouverte, mais elle ne constitue pas une campagne ou un brouillon persistant dans le compte. Copie locale des réglages ci-dessus pour reprise.

Incohérence de l’interface : la table de ciblage contient bien les trois pays et l’option présence, mais la synthèse affiche « Tous les pays et territoires ». Ne pas publier avant résolution et vérification de la persistance du ciblage. La console avertit également qu’un bloqueur de publicité gêne son fonctionnement ; sa cause n’a pas été identifiée et aucune protection n’a été désactivée.

## Conversion importée et vérifiée

Une seule action active observée dans la table Google Ads : « HOMYA — achat réellement encaissé (GA4) », source « Site Web (Google Analytics (GA4)) », principale, toutes les conversions, fenêtre après clic 90 jours, incluse dans l’objectif Achat du compte. État « En attente de conversions », zéro conversion et zéro valeur. Configuration lors de l’import : valeur provenant de GA4, repli zéro (aucun montant fictif), attribution basée sur les données. Événement source `purchase` de la propriété 559384141, aucun autre événement importé. Aucun extrait Google Ads installé sur le site, afin de ne pas dupliquer l’achat serveur GA4.

## Vérification d’attribution : incomplète

L’association GA4–Ads et l’import sont effectifs. Aucune campagne n’est lancée et aucun achat provenant d’un clic publicitaire n’est disponible : aucun crédit réel de conversion ne peut être confirmé.

Le code actuellement déployé envoie des URL sans query ni referrer (`resources/js/analytics-consent.js`). La collecte des identifiants de clic Google et des paramètres de campagne n’est donc pas validée et doit être testée/corrigée avec une liste strictement autorisée après consentement, sans envoyer de paramètres privés. Le serveur envoie un `client_id` réellement reçu et le moment de l’encaissement ; il n’envoie pas de `session_id`. Selon Google, l’absence de session_id n’interdit pas l’export vers Google Ads ; les exigences d’attribution à une session sont différentes. Ne pas inventer un clic, une session ou un encaissement pour rendre le diagnostic vert.

Référence officielle vérifiée : https://developers.google.com/analytics/devguides/collection/protocol/ga4/use-cases (export publicitaire : au plus 63 jours après le dernier événement en ligne, dans la fenêtre d’attribution, événement daté dans les 72 dernières heures ; session_id non requis pour cet export).

## Avant lancement

- Finaliser la facturation et examiner la vérification annonceur.
- Vérifier l’éligibilité Shopping des produits par destination FR/BE/DE : le flux actuellement choisi s’appelle HOMYA-FR, ce nom ne prouve pas une éligibilité aux trois pays.
- Résoudre l’incohérence du ciblage et conserver la sélection de produits approuvés.
- Vérifier les identifiants publicitaires et la réception/attribution d’un encaissement réel consenti.
- Revoir la stratégie d’enchères après cette validation.
- Faire valider la publication ; ne pas lancer automatiquement.

Preuves locales : `/private/tmp/homya-ga4-purchase-imported.jpg`, `/private/tmp/homya-shopping-draft-countries.jpg`.

## Correction des clics et reprise du ciblage — 17 octobre 2026

Correction déployée au commit `2ca51ed` : URL envoyée à GA4 construite avec uniquement les identifiants `gclid`, `gbraid`, `wbraid`, après consentement et sur les chemins publics. Valeurs limitées à 512 caractères alphanumériques, tiret et underscore, doublons rejetés. Aucun autre paramètre, recherche, email, referrer, ancre ou URL privée ajouté. Aucun stockage avant consentement ajouté. Les identifiants persistent déjà dans l'URL lors de l'acceptation et du rechargement.

Validation : cinq tests JS réussis (consentement/refus/retrait, conservation des identifiants, exclusion des données privées, chemins privés, doublons et valeurs malformées), build réussi et diff sans erreur. Production : nouveau bundle `app-DoQEo_Fg.js` observé sur homya.eu ; une URL de diagnostic avec identifiant fictif explicitement technique est conservée par le site, sans achat ni clic publicitaire réel. Le navigateur de test n'a pas reçu de nouvel accord pendant cette vérification. L'identifiant de diagnostic a ensuite été retiré de l'URL. Cela confirme le déploiement et la préservation de l'URL, pas une attribution commerciale réelle.

Ciblage réappliqué dans le formulaire : FR, BE, DE, présence uniquement. La recherche avancée retrouve ces trois pays. Malgré la réapplication, la synthèse affiche toujours « Tous les pays et territoires ». Google Ads signale un bloqueur de publicité ; exception pour ads.google.com demandée à l'utilisateur, sans désactiver une protection nous-mêmes. Cause de l'incohérence non prouvée. Aucun clic sur publication : la préparation ne peut pas être déclarée finalisée ou persistante tant que la synthèse et les paramètres divergent.

Preuves : `/private/tmp/homya-countries-verified-settings.jpg`, `/private/tmp/homya-click-tracking-deployed.jpg`. Budget préparé inchangé : 16 EUR/jour moyen. Aucun lancement ni dépense.

### Reprise après désactivation d’uBlock par l’utilisateur

L’ancien formulaire n’était plus ouvert. Nouvelle préparation Shopping reconstruite à partir des paramètres connus, sans publication : budget 16 EUR/jour, objectif achat, maximiser la valeur de conversion, groupe HOMYA — catalogue éligible, flux HOMYA-FR. Pays recherchés ensemble dans la sélection avancée : trois résultats France/Belgique/Allemagne, action « Tout cibler », puis « Enregistrer ». La table de paramètres contient ces trois pays ; présence uniquement sélectionnée.

La synthèse affiche néanmoins toujours « Tous les pays et territoires », confirmé visuellement après la désactivation d’uBlock déclarée par l’utilisateur. Sa désactivation n’a pas résolu l’incohérence. Il n’est pas démontré que le bloqueur en était la cause ; les messages présents dans le DOM ne prouvent pas qu’un avertissement est visible à l’écran. Aucun blocage supplémentaire des extensions ni autre modification de protection effectués.

Le formulaire reste ouvert pour reprise ; aucune campagne persistante créée ni publiée. La préparation locale est complète comme référence, mais la validation opérationnelle du ciblage Google Ads reste bloquée par cette contradiction. Ne pas publier tant qu’elle subsiste. Preuves : `/private/tmp/homya-shopping-countries-after-ublock.jpg` et `/private/tmp/homya-shopping-summary-inconsistent.jpg`.

### Limite de sauvegarde Shopping et voie de reprise vérifiée

La documentation officielle Google confirme que les brouillons de campagne dans l'interface Web ne sont pas disponibles pour Shopping : https://support.google.com/google-ads/answer/6250195?hl=en . Ce constat ne résout pas l'incohérence géographique, mais explique pourquoi la préparation ne peut pas être conservée comme un brouillon Shopping Web. Les paramètres supplémentaires du formulaire proposent dates, options URL et réseaux ; aucune création en pause observée. Aucune publication suivie d'une pause, aucune date de lancement futur ajoutée.

Alternative officielle : Google Ads Editor permet un statut de campagne brouillon, qui n'est pas envoyé au compte lors de la publication d'autres changements : https://support.google.com/google-ads/editor/answer/43955?hl=en . L'application n'est pas disponible sur ce poste (tentative d'ouverture : application introuvable). Page officielle https://ads.google.com/home/tools/ads-editor/ : tentative d'accès échouée avec ERR_NAME_NOT_RESOLVED. Installation demandée à l'utilisateur pour poursuivre cette voie. Aucun nouvel accès OAuth demandé ou accordé, aucune campagne créée en production. Les journaux console du formulaire ne montrent aucune erreur expliquant la divergence du ciblage. Cause exacte non identifiée.
