<?php

declare(strict_types=1);

use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\ContainerListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;

use function PHPStan\Testing\assertType;

assertType('stdClass', Dispatcher::createFromListenerProvider(new ArrayListenerProvider([]))->dispatch(new stdClass()));
assertType('stdClass', Dispatcher::createFromListenerProvider(new ContainerListenerProvider(new PsrContainer(new Container())))->dispatch(new stdClass()));
