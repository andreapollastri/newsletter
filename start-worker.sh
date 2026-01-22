#!/bin/bash

echo "🚀 Avvio worker newsletter..."
echo "Premi Ctrl+C per fermare"

# Ciclo infinito per riavviare il worker se si blocca
while true; do
    echo "$(date): Avvio worker..."
    php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-jobs=1000
    
    echo "$(date): Worker terminato, riavvio tra 5 secondi..."
    sleep 5
done
