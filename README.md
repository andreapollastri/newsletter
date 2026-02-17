# 📰 Newsletter System

Sistema completo di gestione newsletter per Laravel con Filament.

## ✨ Funzionalità

- 👥 **Gestione Subscriber**: Import/Export CSV, tagging, status
- 📧 **Campagne e Messaggi**: Organizzazione gerarchica
- 🎨 **Template HTML**: Personalizzabili con placeholder
- ⏰ **Invio Schedulato**: Automatico con cron
- 📊 **Tracking Completo**: Aperture, click, unsubscribe
- 🎯 **Targeting**: Filtri per tag e status
- 📈 **Dashboard**: Statistiche e monitoraggio
- 🚦 **Rate Limiting**: Limiti configurabili per minuto/ora/giorno

## 🚀 Quick Start

```bash
# 1. Popola con dati di esempio (opzionale ma consigliato)
php artisan newsletter:seed-data

# 2. Avvia il worker della coda
./start-worker.sh

# 3. Accedi al sistema
# URL: https://newsletter.test
# Email: admin@newsletter.test
# Password: password
```

## 📧 Invio Newsletter

### Immediato

1. **Newsletter > Messaggi** → Nuovo messaggio
2. **Status = Pronto** → **"Invia Ora"**
3. Email inviate automaticamente via coda

### Schedulato

1. Imposta **"Data Programmata"**
2. Il sistema invia automaticamente

## 📊 Monitoraggio

- **Dashboard**: KPI principali
- **Messaggi**: Status e conteggi invii
- **Dettagli**: Tracking individuale

## 🚦 Rate Limiting

Configura limiti di invio per rispettare i vincoli del provider SMTP:

```env
NEWSLETTER_RATE_LIMIT_PER_MINUTE=60    # Max email/minuto
NEWSLETTER_RATE_LIMIT_PER_HOUR=1000    # Max email/ora
NEWSLETTER_RATE_LIMIT_PER_DAY=10000    # Max email/giorno
```

Monitora i limiti correnti:

```bash
php artisan newsletter:rate-limits
```

## 🔧 Troubleshooting

```bash
# Se email non partono
php artisan newsletter:process-pending

# Controlla coda
php artisan tinker --execute="DB::table('jobs')->count()"

# Test email
php artisan tinker --execute="Mail::raw('Test',fn(\$m)=>\$m->to('test@example.com'))"

# Verifica rate limits
php artisan newsletter:rate-limits
```

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
