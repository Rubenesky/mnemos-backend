<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent request — {{ $orgName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">

                {{-- Header bar --}}
                <tr>
                    <td align="center" height="48"
                        style="background-color:#0f172a;height:48px;padding:0 24px;">
                        <span style="color:#f59e0b;font-size:20px;font-weight:bold;letter-spacing:1px;">
                            Mnemos
                        </span>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px 40px 24px 40px;color:#0f172a;font-size:15px;line-height:1.6;">

                        <p style="margin:0 0 16px 0;">
                            Hello, {{ $consent->person_name }},
                        </p>

                        <p style="margin:0 0 8px 0;">
                            <strong>{{ $orgName }}</strong> has sent you a consent request regarding the following asset:
                        </p>

                        <p style="margin:0 0 24px 0;font-size:16px;font-weight:bold;color:#0f172a;">
                            {{ $consent->asset?->metadata?->title ?? $consent->asset?->original_name ?? 'an asset' }}
                        </p>

                        <p style="margin:0 0 24px 0;">
                            Please review the request and let us know whether you grant or deny consent by clicking the button below.
                        </p>

                        {{-- CTA button --}}
                        <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                            <tr>
                                <td align="center"
                                    style="background-color:#f59e0b;border-radius:6px;">
                                    <a href="{{ $consentUrl }}"
                                       target="_blank"
                                       style="display:inline-block;padding:12px 28px;color:#0f172a;font-weight:bold;font-size:15px;text-decoration:none;border-radius:6px;">
                                        Review and respond &rarr;
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px 0;font-size:13px;color:#64748b;">
                            This link expires on <strong>{{ $expiresAt }}</strong>.
                        </p>

                        <p style="margin:0;font-size:13px;color:#64748b;word-break:break-all;">
                            If the button doesn't work, copy this link:<br>
                            <a href="{{ $consentUrl }}" style="color:#0f172a;">{{ $consentUrl }}</a>
                        </p>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center"
                        style="padding:16px 40px 24px 40px;border-top:1px solid #e2e8f0;">
                        <p style="margin:0;font-size:12px;color:#94a3b8;">
                            Powered by Mnemos
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
