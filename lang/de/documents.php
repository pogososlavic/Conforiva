<?php

return [
    'types' => ['quote' => 'Angebot', 'purchase_order' => 'Bestellung', 'invoice' => 'Rechnung'],
    'no_number' => 'Ohne Nummer', 'date' => 'Datum', 'draft' => 'Entwurf',
    'draft_notice' => 'ENTWURF — NICHT AUSGESTELLT — keine endgültige Rechnung',
    'issuer' => 'Verkäufer / Aussteller', 'capital' => 'Stammkapital', 'registered_office' => 'Sitz',
    'customer' => 'Kunde / Rechnungsstellung', 'email' => 'E-Mail', 'phone' => 'Telefon', 'siren' => 'SIREN', 'siret' => 'SIRET (Sitz)', 'vat_number' => 'Umsatzsteuer-Identifikationsnummer',
    'bank_details' => 'Bankverbindung von HOMYA', 'bank_beneficiary' => 'Kontoinhaber', 'bank_name' => 'Bank',
    'delivery_address' => 'Lieferadresse', 'customer_order_reference' => 'Bestellreferenz des Kunden', 'source_document' => 'Quelldokument', 'ecommerce_order' => 'E-Commerce-Bestellung',
    'valid_until' => 'Gültig bis', 'sale_delivery_date' => 'Verkaufs- / Lieferdatum', 'goods_sale' => 'Warenverkauf',
    'items_eur' => 'Artikel · Beträge in EUR', 'designation_reference' => 'Bezeichnung / Referenz', 'quantity' => 'Menge', 'unit_ex_tax' => 'Einzelpreis netto¹', 'vat' => 'MwSt.', 'total_ex_tax' => 'Gesamt netto', 'total_inc_tax' => 'Gesamt brutto',
    'unit_inc_tax' => 'Einzelpreis brutto', 'to_complete' => 'Auszufüllen', 'eco_contribution' => 'Enthaltener Umweltbeitrag', 'per_unit' => 'Einheit', 'vat_not_collected' => 'Nicht erhoben', 'not_collected' => 'nicht erhoben',
    'ex_tax_note' => '¹ Nettobetrag, je Position aus dem Bruttopreis berechnet und auf den nächsten Cent gerundet; Nettostückpreis mit vier Dezimalstellen.',
    'conditions' => 'Bedingungen', 'payment' => 'Zahlung', 'delivery' => 'Lieferung', 'due_date' => 'Fälligkeitsdatum', 'discount' => 'Skonto', 'late_penalties' => 'Verzugszinsen',
    'collection_indemnity' => 'Pauschale Entschädigung für Beitreibungskosten: 40 € (Geschäftskunde).', 'payment_not_confirmed' => 'Dieses Dokument bestätigt nicht den Eingang einer Zahlung.',
    'quote_acceptance' => 'Annahme durch den Kunden: Name, Datum und Unterschrift mit dem Vermerk „Einverstanden“.', 'purchase_order_validation' => 'Bestätigung durch den Kunden: Name, Datum und Unterschrift.',
    'registered_on_rcs' => 'Eintragung im RCS am', 'registered_on_rne' => 'Eintragung im RNE am', 'manager' => 'Geschäftsführer', 'country_professional' => 'Geschäftskunde',
    'destination_vat_note' => 'Die Mehrwertsteuer richtet sich nach dem Bestimmungsland der Lieferung.',
    'order_quote_payment_terms' => 'Zahlung per Banküberweisung. Die Bestellung und die Artikel werden ab Bestätigung 72 Stunden reserviert. Die Zahlung muss bis zum angegebenen Zeitpunkt eingegangen und zugeordnet sein. Ein Überweisungsbeleg bestätigt nicht den Zahlungseingang. Ohne Bestätigung bis zum Ablauf kann die Bestellung verfallen und die Reservierung aufgehoben werden.',
    'order_quote_delivery_terms' => 'Die Lieferung wird in Frankreich, Belgien und Deutschland als kostenlos angegeben; die Lieferzeit beträgt 3 bis 7 Tage ab bestätigtem Zahlungseingang. Die Annahme sperriger Waren ist mit HOMYA abzustimmen. Installation, Inbetriebnahme und Mitnahme eines Altgeräts sind nicht enthalten, sofern dies nicht ausdrücklich angegeben ist.',
    'order_quote_email_subject' => 'Ihr HOMYA-Angebot Nr. :number',
    'order_quote_email_body' => "Guten Tag :name,\n\nNach Ihrer Bestellbestätigung erhalten Sie anbei das offizielle Handelsangebot von HOMYA Nr. :number. Es enthält die Artikel, Mengen, Bruttopreise und die Mehrwertsteuer nach dem Lieferland.\n\nDas Angebot ist bis :deadline gültig. Es bestätigt keinen Zahlungseingang; die Bestellung setzt die Zuordnung der Zahlung und die im Dokument genannten Lieferbedingungen voraus.\n\nBei Fragen antworten Sie auf diese E-Mail oder schreiben Sie an support@homya.eu.\n\nHOMYA · Kundenservice",
];
