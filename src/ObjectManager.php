<?php

namespace Kassko\DataMapper;

use Kassko\ClassResolver\ClassResolverInterface;
use Kassko\DataMapper\ClassMetadataLoader\LoadingCriteria;
use Kassko\DataMapper\ClassMetadata\ClassMetadataFactoryInterface;
use Kassko\DataMapper\Configuration\Configuration;
use Kassko\DataMapper\Configuration\ObjectKey;
use Kassko\DataMapper\Exception\ObjectMappingException;
use Kassko\DataMapper\Hydrator;
use Kassko\DataMapper\Hydrator\HydrationStrategy\ClosureHydrationStrategy;
use Kassko\DataMapper\Hydrator\HydrationStrategy\DateHydrationStrategy;
use Kassko\DataMapper\LazyLoader\LazyLoaderFactoryInterface;
use Kassko\DataMapper\Listener\Events;
use Kassko\DataMapper\Listener\ObjectListenerResolverInterface;
use Kassko\DataMapper\Query\CacheConfig;
use Kassko\DataMapper\Query\ResultManager;
use Psr\Log\LoggerInterface;

/**
* Manage persistent object.
*
* @author kko
*/
class ObjectManager
{
    private $classMetadataFactory;
    private $configuration;
    private $resultManager;
    private $objectListenerResolver;
    private $classResolver;
    private $lazyLoaderFactory;
    private $logger;
    private $hydratorInstances = [];

    private static $eventToRegisterData = [
        Events::OBJECT_PRE_CREATE => 'preCreate',
        Events::OBJECT_PRE_UPDATE => 'preUpdate',
        Events::OBJECT_PRE_DELETE => 'preDelete',

        Events::OBJECT_POST_CREATE => 'postCreate',
        Events::OBJECT_POST_UPDATE => 'postUpdate',
        Events::OBJECT_POST_DELETE => 'postDelete',
        Events::OBJECT_POST_LOAD => 'postLoad',
        Events::OBJECT_POST_LOAD_LIST => 'postLoadList',
    ];

    protected function __construct()
    {
        $this->resultManager = new ResultManager($this);
    }

    /**
     * Factory method to create ObjectManager instances.
     *
     */
    public static function getInstance()
    {
        return new static;
    }

    /**
     * Factory method to get an hydrator instance or create an hydrator if no instance available.
     *
     * @param $objectClass Object class to hydrate
     *
     * @return AbstractHydrator
     */
    public function getHydratorFor($objectClass)
    {
        if (! isset($this->hydratorInstances[$objectClass])) {

            $this->hydratorInstances[$objectClass] = $this->createHydratorFor($objectClass);
        }

        return $this->hydratorInstances[$objectClass];
    }

    /**
     * Factory method to create an hydrator.
     *
     * @param $objectClass Object class to hydrate
     *
     * @return AbstractHydrator
     */
    public function createHydratorFor($objectClass)
    {
        $metadata = $this->getMetadata($objectClass);
        $propertyAccessStrategy = $metadata->isPropertyAccessStrategyEnabled();

        $hydrator = new Hydrator\Hydrator($this, $propertyAccessStrategy);
        $hydrator->setClassResolver($this->classResolver);

        $fieldsWithHydrationStrategy = $metadata->computeFieldsWithHydrationStrategy();

        $mappedFieldNames = $metadata->getMappedFieldNames();

        foreach ($mappedFieldNames as $mappedFieldName) {

            $strategy = null;

            if ($metadata->isMappedFieldWithStrategy($mappedFieldName)) {

                $fieldStrategy = $fieldsWithHydrationStrategy[$mappedFieldName];
                $strategy = new ClosureHydrationStrategy(
                    $fieldStrategy[$metadata::INDEX_EXTRACTION_STRATEGY],
                    $fieldStrategy[$metadata::INDEX_HYDRATION_STRATEGY]
                );
            }

            if ($metadata->isMappedDateField($mappedFieldName)) {

                $readDateFormat = $metadata->getReadDateFormatByMappedField($mappedFieldName, null);
                $writeDateFormat = $metadata->getWriteDateFormatByMappedField($mappedFieldName, null);

                if (! is_null($readDateFormat) && ! is_null($writeDateFormat)) {
                    $strategy = new DateHydrationStrategy($readDateFormat, $writeDateFormat, $strategy);
                } else {
                    throw new ObjectMappingException(
                        'A date field should provide "readDateFormat" and "writeDateFormat" metadata'
                    );
                }
            }

            if (! is_null($strategy)) {

                $hydrator->addStrategy($mappedFieldName, $strategy);
            }
        }

        //------------------------------------------------------------------------------------------
        if ($metadata->eventsExist()) {
            $hydrator = new Hydrator\EventHydrator($hydrator, $this);
        }

        return $hydrator;
    }

    public function find($objectClass, $id, $findMethod, $repositoryClass)
    {
        if (! isset($repositoryClass)) {
            $repo = $this->getRepository($objectClass);
        } else {
            $repo = $this->classResolver ? $this->classResolver->resolve($repositoryClass) : new $repositoryClass;
        }

        if (! method_exists($repo, $findMethod) || ! is_callable([$repo, $findMethod])) {//method_exists() est nécessaire pour filtrer la méthode magique __call() que ne filtre pas is_callable().
            throw new \BadMethodCallException(
                sprintf(
                    "Error on method call %s::%s",
                    get_class($repo), $findMethod
                )
            );
        }

        return $repo->$findMethod($id);
    }

    public function findCollection($objectClass, $findMethod, $repositoryClass)
    {
        if (! isset($repositoryClass)) {
            $repo = $this->getRepository($objectClass);
        } else {
            $repo = $this->classResolver ? $this->classResolver->resolve($repositoryClass) : new $repositoryClass;
        }

        if (! method_exists($repo, $findMethod) || ! is_callable([$repo, $findMethod])) {//method_exists() est nécessaire pour filtrer la méthode magique __call() que ne filtre pas is_callable().
            throw new \BadMethodCallException(
                sprintf(
                    "Error on method call %s::%s",
                    get_class($repo), $findMethod
                )
            );
        }

        return $repo->$findMethod();
    }

    public function findFromProviders($customSourceClass, $customSourceMethod, $object)
    {
        $customSource = $this->classResolver ? $this->classResolver->resolve($customSourceClass) : new $repositoryClass;

        if (! method_exists($customSource, $customSourceMethod) || ! is_callable([$customSource, $customSourceMethod])) {
            throw new \BadMethodCallException(sprintf('Erreur lors de l\'appel de la m�thode "%s::%s"', get_class($customSource), $customSourceMethod));
        }

        $customSource->$customSourceMethod($object);
    }

    public function getRepository($objectClass)
    {
        $metadata = $this->getMetadata($objectClass);
        $repositoryClass = $metadata->getRepositoryClass();

        if (! isset($repositoryClass)) {
            throw new \LogicException(sprintf('No repository class found in [%s] metadata.', $objectClass));
        }

        return $this->classResolver ? $this->classResolver->resolve($repositoryClass) : new $repositoryClass;
    }

    /**
    * Return the class metadata.
    *
    * @param string $className FQCN without a leading back slash as does get_class()
    *
    * @return \Kassko\DataMapper\ClassMetadata\ClassMetadata
    */
    public function getMetadata($objectClass)
    {
        if (! $objectClass instanceof ObjectKey) {
            $objectClass = new ObjectKey($objectClass);
        }

        $key = $objectClass->getKey();

        return $this->classMetadataFactory->loadMetadata(
            $objectClass,
            LoadingCriteria::createFromConfiguration($this->configuration, $objectClass),
            $this->configuration
        );
    }

    public function setClassMetadataFactory(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
        return $this;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getResultManager()
    {
        return $this->resultManager;
    }

    public function setObjectListenerResolver(ObjectListenerResolverInterface $objectListenerResolver)
    {
        $this->objectListenerResolver = $objectListenerResolver;

        return $this;
    }

    public function setClassResolver(ClassResolverInterface $classResolver)
    {
        $this->classResolver = $classResolver;

        return $this;
    }

    public function executeCommand(Callable $command)
    {
        $command();

        return $this;
    }

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        return $this;
    }

    public function hasListeners()
    {
        return isset($this->objectListenerResolver);
    }

    public function registerEvents($objectClassName)
    {
        if (! isset($this->objectListenerResolver)) {
            return;
        }

        $metadata = $this->getMetadata($objectClassName);
        $listenerClasses = $metadata->getObjectListenerClasses();

        if (isset($listenerClasses)) {
            foreach ($listenerClasses as $listenerClass) {
                $this->objectListenerResolver->registerEvents($listenerClass, self::$eventToRegisterData);
            }
        }
    }

    public function dispatchEvent($objectClassName, $eventName, Callable $eventFactoryMethod)
    {
        if (! $this->hasListeners()) {
            return;
        }

        $metadata = $this->getMetadata($objectClassName);
        $listenerClasses = $metadata->getObjectListenerClasses();

        if (isset($listenerClasses)) {
            foreach ($listenerClasses as $listenerClass) {
                $this->objectListenerResolver->dispatchEvent($listenerClass, $eventName, $eventFactoryMethod());
            }
        }
    }
}
