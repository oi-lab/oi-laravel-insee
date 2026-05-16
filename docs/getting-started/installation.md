---
title: Installation
description: Install and configure the OI Laravel INSEE package
order: 2
---

# Installation

## Install via Composer

Add the package to your Laravel project:

```bash
composer require oi-lab/oi-laravel-insee
```

## Publish Configuration

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=oi-laravel-insee-config
```

This creates a new configuration file at `config/insee.php`.

## Get INSEE API Credentials

Visit the [INSEE Portail API](https://portail-api.insee.fr) and register for API access. You'll receive:

- **CLIENT_SECRET** - Your OAuth client secret (required)
- **CLIENT_ID** - Your OAuth client ID (optional, but recommended for production)

## Set Environment Variables

Add your credentials to your `.env` file:

```env
INSEE_CLIENT_SECRET=your_client_secret_here
INSEE_CLIENT_ID=your_client_id_here
INSEE_BASE_URL=https://api.insee.fr/api-sirene/3.11
INSEE_CACHE_DURATION=82800
```

### Environment Variables Explained

| Variable | Required | Description | Default |
|----------|----------|-------------|---------|
| `INSEE_CLIENT_SECRET` | Yes | Your INSEE API client secret | - |
| `INSEE_CLIENT_ID` | No | Your INSEE API client ID for OAuth | - |
| `INSEE_BASE_URL` | No | The INSEE API base URL | `https://api.insee.fr/api-sirene/3.11` |
| `INSEE_CACHE_DURATION` | No | Token cache duration in seconds (23 hours) | `82800` |

## Verify Installation

Test that everything is set up correctly by checking the API status:

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$status = Insee::getApiStatus();

if ($status['header']['statut'] === 200) {
    echo 'INSEE API is operational!';
}
```

You can also test this in Tinker:

```bash
php artisan tinker
>>> \OiLab\OiLaravelInsee\Facades\Insee::getApiStatus()
```

## What's Next?

Now that you have the package installed and configured, explore the [Usage](/docs/usage) section to start looking up companies and establishments.
