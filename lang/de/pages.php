<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'about_navigation' => 'Über uns',
    'help' => 'Hilfe & Beratung',
    'information' => 'Informationen',
    'pending' => 'Service in Vorbereitung',
    'pending_detail' => 'Der Katalog kann durchsucht werden. Der Verkauf ist noch nicht geöffnet.',
    'questions' => 'Häufige Fragen',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'Über HOMYA',
            'intro' => 'Eine Familiengeschichte seit 2012 – für Wärme und Komfort zu Hause.',
            'paragraphs' => [],
            'sections' => [
                [
                    'title' => 'Unsere Geschichte',
                    'paragraph' => 'HOMYA ist vor allem eine Familiengeschichte. Unser 2012 gegründetes Familienunternehmen baut auf einfachen Werten auf: Vertrauen, Qualität und Service. Heute erfüllt unser Onlineshop zwei wesentliche Bedürfnisse im Zuhause: Wärme und Komfort.',
                ],
                [
                    'title' => 'Holzbrennstoffe für Ihre Heizung',
                    'paragraph' => 'Scheitholz, Pellets und Briketts: Entdecken Sie die in unserem Katalog verfügbaren Brennstoffe. Prüfen Sie Holzarten, Verpackungen und Eigenschaften auf der jeweiligen Produktseite, um einen geeigneten Brennstoff für Kamin, Ofen, Kamineinsatz oder Heizkessel nach den Herstellerangaben auszuwählen.',
                ],
                [
                    'title' => 'Haushaltsgeräte für den Alltag',
                    'paragraph' => 'Kühlschränke, Waschmaschinen, Geschirrspüler, Backöfen, Staubsauger und Kleingeräte: Entdecken Sie die verfügbaren Geräte und Marken in unserem Katalog. Vergleichen Sie die Eigenschaften, um passende Geräte auszuwählen. Unser Angebot umfasst auch Heizgeräte, Möbel und Fernseher.',
                ],
                [
                    'title' => 'Unsere Zusagen',
                    'paragraph' => 'Klare Produktinformationen und Verkaufsbedingungen helfen Ihnen, Ihren Kauf vorzubereiten.',
                    'items' => [
                        'Kostenlose Lieferung nach Frankreich, Belgien und Deutschland gemäß den Bedingungen auf unserer Lieferseite.',
                        'Für andere Länder kontaktieren Sie HOMYA vor einer Bestellung: Machbarkeit, Versandkosten und Lieferzeit müssen bestätigt werden. Diese Ziele sind im Onlinebestellprozess nicht verfügbar.',
                        'Holz aus nachhaltig bewirtschafteten Wäldern.',
                        'Die angegebene Lieferzeit nach Frankreich, Belgien und Deutschland beträgt 3 bis 7 Arbeitstage nach bestätigtem Zahlungseingang. Beachten Sie die Lieferbedingungen und produktspezifischen Informationen.',
                        'Für unsere neuen Geräte gilt eine gesetzliche Gewährleistung von 2 Jahren. Eine etwaige Herstellergarantie ist davon getrennt und gilt nur nach den für das Produkt angegebenen Bedingungen.',
                        'Unser Kundenservice ist unter den unten angegebenen Kontaktdaten für Fragen zu Produkten, Bestellungen und Sendungsverfolgung erreichbar.',
                    ],
                ],
                [
                    'title' => 'Warum HOMYA wählen?',
                    'paragraph' => 'Mehr als zehn Jahre Familiengeschichte, eine Auswahl an Produkten für das Zuhause und Serviceorientierung prägen unsere Tätigkeit. Wir glauben, dass ein angenehmes Zuhause mit guten Produkten und einem vertrauenswürdigen Service beginnt. Vielen Dank, dass Sie Teil unserer Geschichte sind.',
                ],
                [
                    'title' => 'Das HOMYA-Team und das Unternehmen',
                    'paragraph' => 'HOMYA wird von dem unten genannten Geschäftsführer geleitet. Wir handeln als unabhängiger Händler: Marken bleiben Eigentum ihrer jeweiligen Inhaber; eine offizielle Verbindung zu Herstellern wird nicht beansprucht. Nachfolgend finden Sie unsere Unternehmensdaten, den Geschäftssitz und unsere Kontaktdaten.',
                    'company_details' => true,
                ],
            ],
            'useful_information' => 'Nützliche Informationen',
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Unsere Produkte',
            'intro' => 'Klare Orientierung beim Erkunden von HOMYA.',
            'paragraphs' => ['Sehen Sie sich Fotos, technische Daten und Optionen jedes Produkts an, um Ihren Kauf vorzubereiten.', 'HOMYA ist ein unabhängiger Händler. Unsere Geräte sind neu und versiegelt, mit 2 Jahren gesetzlicher Gewährleistung und einer Rechnung auf Ihren Namen.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Das passende Gerät finden',
            'intro' => 'Finden Sie ein Modell für Ihr Vorhaben.',
            'paragraphs' => ['Beginnen Sie mit einem Bereich und wählen Sie Kategorie und Budget. Vergleichen Sie die auf den Produktseiten angegebenen Maße, Kapazitäten, Leistungen und Ausstattungen.', 'Prüfen Sie bei Heizgeräten auch die Installationsanforderungen mit einer qualifizierten Fachkraft.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Kontakt',
            'intro' => 'Fragen zu Produkt, Lieferung, Bestellung oder Rücksendung? Hier finden Sie die HOMYA-Kontaktdaten.',
            'paragraphs' => ['Nennen Sie Ihr Anliegen und gegebenenfalls die Produkt- oder Bestellreferenz.', 'Kontaktieren Sie HOMYA per E-Mail, Telefon, WhatsApp oder SMS über die unten angegebenen Kontaktdaten.', 'Der Sitz nimmt auch zurückgesendete Waren einschließlich sperriger Güter an. Die Modalitäten finden Sie unter Rückgabe & Widerruf.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Lieferung',
            'intro' => 'Kostenlose Lieferung in Frankreich, Belgien und Deutschland.',
            'paragraphs' => ['Ihre Geräte werden durch unseren eigenen Transporteur innerhalb von 3 bis 7 Arbeitstagen nach bestätigtem Zahlungseingang geliefert.', 'Die Bestellungen werden aus Frankreich versandt. Die Gesamtlieferzeit umfasst 1 bis 2 Arbeitstage für die Vorbereitung nach bestätigtem Zahlungseingang, anschließend 2 bis 5 Arbeitstage für den Transport.', 'Arbeitstage sind Montag bis Freitag. Annahmeschluss ist um 14 Uhr Pariser Zeit (Europe/Paris).', 'Die Lieferung ist in Frankreich, Belgien und Deutschland kostenlos.', 'Für Möbel und sperrige Geräte teilen Sie bitte die Zugangsbedingungen mit, um die Lieferung mit HOMYA abzustimmen.', 'Rücksendungen sind bei einem innerhalb von 14 Tagen nach Erhalt erklärten Widerruf kostenlos. Adresse und Ablauf finden Sie unter Rückgabe & Widerruf.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Zahlung',
            'intro' => 'Hier finden Sie die Informationen zur Bezahlung Ihrer Bestellung.',
            'paragraphs' => ['Bestätigen Sie Warenkorb und Kontaktdaten, um Ihre Bestellung zu erfassen. Die Zahlungsanweisungen sind Ihrer Bestellreferenz zugeordnet.', 'Die Übersicht zeigt Artikel, Mengen und Gesamtbetrag in Euro einschließlich Steuern. Die Lieferung ist in Frankreich, Belgien und Deutschland kostenlos.', 'Ihre Artikel sind nach Bestätigung 72 Stunden reserviert. Die Bearbeitung geht nach Bestätigung der Zahlung weiter.', 'Bei Fragen zur Zahlung kontaktieren Sie HOMYA mit Ihrer Bestellnummer.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Bestellverfolgung',
            'intro' => 'Geben Sie Ihre Bestellnummer ein, um den Bearbeitungsstand zu sehen.',
            'paragraphs' => ['Prüfen Sie Erfassung, Zahlung, Vorbereitung, Versand und Zustellung.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Warenkorb',
            'intro' => 'Prüfen Sie Ihre Artikel und bestätigen Sie Ihre Bestellung.',
            'paragraphs' => ['Prüfen Sie Produkte, Optionen und Mengen, bevor Sie Ihre Kontaktdaten eingeben.'],
            'pending' => false,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'Wie finde ich ein Produkt?',
        'answer' => 'Nutzen Sie die Suche oben auf der Seite oder die Katalogfilter: Kategorie, Marke, Zustand und Preisbereich entsprechend den verfügbaren Daten.',
    ], [
        'question' => 'Kann ich die Sprache wechseln?',
        'answer' => 'Die Auswahl FR, EN und DE behält die Produktseite oder den Katalogkontext bei. Fehlende wesentliche Übersetzungen werden angezeigt.',
    ], [
        'question' => 'Wo finde ich technische Daten?',
        'answer' => 'Öffnen Sie eine Produktseite und nutzen Sie den Link Technische Daten. Weitere Fotos sind über die Vorschaubilder zugänglich, sofern vorhanden.',
    ], [
        'question' => 'Kann ich jetzt bestellen?',
        'answer' => 'Ja. Legen Sie Ihre Artikel in den Warenkorb, prüfen Sie die Mengen und bestätigen Sie Ihre Bestellung. Bewahren Sie die Bestellnummer auf, um den Bearbeitungsstand auf der Seite Bestellverfolgung zu sehen.',
    ]],
];
