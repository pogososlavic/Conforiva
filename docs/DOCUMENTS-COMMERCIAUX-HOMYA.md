# Documents commerciaux HOMYA

Demande fonctionnelle autorisée : DECISION-081 du registre central. Cette conception technique accompagne l’implémentation ; elle n’est pas une validation des règles fiscales manquantes.

## Périmètre

Trois types distincts : devis, bon de commande professionnel, facture de vente. Création libre ou reprise du snapshot d’une commande. Brouillons révisables, aperçu, PDF A4, émission contrôlée, téléchargement et archives privées. Les documents émis liés à une commande deviennent téléchargeables uniquement par son propriétaire dans sa session privée. Le récapitulatif e-commerce existant reste distinct.

L’émission manuelle ne crée pas de commande, ne réserve pas de stock et ne confirme pas un paiement. Après validation d’une commande e-commerce, le devis automatique décrit séparément la commande existante ; il n’encaisse rien et n’en confirme pas le paiement. Son PDF est archivé et peut être remis par email lorsque les contrôles métier, la migration commerce et SMTP HOMYA sont prêts. Avoirs, acomptes, remboursements, signatures et transmission structurée via une plateforme agréée restent hors de ce lot.

Le brouillon et l’émission PDF proposent le français, l’anglais et l’allemand. Les libellés fixes, titres, avertissements et montants formatés du PDF suivent cette langue ; elle est conservée dans l’archive à l’émission. Les champs rédigés librement (désignations, adresses, conditions et mentions) ne sont pas traduits automatiquement et doivent être saisis dans la langue voulue. Les PDF déjà émis restent inchangés. Après validation d’une nouvelle commande, le devis est généré et envoyé automatiquement à l’adresse du client par le transport SMTP HOMYA configuré.

## Informations et contrôles

- Identité HOMYA issue de la configuration légale existante, figée à émission. Les trois PDF présentent le nom HOMYA, la forme EURL complète, le capital de 20 000,00 €, le siège à Morigny-Champigny, les SIREN/SIRET, la TVA intracommunautaire, le RCS Évry, les inscriptions RCS/RNE du 20/04/2012 et le gérant David Marechal. Téléphone et email HOMYA figurent uniquement lorsqu’ils ont été fournis et configurés ; aucune coordonnée du médiateur ou des sites de référence n’est utilisée à leur place. Clarification utilisateur du 10/10/2026. Les archives déjà émises conservent leur PDF d’origine.
- Prix TTC confirmés. Le taux est figé sur chaque ligne de commande selon le pays de livraison, avec une classe fiscale et un taux stockés dans le snapshot ; les prix TTC commandés restent inchangés. Configuration actuelle : taux standards FR 20 %, BE 21 %, DE 19 % ; classe bois de chauffage/agglomérés de bois FR 10 %, BE 6 %, DE 7 %. Les combustibles non classés bois de chauffage (dont charbon/lignite) restent au taux standard. Ces mappings suivent les confirmations reçues et les sources officielles vérifiées le 12/10/2026 ; ils doivent être revus lorsque les textes ou la classification produit évoluent. Aucune immatriculation OSS, déclaration ou remise de TVA n’est accomplie par l’application.
- Client, adresses, description / référence, quantité, montants, TVA ou motif légal d’exonération, conditions de paiement et livraison nécessaires avant émission.
- Devis automatique valable exactement 72 heures après la validation de commande, échéance affichée en heure de Paris. Les documents manuels gardent la date de validité saisie. Facture : date réelle de vente/livraison, échéance, escompte ; conditions de retard et indemnité légale de 40 € pour le client professionnel. Le déclenchement automatique est activé par défaut ; l’émission reste soumise aux contrôles techniques de commande, de snapshot fiscal, d’adresse client, de document et de configuration SMTP.
- Un RIB HOMYA valide est utilisé sur les devis et bons de commande lorsqu’il est configuré, sélectionné dans l’administration et stocké chiffré dans le stockage privé. Exception validée par l’utilisateur le 12/10/2026 : son absence ne bloque pas l’émission ni l’envoi email du devis e-commerce automatique ; le PDF omet alors la section des coordonnées bancaires. L’émission manuelle d’un devis ou d’un bon de commande reste conditionnée à un RIB valide. Les coordonnées présentes au moment de l’émission sont figées dans le document ; la suppression ne modifie pas les PDF déjà émis ni l’historique des commandes.
- Numérotation confirmée par l’utilisateur : `HOMYA/FR-001`, `HOMYA/EN-002`, `HOMYA/DE-003`, compteur commun global entre langues et types documentaires, continu et non réinitialisé par année. Le premier numéro est 001. Aucun numéro au brouillon ; le numéro n’est consommé qu’à l’émission, sous verrou. Une collision avec une archive bloque l’émission sans incrément silencieux.
- Confirmation par mot de passe à émission. Données et PDF figés ; duplication crée un nouveau brouillon. Une facture émise par commande au maximum ; une rectification nécessite un processus d’avoir séparé.

## Données et sécurité

Utilisation du stockage privé déjà employé pour les retours : fichiers chiffrés par la clé Laravel, dossiers 0700 / fichiers 0600, verrou exclusif, révisions et écritures atomiques. Une émission est un seul paquet contenant snapshot, numéro, historique, PDF et SHA256 ; l’allocation est calculée sous verrou à partir des émissions effectivement archivées. Un échec de génération ne consomme pas de numéro. La migration additive `2026_10_12_000003_add_destination_vat_and_order_quote_delivery` ajoute les taux/classes aux snapshots de nouvelles commandes et une ligne d’état email unique par commande. Les anciennes commandes restent nullables et ne reçoivent pas de devis automatique sans snapshot fiscal. Aucun rollback destructif n’est prévu.

La remise SMTP est idempotente : `submitted` signifie accepté par le transport, pas remis en boîte ; `unknown` est une issue ambiguë et ne se renvoie jamais automatiquement pour éviter un doublon. Les états et motifs utiles sont visibles dans le détail administrateur de la commande. L’adresse expéditrice doit être exactement `support@homya.eu`, cohérente avec la configuration de contact et le mailer SMTP.

Préserver et sauvegarder le dossier `storage/app/private/commercial-documents` avec la clé de chiffrement privée ; perdre cette clé rend les archives illisibles. Aucun effacement automatique. Conservation / archivage long terme à organiser avec les obligations comptables. Le fonctionnement local mono-instance ne constitue pas une architecture multi-serveur ni un système de comptabilité certifié.

## Vérification prévue

Calculs en centimes côté serveur, validations bloquantes, concurrence et unicité, immutabilité, répétition d’émission, contrôle des commandes sources, autorisations admin/client, PDF Unicode et multipage, pages à 1440/375/320 px. Recette isolée avec données et taux fictifs explicitement nommés ; aucune facture fiscale réelle ni message externe.

## Sources vérifiées le 09/10/2026

- https://entreprendre.service-public.gouv.fr/vosdroits/F31808 : mentions des factures, numérotation, TVA, éco-participation et distinction particuliers/professionnels.
- https://www.impots.gouv.fr/facturation-electronique-qu-est-ce-que-ca-change-pour-moi : calendrier et facturation électronique. Un PDF téléchargé ne remplace pas la transmission structurée lorsque celle-ci est obligatoire ; aucun raccordement à une plateforme agréée dans ce lot.
- https://github.com/dompdf/dompdf : moteur PHP Dompdf, accès distant / scripts désactivés.

## Activation après déploiement

1. Sauvegarder la base commerce.
2. Après le push, appliquer uniquement la migration commerce nouvelle sur Hostinger : `php artisan migrate --database=commerce --path=database/migrations/commerce`.
3. Configurer le transport SMTP dans `.env` avec l’adresse expéditrice `support@homya.eu`, puis vérifier que `MAIL_MAILER=smtp` et que le host est renseigné.
4. L’envoi est activé dans l’application. Après application de la migration et configuration du SMTP, vider le cache de configuration puis vérifier l’état du devis au détail de la commande.
5. Le menu « RIB HOMYA » permet d’ajouter, modifier ou supprimer les coordonnées. L’enregistrement demande le mot de passe administrateur et écrit un fichier chiffré privé ; les anciennes valeurs de `.env` servent uniquement de valeurs initiales jusqu’à la première modification ou suppression depuis le dashboard.

L’application calcule les taux de destination en préparation du régime OSS conformément au choix utilisateur, mais ne vérifie pas l’inscription OSS de HOMYA et ne prépare/dépose pas ses déclarations. La qualification de l’éligibilité OSS dépend notamment du flux d’expédition et de la situation fiscale réelle de l’entreprise ; elle doit être vérifiée par le responsable fiscal. Les taux configurés sont un paramétrage daté, pas une certification fiscale.

Les sources fiscales et de numérotation vérifiées le 12/10/2026 incluent le [régime OSS de la Commission européenne](https://vat-one-stop-shop.ec.europa.eu/one-stop-shop_en), les règles françaises de TVA sur le bois de chauffage (article 278 bis CGI), l’[annexe 2 UStG allemande](https://www.gesetze-im-internet.de/ustg_1980/anlage_2.html), les [taux belges publiés par le SPF Finances](https://finances.belgium.be/fr/entreprises/tva/assujettissement-tva/taux-et-calcul/taux-tva) et les [règles françaises de numérotation chronologique des factures](https://entreprendre.service-public.gouv.fr/vosdroits/F23208). L’usage d’une série globale unique reste à valider comptablement pour les factures, au vu de leur séquence chronologique.

## Vérification de l’identité du 10/10/2026

Les 17 tests documentaires passent (184 assertions), dont le contrôle des coordonnées complètes sur chacun des trois modèles, l’absence de contacts inventés et l’ajout des contacts lorsqu’ils seront configurés. Sept PDF synthétiques régénérés ; devis, bon de commande et facture A4 d’une page inspectés visuellement, facture de cinq pages contrôlée. Aucune émission fiscale réelle ni modification MariaDB.
