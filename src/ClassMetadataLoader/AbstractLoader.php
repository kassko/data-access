<?php

namespace Kassko\DataMapper\ClassMetadataLoader;

use Kassko\DataMapper\ClassMetadata\ClassMetadata;
use Kassko\DataMapper\Configuration\Configuration;

/**
 * Class abstract loader for loaders whose data loaded can be merged with data loaded by anothers loaders.
 * To be renamed MergeableLoader
 *
 * @author kko
 */
abstract class AbstractLoader implements LoaderInterface
{
    public function loadClassMetadata(
        ClassMetadata $classMetadata,
        LoadingCriteriaInterface $loadingCriteria,
        Configuration $configuration,
        DelegatingLoader $delegatingLoader = null
    ) {
        $data = $this->getData($loadingCriteria, $configuration, $delegatingLoader);
        return $this->doLoadClassMetadata($classMetadata, $data);
    }

    public function getData(
        LoadingCriteriaInterface $loadingCriteria,
        Configuration $configuration,
        DelegatingLoader $delegatingLoader
    ) {
        $data = $this->doGetData($loadingCriteria);

        $data = $this->importResource($data, $loadingCriteria, $delegatingLoader, $configuration);
        $data = $this->importConfig($data, $loadingCriteria, $delegatingLoader, $configuration);

        return $data;
    }

    abstract protected function doGetData(LoadingCriteriaInterface $loadingCriteria);

    abstract protected function doLoadClassMetadata(ClassMetadata $classMetadata, array $data);

    private function importResource(
        array $data,
        LoadingCriteriaInterface $loadingCriteria,
        DelegatingLoader $delegatingLoader,
        Configuration $configuration
    ) {
        $defaultResourceDir = $configuration->getDefaultClassMetadataResourceDir();

        if (isset($data['imports']['resources'])) {

            foreach ($data['imports']['resources'] as $resourceSettings) {

                $otherResourcePath = null;
                if (isset($resourceSettings['path'])) {
                    $otherResourcePath = $resourceSettings['path'];
                    if ('.' === $otherResourceDir = dirname($otherResourcePath)) {
                        $otherResourcePath = $defaultResourceDir.'/'.$otherResourcePath;
                    }
                }

                $otherResourceType = null;
                if (isset($resourceSettings['type'])) {
                    $otherResourceType = $resourceSettings['type'];
                }

                $otherResourceClass = null;
                if (isset($resourceSettings['class'])) {
                    $otherResourceClass = $resourceSettings['class'];
                }

                $otherResourceMethod = null;
                if (isset($resourceSettings['method'])) {
                    $otherResourceMethod = $resourceSettings['method'];
                }

                $loadingCriteria = LoadingCriteria::create(
                    $otherResourcePath,
                    $otherResourceType,
                    $otherResourceClass,
                    $otherResourceMethod
                );

                $delegatedLoader = $delegatingLoader->getDelegatedLoader($loadingCriteria);
                $othersData = $delegatedLoader->getData($loadingCriteria, $configuration, $delegatingLoader);
                $data = array_merge_recursive($othersData, $data);
            }
        }

        return $data;
    }

    private function importConfig(
        array $data,
        LoadingCriteriaInterface $loadingCriteria,
        DelegatingLoader $delegatingLoader,
        Configuration $configuration
    ) {
        if (isset($data['imports']['config'])) {

            foreach ($data['imports']['config'] as $resourceSettings) {

                $objectClassConfig = null;
                if (isset($resourceSettings['class'])) {
                    $objectClassConfig = $resourceSettings['class'];
                }

                $loadingCriteria = LoadingCriteria::createFromConfiguration($configuration, $objectClassConfig);
                $delegatedLoader = $delegatingLoader->getDelegatedLoader($loadingCriteria);
                $othersData = $delegatedLoader->getData($loadingCriteria, $configuration, $delegatingLoader);
                $data = array_merge_recursive($othersData, $data);
            }
        }

        return $data;
    }
}
