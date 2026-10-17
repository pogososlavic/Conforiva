# HOMYA — revue Herozion et durcissement du 17 octobre 2026

## Périmètre et méthode

Demande utilisateur : analyser le rapport fourni et apporter les corrections nécessaires. Dépôt HOMYA1, Laravel 13, catalogue, panier/commande, administration, documents, assistance, téléversements et installateurs locaux. Revue du code, tests locaux sur fichiers temporaires et SQLite en mémoire ; consultation des avis de sécurité des registres Composer/npm et une requête HEAD sur la page publique `https://homya.eu/fr`. Aucun scan offensif, test de charge, exploitation distante, modification Hostinger, déploiement, migration MariaDB, transaction ou envoi de message.

Les audits des 10 et 13–14 octobre restent disponibles dans `AUDIT-SECURITE-HOMYA.md` et `AUDIT-SECURITE-HOMYA-20261013.md`. Leurs corrections existantes sont conservées. Les prix, remises, contenus légaux et mécanismes de confirmation des paiements ne sont pas modifiés.

## Rapport Herozion fourni

Version 2.0.14, scan `0b3e0c5e478a` : 70 alertes annoncées, dont quatre détaillées et 66 masquées. Ce scan porte sur les fichiers du dépôt local, pas sur un test d'exploitation du serveur de production. Son score ne démontre pas que 48 failles sont exploitables. Les compteurs de confiance de sa synthèse ne permettent pas de réconcilier chaque résultat individuellement.

| Alerte visible | Qualification dans le flux examiné | Preuve |
| --- | --- | --- |
| SQL, `app/Services/Admin/Messages.php:97` | Faux positif confirmé pour le flux examiné | Query Builder, valeurs liées et corps chiffré. Test existant avec fragments SQL, identifiants malformés et nonces rejoués ; données voisines et tables conservées. |
| SQL, `resources/views/admin/documents/edit.blade.php:21` | Faux positif pour la ligne examinée | Vue Blade et calcul du numéro affiché, aucune exécution SQL dans cette ligne ; champs échappés et formulaire avec CSRF. Numéro réservé côté serveur sous verrou à l'émission. |
| LLM/shell, `scripts/install_admin_management.py:46` | Faux positif pour le flux examiné | Liste d'arguments, `shell=False`, commandes locales contrôlées et gardes d'environnement ; aucune réponse LLM. |
| LLM/shell, `scripts/install_commerce_preview.py:33` | Faux positif pour le flux examiné | Appel Git structuré, `shell=False`, aucune intégration LLM. |

Les 66 alertes masquées restent **non qualifiées individuellement**. La revue élargie ci-dessous ne prétend pas corriger chacune de ces alertes inconnues. Aucun achat Herozion, envoi du dépôt, correctif automatique ou suppression de règle du scanner.

## Actifs et menaces examinés

Actifs : sessions, comptes administrateurs, secrets d'intégration, clients, commandes, paiements, stock, conversations et documents privés. Frontières : navigateur/application ; application/SQL ; application/stockage ; intégrations externes ; outils locaux/hébergement. Scénarios : injection, accès à une commande tierce, falsification de paiement, téléversement exécutable, fuite de debug/journaux et abus de fréquence.

## Corrections et preuves

| ID | Constat et sévérité justifiée | Correction et vérification |
| --- | --- | --- |
| SEC-17-01 | **Moyenne, résistance aux abus.** Les mutations du panier n'avaient pas de plafond de fréquence ; elles peuvent charger catalogue, stock et sessions sous verrou. Aucune saturation distante reproduite. | Limite commune de 60 écritures/minute et 600/heure par IP, partagée entre ajout/modification/retrait et langues. Test initial : la 61e écriture était acceptée par la validation ; après correction, réponse 429, même après renouvellement de session. IP distincte non bloquée. Ne protège pas à elle seule d'un DDoS distribué. |
| SEC-17-02 | **Faible, défense en profondeur locale.** Le disque privé créait les fichiers en 0644 avec une umask 0022. Les dossiers 0700 et le chiffrement existant limitent l'accès ; aucune fuite Web démontrée. | Visibilité explicitement privée pour fichiers et dossiers. Reproduction initiale 0644, correction vérifiée 0600/0700 sur un disque temporaire utilisant le vrai adaptateur. N'applique pas de chmod récursif aux fichiers de production déjà présents. |
| SEC-17-03 | **Faible, comportement des limites.** La frontière commerce conservait le statut d'erreur mais supprimait les en-têtes des exceptions HTTP, dont Retry-After. | Conservation des en-têtes d'erreur du framework, corps générique et politiques privées conservés. Tests 429 avec Retry-After sur les trois mutations du panier. |
| SEC-17-04 | **Informationnelle, configuration conditionnelle.** Le refus des PHP autres que index.php reposait sur mod_rewrite ; aucune exécution d'upload démontrée et les images administrateur sont déjà réencodées en WebP. | Refus FilesMatch indépendant de mod_rewrite, incluant variantes PHP, PHPS/PHT/PHTML/PHAR et doubles extensions. Test des motifs : fichiers exécutables et sauvegardes refusés, index.php/PDF/XML/images/JS autorisés. Application effective Apache/LiteSpeed à vérifier après déploiement. |

Une fixture ancienne d'administration ne contenait pas l'identité réelle de la session (`id`, `username`, `role`). Elle provoquait deux refus de réauthentification avant les assertions métier. Fixture corrigée pour représenter le véritable login, sans réduire les contrôles applicatifs ; tests de mot de passe, RIB invalide et reçu bancaire exact/idempotent repassent.

## Contrôles existants conservés

- Les occurrences SQL brutes applicatives examinées utilisent des expressions constantes ou des placeholders ; colonnes et tables définies côté serveur. Aucune entrée de requête interpolée identifiée dans ces occurrences.
- Blade échappe les textes ; le seul rendu JSON-LD brut utilise les options JSON_HEX et un nonce CSP. Tests d'échappement dans l'administration, l'assistance et les documents.
- Session administrateur renouvelée au login, expiration et empreinte de compte, routes propriétaire refusées aux gestionnaires ; réauthentification pour opérations sensibles.
- Contrôle du propriétaire avant lecture de commandes, PDF et justificatifs. Suivi public limité à une référence aléatoire complète et à une étape, sans coordonnées client.
- Justificatifs : formats/extension/taille bornés, stockage chiffré en quarantaine, quantité bornée, absence de passage automatique en « payé ». Images administrateur décodées/réencodées en WebP, dimensions bornées.
- CSRF fourni par Laravel ; budgets existants pour login, commande, assistance, retours, adresses et téléversements. Ces contrôles n'ont pas été désactivés.
- ProductionGuard : environnement/HTTPS/hôte canonique, debug désactivé, sessions sécurisées, stockage non servi, permissions et absence de serveur Vite de développement. Exceptions publiques sans détails SQL ni secrets.

## Vérifications publiques et dépendances

Requête HEAD unique sur `/fr` : HTTP/2 200, HSTS max-age=31536000, nosniff, SAMEORIGIN, Permissions-Policy, politique de référent et CSP avec nonce observés. Une seconde CSP `upgrade-insecure-requests` est également fournie par l'hébergement ; les politiques s'appliquent conjointement. Aucune configuration du pare-feu/CDN n'est déduite du seul nom du serveur.

`composer audit --locked --no-interaction --format=json` : aucun avis ni paquet abandonné. `npm audit --json` : zéro vulnérabilité signalée, incluant les dépendances de développement. Ce résultat est limité aux avis disponibles à la date du contrôle ; aucune dépendance n'a été mise à jour.

Le contrôle `homya:security-audit --json` local indique naturellement que le poste n'est pas configuré comme production (URL HTTP locale, debug et sessions locales). Ce résultat n'a pas été interprété comme la configuration effective de homya.eu. Les noms/booleans seuls ont été examinés, aucun secret affiché.

## Livraison et actions restantes par priorité

### Résultats de vérification

Suite PHP complète : **518 tests réussis, 14 178 assertions**, avec 512 Mo uniquement pour le processus de test. Les fixtures de sockets Unix ont nécessité une exécution autorisée hors sandbox ; elles n'ont pas connecté la base MariaDB réelle. Les premières exécutions ont identifié les deux attentes anciennes du footer et la session administrative incomplète, corrigées dans les fixtures. Les refus dus aux sockets bloqués et à la limite mémoire initiale ne sont pas présentés comme des failles applicatives.

Tests ciblés de durcissement : **3 tests / 87 assertions**, incluant les permissions physiques avec une umask permissive, la résistance au changement de langue/session du budget panier, les en-têtes 429 et les motifs Apache indépendants de mod_rewrite. Tests Python des gardes locales : **28 tests réussis**, processus/SQL/démarrages simulés. Pint, syntaxe PHP des fichiers applicatifs modifiés et `git diff --check` réussis. Aucun build d'assets requis par ces modifications serveur.

1. **P0 — appliquer les correctifs au serveur après validation du déploiement.** Paquet sans installateurs locaux ni sorties de tests ; racine Web `public/`. Régénérer les caches de configuration/routes selon la procédure de livraison existante, puis exécuter `php artisan homya:security-audit --json` sur le serveur. Pas de migration requise par ces correctifs. Vérifier le refus HTTP des fichiers sensibles et des exécutables avec des témoins non sensibles, sans publier de code exécutable de test.
2. **P1 — vérifier Hostinger.** Permissions des anciens fichiers privés et de la configuration, exposition des sauvegardes, pare-feu/CDN et identification correcte des IP clientes. Avant changement d'une politique réseau, vérifier les effets sur visiteurs, administration et robots Google. MFA des comptes hébergeur/Git/Google, privilèges minimaux et suppression des accès inutiles : état effectif non inspecté ici.
3. **P1 — sauvegarde et récupération.** Vérifier une sauvegarde privée complète et une restauration en environnement isolé ; leur existence ne prouve pas leur restaurabilité. Aucun test de restauration réelle effectué ici.
4. **P2 — maintenir et surveiller.** Réexaminer les avis de dépendances après mises à jour ; suivre 429/5xx, échecs de connexion, accès administrateur et événements de paiement sans consigner de secrets. La limitation catalogue existante reste conditionnée à la validation des IP et des plages des robots ; ne pas l'activer aveuglément. Aucun monitoring continu mis en place dans cette intervention.

Les alertes masquées exigent leur export détaillé pour une clôture individuelle. Les comportements serveur effectifs, MFA et règles CDN ne sont pas certifiés par les tests locaux. Aucun site invulnérable ou absence générale de vulnérabilités revendiquée.

## Vérification publique après intervention utilisateur — 17 octobre 2026

L'utilisateur a transmis les résultats du terminal Hostinger : PHP 8.5.11 ; optimize:clear, config:cache, route:cache et view:cache réussis ; homya:security-audit avec configuration_passed=true pour les 18 contrôles listés. Ce sont des preuves fournies par l'utilisateur, distinctes de l'inspection distante directe. hosting_tls_permissions_backups_verified reste false car la commande ne réalise pas ces contrôles d'hébergement.

Contrôle direct limité à 13 requêtes HEAD, sans téléchargement de fichiers privés ni écriture : accueil et catalogue 200 ; panier 200 avec no-store/private et no-referrer ; commande sans panier 302 vers le panier ; connexion admin 200 avec no-store/private, no-referrer et X-Frame-Options DENY ; HTTP et www redirigent en 301 vers https://homya.eu/fr. HTTPS validé par le client sans désactivation de vérification du certificat. HSTS, nosniff, Permissions-Policy et CSP avec nonce observés sur la réponse applicative publique.

Les chemins .env, .git/config, composer.json, storage/logs/laravel.log et scripts/install_commerce_preview.py renvoient 403. Un chemin témoin PHP non créé dans media/admin-products renvoie également 403 : comportement de refus observé, aucun fichier exécutable téléversé ou lancé. Cette vérification ne prouve pas l'absence de tout autre chemin d'exposition ni le comportement lorsque mod_rewrite est absent.

Le dépôt local contient désormais le commit dfc8bdf (nouvellescorrections), avec les correctifs et tests de cette revue. L'identité exacte de la version serveur et les empreintes des cinq fichiers applicatifs restent à comparer depuis le terminal Hostinger. Les réponses publiques ci-dessus ne suffisent pas à confirmer les nouvelles limites du panier ou la visibilité du disque privé. Aucun test de rafale des limites n'a été effectué en production ; les tests automatisés locaux restent la preuve de ces comportements une fois la version serveur concordante.
