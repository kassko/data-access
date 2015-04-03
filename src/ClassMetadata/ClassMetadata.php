<?php

namespace Kassko\DataMapper\ClassMetadata;

use Kassko\DataMapper\Exception\ObjectMappingException;
use Kassko\DataMapper\Hydrator\Hydrator;

/**
* Contains class metadata.
*
* @author kko
*/
class ClassMetadata
{
    const INDEX_EXTRACTION_STRATEGY = 0;
    const INDEX_HYDRATION_STRATEGY = 1;
    const INDEX_EXTENSION_CLASS = 2;
    const INDEX_METADATA_EXTENSION_CLASS = 3;

    private $fieldExclusionPolicy = 'include_all';
    private $includedFields = [];
    private $excludedFields = [];
    private $originalFieldNames = [];
    private $mappedFieldNames = [];
    private $mappedDateFieldNames = [];
    private $mappedIdFieldName;
    private $mappedIdCompositePartFieldName = [];
    private $mappedVersionFieldName;
    private $toOriginal = [];
    private $toMapped = [];
    private $fieldsDataByKey = [];
    private $columnDataName = 'field';
    private $valueObjects = [];
    private $repositoryClass;
    private $customHydrator;
    private $objectReadDateFormat;
    private $objectWriteDateFormat;
    private $propertyAccessStrategyEnabled;

    /**
     * @var string Fqcn of class which contains some property metadata as "callback"
     */
    private $propertyMetadataExtensionClass;

    /**
     * @var string Fqcn of class which contains some class metadata as "callback"
     */
    private $classMetadataExtensionClass;

    private $mappedTransientFieldNames = [];
    private $fieldsWithHydrationStrategy = [];

    private $dataSources = [];
    private $dataSourcesStore = [];
    private $providers = [];
    private $providersStore = [];
    private $refSources = [];
    
    private $getters = [];
    private $setters = [];

    private $objectListenerClasses = [];
    private $idGetter;
    private $idSetter;
    private $versionGetter;
    private $versionSetter;

    private $onBeforeExtract;
    private $onAfterExtract;
    private $onBeforeHydrate;
    private $onAfterHydrate;

    /**
     * @var array class methods
     */
    private $methods;

    /**
     * @var ReflectionClass
     */
    protected $reflectionClass;


    /**
     * @param object|string $objectClass
     */
    public function __construct($objectClass)
    {
        $objectClass = \Doctrine\Common\Util\ClassUtils::getRealClass($objectClass);
        $this->reflectionClass = new \ReflectionClass($objectClass);
        $this->methods = get_class_methods($objectClass);
    }

    /**
    * Gets the fully-qualified class name of this persistent class.
    *
    * @return string
    */
    public function getName()
    {
        return $this->reflectionClass->getName();
    }

    /**
    * Gets the ReflectionClass instance for this mapped class.
    *
    * @return \ReflectionClass
    */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }

    public function compile()
    {
        if (isset($this->objectReadDateFormat) || isset($this->objectWriteDateFormat)) {

            foreach ($this->fieldsDataByKey as $fieldName => &$fieldDataByKey) {

                if (isset($fieldDataByKey['field']['type']) && 'date' == $fieldDataByKey['field']['type']) {

                    if (isset($this->objectReadDateFormat)) {
                        $fieldDataByKey['field']['readDateConverter'] = $this->objectReadDateFormat;
                    }

                    if (isset($this->objectWriteDateFormat)) {
                        $fieldDataByKey['field']['writeDateConverter'] = $this->objectWriteDateFormat;
                    }
                }
            }

            unset($fieldDataByKey);
        }

        $this->normalizeDataSourcesStore();
        $this->normalizeProvidersStore();

        foreach ($this->refSources as $mappedFieldName => $refSource) {
            if (! isset($this->dataSources[$mappedFieldName]) && null !== $dataSource = $this->findDataSourceByIdBeforeCompilation($refSource)) {
                $this->dataSources[$mappedFieldName] = $dataSource;
            } elseif (! isset($this->providers[$mappedFieldName]) && null !== $provider = $this->findProviderByIdBeforeCompilation($refSource)) {
                $this->providers[$mappedFieldName] = $providers;
            }
        }
    }

    public function getFieldExclusionPolicy()
    {
        return $this->fieldExclusionPolicy;
    }

    public function setFieldExclusionPolicy($fieldExclusionPolicy)
    {
        $this->fieldExclusionPolicy = $fieldExclusionPolicy;
        return $this;
    } 

    public function getRepositoryClass()
    {
        return $this->repositoryClass;
    }

    public function setRepositoryClass($repositoryClass)
    {
        $this->repositoryClass = $repositoryClass;
        return $this;
    }

    public function getObjectReadDateFormat()
    {
        return $this->objectReadDateFormat;
    }

    public function setObjectReadDateFormat($objectReadDateFormat)
    {
        $this->objectReadDateFormat = $objectReadDateFormat;
        return $this;
    }

    public function getObjectWriteDateFormat()
    {
        return $this->objectWriteDateFormat;
    }

    public function setObjectWriteDateFormat($objectWriteDateFormat)
    {
        $this->objectWriteDateFormat = $objectWriteDateFormat;
        return $this;
    }

    public function setOriginalFieldNames(array $fieldNames)
    {
        $this->originalFieldNames = $fieldNames;
        return $this;
    }

    public function getMappedDateFieldNames()
    {
        return $this->mappedDateFieldNames;
    }

    public function setMappedDateFieldNames(array $mappedDateFieldNames)
    {
        $this->mappedDateFieldNames = $mappedDateFieldNames;
        return $this;
    }

    public function getMappedFieldNames()
    {
        return $this->mappedFieldNames;
    }

    public function setMappedFieldNames(array $mappedFieldNames)
    {
        $this->mappedFieldNames = $mappedFieldNames;
        return $this;
    }

    public function getFieldsDataByKey()
    {
        return $this->fieldsDataByKey;
    }

    public function setFieldsDataByKey(array $fieldDataByKey)
    {
        $this->fieldsDataByKey = $fieldDataByKey;
        return $this;
    }

    public function getMappedIdFieldName()
    {
        return $this->mappedIdFieldName;
    }

    public function setMappedIdFieldName($mappedIdFieldName)
    {
        $this->mappedIdFieldName = $mappedIdFieldName;
        return $this;
    }

    public function getMappedIdCompositePartFieldName()
    {
        return $this->mappedIdCompositePartFieldName;
    }

    public function setMappedIdCompositePartFieldName(array $mappedIdCompositePartFieldName)
    {
        $this->mappedIdCompositePartFieldName = $mappedIdCompositePartFieldName;
        return $this;
    }

    public function getMappedVersionFieldName()
    {
        return $this->mappedVersionFieldName;
    }

    public function setMappedVersionFieldName($mappedVersionFieldName)
    {
        $this->mappedVersionFieldName = $mappedVersionFieldName;
        return $this;
    }

    public function getToOriginal()
    {
        return $this->toOriginal;
    }

    public function setToOriginal(array $toOriginal)
    {
        $this->toOriginal = $toOriginal;
        return $this;
    }

    public function getToMapped()
    {
        return $this->toMapped;
    }

    public function setToMapped(array $toMapped)
    {
        $this->toMapped = $toMapped;
        return $this;
    }

    public function isPropertyAccessStrategyEnabled()
    {
        return $this->propertyAccessStrategyEnabled;
    }

    public function setPropertyAccessStrategyEnabled($propertyAccessStrategyEnabled)
    {
        $this->propertyAccessStrategyEnabled = $propertyAccessStrategyEnabled;
        return $this;
    }

    /**
     * Gets the value of metadataExtensionClass for a given fieldName.
     *
     * @var mappedFieldName string Nom d'un champs pour lequel on cherche la classe contenant ses métadonnées de callback.
     *
     * @return string Fqcn de la classes contenant les métadonnées de type "callback" pour un champs donné
     */
    public function getMetadataExtensionClassByMappedField($mappedFieldName)
    {
        $prefix = '';

        if (null != $data = $this->getDataForField($mappedFieldName, $this->columnDataName)) {

            switch (true) {

                case isset($data['fieldMappingExtensionClass']):
                    return $prefix.$data['fieldMappingExtensionClass'];

                case isset($this->propertyMetadataExtensionClass):
                    return $prefix.$this->propertyMetadataExtensionClass;
            }
        }

        return null;
    }

    /**
     * Gets the value of propertyMetadataExtensionClass.
     *
     * @return string Fqcn of class wich contains some property metadata as "callback"
     */
    public function getPropertyMetadataExtensionClass()
    {
        return $this->propertyMetadataExtensionClass;
    }

    /**
     * Sets the value of propertyMetadataExtensionClass.
     *
     * @param string $propertyMetadataExtensionClass Fqcn of class wich contains some property metadata as "callback"
     *
     * @return self
     */
    public function setPropertyMetadataExtensionClass($propertyMetadataExtensionClass)
    {
        $this->propertyMetadataExtensionClass = $propertyMetadataExtensionClass;

        return $this;
    }

    /**
     * Gets the value of classMetadataExtensionClass.
     *
     * @return string Fqcn of class wich contains some property metadata as "callback"
     */
    public function getClassMetadataExtensionClass()
    {
        return $this->classMetadataExtensionClass;
    }

    /**
     * Sets the value of classMetadataExtensionClass.
     *
     * @param string $classMetadataExtensionClass Fqcn de la classes wich contains some class metadata as "callback"
     *
     * @return self
     */
    public function setClassMetadataExtensionClass($classMetadataExtensionClass)
    {
        $this->classMetadataExtensionClass = $classMetadataExtensionClass;

        return $this;
    }

    public function isValueObject($mappedFieldName)
    {
        return array_key_exists($mappedFieldName, $this->valueObjects);
    }

    public function getFieldsWithValueObjects()
    {
        return array_keys($this->valueObjects);
    }

    public function setValueObjects(array $valueObjects)
    {
        $this->valueObjects = $valueObjects;
        return $this;
    }

    public function getValueObjectInfo($mappedFieldName)
    {
        $valueObjectInfo = $this->valueObjects[$mappedFieldName];

        $mappingResourcePath = null;
        if (isset($valueObjectInfo['mappingResourcePath'], $valueObjectInfo['mappingResourceName'])) {
            $mappingResourcePath = $valueObjectInfo['mappingResourcePath'].'/'.$valueObjectInfo['mappingResourceName'];
        } elseif (isset($valueObjectInfo['mappingResourceName'])) {
            $mappingResourcePath = $valueObjectInfo['mappingResourceName'];
        }

        return [
            $valueObjectInfo['class'],
            $mappingResourcePath,
            isset($valueObjectInfo['mappingResourceType']) ? $valueObjectInfo['mappingResourceType'] : null,
        ];
    }

    public function isTransient($mappedFieldName)
    {
        return in_array($mappedFieldName, $this->mappedTransientFieldNames);
    }

    public function setMappedTransientFieldNames(array $mappedTransientFieldNames)
    {
        $this->mappedTransientFieldNames = $mappedTransientFieldNames;
        return $this;
    }

    public function isNotManaged($mappedFieldName)
    {//todo: optimize it.
        return 
            ( 
                ('include_all' === $this->fieldExclusionPolicy && isset($this->excludedFields[$mappedFieldName]))
                ||
                ('exclude_all' === $this->fieldExclusionPolicy && isset($this->includedFields[$mappedFieldName]))
            )
        ;
    }

    public function setIncludedFields(array $includedFields)
    {
        $this->includedFields = $includedFields;
        return $this;
    }

    public function setExcludedFields(array $excludedFields)
    {
        $this->excludedFields = $excludedFields;
        return $this;
    }

    public function getOnBeforeExtract()
    {
        return $this->onBeforeExtract;
    }

    public function setOnBeforeExtract($onBeforeExtract)
    {
        $this->onBeforeExtract = $onBeforeExtract;
        return $this;
    }

    public function getOnAfterExtract()
    {
        return $this->onAfterExtract;
    }

    public function setOnAfterExtract($onAfterExtract)
    {
        $this->onAfterExtract = $onAfterExtract;
        return $this;
    }

    public function getOnBeforeHydrate()
    {
        return $this->onBeforeHydrate;
    }

    public function setOnBeforeHydrate($onBeforeHydrate)
    {
        $this->onBeforeHydrate = $onBeforeHydrate;
        return $this;
    }

    public function getOnAfterHydrate()
    {
        return $this->onAfterHydrate;
    }

    public function setOnAfterHydrate($onAfterHydrate)
    {
        $this->onAfterHydrate = $onAfterHydrate;
        return $this;
    }

    public function getFieldsWithHydrationStrategy()
    {
        return $this->fieldsWithHydrationStrategy;
    }

    public function setFieldsWithHydrationStrategy(array $fieldsWithHydrationStrategy)
    {
        $this->fieldsWithHydrationStrategy = $fieldsWithHydrationStrategy;

        return $this;
    }

    public function computeFieldsWithHydrationStrategy()
    {
        $fieldsWithHydrationStrategy = [];

        foreach ($this->fieldsWithHydrationStrategy as $mappedFieldName => $fieldStrategy) {

            $class = $fieldStrategy[self::INDEX_EXTENSION_CLASS];
            if (is_null($class)) {
                $class = $this->propertyMetadataExtensionClass;
            }

            if (is_null($class)) {
                $class = $this->getName();
            }

            $index = self::INDEX_HYDRATION_STRATEGY;
            $strategy = $fieldStrategy[$index];
            $fieldStrategy[$index] = function ($valueContext, $context) use ($class, $strategy) {
                return $class::$strategy($valueContext, $context);
            };


            $index = self::INDEX_EXTRACTION_STRATEGY;
            $strategy = $fieldStrategy[$index];
            $fieldStrategy[$index] = function ($valueContext, $context) use ($class, $strategy) {
                return $class::$strategy($valueContext, $context);
            };

            $fieldsWithHydrationStrategy[$mappedFieldName] = $fieldStrategy;
        }

        return $fieldsWithHydrationStrategy;
    }

    public function getObjectListenerClasses()
    {
        return $this->objectListenerClasses;
    }

    public function setObjectListenerClasses(array $objectListenerClasses)
    {
        $this->objectListenerClasses = $objectListenerClasses;
        return $this;
    }

    public function getOriginalFieldNames()
    {
        return $this->originalFieldNames;
    }

    public function getTypeOfMappedField($mappedFieldName)
    {
        if (null != $data = $this->getDataForField($mappedFieldName, $this->columnDataName)) {
            if ('mixed' === $data['type']) {
                return null;
            }

            return $data['type'];
        }

        return 'string';
    }

    public function getClassOfMappedField($mappedFieldName)
    {
        if (null != $data = $this->getDataForField($mappedFieldName, $this->columnDataName)) {
            return $data['class'];
        }

        return null;
    } 

    public function getOriginalFieldName($mappedFieldName)
    {
        if (! isset($this->toOriginal[$mappedFieldName])) {
            return $mappedFieldName;
        }

        return $this->toOriginal[$mappedFieldName];
    }

    public function getMappedFieldName($originalFieldName)
    {
        if (! isset($this->toMapped[$originalFieldName])) {
            return $originalFieldName;
        }

        return $this->toMapped[$originalFieldName];
    }

    public function isMappedDateField($mappedFieldName)
    {
        return in_array($mappedFieldName, $this->mappedDateFieldNames);
    }

    public function isMappedFieldWithStrategy($mappedFieldName)
    {
        return array_key_exists($mappedFieldName, $this->fieldsWithHydrationStrategy);
    }

    public function getReadDateFormatByMappedField($mappedFieldName, $default)
    {
        if (null != $data = $this->getDataForField($mappedFieldName, $this->columnDataName)) {
            return isset($data['readDateConverter']) ? $data['readDateConverter'] : $this->objectReadDateFormat;//<=== A REVOIR !!! Kassko
        }

        return $default;
    }

    public function getWriteDateFormatByMappedField($mappedFieldName, $default)
    {
        if (null != $data = $this->getDataForField($mappedFieldName, $this->columnDataName)) {
            return isset($data['writeDateConverter']) ? $data['writeDateConverter'] : $this->objectWriteDateFormat;//<=== A REVOIR !!! Kassko
        }

        return $default;
    }

    public function eventsExist()
    {
        return
            isset($this->onBeforeExtract)
            || isset($this->onAfterExtract)
            || isset($this->onBeforeHydrate)
            || isset($this->onAfterHydrate)
        ;
    }

    public function hasId()
    {
        return isset($this->mappedIdFieldName);
    }

    public function hasIdComposite()
    {
        return isset($this->mappedIdCompositePartFieldName);
    }

    public function isVersionned()
    {
        return isset($this->mappedVersionFieldName);
    }

    public function getIdFieldName()
    {
        if (! isset($this->mappedIdFieldName)) {
            throw new ObjectMappingException(sprintf('In your use case, the Id field name is needed for object "%s"', $this->getName()));
        }
        return $this->getOriginalFieldName($this->mappedIdFieldName);
    }

    public function getVersionFieldName()
    {
        return $this->getOriginalFieldName($this->mappedVersionFieldName);
    }

    public function extractId($object, Hydrator $hydrator)
    {
        return $hydrator->extractProperty($object, $this->mappedIdFieldName);
    }

    public function extractIdComposite($object, Hydrator $hydrator)
    {
        return array_map(
            function ($mappedIdFieldName) use ($object, $hydrator) {

                return $hydrator->extractProperty($object, $this->mappedVersionFieldName);
            },
            $this->mappedIdCompositePartFieldName
        );
    }

    public function extractVersion($object, Hydrator $hydrator)
    {
        return $hydrator->extractProperty($object, $this->mappedVersionFieldName);
    }

    public function extractField($object, $fieldName, Hydrator $hydrator)
    {
        return $hydrator->extractProperty($object, $fieldName);
    }

    public function getIdGetter()
    {
        if (null === $this->idGetter) {
            $this->idGetter = $this->getterise($this->mappedIdFieldName);
        }

        return $this->idGetter;
    }

    public function getIdSetter()
    {
        if (null === $this->idSetter) {
            $this->idSetter = $this->setterise($this->mappedIdFieldName);
        }

        return $this->idSetter;
    }

    public function getVersionGetter()
    {
        if (null === $this->versionGetter) {
            $this->versionGetter = $this->getterise($this->mappedVersionFieldName);
        }

        return $this->versionGetter;
    }

    public function getVersionSetter()
    {
        if (null === $this->versionSetter) {
            $this->versionSetter = $this->setterise($this->mappedVersionFieldName);
        }

        return $this->versionSetter;
    }

    public function getterise($mappedFieldName)
    {
        if (! isset($mappedFieldName)) {
            return null;
        }

        if (isset($this->getters[$mappedFieldName])) {

            if (isset($this->getters[$mappedFieldName]['name'])) {
                return $this->getters[$mappedFieldName]['name'];
            }

            if (isset($this->getters[$mappedFieldName]['prefix'])) {
                return $this->getters[$mappedFieldName]['prefix'].ucfirst($mappedFieldName);
            }

            static $defaultsGettersTypes = ['get', 'is', 'has'];
            foreach ($defaultsGettersTypes as $getterType) {
                if (in_array($getter = $getter.ucfirst($mappedFieldName), $this->methods)) {
                    return $this->getters[$mappedFieldName]['name'] = $getter;
                }
            }
        }

        return 'get'.ucfirst($mappedFieldName);
    }

    public function setterise($mappedFieldName)
    {
        if (! isset($mappedFieldName)) {
            return null;
        }

        if (isset($this->setters[$mappedFieldName])) {

            if (isset($this->setters[$mappedFieldName]['name'])) {
                return $this->setters[$mappedFieldName]['name'];
            }

            if (isset($this->setters[$mappedFieldName]['prefix'])) {
                return $this->setters[$mappedFieldName]['prefix'].ucfirst($mappedFieldName);
            }

            if (in_array($setter = 'set'.ucfirst($mappedFieldName), $this->methods)) {
                return $this->setters[$mappedFieldName]['name'] = $setter;
            }
        }

        return 'set'.ucfirst($mappedFieldName);
    }

    private function getDataForField($mappedFieldName, $columnDataName)
    {
        if (! isset($this->fieldsDataByKey[$mappedFieldName][$columnDataName])) {
            return null;
        }

        return $this->fieldsDataByKey[$mappedFieldName][$columnDataName];
    }

    public function findSourceById($id)
    {//@todo: optimize it.
        foreach ($this->dataSourcesStore as $dataSource) {
            if ($dataSource['id'] === $id) {
                return $this->createSourcePropertyMetadataFromArrayData($dataSource);
            }
        }

        foreach ($this->dataSources as $dataSource) {
            if ($dataSource['id'] === $id) {
                return $this->createSourcePropertyMetadataFromArrayData($dataSource);
            }
        }

        foreach ($this->providersStore as $provider) {
            if ($provider['id'] === $id) {
                return $this->createSourcePropertyMetadataFromArrayData($provider);
            }
        }

        foreach ($this->providers as $provider) {
            if ($provider['id'] === $id) {
                return $this->createSourcePropertyMetadataFromArrayData($provider);
            }
        }

        throw new ObjectMappingException(sprintf('No source found for the given id "%s".', $id));
    }

    private function createSourcePropertyMetadataFromArrayData(array $source)
    {
        $sourcePropertyMetadata = new SourcePropertyMetadata;

        $sourcePropertyMetadata->id = $source['id'];
        $sourcePropertyMetadata->class = $source['class'];
        $sourcePropertyMetadata->method = $source['method'];
        $sourcePropertyMetadata->args = $source['args'];
        $sourcePropertyMetadata->lazyLoading = $source['lazyLoading'];
        $sourcePropertyMetadata->supplySeveralFields = $source['supplySeveralFields'];
        $sourcePropertyMetadata->onFail = $source['onFail'];
        $sourcePropertyMetadata->exceptionClass = $source['exceptionClass'];
        $sourcePropertyMetadata->badReturnValue = $source['badReturnValue'];
        $sourcePropertyMetadata->fallbackSourceId = $source['fallbackSourceId'];

        return $sourcePropertyMetadata;
    }

    //========================= Data sources : begin

    public function getDataSources()
    {
        return $this->dataSources;
    }

    public function setDataSources(array $dataSources)
    {
        $this->dataSources = $dataSources;

        return $this;
    }

    public function setDataSourcesStore(array $dataSourcesStore)
    {
        $this->dataSourcesStore = $dataSourcesStore;

        return $this;
    }

    public function hasDataSource($mappedFieldName)
    {
        return isset($this->dataSources[$mappedFieldName]);
    }

    public function getFieldsWithDataSources()
    {
        return array_keys($this->dataSources);
    }

    public function getDataSourceInfo($mappedFieldName)
    {
        return $this->createSourcePropertyMetadataFromArrayData($this->dataSources[$mappedFieldName]);
    }

    /**
     * Retrieve fields with the same data source as $mappedFieldNameRef.
     *
     * @param array $mappedFieldNameRef The reference field.
     *
     * @return array
     */
    public function getFieldsWithSameDataSource($mappedFieldNameRef)
    {
        if (! isset($this->dataSources[$mappedFieldNameRef])) {
            throw new ObjectMappingException(sprintf('A "data source" metadata is expected for the field "%s".', $mappedFieldNameRef));
        }

        $class = $this->dataSources[$mappedFieldNameRef]['class'];
        $method = $this->dataSources[$mappedFieldNameRef]['method'];

        $propLoadedTogether = [];
        foreach ($this->dataSources as $mappedFieldName => $value) {

            if ($mappedFieldName !== $mappedFieldNameRef && $value['class'] === $class && $value['method'] === $method) {
                $propLoadedTogether[] = $mappedFieldName;
            }
        }

        return $propLoadedTogether;
    }

    //========================= Data sources : end

    //========================= Providers : begin

    public function getProviders()
    {
        return $this->providers;
    }

    public function setProviders(array $providers)
    {
        $this->providers = $providers;

        return $this;
    }

    public function setProvidersStore(array $providersStore)
    {
        $this->providersStore = $providersStore;

        return $this;
    }

    public function hasProvider($mappedFieldName)
    {
        return isset($this->providers[$mappedFieldName]);
    }

    public function getFieldsWithProviders()
    {
        return array_keys($this->providers);
    }

    public function getProviderInfo($mappedFieldName)
    {
        return $this->createSourcePropertyMetadataFromArrayData($this->providers[$mappedFieldName]);
    }

    /**
     * Retrieve fields with the same provider as $mappedFieldNameRef.
     *
     * @param array $mappedFieldNameRef The reference field.
     *
     * @return array
     */
    public function getFieldsWithSameProvider($mappedFieldNameRef)
    {
        if (! isset($this->providers[$mappedFieldNameRef])) {
            throw new ObjectMappingException(sprintf('A "data source" metadata is expected for the field "%s".', $mappedFieldNameRef));
        }

        $class = $this->providers[$mappedFieldNameRef]['class'];
        $method = $this->providers[$mappedFieldNameRef]['method'];

        $propLoadedTogether = [];
        foreach ($this->providers as $mappedFieldName => $value) {

            if ($mappedFieldName !== $mappedFieldNameRef && $value['class'] === $class && $value['method'] === $method) {
                $propLoadedTogether[] = $mappedFieldName;
            }
        }

        return $propLoadedTogether;
    }

    //========================= Providers : end

    public function setRefSources(array $refSources)
    {
        $this->refSources = $refSources;

        return $this;
    }

    public function setGetters(array $getters)
    {
        $this->getters = $getters;

        return $this;
    }

    public function setSetters(array $setters)
    {
        $this->setters = $setters;

        return $this;
    }

    public function existsMappedFieldName($mappedFieldName)
    {
        return in_array($mappedFieldName, $this->mappedFieldNames);
    }

    public function hasCustomHydrator()
    {
        return isset($this->customHydrator);
    }

    public function setCustomHydrator(array $customHydrator)
    {
        $this->customHydrator = $customHydrator;
    }

    public function getCustomHydratorInfo()
    {
        return [
            $this->customHydrator['class'],
            $this->customHydrator['hydrateMethod'],
            $this->customHydrator['extractMethod'],
        ];
    }

    private function normalizeDataSourcesStore()
    {
        foreach ($this->dataSourcesStore as $dataSource) {
            $dataSource['supplySeveralFields'] = false;
        }
    }

    private function normalizeProvidersStore()
    {
        foreach ($this->providersStore as $provider) {
            $provider['supplySeveralFields'] = false;
        }
    }

    private function findDataSourceByIdBeforeCompilation($id)
    {
        foreach ($this->dataSourcesStore as $dataSource) {
            if ($dataSource['id'] === $id) {
                return $dataSource;
            }
        }

        foreach ($this->dataSources as $dataSource) {
            if ($dataSource['id'] === $id) {
                return $dataSource;
            }
        }

        return null;
    }

    private function findProviderByIdBeforeCompilation($id)
    {
        foreach ($this->providersStore as $provider) {
            if ($provider['id'] === $id) {
                return $provider;
            }
        }

        foreach ($this->providers as $provider) {
            if ($provider['id'] === $id) {
                return $provider;
            }
        }

        return null;
    }
}
