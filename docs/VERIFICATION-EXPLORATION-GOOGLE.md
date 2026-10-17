# Vérification Googlebot et PageSpeed — 17 octobre 2026

Périmètre : dépôt HOMYA1 et lectures HTTP publiques limitées de https://homya.eu. Aucune modification de production, de configuration privée ou de base de données.

## Résultats observés

- `https://homya.eu/robots.txt` : HTTP 200, `User-agent: *`, `Allow: /`, sitemap déclaré sur le même domaine.
- `https://homya.eu/fr`, User-Agent mobile Chrome-Lighthouse : HTTP 200, aucune redirection, vérification TLS réussie ; premier octet environ 0,85 s, réponse complète environ 1,16 s sur une requête.
- Même URL, User-Agent Googlebot : HTTP 200, aucune redirection, TLS valide ; premier octet environ 0,67 s, réponse complète environ 0,98 s sur une requête.
- Réponse Lighthouse : meta robots et X-Robots-Tag `index, follow, max-image-preview:large` ; canonique `https://homya.eu/fr`.

## Inspection du code

Les deux `.htaccess` ciblent des outils de copie hors ligne, sans motif Googlebot, Lighthouse ou HeadlessChrome. Le robots statique interdit volontairement l’exploration ; la configuration Apache publique le redirige vers le contrôleur Laravel, qui autorise effectivement le domaine public. Le mode local reste fermé à l’indexation.

La limitation de requêtes est en observation par défaut. En mode bloquant, les IP appartenant aux listes Google vérifiées sont exemptées ; une liste absente, expirée ou invalide provoque un retour en observation. Un User-Agent seul ne donne aucun privilège. Les protections de production, des secrets et des espaces privés restent nécessaires.

## Vérification locale

Commande : `php vendor/bin/phpunit tests/Feature/StorefrontProtectionTest.php tests/Feature/GoogleSeoMerchantTest.php tests/Feature/SecurityHeadersTest.php`.

Résultat : 39 tests réussis, 984 assertions, sans base réelle.

## Conclusion et limites

Aucun blocage Googlebot ou Lighthouse reproductible identifié ; aucune correction du code ni ouverture supplémentaire des accès justifiée. Les modifications À propos présentes avant cet audit sont conservées.

Les User-Agent simulés ne prouvent pas l’accessibilité depuis les IP réelles de Google. Aucun score Lighthouse, Core Web Vitals ou diagnostic PageSpeed réel mesuré. Un incident ponctuel du service tiers, son quota API, un délai dépassé ou une règle CDN dépendante de la région restent des hypothèses, sans cause confirmée. Aucune revue exhaustive du CDN, du DNS mondial, des pages produit ou de la configuration privée Hostinger.

Références : https://developers.google.com/speed/docs/insights/v5/about et https://developers.google.com/speed/docs/insights/rest/v5/pagespeedapi/runpagespeed.
