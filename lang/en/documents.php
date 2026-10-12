<?php

return [
    'types' => ['quote' => 'Quote', 'purchase_order' => 'Purchase order', 'invoice' => 'Invoice'],
    'no_number' => 'Unnumbered', 'date' => 'Date', 'draft' => 'Draft',
    'draft_notice' => 'DRAFT — NOT ISSUED — not a final invoice',
    'issuer' => 'Seller / issuer', 'capital' => 'Share capital', 'registered_office' => 'Registered office',
    'customer' => 'Customer / billing', 'email' => 'Email', 'phone' => 'Phone', 'siren' => 'SIREN', 'siret' => 'SIRET (registered office)', 'vat_number' => 'EU VAT number',
    'bank_details' => 'HOMYA bank details', 'bank_beneficiary' => 'Beneficiary', 'bank_name' => 'Bank',
    'delivery_address' => 'Delivery address', 'customer_order_reference' => 'Customer purchase order reference', 'source_document' => 'Source document', 'ecommerce_order' => 'E-commerce order',
    'valid_until' => 'Valid until', 'sale_delivery_date' => 'Sale / delivery date', 'goods_sale' => 'sale of goods',
    'items_eur' => 'Items · amounts in EUR', 'designation_reference' => 'Description / reference', 'quantity' => 'Qty', 'unit_ex_tax' => 'Unit price excl. tax¹', 'vat' => 'VAT', 'total_ex_tax' => 'Total excl. tax', 'total_inc_tax' => 'Total incl. tax',
    'unit_inc_tax' => 'Unit price incl. tax', 'to_complete' => 'To be completed', 'eco_contribution' => 'Included eco-contribution', 'per_unit' => 'unit', 'vat_not_collected' => 'Not collected', 'not_collected' => 'not collected',
    'ex_tax_note' => '¹ Amount excluding tax calculated and rounded to the nearest cent per line from the tax-inclusive price; unit price excluding tax is shown to four decimal places.',
    'conditions' => 'Terms and conditions', 'payment' => 'Payment', 'delivery' => 'Delivery', 'due_date' => 'Due date', 'discount' => 'Early-payment discount', 'late_penalties' => 'Late-payment penalties',
    'collection_indemnity' => 'Fixed €40 recovery-cost indemnity (business customer).', 'payment_not_confirmed' => 'This document does not confirm receipt of payment.',
    'quote_acceptance' => 'Customer acceptance: name, date and signature preceded by “Approved”.', 'purchase_order_validation' => 'Customer approval: name, date and signature.',
    'registered_on_rcs' => 'Registered with RCS on', 'registered_on_rne' => 'Registered with RNE on', 'manager' => 'Manager', 'country_professional' => 'business customer',
    'destination_vat_note' => 'VAT determined according to the destination country of delivery.',
    'order_quote_payment_terms' => 'Payment by bank transfer. The order and items are reserved for 72 hours from confirmation. Payment must be received and reconciled by the stated deadline. A transfer receipt does not confirm receipt of funds. Without confirmation by the deadline, the order may expire and the reservation may be released.',
    'order_quote_delivery_terms' => 'Delivery is stated as free in France, Belgium and Germany, within 3 to 7 days from confirmed payment. Receipt of bulky items must be arranged with HOMYA. Installation, commissioning and collection of an old appliance are not included unless expressly stated.',
    'order_quote_email_subject' => 'Your HOMYA quote no. :number',
    'order_quote_email_body' => "Hello :name,\n\nFollowing confirmation of your order, please find attached official HOMYA commercial quote no. :number. It reflects the items, quantities, tax-inclusive amounts and VAT determined according to the delivery destination.\n\nThe quote is valid until :deadline. It does not confirm receipt of payment; the order remains subject to payment reconciliation and the delivery terms stated in the document.\n\nIf you have any questions, reply to this email or contact support@homya.eu.\n\nHOMYA · Support",
];
