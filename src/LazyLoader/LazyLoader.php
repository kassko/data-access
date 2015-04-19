<?php

namespace Kassko\DataMapper\LazyLoader;

use Kassko\DataMapper\ObjectManager;

/**
 * Lazy load object properties.
 *
 * @author kko
 */
class LazyLoader
{
    private $objectManager;
    private $objectClass;

    public function __construct(ObjectManager $objectManager, $objectClass)
    {
        $this->objectManager = $objectManager;
        $this->objectClass = $objectClass;
    }

    /**
     * Load an object.
     * Some properties can be loaded only when needed for performance reason.
     *
     * @param array $object The object for wich we have to load property
     * @param array $propertyName The property to load
     */
    public function load($object)
    {
        if (get_class($object) !== $this->objectClass) {
            throw new \LogicException(sprintf('Invalid object type. Expected "%s" but got "%s".', $this->objectClass, get_class($object)));
        }

        $hydrator = $this->objectManager->getHydratorFor($this->objectClass);
        $hydrator->load($object);
    }

    /**
     * Load an object property.
     * This property can be loaded only if needed for performance reason.
     *
     * @param array $object The object for wich we have to load property
     * @param array $propertyName The property to load
     */
    public function loadProperty($object, $propertyName)
    {
        if (get_class($object) !== $this->objectClass) {
            throw new \LogicException(sprintf('Invalid object type. Expected "%s" but got "%s".', $this->objectClass, get_class($object)));
        }

        if ($this->objectManager->isPropertyLoaded($object, $propertyName)) {
            return;   
        }

        $hydrator = $this->objectManager->getHydratorFor($this->objectClass);
        $hydrator->loadProperty($object, $propertyName);
    }
}
