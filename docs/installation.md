# Installation & Quickstart

`nqphp` is a modern, lightweight PHP 8.4+ micro-framework designed for developers who want complete architectural clarity, native PHP speed, and zero configuration boilerplate.

## Requirements

Before installing `nqphp`, ensure your development environment satisfies the following:

- **PHP 8.4** or higher.
- Required PHP extensions:
  - `ext-json`
  - `ext-ctype`
  - `ext-pdo` (and `pdo_sqlite` or database driver if using Entities)
  - `ext-session`
- **Composer** 2.2+.

## Project Initialization

### 1. Create a New Project

Install via Composer or create a standard application layout:

```bash
composer create-project nqai/nqphp-skeleton my-app
cd my-app
```

### 2. Standard Directory Layout

`nqphp` adopts a feature-sliced architecture by default:

```text
my-app/
├── public/
│   └── index.php          # Single entry point
├── src/
│   ├── Core/              # Framework core (or vendor)
│   └── Feature/           # Feature slices (domain modules)
│       ├── Auth/
│       │   ├── Controller/
│       │   ├── Dto/
│       │   └── Service/
│       └── Billing/
│           ├── Controller/
│           └── Entity/
├── config/                # Environment and feature configs
├── tests/                 # PHPUnit test suites
└── composer.json
```

### 3. Running Development Server

Start PHP's built-in web server pointing to the `public/` directory:

```bash
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000` in your browser.

## The Entry Point (`public/index.php`)

The front controller initializes the `Kernel` with the project root directory and dispatches the incoming HTTP request:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nqphp\Core\Kernel\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel(dirname(__DIR__));
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```
