<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; border-radius: 8px; padding: 40px; text-align: center;">
        <h1 style="color: #1a1a1a; margin-bottom: 20px;">Conferma la tua iscrizione</h1>
        
        @if($name)
            <p style="font-size: 16px; margin-bottom: 20px;">Ciao {{ $name }},</p>
        @endif
        
        <p style="font-size: 16px; margin-bottom: 30px;">
            Grazie per esserti iscritto alla nostra newsletter. Per completare l'iscrizione, clicca sul pulsante qui sotto.
        </p>
        
        <a href="{{ $confirmUrl }}" 
           style="display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: 600; font-size: 16px;">
            Conferma iscrizione
        </a>
        
        <p style="font-size: 14px; color: #666; margin-top: 30px;">
            Se non hai richiesto questa iscrizione, puoi ignorare questa email.
        </p>
    </div>
</body>
</html>
