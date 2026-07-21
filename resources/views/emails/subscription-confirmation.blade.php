<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; border-radius: 8px; padding: 40px; text-align: center;">
        <h1 style="color: #1a1a1a; margin-bottom: 20px;">{{ __('Confirm your subscription') }}</h1>

        @if($name)
            <p style="font-size: 16px; margin-bottom: 20px;">{{ __('Hello :name,', ['name' => $name]) }}</p>
        @endif

        <p style="font-size: 16px; margin-bottom: 30px;">
            {{ __('Thank you for subscribing to our newsletter. Click the button below to complete your subscription.') }}
        </p>

        <a href="{{ $confirmUrl }}"
           style="display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: 600; font-size: 16px;">
            {{ __('Confirm subscription') }}
        </a>

        <p style="font-size: 14px; color: #666; margin-top: 30px;">
            {{ __('If you did not request this subscription, you can ignore this email.') }}
        </p>
    </div>
</body>
</html>
