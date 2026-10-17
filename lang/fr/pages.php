<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'about_navigation' => 'À propos',
    'help' => 'Aide & conseils',
    'information' => 'Informations',
    'pending' => 'Service en préparation',
    'pending_detail' => 'Le catalogue est consultable. Les ventes ne sont pas encore ouvertes.',
    'questions' => 'Questions fréquentes',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'À propos de HOMYA',
            'intro' => 'Depuis 2012, une histoire de famille autour de la chaleur et du confort de la maison.',
            'paragraphs' => [],
            'sections' => [
                [
                    'title' => 'Notre histoire',
                    'paragraph' => 'HOMYA, c’est avant tout une histoire de famille. Fondée en 2012, notre entreprise familiale s’est construite sur des valeurs simples : la confiance, la qualité et le sens du service. Aujourd’hui, notre boutique en ligne répond à deux besoins essentiels de la maison : la chaleur et le confort.',
                ],
                [
                    'title' => 'Du bois pour votre chauffage',
                    'paragraph' => 'Bûches, granulés, briquettes : découvrez les combustibles disponibles dans notre catalogue. Les essences, conditionnements et caractéristiques sont à vérifier sur chaque fiche produit pour choisir un combustible adapté à votre cheminée, poêle, insert ou chaudière, selon les préconisations du fabricant.',
                ],
                [
                    'title' => 'L’électroménager pour votre quotidien',
                    'paragraph' => 'Réfrigérateurs, lave-linge, lave-vaisselle, fours, aspirateurs et petit électroménager : explorez les appareils et les marques disponibles au catalogue. Comparez leurs caractéristiques pour choisir l’équipement qui correspond à vos attentes. Notre offre comprend également des appareils de chauffage, du mobilier et des téléviseurs.',
                ],
                [
                    'title' => 'Nos engagements',
                    'paragraph' => 'Des informations claires sur les produits et les conditions de vente vous aident à préparer votre achat.',
                    'items' => [
                        'Livraison gratuite en France, en Belgique et en Allemagne, selon les modalités de notre page Livraison.',
                        'Pour un autre pays, contactez HOMYA avant toute commande : la faisabilité, les frais d’expédition et le délai doivent être confirmés. Ces destinations ne sont pas proposées dans le parcours de commande en ligne.',
                        'Bois issu de forêts gérées durablement.',
                        'Livraison annoncée en 3 à 7 jours ouvrés à compter du paiement confirmé pour la France, la Belgique et l’Allemagne. Consultez les modalités et les informations propres au produit.',
                        'Garantie légale de conformité de 2 ans pour nos appareils neufs. Une éventuelle garantie commerciale du fabricant est distincte et s’applique uniquement selon les conditions indiquées pour le produit.',
                        'Un service client joignable aux coordonnées ci-dessous pour vos questions sur les produits, les commandes et le suivi.',
                    ],
                ],
                [
                    'title' => 'Pourquoi choisir HOMYA ?',
                    'paragraph' => 'Plus de dix ans d’histoire familiale, une sélection de produits pour la maison et le sens du service guident notre activité. Nous croyons qu’une maison agréable commence par de bons produits et un service en lequel on peut avoir confiance. Merci de faire partie de notre histoire.',
                ],
                [
                    'title' => 'L’équipe HOMYA et l’entreprise',
                    'paragraph' => 'HOMYA est dirigée par le gérant identifié ci-dessous. Notre entreprise intervient en tant que revendeur indépendant : les marques restent la propriété de leurs titulaires, sans affiliation officielle revendiquée avec les fabricants. Retrouvez notre identité légale, notre siège et nos coordonnées ci-dessous.',
                    'company_details' => true,
                ],
            ],
            'useful_information' => 'Informations utiles',
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Nos produits',
            'intro' => 'Les repères pour explorer HOMYA en toute clarté.',
            'paragraphs' => ['Consultez les photos, les caractéristiques et les options de chaque produit pour préparer votre achat.', 'HOMYA est un revendeur indépendant. Nos appareils sont neufs sous scellé, avec une garantie légale de conformité de 2 ans et une facture à votre nom.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Bien choisir',
            'intro' => 'Trouver un appareil adapté à votre projet.',
            'paragraphs' => ['Commencez par un univers, puis choisissez une catégorie et votre budget. Comparez les dimensions, capacités, puissances et équipements réellement indiqués sur les fiches.', 'Pour les appareils de chauffage, vérifiez également les contraintes d’installation avec un professionnel qualifié.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Contact',
            'intro' => 'Une question sur un produit, la livraison, votre commande ou un retour ? Retrouvez les coordonnées HOMYA.',
            'paragraphs' => ['Indiquez l’objet de votre demande et, si nécessaire, la référence du produit ou de la commande.', 'Contactez HOMYA par e-mail, téléphone, WhatsApp ou SMS aux coordonnées ci-dessous.', 'Le siège reçoit également les produits retournés, y compris les biens volumineux. Consultez Retours & rétractation pour les modalités.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Livraison',
            'intro' => 'Livraison gratuite en France, en Belgique et en Allemagne.',
            'paragraphs' => ['Vos appareils sont livrés par notre propre transporteur en 3 à 7 jours ouvrés à compter du paiement confirmé.', 'Les commandes sont expédiées depuis la France. Le délai global de livraison comprend 1 à 2 jours ouvrés de préparation après confirmation du paiement, puis 2 à 5 jours ouvrés de transport.', 'Les jours ouvrés sont du lundi au vendredi. L’heure limite de prise en compte est 14 h, heure de Paris (Europe/Paris).', 'La livraison est gratuite en France, en Belgique et en Allemagne.', 'Pour les meubles et appareils volumineux, indiquez les conditions d’accès afin d’organiser la livraison avec HOMYA.', 'Les retours sont gratuits pour les rétractations notifiées dans les 14 jours après réception. Consultez Retours & rétractation pour l’adresse et l’organisation du retour.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Paiement',
            'intro' => 'Retrouvez les informations utiles pour régler votre commande.',
            'paragraphs' => ['Validez votre panier et vos coordonnées pour enregistrer votre commande. Les instructions de règlement sont associées à votre référence de commande.', 'Le récapitulatif présente les articles, les quantités et le montant total en euros, toutes taxes comprises. La livraison est gratuite en France, en Belgique et en Allemagne.', 'Vos articles sont réservés pendant 72 heures après validation. Le traitement de la commande se poursuit après confirmation du règlement.', 'Pour toute question concernant votre règlement, contactez HOMYA en indiquant votre numéro de commande.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Suivi de commande',
            'intro' => 'Saisissez votre numéro de commande pour retrouver les étapes de son traitement.',
            'paragraphs' => ['Consultez l’enregistrement, le paiement, la préparation, l’expédition et la livraison de votre commande.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Panier',
            'intro' => 'Retrouvez vos articles et validez votre commande.',
            'paragraphs' => ['Vérifiez les produits, les options et les quantités de votre panier avant de compléter vos coordonnées.'],
            'pending' => false,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'Comment rechercher un produit ?',
        'answer' => 'Utilisez la recherche en haut de page ou les filtres du catalogue : catégorie, marque, état et intervalle de prix selon les données disponibles.',
    ], [
        'question' => 'Puis-je changer de langue ?',
        'answer' => 'Le sélecteur FR, EN et DE conserve la fiche ou le contexte du catalogue. Si une traduction indispensable manque, le site l’indique.',
    ], [
        'question' => 'Comment consulter les caractéristiques ?',
        'answer' => 'Ouvrez une fiche, puis utilisez le lien Caractéristiques. Les photos supplémentaires sont accessibles par les miniatures lorsqu’elles sont disponibles.',
    ], [
        'question' => 'Puis-je commander maintenant ?',
        'answer' => 'Oui. Ajoutez vos articles au panier, vérifiez les quantités et validez votre commande. Conservez votre numéro pour consulter les étapes de son traitement sur la page Suivi de commande.',
    ]],
];
