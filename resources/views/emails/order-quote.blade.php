<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('documents.order_quote_email_title', [], $language) }}</title>
    <style>
        @media(max-width:480px) {
            .quote-email-header, .quote-email-body, .quote-email-footer { padding: 20px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f5f3ed;color:#252525;font-family:Arial,Helvetica,sans-serif;overflow-wrap:break-word;word-break:break-word;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ __('documents.order_quote_email_preheader', ['number' => $number], $language) }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f3ed;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;table-layout:fixed;background:#ffffff;border:1px solid #e5e1d7;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="height:6px;background:#f5c842;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="quote-email-header" style="padding:26px 30px 20px;border-bottom:1px solid #eeeae1;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="font-size:26px;line-height:1;font-weight:800;letter-spacing:-1px;color:#222222;">HOMYA<span style="color:#d5a900;">.</span></td>
                                    <td align="right" style="font-size:12px;line-height:1.4;color:#68665f;">{{ __('documents.order_quote_email_title', [], $language) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="quote-email-body" style="padding:30px;">
                            <h1 style="margin:0 0 18px;font-size:24px;line-height:1.25;letter-spacing:-.4px;color:#222222;">{{ __('documents.order_quote_email_title', [], $language) }}</h1>
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.65;color:#333333;">{{ __('documents.order_quote_email_greeting', ['name' => $name], $language) }}</p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.65;color:#55534d;">{{ __('documents.order_quote_email_intro', [], $language) }}</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;background:#fbf8ee;border:1px solid #eee5c8;border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 5px;font-size:11px;line-height:1.4;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#777267;">{{ __('documents.order_quote_email_reference', [], $language) }}</p>
                                        <p style="margin:0 0 8px;font-size:16px;line-height:1.4;font-weight:700;color:#252525;overflow-wrap:anywhere;">{{ $number }}</p>
                                        <p style="margin:0;font-size:13px;line-height:1.5;color:#615d51;">{{ __('documents.order_quote_email_validity', ['deadline' => $deadline], $language) }}</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;border:1px solid #e8e7e2;border-radius:8px;">
                                <tr>
                                    <td width="54" valign="middle" align="center" style="padding:15px 0 15px 12px;">
                                        <span style="display:inline-block;padding:9px 7px;border-radius:6px;background:#fff1bd;color:#6c5310;font-size:11px;line-height:1;font-weight:800;">PDF</span>
                                    </td>
                                    <td valign="middle" style="padding:15px 16px;">
                                        <p style="margin:0 0 4px;font-size:14px;line-height:1.4;font-weight:700;color:#282722;">{{ __('documents.order_quote_email_attachment_title', [], $language) }}</p>
                                        <p style="margin:0;font-size:12px;line-height:1.5;color:#6b6962;">{{ __('documents.order_quote_email_attachment_note', [], $language) }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px;padding:14px 16px;border-left:3px solid #e4bd3a;background:#faf9f5;font-size:12px;line-height:1.6;color:#5d5a52;">{{ __('documents.order_quote_email_payment_note', [], $language) }}</p>
                            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#55534d;">{{ __('documents.order_quote_email_help', [], $language) }} <a href="mailto:{{ config('contact.email') }}" style="color:#5b4a12;text-decoration:underline;">{{ config('contact.email') }}</a>.</p>
                            <p style="margin:18px 0 0;font-size:14px;line-height:1.5;font-weight:700;color:#292821;">{{ __('documents.order_quote_email_signature', [], $language) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="quote-email-footer" style="padding:16px 30px;background:#faf9f6;border-top:1px solid #eeeae1;font-size:11px;line-height:1.55;color:#77746c;">
                            HOMYA · <a href="https://homya.eu" style="color:#62531e;text-decoration:underline;">homya.eu</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
