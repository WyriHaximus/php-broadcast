<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\DoNotHandle;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function array_map;
use function class_exists;
use function in_array;

final class Collector implements ItemCollector
{
    private const int THE_NUMBER_OF_PARAMETERS_REQUIRED_FOR_A_METHOD_TO_BE_AN_EVENT_HANDLER_IS_ONE = 1;

    /** @return iterable<ItemContract> */
    public function collect(ReflectionClass $class): iterable
    {
        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            if ($method->isConstructor()) {
                continue;
            }

            if ($method->isDestructor()) {
                continue;
            }

            if ($method->getNumberOfParameters() !== self::THE_NUMBER_OF_PARAMETERS_REQUIRED_FOR_A_METHOD_TO_BE_AN_EVENT_HANDLER_IS_ONE) {
                continue;
            }

            if (in_array(DoNotHandle::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $method->getAttributes()), true)) {
                continue;
            }

            $eventTypeHolder = $method->getParameters()[0]->getType();
            if ($eventTypeHolder instanceof ReflectionIntersectionType) {
                continue;
            }

            if ($eventTypeHolder instanceof ReflectionUnionType) {
                $eventTypes = $eventTypeHolder->getTypes();
            } else {
                $eventTypes = [$eventTypeHolder];
            }

            foreach ($eventTypes as $eventType) {
                if (! class_exists((string) $eventType)) {
                    continue;
                }

                yield new Item(
                    (string) $eventType,
                    $class->getName(),
                    $method->getName(),
                    $method->isStatic(),
                    $class->implementsInterface(AsyncListener::class),
                );
            }
        }
    }
}
