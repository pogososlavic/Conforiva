# HOME CONFORT — panier, commande, livraison et retours

Audit et corrections locales du 20 octobre 2026. Cible déclarée : **Hostinger Web ou Cloud**. Aucun transfert, déploiement, paiement, e-mail réel, migration ou commande client créé pendant cette intervention. Le dossier réel du projet est `/Applications/XAMPP/xamppfiles/htdocs/HOMECONFORT`.

## Corrections installées

- **Panier** : le lien vers une fiche conserve l’option exacte achetée (`variant=v2`, par exemple). Le changement de langue pendant la commande reste sur la commande et conserve le panier.
- **Validation** : les erreurs de saisie ne conservent plus de tableaux dans les champs texte ni de chaînes sans borne dans les anciennes valeurs de formulaire. La récupération après erreur garde les coordonnées utiles, l’adresse de facturation et les quantités admissibles de reprise, mais exclut les jetons de commande et les cases d’acceptation. Le défaut précédent pouvait empêcher le formulaire de se réafficher. Le correctif couvre aussi les exceptions de validation déjà converties en redirection par le pipeline Laravel.
- **Livraison et devis** : les traductions FR/EN/DE du devis et les bandeaux précisent les jours **ouvrés**. Le devis ne contredit plus la reprise gratuite d’un ancien appareil électrique ou électronique équivalent à la livraison, dans la limite achetée. Les références et quantités de reprise enregistrées sont conservées dans le devis automatique. Installation et mise en service restent soumises aux mentions explicites existantes.
- **E-mails de devis** : retrait de l’adresse et du domaine hérités codés dans ces modèles. Le pied d’e-mail utilise uniquement une origine publique validée ; aucune nouvelle adresse n’est inventée. Le futur e-mail commercial reste à renseigner dans la configuration générale.
- **Rétractation** : choix visible et requis du téléchargement, sans présélection au premier accès. Après vérification et confirmation, transmission HTTP directe de l’accusé HTML autonome, sans page intermédiaire imposant un second clic. Une répétition renvoie le même document, sans doublon ni nouvel horodatage. La session autorisée peut retrouver son dernier accusé. Une panne de rendu après enregistrement ne provoque pas une seconde déclaration lors de la reprise. Confidentialité, chiffrement, absence d’indexation et politique restrictive du document conservés. Le formulaire explique que la notification est enregistrée même si le téléchargement est ensuite annulé.
- **Préparation Hostinger** : le contrôle préalable des e-mails utilise les mêmes conditions que l’envoi réel du devis, dont une adresse expéditrice valide et identique au contact. Il ne se connecte pas au SMTP. Le kit précrée le dossier privé de verrous de session et le guide détaille PHP web/CLI, cron et migration sélective des données privées.

25 fichiers installés avec contrôle des SHA avant écriture et sauvegardes privées. Le petit bloc CSS du choix de téléchargement a été ajouté à la feuille existante, en préservant les autres modifications. Manifeste : `storage/app/private/checkout-audit-20261020/installed-manifest.json`.

## Engagements vérifiés dans le code et les textes

| Élément | Constat local |
| --- | --- |
| Zones et frais | France, Belgique et Allemagne ; livraison gratuite selon la politique déjà publiée |
| Départ et retours de marchandises | Rue Aristote, Mont de Terre, Fives, 59000 Lille, France ; adresse confirmée par le propriétaire |
| Siège social | Rémire-Montjoly, distinct du lieu de retour des marchandises |
| Délais publiés | 1–2 jours ouvrés de préparation + 2–5 de transport, soit 3–7 après paiement confirmé ; lundi–vendredi, limite 14 h Europe/Paris |
| Commande | Prix et montants recalculés côté serveur ; panier, variantes, consentements et changements d’offre contrôlés |
| Réservation | Délai de 72 h et libération idempotente couverts en base de test SQLite en mémoire |
| Paiement | Une preuve de virement du client ne vaut pas fonds reçus ; rapprochement exact et non réutilisable couvert par les tests |
| Retours | Notification et renvoi distingués, accusé privé, aucune obligation de motif ou de coordonnées bancaires pour notifier |

Ce relevé vérifie l’application et ses engagements publiés. Il ne prouve pas l’exécution physique du transport, la disponibilité réelle du stock, une collecte ou un remboursement. Aucun stock réel n’a été modifié ni recompté.

## Vérifications et preuves

| Contrôle | Résultat |
| --- | --- |
| Tests ciblés installés | **89 tests / 4 549 assertions réussis** : panier, commande, consentements, réservations, virement, facturation, reprise, livraison, retours et préparation du déploiement |
| Suite complète après intégration | **621 tests / 95 589 assertions réussis** sur l’état partagé du projet à cet instant |
| Revue indépendante du correctif de validation et des retours | Aucun défaut bloquant identifié ; **14 tests / 236 assertions réussis** en copie isolée |
| Régression validation | 3 tests échouaient avant correction ; 3 / 38 passent après correction |
| Régression devis | Perte de la reprise reproduite avant correction ; 2 tests / 83 assertions passent en staging corrigé |
| Régression transmission de retour | Ancienne réponse 302 reproduite ; réponse directe 200 et reprise sans doublon couvertes après correction |
| Style PHP et espaces | Pint et `git diff --check` réussis sur les fichiers concernés |
| Assets | Compilation Vite réussie |
| Chrome | **36 rendus synthétiques FR/EN/DE** : panier, commande, déclaration et vérification de retour, à 320/768/1440 px ; aucun débordement global ou champ hors écran, radio 20 × 20 px requis et initialement non coché |

Les tests SQL utilisent une base SQLite en mémoire lorsque nécessaire ; les nouveaux tests de navigation et de récupération interdisent les connexions SQL. Les pages Chrome sont des rendus synthétiques interceptés localement : aucun parcours client réel ni appel de commande au serveur local. La première fixture réutilisait le brouillon entre langues ; le choix restauré a été observé, puis les sessions ont été isolées pour contrôler le premier accès. Ce n’était pas une présélection de l’application.

Rapports, JUnit, captures et contre-épreuves archivés sous `storage/app/private/checkout-audit-20261020/evidence/`. Les tests démontrent les cas exercés, pas une certification juridique ou un feu vert de production.

## Kit Hostinger actualisé

Dossier : `output/hostinger/livraison-checkout-20261020/`.

- `application.tar.gz` : **362 fichiers**, empreintes et contenus contrôlés, sans `.env`, dépendances installées, sessions, journaux, données clients ou contenu privé.
- `catalogue.tar.gz` : **3 753 fichiers**, empreintes et contenus contrôlés.
- `manifest.json` : inventaire séparé des **28 413 médias, 4 196 184 013 octets**, à transférer selon un plan distinct ; les médias ne sont pas inclus dans ces archives.
- Archives en 0600 ; dossiers privés, logs et verrous du squelette en 0700. Correspondance des fichiers application/catalogue avec les sources observée après génération.

Ce kit remplace celui du début de journée pour cette révision. Il ne contient pas automatiquement le RIB chiffré, les documents, demandes de retour ou revues Google privés. Leur reprise doit être sélective, conserver la clé de chiffrement appropriée et être vérifiée séparément. Ne pas transférer tous les comptes ou jetons locaux. Voir le [guide Hostinger actualisé](../deploy/hostinger/README.md).

## À finaliser avant mise en ligne

1. Fournir le **domaine public exact** et le **nouvel e-mail HOME CONFORT**. Hostinger est l’hébergeur, pas un domaine. Vérifier le contact et l’expéditeur réels puis la réception du devis en recette autorisée.
2. Confirmer dans hPanel l’offre exacte et ses capacités : **PHP >= 8.4.1 pour le web et la CLI**, extensions, SSH/Composer, stockage disponible, racine pointant exclusivement vers `public/`, HTTPS et droits privés. Ces capacités ne sont pas encore observées sur le compte.
3. Préparer et vérifier sauvegarde/restauration, migration de la base et des données privées retenues, stock, RIB, documents et accès d’administration de production. Aucun de ces transferts n’a été exécuté ici.
4. Configurer puis tester le cron d’expiration des réservations, avec PHP CLI exact et verrous fonctionnels. Le scheduler peut écrire en base et appeler des intégrations : il n’a pas été exécuté sur les données réelles pendant l’audit.
5. Effectuer la recette sur l’hébergement : commande synthétique autorisée, e-mail reçu, virement et rapprochement, expiration, transmission de l’accusé et organisation réelle des retours/reprises. Finaliser la qualification juridique du dispositif complet de rétractation et les obligations de contact avant ouverture ; la remise HTTP du fichier choisi est prouvée techniquement, sans certification juridique exhaustive.

Aucun flux Google, indexation ou paiement n’a été activé par cette intervention. L’acceptation Merchant Center reste indépendante de ces corrections.

## Sources officielles examinées

- [Service Public Entreprendre — commerce en ligne](https://entreprendre.service-public.gouv.fr/vosdroits/F23455), version indiquée vérifiée au 19 juin 2026 : information et confirmation de rétractation en ligne.
- [Ministère de l’Économie — vente à distance et rétractation](https://www.economie.gouv.fr/particuliers/mes-droits-conso/bien-consommer/vente-distance-tout-savoir-sur-votre-droit-de-retractation).
- [BGB officiel](https://www.gesetze-im-internet.de/bgb/BJNR001950896.html), §356a et §357, version affichant la modification du 23 juillet 2026.
- [Hostinger — PHP web et distinction CLI](https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/), [Composer avec PHP explicite](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/), [cron et caractères spéciaux](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/).

Consultation le 20 octobre 2026. Les rapports spécialisés archivés précisent les limites des sources et contrôles. Les possibilités générales de Hostinger ne prouvent pas les capacités du compte retenu.
