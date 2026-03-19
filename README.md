# MyAdmin Authorize.Net Payments Plugin

[![Tests](https://github.com/detain/myadmin-authorizenet-payments/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-authorizenet-payments/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-authorizenet-payments/version)](https://packagist.org/packages/detain/myadmin-authorizenet-payments)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-authorizenet-payments/downloads)](https://packagist.org/packages/detain/myadmin-authorizenet-payments)
[![License](https://poser.pugx.org/detain/myadmin-authorizenet-payments/license)](https://packagist.org/packages/detain/myadmin-authorizenet-payments)

An Authorize.Net payment gateway integration plugin for the [MyAdmin](https://github.com/detain/myadmin) hosting management platform. This package provides complete credit card processing capabilities including charging, authorization, refund, void, and card verification workflows through the Authorize.Net AIM (Advanced Integration Method) API.

## Features

- **Credit card processing** via Authorize.Net AIM gateway (AUTH_CAPTURE and AUTH_ONLY)
- **Refunds and voids** for completed transactions through the `AuthorizeNetCC` class
- **Card validation** supporting Visa, MasterCard, AMEX, Discover, JCB, Diners Club, China UnionPay, Maestro, Laser, and InstaPayment schemes
- **Two-charge card verification** for new credit cards added to customer accounts
- **Card management UI** for customers to add, remove, verify, and set primary billing cards
- **Admin tools** for enabling/disabling CC billing, whitelist management, transaction viewing, and refund processing
- **Authorize.Net response parsing** with full CSV field mapping and three-strategy parser (full regex, partial regex, CSV fallback)
- **Security features** including CSRF protection on all state-changing operations, ACL-based admin access control, and automatic credential stripping from log entries

## Requirements

- PHP >= 8.0
- ext-soap
- ext-curl
- ext-mbstring
- Symfony EventDispatcher ^5.0
- MyAdmin plugin infrastructure

## Installation

Install via Composer:

```sh
composer require detain/myadmin-authorizenet-payments
```

The plugin registers itself with the MyAdmin event dispatcher through `Plugin::getHooks()`, which binds:
- `system.settings` -- registers Authorize.Net configuration fields (login, password, API key, referrer)
- `function.requirements` -- registers all function and page requirements for lazy loading

## Configuration

The plugin uses the following settings (configurable through MyAdmin admin panel under Billing > Authorize.Net):

| Setting | Description |
|---------|-------------|
| `authorizenet_enable` | Enable or disable Authorize.Net processing |
| `authorizenet_login` | API login name |
| `authorizenet_password` | API password |
| `authorizenet_key` | API transaction key |
| `authorizenet_referrer` | Optional referrer URL |

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

To generate a coverage report:

```sh
vendor/bin/phpunit --coverage-html coverage/
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) license.
