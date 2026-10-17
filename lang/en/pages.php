<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'about_navigation' => 'About us',
    'help' => 'Help & advice',
    'information' => 'Information',
    'pending' => 'Service in preparation',
    'pending_detail' => 'You can browse the catalogue. Sales are not open yet.',
    'questions' => 'Frequently asked questions',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'About HOMYA',
            'intro' => 'A family story since 2012, focused on warmth and comfort at home.',
            'paragraphs' => [],
            'sections' => [
                [
                    'title' => 'Our story',
                    'paragraph' => 'HOMYA is first and foremost a family story. Founded in 2012, our family business was built on simple values: trust, quality and service. Today, our online shop addresses two essential needs at home: warmth and comfort.',
                ],
                [
                    'title' => 'Wood fuels for your heating',
                    'paragraph' => 'Logs, pellets and briquettes: discover the fuels available in our catalogue. Check each product page for wood species, packaging and specifications to choose a fuel suited to your fireplace, stove, insert or boiler, following the manufacturer’s recommendations.',
                ],
                [
                    'title' => 'Appliances for everyday life',
                    'paragraph' => 'Refrigerators, washing machines, dishwashers, ovens, vacuum cleaners and small appliances: explore the appliances and brands available in our catalogue. Compare specifications to choose equipment that meets your needs. Our range also includes heating equipment, furniture and televisions.',
                ],
                [
                    'title' => 'Our commitments',
                    'paragraph' => 'Clear product information and terms of sale help you prepare your purchase.',
                    'items' => [
                        'Free delivery to France, Belgium and Germany, under the terms set out on our Delivery page.',
                        'For another country, contact HOMYA before ordering: feasibility, shipping charges and delivery time must be confirmed. These destinations are not available in the online checkout.',
                        'Wood sourced from sustainably managed forests.',
                        'Delivery is stated as 3 to 7 business days after confirmed payment for France, Belgium and Germany. Please check the delivery terms and product-specific information.',
                        'A 2-year legal guarantee of conformity for our new appliances. Any manufacturer’s commercial guarantee is separate and applies only under the conditions specified for the product.',
                        'Customer service is available through the contact details below for questions about products, orders and tracking.',
                    ],
                ],
                [
                    'title' => 'Why choose HOMYA?',
                    'paragraph' => 'More than ten years of family history, a selection of products for the home and a focus on service guide our business. We believe a welcoming home starts with good products and service you can trust. Thank you for being part of our story.',
                ],
                [
                    'title' => 'The HOMYA team and company',
                    'paragraph' => 'HOMYA is managed by the person identified below. We operate as an independent retailer: trademarks remain the property of their respective owners, with no official manufacturer affiliation claimed. Find our legal identity, registered office and contact details below.',
                    'company_details' => true,
                ],
            ],
            'useful_information' => 'Useful information',
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Our products',
            'intro' => 'Clear guidance for exploring HOMYA.',
            'paragraphs' => ['Browse the photos, specifications and options for each product to prepare your purchase.', 'HOMYA is an independent retailer. Our appliances are new and sealed, with a 2-year statutory conformity guarantee and an invoice in your name.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Choosing your appliance',
            'intro' => 'Find a model for your project.',
            'paragraphs' => ['Start with a department, then select a category and your budget. Compare the dimensions, capacities, power and features actually listed on the product pages.', 'For heating appliances, also check installation requirements with a qualified professional.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Contact',
            'intro' => 'A question about a product, delivery, your order or a return? Find HOMYA contact details here.',
            'paragraphs' => ['State your enquiry and, where relevant, the product or order reference.', 'Contact HOMYA by email, telephone, WhatsApp or SMS using the details below.', 'The registered office also receives returned goods, including bulky items. See Returns & withdrawal for the arrangements.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Delivery',
            'intro' => 'Free delivery in France, Belgium and Germany.',
            'paragraphs' => ['Your appliances are delivered by our own carrier in 3 to 7 business days after payment is confirmed.', 'Orders are dispatched from France. The overall delivery period includes 1 to 2 business days for preparation after payment confirmation, followed by 2 to 5 business days for transport.', 'Business days are Monday to Friday. The cut-off time is 2 p.m., Paris time (Europe/Paris).', 'Delivery is free in France, Belgium and Germany.', 'For furniture and bulky appliances, share access details to arrange delivery with HOMYA.', 'Returns are free for withdrawal notified within 14 days of receipt. See Returns & withdrawal for the address and return arrangements.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Payment',
            'intro' => 'Find the information you need to pay for your order.',
            'paragraphs' => ['Confirm your basket and contact details to register your order. Payment instructions are associated with your order reference.', 'The summary lists your items, quantities and total in euros including taxes. Delivery is free in France, Belgium and Germany.', 'Your items are reserved for 72 hours after confirmation. Order processing continues once payment is confirmed.', 'For payment enquiries, contact HOMYA with your order number.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Order tracking',
            'intro' => 'Enter your order number to see its processing progress.',
            'paragraphs' => ['Check order registration, payment, preparation, dispatch and delivery.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Basket',
            'intro' => 'Review your items and confirm your order.',
            'paragraphs' => ['Check your products, options and quantities before entering your contact details.'],
            'pending' => false,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'How can I find a product?',
        'answer' => 'Use the search at the top of the page or catalogue filters: category, brand, condition and price range, according to the available data.',
    ], [
        'question' => 'Can I change the language?',
        'answer' => 'The FR, EN and DE selector preserves the product page or catalogue context. The site indicates when an essential translation is missing.',
    ], [
        'question' => 'Where can I find specifications?',
        'answer' => 'Open a product page, then use the Specifications link. Additional photos are accessible through thumbnails when available.',
    ], [
        'question' => 'Can I order now?',
        'answer' => 'Yes. Add your items to the basket, check quantities and confirm your order. Keep your order number to see its processing progress on the Order tracking page.',
    ]],
];
