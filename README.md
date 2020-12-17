# Broadcast

✨ Statically on composer install/update compiled event dispatcher

![Continuous Integration](https://github.com/wyrihaximus/php-broadcast/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/wyrihaximus/broadcast/v/stable.png)](https://packagist.org/packages/wyrihaximus/broadcast)
[![Total Downloads](https://poser.pugx.org/wyrihaximus/broadcast/downloads.png)](https://packagist.org/packages/wyrihaximus/broadcast/stats)
[![Code Coverage](https://coveralls.io/repos/github/WyriHaximus/php-broadcast/badge.svg?branchmaster)](https://coveralls.io/github/WyriHaximus/php-broadcast?branch=master)
[![Type Coverage](https://shepherd.dev/github/WyriHaximus/php-broadcast/coverage.svg)](https://shepherd.dev/github/WyriHaximus/php-broadcast)
[![License](https://poser.pugx.org/wyrihaximus/broadcast/license.png)](https://packagist.org/packages/wyrihaximus/broadcast)

# Installation

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/broadcast
```

# Usage

Any package setting the following in their `composer.json` will have its autoloading directories and files scanned
for any classes that implements `WyriHaximus\Broadcast\Contracts\Listener`. Each public method with a concrete object
type hint on classes implementing `WyriHaximus\Broadcast\Contracts\Listener` will be registered as an event listener
for that object in the type hint.

```json
{
  "extra": {
    "wyrihaximus": {
      "broadcast": {
        "has-listeners": true
      }
    }
  }
}
```

To use those automatically picked up event listeners, use the `WyriHaximus\Broadcast\ContainerListenerProvider`, which
needs a `PSR-11` container to work.

The following example uses the dummy event and listener coming with this package:

```php
use WyriHaximus\Broadcast\ContainerListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;
use WyriHaximus\Broadcast\Dummy\Event;

$event = new Event();

(new Dispatcher(new ContainerListenerProvider($container), $logger))->dispatch($event)
```

## Listener

The following listener is from one of my apps and listeners both on intialize and shutdown events. The logic has been 
taken out, but logging is left intact to demonstracte a simple listener example.

```php
<?php

declare(strict_types=1);

namespace WyriHaximus\Apps\WyriHaximusNet\GitHub\Ingest;

use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Log\LoggerInterface;
use WyriHaximus\Broadcast\Contracts\Listener;

final class Consumer implements Listener
{
    private LoggerInterface $logger;

    public function __construct(ConsumerContract $consumer, Producer $producer, LoggerInterface $logger)
    {
        $this->logger   = $logger;
    }

    public function start(Initialize $event): void
    {
        $this->logger->debug('Starting to consume ingested GitHub WebHook events');
    }

    public function stop(Shutdown $event): void
    {
        $this->logger->debug('Stopping to consume ingested GitHub WebHook events');
    }
}
```

# License

The MIT License (MIT)

Copyright (c) 2020 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
