---
title: Introduction
description: Overview of the OI Laravel INSEE package
order: 1
---

# Introduction to OI Laravel INSEE

OI Laravel INSEE is a Laravel package that provides seamless integration with the French INSEE SIRENE API. It allows you to look up company and establishment information from France's official government business registry.

## What is INSEE SIRENE?

INSEE (Institut National de la Statistique et des Études Économiques) is France's National Institute of Statistics and Economic Studies. The SIRENE API provides access to a comprehensive database of French companies and establishments.

Each company is identified by a unique 9-digit **SIREN** (Système d'Identification du Répertoire des Entreprises), while each individual establishment is identified by a 14-digit **SIRET** (Système d'Identification du Répertoire des Établissements).

## What This Package Provides

The OI Laravel INSEE package simplifies working with the INSEE SIRENE API by offering:

- **5 core methods** for accessing company and establishment data
- **Automatic "dirigeant" injection** for natural persons (entrepreneurs and self-employed individuals)
- **Facade pattern** for simple, fluent access
- **Dependency Injection** support for testable, maintainable code
- **Built-in token caching** to optimize API requests and manage rate limits
- **Type-safe responses** structured for easy integration with your application

## Key Features

### Automatic Dirigeant Injection

When querying a natural person (entrepreneur, micro-entrepreneur, etc.), the package automatically extracts leadership information (name, surname, first name, gender) and injects it as a `dirigeant` key in the response. This saves you from manually parsing this data.

### Dual Access Patterns

Choose what works best for your code:
- **Facade**: `use OiLab\OiLaravelInsee\Facades\Insee;` for simple operations
- **Dependency Injection**: Inject the `Client` class for testable, type-hinted access

### Token Caching

The package automatically caches API tokens for 23 hours, reducing unnecessary authentication calls and helping you stay within rate limits.

## Requirements

- PHP 8.2 or higher
- Laravel 11 or higher
- INSEE API credentials from [portail-api.insee.fr](https://portail-api.insee.fr)

## Quick Start

Install the package and configure your API credentials:

```bash
composer require oi-lab/oi-laravel-insee
```

Then publish the configuration:

```bash
php artisan vendor:publish --tag=oi-laravel-insee-config
```

Set your environment variables:

```
INSEE_CLIENT_SECRET=your_client_secret_here
INSEE_CLIENT_ID=your_client_id_here
```

Verify everything is working:

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$status = Insee::getApiStatus();
```

Next, check out the [Installation guide](/docs/getting-started/installation) for detailed setup instructions.
