<?php

return [
    'types' => ['quote' => 'Devis', 'purchase_order' => 'Bon de commande', 'invoice' => 'Facture'],
    'no_number' => 'Sans numéro', 'date' => 'Date', 'draft' => 'Brouillon',
    'draft_notice' => 'BROUILLON — NON ÉMIS — ne constitue pas une facture définitive',
    'issuer' => 'Vendeur / émetteur', 'capital' => 'Capital social', 'registered_office' => 'Siège social',
    'customer' => 'Client / facturation', 'email' => 'Email', 'phone' => 'Téléphone', 'siren' => 'SIREN', 'siret' => 'SIRET (siège)', 'vat_number' => 'TVA intracommunautaire',
    'bank_details' => 'Coordonnées bancaires HOMYA', 'bank_beneficiary' => 'Bénéficiaire', 'bank_name' => 'Banque',
    'delivery_address' => 'Adresse de livraison', 'customer_order_reference' => 'Référence du bon de commande client', 'source_document' => 'Document d’origine', 'ecommerce_order' => 'Commande e-commerce',
    'valid_until' => 'Valable jusqu’au', 'sale_delivery_date' => 'Date de vente / livraison', 'goods_sale' => 'vente de biens',
    'items_eur' => 'Articles · montants en EUR', 'designation_reference' => 'Désignation / référence', 'quantity' => 'Qté', 'unit_ex_tax' => 'P.U. HT¹', 'vat' => 'TVA', 'total_ex_tax' => 'Total HT', 'total_inc_tax' => 'Total TTC',
    'unit_inc_tax' => 'P.U. TTC', 'to_complete' => 'À renseigner', 'eco_contribution' => 'Éco-participation incluse', 'per_unit' => 'unité', 'vat_not_collected' => 'Non collectée', 'not_collected' => 'non collectée',
    'ex_tax_note' => '¹ Montant HT calculé et arrondi au centime sur chaque ligne à partir du prix TTC ; prix unitaire HT affiché avec quatre décimales.',
    'conditions' => 'Conditions', 'payment' => 'Paiement', 'delivery' => 'Livraison', 'due_date' => 'Échéance', 'discount' => 'Escompte', 'late_penalties' => 'Pénalités de retard',
    'collection_indemnity' => 'Indemnité forfaitaire pour frais de recouvrement : 40 € (client professionnel).', 'payment_not_confirmed' => 'Ce document ne constitue pas une confirmation de réception d’un paiement.',
    'quote_acceptance' => 'Acceptation du client : nom, date et signature précédés de « Bon pour accord ».', 'purchase_order_validation' => 'Validation du client : nom, date et signature.',
    'registered_on_rcs' => 'Inscription au RCS', 'registered_on_rne' => 'Inscription au RNE', 'manager' => 'Gérant', 'country_professional' => 'client professionnel',
    'destination_vat_note' => 'TVA déterminée selon le pays de destination de la livraison.',
    'order_quote_payment_terms' => 'Paiement par virement bancaire. La commande et les articles sont réservés pendant 72 heures à compter de la validation. Le paiement doit être reçu et rapproché avant l’échéance indiquée. Une preuve de virement ne confirme pas la réception des fonds. Sans confirmation à l’échéance, la commande peut expirer et la réservation être libérée.',
    'order_quote_delivery_terms' => 'Livraison annoncée gratuite en France, Belgique et Allemagne, sous 3 à 7 jours à compter de la confirmation du paiement. La réception des articles volumineux doit être organisée avec HOMYA. Installation, mise en service et reprise d’un ancien appareil ne sont pas incluses sauf mention expresse.',
    'order_quote_email_subject' => 'Votre devis HOMYA n° :number',
    'order_quote_email_body' => "Bonjour :name,\n\nÀ la suite de la validation de votre commande, vous trouverez en pièce jointe le devis commercial HOMYA n° :number. Il reprend les articles, quantités, montants TTC et la TVA calculée selon le pays de livraison.\n\nLe devis est valable jusqu’au :deadline. Il ne confirme pas la réception du paiement ; la commande reste soumise au rapprochement effectif du règlement et aux conditions de livraison indiquées dans le document.\n\nPour toute question, répondez à ce message ou contactez support@homya.eu.\n\nHOMYA · Assistance",
];
