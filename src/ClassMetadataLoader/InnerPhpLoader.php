<?php

namespace Kassko\DataMapper\ClassMetadataLoader;

use Kassko\DataMapper\ClassMetadata\ClassMetadata;

/**
 * Class metadata loader for php embeded in objects.
 *
 * @author kko
 */
class InnerPhpLoader extends AbstractLoader
{
    private $classMetadata;

    public function supports(LoadingCriteriaInterface $loadingCriteria)
    {
        return
            'inner_php' === $loadingCriteria->getResourceType()
            &&
            method_exists($loadingCriteria->getResourceClass(), $loadingCriteria->getResourceMethod())
        ;
    }

    protected function doGetData(LoadingCriteriaInterface $loadingCriteria)
    {
        $callable = [$loadingCriteria->getResourceClass(), $loadingCriteria->getResourceMethod()];
        return $callable();
    }

    protected function doLoadClassMetadata(ClassMetadata $classMetadata, array $data)
    {
        $this->classMetadata = $classMetadata;

        $this->loadClassAnnotations($data);
        $this->loadFieldAnnotations($data);

        return $this->classMetadata;
    }

    private function loadClassAnnotations(array $data)
    {
        if (isset($data['object']['providerClass'])) {
            $this->classMetadata->setRepositoryClass($data['object']['providerClass']);
        }

        if (isset($data['object']['readDateConverter'])) {
            $this->classMetadata->setObjectReadDateFormat($data['object']['readDateConverter']);
        }

        if (isset($data['object']['writeDateConverter'])) {
            $this->classMetadata->setObjectWriteDateFormat($data['object']['writeDateConverter']);
        }

        if (isset($data['object']['propertyAccessStrategy'])) {
            $this->classMetadata->setPropertyAccessStrategyEnabled($data['object']['propertyAccessStrategy']);
        }

        if (isset($data['object']['fieldMappingExtensionClass'])) {
            $this->classMetadata->setPropertyMetadataExtensionClass($data['object']['fieldMappingExtensionClass']);
        }

        if (isset($data['object']['classMappingExtensionClass'])) {
            $this->classMetadata->setClassMetadataExtensionClass($data['object']['classMappingExtensionClass']);
        }

        if (isset($data['object']['customHydrator'])) {
            $this->classMetadata->setCustomHydrator($data['object']['customHydrator']);
        }

        if (isset($data['objectListeners'])) {
            $this->classMetadata->setObjectListenerClasses($data['objectListeners']);
        }

        if (isset($data['interceptors']['postExtract'])) {
            $this->classMetadata->setOnAfterExtract($data['interceptors']['postExtract']);
        }

        if (isset($data['interceptors']['postHydrate'])) {
            $this->classMetadata->setOnAfterHydrate($data['interceptors']['postHydrate']);
        }

        if (isset($data['interceptors']['preExtract'])) {
            $this->classMetadata->setOnBeforeExtract($data['interceptors']['preExtract']);
        }

        if (isset($data['interceptors']['preHydrate'])) {
            $this->classMetadata->setOnBeforeHydrate($data['interceptors']['preHydrate']);
        }
    }

    private function loadFieldAnnotations(array $data)
    {
        if (! isset($data['fields'])) {
            return;
        }

        $fieldsDataByKey = [];
        $mappedFieldNames = [];
        $mappedDateFieldNames = [];
        $originalFieldNames = [];
        $toOriginal = [];
        $toMapped = [];
        $toOneAssociations = [];
        $toManyAssociations = [];
        $providers = [];
        $valueObjects = [];
        $mappedIdFieldName = null;
        $mappedIdCompositePartFieldName = [];
        $mappedVersionFieldName = null;
        $mappedTransientFieldNames = [];
        $mappedManagedFieldNames = [];
        $fieldsWithHydrationStrategy = [];

        if (isset($data['id'])) {
            $mappedIdFieldName = $data['id'];
        }

        if (isset($data['idComposite'])) {
            $mappedIdCompositePartFieldName = $data['idComposite'];
        }

        if (isset($data['version'])) {
            $mappedVersionFieldName = $data['version'];
        }

        if (isset($data['transient'])) {
            $mappedTransientFieldNames = $data['transient'];
        }

        $dataName = 'fields';
        foreach ($data[$dataName] as $mappedFieldName => $fieldData) {

            //Normalisation: begin
            if (is_numeric($mappedFieldName)) {//if $mappedFieldName is a numeric index, $fieldData contains the field.
                $mappedFieldName = $fieldData;                
            }

            if (! is_array($fieldData)) {
                $fieldData = ['name' => $fieldData];
            }

            if (! isset($fieldData['type'])) {
                $fieldData['type'] = 'string';
            }
            //Normalisation: end

            $mappedManagedFieldNames[] = $mappedFieldName;

            $mappedFieldNames[] = $mappedFieldName;
            $originalFieldNames[] = $fieldData['name'];

            $toOriginal[$mappedFieldName] = $fieldData['name'];
            $toMapped[$fieldData['name']] = $mappedFieldName;

            $fieldDataByKey['field'] = $fieldData;

            if ('date' === $fieldData['type']) {
               $mappedDateFieldNames[] = $mappedFieldName;
            }

            if (isset($fieldData['writeConverter']) || isset($fieldData['readConverter'])) {

                $fieldsWithHydrationStrategy[$mappedFieldName] = [];
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_EXTRACTION_STRATEGY] = null;
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_HYDRATION_STRATEGY] = null;
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_EXTENSION_CLASS] = null;
            }

            if (isset($fieldData['writeConverter'])) {
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_EXTRACTION_STRATEGY] = $fieldData['writeConverter'];
            }

            if (isset($fieldData['readConverter'])) {
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_HYDRATION_STRATEGY] = $fieldData['readConverter'];
            }

            if (isset($fieldData['mappingExtensionClass'])) {
                $fieldsWithHydrationStrategy[$mappedFieldName][ClassMetadata::INDEX_EXTENSION_CLASS] = $fieldData['mappingExtensionClass'];
            }

            $fieldsDataByKey[$mappedFieldName] = $fieldDataByKey;
        }

        if (isset($data['toOneProvider'])) {

            foreach ($data['toOneProvider'] as $associationName => $toOneData) {
                $toOneAssociations[$mappedFieldName][] = ['name' => $associationName] + $toOneData;
            }
        }

        if (isset($data['toManyProvider'])) {

            foreach ($data['toManyProvider'] as $associationName => $toManyData) {
                $toManyAssociations[$mappedFieldName][] = ['name' => $associationName] + $toManyData;
            }
        }

        if (isset($data['dataSource'])) {
            $providers[$mappedFieldName] = $data['dataSource'];
        }

        if (isset($data['valueObjects'])) {
            $valueObjects = $data['valueObjects'];
        }

        if (count($fieldsDataByKey)) {
            $this->classMetadata->setFieldsDataByKey($fieldsDataByKey);
        }

        if (count($mappedFieldNames)) {
            $this->classMetadata->setMappedFieldNames($mappedFieldNames);
        }

        if (count($originalFieldNames)) {
            $this->classMetadata->setOriginalFieldNames($originalFieldNames);
        }

        if (count($toOriginal)) {
            $this->classMetadata->setToOriginal($toOriginal);
        }

        if (count($toMapped)) {
            $this->classMetadata->setToMapped($toMapped);
        }

        if (count($toOneAssociations)) {
            $this->classMetadata->setToOneAssociations($toOneAssociations);
        }

        if (count($toManyAssociations)) {
            $this->classMetadata->setToManyAssociations($toManyAssociations);
        }

        if (count($providers)) {
            $this->classMetadata->setProviders($providers);
        }

        if (count($valueObjects)) {
            $this->classMetadata->setValueObjects($valueObjects);
        }

        if (isset($mappedIdFieldName)) {
            $this->classMetadata->setMappedIdFieldName($mappedIdFieldName);
        }

        if (count($mappedIdCompositePartFieldName)) {
            $this->classMetadata->setMappedIdCompositePartFieldName($mappedIdCompositePartFieldName);
        }

        if (isset($mappedVersionFieldName)) {
            $this->classMetadata->setMappedVersionFieldName($mappedVersionFieldName);
        }

        if (count($mappedDateFieldNames)) {
            $this->classMetadata->setMappedDateFieldNames($mappedDateFieldNames);
        }

        if (count($mappedTransientFieldNames)) {
            $this->classMetadata->setMappedTransientFieldNames($mappedTransientFieldNames);
        }

        if (count($mappedManagedFieldNames)) {
            $this->classMetadata->setMappedManagedFieldNames($mappedManagedFieldNames);
        }

        if (count($fieldsWithHydrationStrategy)) {
            $this->classMetadata->setFieldsWithHydrationStrategy($fieldsWithHydrationStrategy);
        }
    }
}