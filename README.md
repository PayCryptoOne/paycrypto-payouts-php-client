# PayCrypto payouts PHP client

Клиент Guzzle для API выплат PayCrypto: базовый URL по умолчанию `https://api.paycrypto.one/api/v1/` (в нём уже есть `/api/v1`), дальше относительные пути `payout/...`. Авторизация: `Authorization: Bearer sk_payout_*`; мастер-пароль при создании выплаты — заголовок `X-Payout-Master-Password` или поле `masterPassword` в теле.

## Установка

С **Packagist** (предпочтительно), пакет **`paycryptoone/paycrypto-payouts-php-client`**:

```bash
composer require paycryptoone/paycrypto-payouts-php-client
```

Без Packagist — только **GitHub** как VCS. В репозитории есть **git-тег `v1.0.0`**, поэтому в корневом проекте достаточно стабильного режима по умолчанию (без `minimum-stability: dev`):

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/PayCryptoOne/paycrypto-payouts-php-client.git"
    }
  ],
  "require": {
    "paycryptoone/paycrypto-payouts-php-client": "^1.0"
  }
}
```

Дальше `composer update paycryptoone/paycrypto-payouts-php-client`. Composer возьмёт стабильную **`1.0.0`** с тега.

Нужна именно последняя **`main`** без ожидания нового тега — добавь в корень проекта **`"minimum-stability": "dev"`** и **`"prefer-stable": true`**, либо укажи в `require` версию **`dev-main`**. В манифесте пакета задан **`branch-alias`** (`dev-main` → `1.0.x-dev`), чтобы ограничение **`^1.0`** согласовывалось с dev-веткой.

Локально из клона:

```bash
git clone https://github.com/PayCryptoOne/paycrypto-payouts-php-client.git
cd paycrypto-payouts-php-client
composer install
```

## Инициализация

```php
<?php

use PayCrypto\Payouts\Client\PayoutClient;

$client = new PayoutClient(
    getenv('PAYCRYPTO_PAYOUT_API_KEY'),
    getenv('PAYCRYPTO_PAYOUT_BASE_URL') ?: 'https://api.paycrypto.one/api/v1/'
);
```

## Примеры по endpoint-ам

### Семантика `currency` и `to_currency`

**`amount`** всегда в валюте **`currency`**. Если **`to_currency`** не передан или совпадает с **`currency`**, конвертации нет. Если **`to_currency`** другой — сумма пересчитывается в **`to_currency`** по курсам из базы (через USD). Пример: `amount` = `0.01`, `currency` = `USD`, `to_currency` = `LTC` — к получателю уйдёт эквивалент 0,01 USD в LTC.

**`network`** и **`address`** должны соответствовать **итоговой** монете (`to_currency` при конвертации, иначе `currency`). С баланса кошелька списывается монета фактической выплаты.

### `POST /payout/services` — список сервисов выплат

```php
$services = $client->services();
var_dump($services['data']);
```

### `POST /payout/calc` — расчет комиссии и суммы

#### Вариант 1: без конвертации

```php
$calcDirect = $client->calc([
    'amount' => '10',
    'address' => 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir',
    'currency' => 'USDT',
    'network' => 'TRON',
]);
var_dump($calcDirect['data']);
```

#### Вариант 2: с приоритетом сети

```php
$calcWithPriority = $client->calc([
    'amount' => '25',
    'address' => '0x1111111111111111111111111111111111111111',
    'currency' => 'USDT',
    'network' => 'ETH',
    'priority' => 'high',
]);
var_dump($calcWithPriority['data']);
```

#### Вариант 3: сумма в USD, выплата в USDT

```php
$calcConvert = $client->calc([
    'amount' => '100',
    'address' => 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir',
    'currency' => 'USD',
    'to_currency' => 'USDT',
    'network' => 'TRON',
]);
var_dump($calcConvert['data']);
```

### `POST /payout` — создание выплаты

#### Вариант 1: master password вторым аргументом

```php
$created = $client->createPayout([
    'amount' => '0.01',
    'address' => 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir',
    'currency' => 'USDT',
    'network' => 'TRON',
    'order_id' => 'order_'.time(),
],
getenv('PAYCRYPTO_PAYOUT_MASTER_PASSWORD')
);
var_dump($created['data']);
```

#### Вариант 2: сумма в USD, выплата в USDT

```php
$createdConvert = $client->createPayout([
    'amount' => '150',
    'address' => '0x1111111111111111111111111111111111111111',
    'currency' => 'USD',
    'to_currency' => 'USDT',
    'network' => 'ETH',
    'order_id' => 'order_convert_'.time(),
],
getenv('PAYCRYPTO_PAYOUT_MASTER_PASSWORD')
);
var_dump($createdConvert['data']);
```

#### Вариант 3: `masterPassword` в теле

```php
$createdLegacy = $client->createPayout([
    'amount' => '5',
    'address' => 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir',
    'currency' => 'USDT',
    'network' => 'TRON',
    'order_id' => 'order_body_mp_'.time(),
    'masterPassword' => getenv('PAYCRYPTO_PAYOUT_MASTER_PASSWORD'),
]);
var_dump($createdLegacy['data']);
```

### `POST /payout/info` — информация по выплате

```php
$info = $client->info('00000000-0000-4000-8000-000000000001');
var_dump($info['data']);
```

### `POST /payout/list` — история выплат

#### Вариант 1: без фильтров

```php
$historyAll = $client->list();
var_dump($historyAll['data']);
```

#### Вариант 2: фильтр по датам

```php
$historyWithDates = $client->list([
    'date_from' => '2026-05-01 00:00:00',
    'date_to' => '2026-05-08 23:59:59',
]);
var_dump($historyWithDates['data']);
```

#### Вариант 3: пагинация по cursor

```php
$page1 = $client->list();
$nextCursor = $page1['data']['paginate']['nextCursor'] ?? null;
if ($nextCursor) {
    $page2 = $client->list(['cursor' => $nextCursor]);
    var_dump($page2['data']);
}
```

### `POST /payout/resend` — повтор webhook

```php
$resent = $client->resend('00000000-0000-4000-8000-000000000001');
var_dump($resent['data']);
```

## Обработка ошибок

```php
use PayCrypto\Payouts\Client\PayoutApiException;

try {
    $client->services();
} catch (PayoutApiException $e) {
    var_dump($e->statusCode, $e->getMessage(), $e->responseBody);
}
```

## Webhook

```php
use PayCrypto\Payouts\Client\Webhook;

$ok = Webhook::verify($decodedJsonArray, $webhookSecretFromSettings);
if (!$ok) {
    throw new RuntimeException('Invalid payout webhook sign');
}
```

## Тесты

```bash
composer test
```

## Живой API (расширенные сценарии)

`composer test:live` выполняет `scripts/live-api.php`: реальные HTTP-запросы при заданном `PAYCRYPTO_PAYOUT_API_KEY` (см. `.env.example`).

Поведение такое же, как у Node-клиента: безопасные шаги всегда; выплата и повторный `create` с тем же `order_id` только при `PAYCRYPTO_PAYOUT_LIVE_WITHDRAW=1` и `PAYCRYPTO_PAYOUT_MASTER_PASSWORD`. Скрипт `composer e2e` при заданном мастер-пароле всегда создаёт одну реальную выплату (короткий сценарий).

```bash
composer test:live
```

## Скрипты

```bash
composer smoke
composer e2e
```

Переменные окружения см. `.env.example`.

Если `composer` и `php` не установлены локально, можно из каталога пакета:

```bash
docker run --rm --add-host=host.docker.internal:host-gateway -v "$PWD":/app -w /app \
  -e PAYCRYPTO_PAYOUT_BASE_URL=http://host.docker.internal:3002/api/v1/ \
  -e PAYCRYPTO_PAYOUT_API_KEY=... \
  composer:2 run test:live
```

Для полного прогона с выплатой добавь в `docker run` переменные `-e PAYCRYPTO_PAYOUT_LIVE_WITHDRAW=1 -e PAYCRYPTO_PAYOUT_MASTER_PASSWORD=...`.
