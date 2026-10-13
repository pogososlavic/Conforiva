# Recettes locales avec accès privé

Les quatre commandes Python existantes restent les points d'entrée. Elles lancent
un processus Node indépendant, qui importe les scénarios de ce dossier. Les
identifiants restent uniquement en mémoire dans Node : aucun argument CLI,
variable d'environnement, code généré, trace ou état de session enregistré.

Le fichier d'accès doit appartenir au projet courant, dans
`storage/app/private/admin/access-*.txt`. Le dernier nom valide est sélectionné.
Son répertoire doit être détenu par l'utilisateur courant avec le mode `0700`,
et le fichier avec le mode `0600`. Les liens symboliques et les fichiers ayant
plusieurs liens physiques sont refusés. Les ancêtres jusqu'à la racine du système
doivent appartenir à l'utilisateur ou à root, sans écriture pour groupe/autrui
(exception du répertoire temporaire système root avec bit sticky). Un fichier absent ou invalide interrompt
la recette ; aucun accès du projet voisin `HOMYA` n'est utilisé. Ne copiez pas
des accès de production pour satisfaire cette condition.

Le runtime Node et le paquet `playwright` déjà installés dans
`~/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/` sont utilisés
par défaut. Un autre runtime installé peut être sélectionné via les deux
réglages non secrets `HOMYA_QA_NODE` (exécutable) et
`HOMYA_QA_PLAYWRIGHT_MODULE` (chemin absolu du paquet contenant `package.json`).
`PLAYWRIGHT_BROWSERS_PATH` peut désigner des navigateurs déjà installés. Ces
commandes n'installent aucune dépendance ni aucun navigateur.

Avant de lire les accès privés, le runner exige un `.env` privé, `APP_ENV=local`,
`APP_URL=http://127.0.0.1:8080`, aucun cache Laravel de configuration ni URL DB
substituée, un backend commerce `homya_local` avec son compte dédié et le socket
MariaDB XAMPP sur 3307, et un mailer `log` ou `array`. Le runner interne refuse
aussi les réglages de débogage/instrumentation hérités et passe au navigateur
uniquement un environnement minimal sans accès applicatifs. Cette vérification
des fichiers du dépôt ne certifie pas le `DocumentRoot` ou la configuration déjà
chargée par le serveur sur 8080 : vérifier séparément qu'il sert le projet local
autorisé avant une recette. Si le serveur sert une copie `HOMYA`, contrôler ses
réglages et son installation explicitement ; aucun accès de cette copie n'est
emprunté par ces scripts.

Ces gardes ne protègent pas contre un utilisateur root ou un autre processus
du même utilisateur capable de remplacer le code ou d'inspecter sa mémoire.

Tous les contextes sont limités à `http://127.0.0.1:8080`. Les appels API refusent
les destinations externes et ne suivent pas les redirections. Les service
workers, WebSockets et téléchargements sont bloqués. Les captures masquent les
champs de mot de passe, les codes d'accès et les références privées et sont
enregistrées avec des permissions privées. Le mode `--screenshots-only` des
documents se reconnecte dans son nouveau contexte et exige un brouillon portant
le nom de recette attendu.

La fixture de téléversement doit être le fichier PNG synthétique prévu dans
`/private/tmp/homya-admin-upload-fixture.png`, régulier, détenu par l'utilisateur,
sans lien ni écriture pour groupe/autrui, de taille bornée. Elle est lue par
descripteur et remise à Playwright sous forme de buffer, sans seconde lecture
du chemin. Son en-tête PNG est contrôlé ; l'intégrité complète de l'image reste
validée par le traitement applicatif habituel.

Les recettes de gestion, de téléversement et de documents peuvent modifier des
messages ou brouillons fictifs sur le serveur local. Elles nécessitent une
autorisation adaptée à cet environnement avant exécution. La correction de
sécurité a été contrôlée avec des fixtures temporaires et des doubles de
contexte, sans lancer ces recettes, sans lire les accès réels et sans utiliser
MariaDB, homya.eu, un service de messagerie ou un navigateur réel.

Contrôles isolés, sans navigateur :

```sh
node --test scripts/browser_qa/security.test.mjs
python3 -m unittest discover -s scripts -p test_browser_qa_local.py
```
