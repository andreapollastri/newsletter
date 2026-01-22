<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Disiscrizione</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-4 text-center">Conferma Disiscrizione</h1>
            <p class="text-gray-600 mb-6 text-center">
                Sei sicuro di volerti disiscrivere dalla nostra newsletter?
            </p>
            <p class="text-gray-500 text-sm mb-8 text-center">
                Non riceverai più i nostri aggiornamenti e le nostre comunicazioni.
            </p>
            
            <form action="{{ route('unsubscribe.confirm', $subscriber) }}" method="POST" class="space-y-4">
                @csrf
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                    Sì, disiscrivimi
                </button>
            </form>
        </div>
    </div>
</body>
</html>
