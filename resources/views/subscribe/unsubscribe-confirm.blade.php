<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Confirm Unsubscribe') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-sm w-full">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            
            <h1 class="text-xl font-medium text-gray-900 mb-3">
                {{ __('We are sorry to see you go') }}
            </h1>
            
            <p class="text-gray-500 text-sm mb-8">
                {{ __('Do you confirm unsubscription from :app?', ['app' => config('app.name')]) }}
            </p>
            
            <form action="{{ route('unsubscribe.confirm', $subscriber) }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2.5 px-4 rounded-lg transition duration-150 text-sm">
                    {{ __('Confirm unsubscription') }}
                </button>
            </form>
        </div>
    </div>
</body>
</html>
