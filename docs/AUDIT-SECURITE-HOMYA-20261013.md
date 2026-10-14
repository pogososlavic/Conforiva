# HOMYA — revue du scan et corrections de sécurité

13 octobre 2026. Projet : dépôt HOMYA1, Laravel 13 et scripts locaux XAMPP. Source initiale : rapport Herozion 2.0.14, scan `893cb5a3d32d`, annonçant 72 alertes, dont seulement cinq détaillées. Les 67 emplacements masqués n'ont pas été obtenus ; leur nombre et leur sévérité ne sont pas une preuve d'exploitabilité.

L'utilisateur a demandé la correction des alertes visibles et une revue élargie, puis autorisé les contrôles de syntaxe, les tests locaux sur données fictives et le commit/push pour publication. La revue porte sur le code local et les dépendances. Les recettes avec le véritable administrateur, MariaDB/XAMPP, les commandes réelles, les e-mails et les tests offensifs de production sont exclus de cette vérification.

## 1. Les cinq alertes détaillées

| Référence du scan | Qualification après lecture des flux | Preuve |
| --- | --- | --- |
| `app/Services/Admin/Messages.php:97` — SQL Injection | Faux positif dans le flux examiné | `insert(array)` et `where()` du Query Builder ; tables/colonnes fixes, valeurs liées séparément, contenu chiffré. Le framework compile des placeholders puis transmet les bindings séparément. |
| `resources/views/admin/documents/edit.blade.php:21` — SQL Injection | Faux positif | Cette vue ne lance aucune requête SQL. Elle construit un formulaire et des URL avec `route()` et affiche des valeurs avec l'échappement Blade. |
| `scripts/install_admin_management.py:38` — sortie LLM vers un shell | Faux positif dans le flux examiné | `subprocess.run` reçoit une liste de programme/arguments fixes ou contrôlés ; aucun appel LLM, aucun shell. Les opérations SQL ciblent explicitement MariaDB XAMPP. |
| `scripts/install_commerce_preview.py:29` — sortie LLM vers un shell | Faux positif dans le flux examiné | Les arguments Git sont des listes définies dans le script, sans `shell=True`. |
| `scripts/install_commerce_preview.py:53` — sortie LLM vers un shell | Faux positif dans le flux examiné | Le nom de fichier provient de Git et des chemins retenus ; il devient un argument `git show`, sans interprétation par un shell. |

Aucune règle de scan n'a été supprimée pour masquer ces alertes. Un nouveau score Herozion peut encore refléter ses motifs génériques ; la qualification ci-dessus repose sur les entrées, les appels et les protections observés.

## 2. Constats réels et corrections

### SEC-001 — Identifiants dans les arguments des recettes navigateur

**Sévérité : moyenne, risque local.** Quatre scripts lisaient un fichier d'accès du projet voisin HOMYA, inséraient ses identifiants dans du JavaScript et transmettaient ce code comme argument `playwright-cli run-code`. Le mot de passe pouvait ainsi être visible dans les arguments du processus pendant l'exécution. Aucune fuite effective ni compromission Web n'a été établie.

Fichiers concernés : `verify_admin_local.py`, `verify_admin_management_local.py`, `verify_admin_upload_local.py` et `verify_commercial_documents_local.py`.

Correction : ces commandes lancent maintenant un runner Node avec des scénarios ESM statiques. Seul ce processus lit les accès du dépôt courant, en mémoire. Aucun secret n'est interpolé dans les arguments, les variables d'environnement, du code généré, des fichiers temporaires ou les rapports. Les erreurs retournent des codes génériques ; les sorties capturées ne sont pas reproduites comme diagnostics.

Les fichiers d'accès doivent être réguliers, détenus par l'utilisateur courant, en `0600`, sous un dossier privé `0700`. Les liens symboliques, liens physiques supplémentaires et ancêtres modifiables par autrui sont refusés. Le runner contrôle la configuration locale avant toute lecture privée ou ouverture du navigateur. Les requêtes externes, WebSockets et service workers sont bloqués ; les appels API ne suivent pas les redirections. Les captures masquent les mots de passe et les références d'accès et restent dans des fichiers privés.

**Vérifié** par revue indépendante et 19 tests isolés, sans accès réel ni navigateur. L'environnement réellement servi sur le port 8080 doit être vérifié par l'opérateur avant une recette : les fichiers du dépôt ne suffisent pas à le certifier. L'historique d'autres runtimes CLI n'a pas été examiné. Voir `scripts/browser_qa/README.md`.

### SEC-002 — Fichier de configuration temporaire privé trop tardivement

**Sévérité : faible, risque local.** Le script d'installation de prévisualisation écrivait son `.env` temporaire avant d'appliquer `chmod(0600)`. Une umask permissive pouvait donc laisser une fenêtre de lecture locale pendant la création.

Correction : création exclusive avec permissions `0600` dès l'ouverture, `O_NOFOLLOW`, puis écriture et synchronisation. Les copies de sauvegarde utilisent également une création privée ; les liens et chemins ambigus sont refusés. Aucun fichier `.env` réel n'a été modifié pour cette correction.

**Vérifié** sur fixtures, y compris avec une umask permissive et un contrôle des droits au moment de l'ouverture.

### SEC-003 — Gardes incomplètes des opérations annoncées comme locales

**Sévérité : faible, risque opérationnel local.** La prévisualisation ne contrôlait le mode local que dans un de ses modes ; le démarrage du scheduler ne contrôlait pas explicitement l'environnement et la cible avant sa commande. Une configuration substituée pouvait conduire à une opération dans un contexte différent de celui annoncé. Ce n'est pas un accès distant non authentifié.

Correction : contrôle préalable des deux copies pour la prévisualisation, de l'environnement avant les opérations admin et de la cible commerce XAMPP avant le scheduler. Les configurations non locales, URL publiques, caches Laravel, substitutions/doublons dotenv et cibles commerce différentes sont refusés. Les processus enfants reçoivent un environnement réduit, des arguments séparés, `shell=False` et des timeouts. Les valeurs privées et erreurs SQL restent absentes des diagnostics. Les gardes sont répétées avant les actions sensibles.

Le helper `scripts/local_operation_security.py` ajoute aussi des contrôles de propriétaire, permissions, liens et ancêtres ainsi que des écritures privées des sauvegardes, journaux et PID. Ces derniers contrôles constituent une défense supplémentaire contre une substitution locale.

**Vérifié** par 28 tests Python avec répertoires temporaires et subprocess simulés. Aucun installateur, service ou scheduler réel n'a été exécuté.

### FONC-001 — Résolution de classe pour les PDF brouillons

Anomalie fonctionnelle distincte : `DocumentStore` appelait `BankTransfer::class` sans importer `App\Services\Commerce\BankTransfer`. L'import manquant a été ajouté. Les PDF brouillons devis/bon de commande/facture sont vérifiés sans émission de document ni consommation de numéro. Ce défaut n'est pas compté comme une vulnérabilité.

## 3. Revue élargie

- **SQL PHP** : recherches admin avec placeholders/bindings, échappement des motifs LIKE, expressions Raw constantes, identifiants de schéma sur listes fixes et contrôles d'empreinte des journaux locaux. Aucune injection SQL confirmée dans ces appels examinés.
- **Commandes et PDF clients** : secret propriétaire aléatoire de 256 bits en session, requête simultanée sur UUID et `owner_hash`, document émis lié à la même commande. Un UUID seul ne donne pas accès au dossier.
- **Assistance et retours** : droits établis côté serveur dans la session ; reprise de conversation par UUID et code aléatoire, comparaison constante et rotation de session. Le suivi public ne fournit que référence et étape.
- **Administration** : frontière commune authentifiée, session renouvelée, limites temporelles, throttling, contrôles CSRF, domaine/HTTPS et activation explicite en production. L'absence d'un contrôle répété dans chaque contrôleur n'établit pas une absence d'autorisation.
- **Fichiers et rendu** : archives privées chiffrées, stockage non servi, vérification des preuves de virement, photos réencodées en WebP après contrôle de dimensions, échappement Blade et JSON-LD encodé. Les générateurs PDF désactivent les ressources distantes, PHP et JavaScript.
- **Secrets locaux** : contrôle de métadonnées uniquement ; `.env` observé en `0600`, `storage/app/private` et `storage/logs` en `0700`, détenus par l'utilisateur courant, sans lien. Aucun `.env` privé, clé ou base n'est suivi par Git dans les noms examinés ; `.env.example` est le fichier d'exemple suivi.
- **Dépendances** : `npm audit --ignore-scripts --json` : zéro avis signalé ; `composer audit --locked --no-plugins --no-scripts --no-cache --format=json` : aucun avis et aucun paquet abandonné. Ces commandes consultent les bases d'avis des registres, sans mise à jour de dépendances. [npm audit](https://docs.npmjs.com/cli/v11/commands/npm-audit/), [Composer audit](https://getcomposer.org/doc/03-cli.md#audit).

Les résultats négatifs sont bornés aux flux examinés. Les 67 alertes masquées restent non attribuables individuellement ; aucune conclusion générale d'absence de vulnérabilités n'est tirée de leurs agrégats ou des audits de dépendances.

## 4. Vérification finale locale

**139 tests réussis** : 92 tests PHP / 1 694 assertions, 15 tests Node, 4 tests Python de recettes et 28 tests Python d'opérations locales. Pint, syntaxe PHP/Node/Python et `git diff --check` contrôlent les fichiers concernés.

Les suites PHP utilisent des fixtures, SQLite en mémoire, des répertoires temporaires et des protections contre les requêtes HTTP sortantes ; les tests Node/Python utilisent des fichiers fictifs et des doubles de processus/contextes. Les anciennes attentes de numérotation des documents ont été alignées sur la série globale HOMYA déjà présente dans l'application, avec RIB artificiel ; les protections de collision, immutabilité, concurrence, idempotence et propriété restent vérifiées. Aucune règle commerciale de production n'a été changée pour faire passer un test.

Commandes reproductibles :

```sh
php vendor/bin/phpunit --filter '(SecurityHeadersTest|AdminManagementTest|AdminDashboardTest|CommerceTest|ReturnRequestsTest|GoogleSeoMerchantTest|CommercialDocumentsTest)'
node --test scripts/browser_qa/security.test.mjs
python3 -m unittest discover -s scripts -p test_browser_qa_local.py -v
python3 -m unittest discover -s scripts -p local_operation_security_test.py -v
```

La correction complémentaire du sitemap préparée dans la même session est également comprise dans les tests ciblés. L'ajout préalable d'Herozion comme dépendance de développement est conservé ; il ne fait pas partie de l'exécution du site PHP.

## 5. Limites de livraison

Cette revue ne constitue pas un test d'intrusion de homya.eu, une inspection des logs privés Hostinger, une vérification de tous les paramètres effectifs du CDN/hébergement, ni un examen des 67 emplacements non fournis. Le commit/push publie le code et les tests ; les scripts d'installation et recettes locales restent soumis à leurs autorisations d'exécution propres.

## 6. Second rapport Herozion fourni

Le scan `c1ee587bb3d0`, réalisé avec Herozion 2.0.14 et fourni après le commit `ec40621`, annonce **67 alertes : 46 critiques, 10 élevées et 11 moyennes, dont quatre détaillées et 63 masquées**. Le précédent en annonçait 72. Cette baisse de cinq ne permet pas d'attribuer cinq corrections précises : les identifiants et emplacements de toutes les alertes ne sont pas fournis. Le score `0/100` constitue le résultat de l'outil ; il ne démontre pas l'exploitabilité des flux ci-dessous.

Les quatre emplacements visibles reprennent des alertes du premier rapport. Deux revues indépendantes du code actuel confirment leur qualification :

| Emplacement du second rapport | Preuve vérifiée sur `ec40621` | Qualification |
| --- | --- | --- |
| `app/Services/Admin/Messages.php:97` | Table/colonnes constantes ; `insert(array)` et `where()` du Query Builder. Le framework installé compile les placeholders et transmet les bindings séparément : `Query/Builder.php:4231`, `Query/Grammars/Grammar.php:1289`, `Database/Grammar.php:219`, `Database/Connection.php:595`. Aucun objet `Expression` issu d'une entrée utilisateur. | Faux positif SQL dans ce flux. |
| `resources/views/admin/documents/edit.blade.php:21` | Formulaire POST, `route()`, `config()` et `str_pad()`. Aucun SQL ; les affichages `{{ … }}` sont compilés avec `e(%s)` par Blade. La langue est normalisée/validée dans le traitement du document. | Faux positif SQL. |
| `scripts/install_admin_management.py:46` | `subprocess.run` reçoit une liste, `shell=False`, timeout de 60 secondes et environnement limité. Ses appelants ciblent le client MariaDB XAMPP et un script PHP du dépôt ; aucune entrée LLM. | Faux positif « sortie LLM vers un shell ». |
| `scripts/install_commerce_preview.py:33` | Liste d'arguments, `shell=False`, timeout de 30 secondes et environnement limité. Son appelant utilise deux commandes Git définies dans le script. Les noms de fichier de l'autre appel Git sont contrôlés et restent des arguments séparés. | Faux positif « sortie LLM vers un shell ». |

Aucune modification du code applicatif ni suppression globale de règles n'est requise pour ces quatre alertes. Ces preuves permettent leur requalification ; elles ne modifient pas automatiquement le résultat affiché par Herozion. Les **63 alertes masquées du second rapport restent non attribuables individuellement**, malgré la revue élargie déjà décrite. Un export détaillé serait nécessaire pour relier chacune à une correction ou à une qualification.

### Preuve de non-régression ajoutée et exécutée

`AdminManagementTest::test_message_sql_fragments_remain_bound_and_encrypted_without_changing_neighboring_data` vérifie le service réellement signalé, sur SQLite en mémoire. Les nonce et identifiants contenant guillemets et fragments SQL restent dans les bindings ; le journal des requêtes ne contient jamais ces entrées interpolées. Le corps et le client restent chiffrés et sont relus intacts ; l'identifiant malformé et le nonce rejoué sont refusés. Les tables et les lignes voisines sont préservées. Les transports e-mail et HTTP sont contrôlés sans envoi. **Le nouveau test passe avec 161 assertions.**

Nouvelle exécution des suites ciblées : **140 tests réussis**, soit 93 PHP / 1 855 assertions, 15 Node, 4 Python de recettes et 28 Python d'opérations locales. Les tests PHP ont été lancés avec `APP_ENV=testing` et une clé de chiffrement artificielle. Aucun accès à MariaDB/XAMPP, installateur réel ou test de production. Pint et `php -l` passent sur le fichier ajouté à la vérification.

Les trois commandes Node/Python de la section 4 restent identiques. Commande PHP de cette seconde vérification :

```sh
APP_ENV=testing APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php vendor/bin/phpunit --filter '(SecurityHeadersTest|AdminManagementTest|AdminDashboardTest|CommerceTest|ReturnRequestsTest|GoogleSeoMerchantTest|CommercialDocumentsTest)'
```

## 7. Revue élargie et corrections du 14 octobre 2026

Le nouveau rapport utilisateur `669c546e6525` reprend les mêmes 67 alertes, quatre détaillées et 63 masquées. La demande de correction complète a conduit à trois revues indépendantes supplémentaires : scripts historiques restant hors du premier durcissement, surface de déploiement/stockage/journaux, et transitions commande/virement/stock. Les constats ci-dessous proviennent de ces flux vérifiés ; leurs emplacements ne peuvent pas être attribués aux 63 alertes masquées.

### Constats corrigés avec reproduction sur fixtures

| Référence | Observation et qualification | Correction et preuve |
| --- | --- | --- |
| SEC-004 — Diagnostics de devis automatiques | **Faible, confidentialité des journaux privés.** `AutomaticOrderQuote` conservait un extrait de message et le code arbitraire d'exception. Les filtres e-mail/mot de passe laissaient des noms, adresses, fragments bancaires ou codes contenant des données. Deux fixtures reproduisent la conservation de ces valeurs ; aucune fuite publique ou effective n'est établie. | Message et code arbitraire supprimés ; seuls référence d'ordre hachée, étape fixe et type d'exception sont enregistrés. Deux tests / 26 assertions passent et vérifient aussi le maintien de l'état de devis bloqué, sans envoi. |
| SEC-005 — Temporaire d'installation d'optimisation | **Faible, écriture locale hors cible.** `pending.exists()` ignorait un lien symbolique cassé ; `shutil.copy2()` créait sa destination avant le refus ultérieur. Le test avant correction reproduit cette écriture uniquement dans un dossier temporaire. Précondition : possibilité de placer ce fichier dans le répertoire local. | Création exclusive `O_EXCL/O_NOFOLLOW`, permissions privées dès ouverture, contrôles propriétaire/type/liens/ancêtres, empreintes et identité de l'inode avant remplacement. Douze tests passent : cible extérieure intacte, collision et substitutions refusées, permissions finales conservées. |
| SEC-006 — Installateur commerce local | **Faible, risque opérationnel.** Une configuration `production` pouvait atteindre le registre et les mutations XAMPP/configuration avant le refus de la commande PHP. Reproduction avec effets simulés. Les commandes SQL directes restent locales ; aucune écriture distante démontrée. | Gardes locales avant application/reprises/vérification et commandes sensibles ; environnement enfant réduit. Copies, sauvegardes et preuves privées dès création, JSON de reprise contrôlés, noms de tables validés. Seize tests passent ; production/URL publique/cache refusés avant effet. Le CLI avait déjà une umask privée : ses anciens `chmod` tardifs ne constituent pas une nouvelle fuite démontrée. |
| SEC-007 — Journaux de démarrage | **Faible, intégrité locale.** Le journal ouvert en append suivait un lien symbolique ou physique et pouvait recevoir une sortie dans un autre fichier. Reproduit sur deux fichiers fictifs ; le répertoire runtime privé réduit la menace et aucun accès Web n'est établi. | Ouverture `O_NOFOLLOW/O_NONBLOCK`, contrôle par descripteur du type, propriétaire, lien unique et mode `0600`, avant enfant. Environnement réduit et timeouts. Refus des liens/FIFO sans écriture ni démarrage. |
| FONC-002 — Disponibilité locale | **Défaut fonctionnel, portée locale.** Le contrôle de disponibilité suivait une redirection et acceptait une réponse `200` d'une origine externe comme état prêt. Reproduit avec un double HTTP, sans réseau. | Redirections refusées ; origine exacte `http://127.0.0.1:8080` et HTTP `200` requis. Les treize tests du lanceur couvrent ce point et SEC-007. |
| FONC-003 — Activation du virement différé | **Risque de disponibilité du stock.** Le statut intermédiaire `pending_payment` était engagé avant le RIB. Une erreur de conversion pouvait laisser cette réservation hors expiration. Six des huit nouveaux cas échouaient avant correction sur SQLite mémoire. Aucun paiement frauduleux démontré. | Statut `pending_bank_transfer` et RIB chiffré engagés ensemble sous verrou dans une seule transaction. Une erreur de lecture/chiffrement/écriture préserve l'état différé et son expiration ; 72 heures initiales, idempotence et refus des anciennes tentatives carte conservés. Huit cas / 105 assertions passent ; suite Commerce : 39 tests / 822 assertions. |

**Durcissement préventif des reçus CLI :** `ConfirmBankTransfer` refuse désormais les liens d'ancêtres, liens physiques, alias/traversées et dossiers/fichiers non privés avant SQL. Le chemin canonique, propriétaire, mode `0700/0600`, identité et lien unique sont contrôlés ; lecture bornée à 8 192 octets. Sept fixtures autrefois acceptées en prévisualisation ont été reproduites ; les 18 tests / 92 assertions passent après correction. La validation de la réception par HOMYA, le montant exact, l'idempotence et le règlement du stock restent inchangés. Cette défense locale supplémentaire n'établit pas une possibilité de paiement par un visiteur Web.

### Relecture et résultat natif du scanner

Les changements ont reçu une relecture indépendante : aucune régression bloquante identifiée dans les flux examinés. Les protections serveur existantes, les requêtes paramétrées, la propriété des commandes et le traitement privé des preuves sont préservés. La présence de `mod_rewrite`, les permissions/configurations effectivement déployées et la concurrence réelle MariaDB ne sont pas prouvées par ces fixtures.

Un scan standard **hors ligne**, `scan . --offline --output json`, a été exécuté sans push cloud, achat ni correctif automatique. Le résultat privé `34a367a34d7043a5b38d0c8527734c24` annonce encore 67 alertes / score 0, avec `partial_results=true`, quatre descriptions et 63 masquées. Les quatre identifiants visibles sont :

- `e6bac1eda081` : `Messages.php:97`, SQL — paramètres liés vérifiés.
- `42689eefce9a` : `edit.blade.php:21`, SQL — aucun SQL dans la vue.
- `127208566da0` : `install_admin_management.py:46`, LLM/shell — liste d'arguments, aucun LLM/shell.
- `b9e489dbe4f3` : `install_commerce_preview.py:33`, même qualification.

Leurs qualifications restent celles de la section 6. Aucune exclusion globale, waiver ou suppression de règle n'a été appliquée. Le résultat natif inchangé ne mesure pas la correction des défauts indépendants ci-dessus. **Les 63 alertes sans emplacement restent non qualifiées individuellement** ; un rapport détaillé reste nécessaire pour affirmer le traitement de chacune.

### Vérification complète et antériorité des attentes d'interface

La première suite complète a rencontré les sockets Unix fictifs interdits par le bac à sable ; sa réexécution autorisée avec ces sockets a parcouru 446 tests, dont 442 réussis, avec quatre échecs d'interface. Une vérification isolée des quatre classes de tests et des classes applicatives concernées dans leur version `edde03e` reproduit exactement ces quatre échecs : ils sont antérieurs aux corrections présentes.

Les fixtures/attentes portent sur la description factuelle de secours, les contacts configurables et les liens/mentions de virement du pied de page. Leur actualisation conserve les contrôles de destination, traduction et échappement ; aucun contenu de page, coordonnée, prix ou règle commerciale n'est modifié pour faire passer un test. Les preuves locales sont conservées sous `output/qa-security-20261014/` ; le JSON natif du scanner est privé et ignoré par Git.

**Résultat final : 534 tests réussis**, soit la suite PHP complète de **446 tests / 12 577 assertions**, **73 tests Python** (28 gardes précédentes, 4 wrappers navigateur, 16 installateur commerce, 12 optimisation, 13 démarrage) et **15 tests Node**. Les quatre classes d'interface actualisées passent aussi séparément : 27 tests / 4 181 assertions. Pint, syntaxe de dix fichiers PHP et six fichiers Python, et `git diff --check` passent. Les tests supplémentaires reposent sur SQLite mémoire, sockets Unix fictifs, fichiers temporaires privés et doubles de processus/HTTP/e-mail ; aucun installateur, démarrage de service, migration MariaDB ou envoi réel n'a été exécuté.

Commande PHP finale, avec clé artificielle et rapport JUnit local :

```sh
APP_ENV=testing APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php -d memory_limit=512M vendor/bin/phpunit --log-junit output/qa-security-20261014/phpunit-final.xml
```

Les nouvelles suites Python se reproduisent avec `python3 -m unittest discover -s scripts -p test_install_commerce_local.py`, puis les motifs `test_install_local_optimization_security.py` et `test_start_homya_local.py`. Les autres commandes de la section 4 restent valables. Le commit/push livre les correctifs et leurs tests ; il ne remplace pas une inspection de la configuration effective Hostinger ni une identification des 63 alertes masquées.
