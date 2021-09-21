<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Helpers;

class InterfaceHelper
{
    /**
     * Get every class that implements $interfaceName
     */
    public static function getImplementations(string $interfaceName): array
    {
        return \array_filter(\get_declared_classes(), function($className) use ($interfaceName) {
            return self::implementsInterface($className, $interfaceName);
        });
    }

    /**
     * Returns true|false if the $implementerClassName implements interface $interfaceName
     */
    public static function implementsInterface(string $implementerClassName, string $interfaceName): bool
    {
        return \in_array($interfaceName, \class_implements($implementerClassName), true);
    }
}
