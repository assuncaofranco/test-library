Curl Library
===============



## Introduction

This library eases the API calls between V2 micro-services.

## Use

The CurlClient implements multiple traits providing multiple setters :

### LoggerTrait

* **setLogger** (optional) : Allows to specify a PSR-3 compliant Logger to log information during API calls.

### RequestStackTrait

* **setRequestStack** (optional) : Allows to specify a Symfony RequestStack object to be used. This allows the CurlClient to retrieve (and create if not present) a V2 Request Identifier which will be the same across all calls from the same Request and which will be communicated to the called micro-service.

### Guard Trait

* **setGuardHost** (optional) : Allows to specify the host of the Guard micro-service.

* **setGuardPublicKey** (optional) : Allows to specify the guard public key of the sender micro-service.

* **setGuardPrivateKey** (optional) : Allows to specify the guard private key of the sender micro-service.


### Example to call a micro-service that does NOT implement Guard protection

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new Client(['base_uri' => 'http://host/']);

$client->setLogger(new Logger());
$client->setRequestStack(new RequestStack());

$response = $client->get('/test');
```

### Example to call a micro-service that does implement Guard protection

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new Client(['base_uri' => 'http://host/']);

$client->setLogger(new Logger());
$client->setRequestStack(new RequestStack());

$client->setGuardHost('http://guard.local');
$client->setGuardPublicKey('public');
$client->setGuardPrivateKey('private');

$response = $client->get('/test', ['guard_protected' => true]);
```
