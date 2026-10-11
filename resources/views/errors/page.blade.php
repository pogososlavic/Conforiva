@php
    $locale = in_array(app()->getLocale(), config('homya.locales', ['fr', 'en', 'de']), true) ? app()->getLocale() : 'fr';
    $messages = [
        'fr' => [
            'eyebrow' => 'HOMYA · Assistance', 'generic_title' => 'Une difficulté est survenue',
            'generic_message' => 'Nous ne pouvons pas afficher cette page pour le moment. Réessayez dans quelques instants.',
            'titles' => [400 => 'Adresse du site non reconnue', 401 => 'Connexion requise', 403 => 'Accès non autorisé', 404 => 'Cette page est introuvable', 405 => 'Action momentanément impossible', 419 => 'Votre session a expiré', 422 => 'Informations à vérifier', 429 => 'Veuillez patienter un instant', 500 => 'Un incident technique est survenu', 502 => 'Le service répond avec retard', 503 => 'Le service est momentanément indisponible', 504 => 'Le chargement prend plus de temps que prévu'],
            'details' => [400 => 'Vérifiez que vous utilisez le site homya.eu.', 401 => 'Connectez-vous pour continuer.', 403 => 'Cette page n’est pas accessible avec votre compte.', 404 => 'Le lien a peut-être changé ou la page n’existe plus.', 405 => 'Revenez à l’accueil et réessayez depuis la page concernée.', 419 => 'Revenez à la page précédente et recommencez votre action.', 422 => 'Certaines informations n’ont pas pu être traitées. Vérifiez le formulaire et réessayez.', 429 => 'Trop de demandes ont été reçues en peu de temps. Réessayez dans quelques minutes.', 500 => 'Une erreur empêche le chargement de la page. Réessayez dans quelques instants.', 502 => 'La page ne répond pas normalement. Réessayez dans quelques instants.', 503 => 'Une vérification ou un incident empêche momentanément son chargement. Réessayez dans quelques minutes.', 504 => 'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.'],
            'reference' => 'Référence de suivi', 'reference_help' => 'Si vous contactez HOMYA, indiquez cette référence.',
            'home' => 'Retour à l’accueil', 'contact' => 'Contacter HOMYA', 'subject' => 'Aide concernant le site HOMYA',
        ],
        'en' => [
            'eyebrow' => 'HOMYA · Support', 'generic_title' => 'Something went wrong',
            'generic_message' => 'We cannot display this page right now. Please try again shortly.',
            'titles' => [400 => 'Website address not recognised', 401 => 'Sign-in required', 403 => 'Access unavailable', 404 => 'This page cannot be found', 405 => 'This action is temporarily unavailable', 419 => 'Your session has expired', 422 => 'Please check your information', 429 => 'Please wait a moment', 500 => 'A technical issue occurred', 502 => 'The service is taking longer than usual', 503 => 'The service is temporarily unavailable', 504 => 'The page is taking longer than expected'],
            'details' => [400 => 'Check that you are using homya.eu.', 401 => 'Sign in to continue.', 403 => 'Your account cannot access this page.', 404 => 'The link may have changed or the page may no longer exist.', 405 => 'Return to the home page and try again.', 419 => 'Return to the previous page and repeat your action.', 422 => 'Some information could not be processed. Check the form and try again.', 429 => 'We received too many requests in a short time. Please try again in a few minutes.', 500 => 'An error prevented the page from loading. Please try again shortly.', 502 => 'The page is not responding normally. Please try again shortly.', 503 => 'A check or technical issue is temporarily preventing the page from loading. Please try again in a few minutes.', 504 => 'The server is taking too long to respond. Please try again shortly.'],
            'reference' => 'Support reference', 'reference_help' => 'If you contact HOMYA, please include this reference.',
            'home' => 'Back to home', 'contact' => 'Contact HOMYA', 'subject' => 'Help with the HOMYA website',
        ],
        'de' => [
            'eyebrow' => 'HOMYA · Hilfe', 'generic_title' => 'Ein Problem ist aufgetreten',
            'generic_message' => 'Diese Seite kann gerade nicht angezeigt werden. Bitte versuchen Sie es gleich noch einmal.',
            'titles' => [400 => 'Website-Adresse nicht erkannt', 401 => 'Anmeldung erforderlich', 403 => 'Zugriff nicht möglich', 404 => 'Diese Seite wurde nicht gefunden', 405 => 'Diese Aktion ist vorübergehend nicht möglich', 419 => 'Ihre Sitzung ist abgelaufen', 422 => 'Bitte überprüfen Sie Ihre Angaben', 429 => 'Bitte warten Sie einen Moment', 500 => 'Ein technisches Problem ist aufgetreten', 502 => 'Der Dienst antwortet verzögert', 503 => 'Der Dienst ist vorübergehend nicht verfügbar', 504 => 'Das Laden dauert länger als erwartet'],
            'details' => [400 => 'Bitte prüfen Sie, ob Sie homya.eu verwenden.', 401 => 'Melden Sie sich an, um fortzufahren.', 403 => 'Ihr Konto hat keinen Zugriff auf diese Seite.', 404 => 'Der Link wurde möglicherweise geändert oder die Seite existiert nicht mehr.', 405 => 'Gehen Sie zur Startseite zurück und versuchen Sie es erneut.', 419 => 'Gehen Sie zur vorherigen Seite zurück und wiederholen Sie Ihre Aktion.', 422 => 'Einige Angaben konnten nicht verarbeitet werden. Bitte prüfen Sie das Formular.', 429 => 'In kurzer Zeit gingen zu viele Anfragen ein. Bitte versuchen Sie es in einigen Minuten erneut.', 500 => 'Ein Fehler verhindert das Laden der Seite. Bitte versuchen Sie es gleich noch einmal.', 502 => 'Die Seite antwortet gerade nicht normal. Bitte versuchen Sie es gleich noch einmal.', 503 => 'Eine Prüfung oder ein technisches Problem verhindert vorübergehend das Laden. Bitte versuchen Sie es in einigen Minuten erneut.', 504 => 'Der Server benötigt zu lange für eine Antwort. Bitte versuchen Sie es gleich noch einmal.'],
            'reference' => 'Support-Referenz', 'reference_help' => 'Bitte geben Sie diese Referenz an, wenn Sie HOMYA kontaktieren.',
            'home' => 'Zur Startseite', 'contact' => 'HOMYA kontaktieren', 'subject' => 'Hilfe zur HOMYA-Website',
        ],
    ];
    $copy = $messages[$locale];
    $status = (int) ($status ?? 500);
    $title = $copy['titles'][$status] ?? $copy['generic_title'];
    $details = $copy['details'][$status] ?? $copy['generic_message'];
    $reference = is_string($reference ?? null) ? $reference : null;
    $contactEmail = config('contact.email');
    $contactEmail = is_string($contactEmail) && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : null;
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f5c842">
    <title>{{ $title }} · HOMYA</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #28241b; background: #faf9f6; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; }
        main { width: min(100%, 620px); padding: clamp(28px, 7vw, 64px); background: #fff; border: 1px solid #28241b24; border-radius: 20px; box-shadow: 0 18px 60px #28241b12; }
        .brand { display: inline-block; color: #28241b; font-size: 23px; font-weight: 850; letter-spacing: -.06em; text-decoration: none; }
        .brand span { color: #a76b00; }
        .eyebrow { margin: 42px 0 12px; color: #6d675b; font-size: 12px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        h1 { max-width: 13ch; margin: 0; font-family: Georgia, "Times New Roman", serif; font-size: clamp(34px, 8vw, 52px); line-height: 1.08; letter-spacing: -.035em; }
        .details { margin: 20px 0 0; color: #514c42; font-size: 17px; line-height: 1.65; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .button { display: inline-flex; min-height: 48px; align-items: center; justify-content: center; padding: 11px 18px; border: 1px solid #947200; border-radius: 9px; background: #f5c842; color: #28241b; font-size: 15px; font-weight: 750; text-align: center; text-decoration: none; }
        .button:hover { background: #f9d96f; }
        .contact { display: inline-flex; min-height: 48px; align-items: center; padding: 11px 8px; color: #28241b; font-size: 15px; font-weight: 650; text-underline-offset: 3px; }
        .reference { margin-top: 32px; padding: 16px; border-radius: 10px; background: #faf9f6; color: #514c42; font-size: 14px; }
        .reference strong, .reference code { display: block; }
        .reference code { margin-top: 5px; color: #28241b; font-size: 15px; font-weight: 750; letter-spacing: .04em; overflow-wrap: anywhere; }
        .reference p { margin: 8px 0 0; }
        a:focus-visible { outline: 3px solid #28241b; outline-offset: 4px; }
        @media (max-width: 480px) { body { padding: 14px; } main { border-radius: 14px; } .eyebrow { margin-top: 32px; } .actions { display: grid; } .contact { justify-content: center; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; } }
    </style>
</head>
<body>
<main aria-labelledby="error-title">
    <a class="brand" href="{{ url('/'.$locale) }}" aria-label="HOMYA">HOMYA<span>.</span></a>
    <p class="eyebrow">{{ $copy['eyebrow'] }}</p>
    <h1 id="error-title">{{ $title }}</h1>
    <p class="details">{{ $details }}</p>
    <div class="actions">
        <a class="button" href="{{ url('/'.$locale) }}">{{ $copy['home'] }}</a>
        @if($contactEmail)<a class="contact" href="mailto:{{ $contactEmail }}?subject={{ rawurlencode($copy['subject']) }}">{{ $copy['contact'] }}</a>@endif
    </div>
    @if($reference)
        <aside class="reference" aria-label="{{ $copy['reference'] }}">
            <strong>{{ $copy['reference'] }}</strong>
            <code>{{ $reference }}</code>
            <p>{{ $copy['reference_help'] }}</p>
        </aside>
    @endif
</main>
</body>
</html>
