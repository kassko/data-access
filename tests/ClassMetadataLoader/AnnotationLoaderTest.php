<?php
namespace Kassko\DataMapperTest\ClassMetadataLoader;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Kassko\DataMapper\ClassMetadataLoader;
use Kassko\DataMapper\ClassMetadata;
use Kassko\DataMapper\Configuration\Configuration;

/**
 * Class AnnotationLoaderTest
 * 
 * @author Alexey Rusnak
 */
class AnnotationLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $className = '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotations';

    /**
     * @return void
     */
    public function setUp()
    {
        AnnotationRegistry::registerLoader('class_exists');
    }

    /**
     * @param string $className
     * @return ClassMetadata\ClassMetadata
     */
    public function loadAnnotationMetadata($className)
    {
        $resourcePath = sys_get_temp_dir();
        $resourceType = '';
        $resourceClass = '';
        $resourceMethod = '';
        $classMetadata = new ClassMetadata\ClassMetadata($className);
        $loadingCriteria = ClassMetadataLoader\LoadingCriteria::create(
            $resourcePath,
            $resourceType,
            $resourceClass,
            $resourceMethod
        );

        $loader = new ClassMetadataLoader\AnnotationLoader(new AnnotationReader());
        return $loader->loadClassMetadata($classMetadata, $loadingCriteria, new Configuration());
    }

    /**
     * @test
     */
    public function dataSourcesStoreValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\DataSourcesStore'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        /**
         * @var ClassMetadata\Model\DataSource $dataSource
         */
        $dataSource = $metadata->findDataSourceById('personSource');
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\DataSource', $dataSource);

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $dataSource->getMethod());
        $this->assertEquals('Kassko\Sample\PersonDataSource', $dataSource->getMethod()->getClass());
        $this->assertEquals('getData', $dataSource->getMethod()->getFunction());
        $this->assertEquals(array('#id'), $dataSource->getMethod()->getArgs());
        $this->assertEquals('checkException', $dataSource->getOnFail());
        $this->assertEquals('\RuntimeException', $dataSource->getExceptionClass());
        $this->assertEquals('emptyString', $dataSource->getBadReturnValue());
        $this->assertEquals('testFallbackSourceId', $dataSource->getFallbackSourceId());

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $dataSource->getPreprocessors()[0]);
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $dataSource->getProcessors()[0]);
       
        $this->assertEquals(array('#dependsFirst'), $dataSource->getDepends());
        $this->assertTrue($dataSource->getSupplySeveralFields());
        $this->assertTrue($dataSource->getLazyLoading());
    }

    /**
     * @test
     */
    public function dataSourcesStoreMultiplesDependsValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\DataSourcesStoreMultiplesDepends'
        );

        /**
         * @var ClassMetadata\Model\DataSource $dataSource
         */
        $dataSource = $metadata->findDataSourceById('personSource');

        $this->assertEquals(array('#dependsFirst', '#dependsSecond', '#dependsThird'),  $dataSource->getDepends());
    }

    /**
     * @test
     */
    public function dataSourcesStoreMultiplesProcessorsValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\DataSourcesStoreMultiplesProcessors'
        );

        /**
         * @var ClassMetadata\Model\DataSource $dataSource
         */
        $dataSource = $metadata->findDataSourceById('personSource');

        $this->assertContainsOnlyInstancesOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $dataSource->getPreprocessors());
        $this->assertContainsOnlyInstancesOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $dataSource->getProcessors());
    }

    /**
     * @test
     */
    public function objectValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\Object'
        );
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);

        $this->assertEquals('exclude_all', $metadata->getFieldExclusionPolicy());
        $this->assertEquals('testProviderClass', $metadata->getRepositoryClass());
        $this->assertEquals('testReadDateConverter', $metadata->getObjectReadDateFormat());
        $this->assertEquals('testWriteDateConverter', $metadata->getObjectWriteDateFormat());
        $this->assertEquals('testFieldMappingExtensionClass', $metadata->getPropertyMetadataExtensionClass());
        $this->assertEquals('testClassMappingExtensionClass', $metadata->getClassMetadataExtensionClass());
        $this->assertTrue($metadata->isPropertyAccessStrategyEnabled());
    }

    /**
     * @test
     */
    public function providersStoreValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\ProvidersStore'
        );
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        
        /**
         * @var $provider
         */
        $provider = $metadata->findProviderById('providers#1');
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\Provider', $provider);
        $this->assertEquals('providers#1', $provider->getId());
        $this->assertTrue($provider->getLazyLoading());
        $this->assertTrue($provider->getSupplySeveralFields());
        $this->assertInternalType('array', $provider->getDepends());
        $this->assertEquals(1, count($provider->getDepends()));
        foreach ($provider->getDepends() as $dependency) {
            $this->assertEquals('depend#1', $dependency);
        }
        $this->assertEquals('\RuntimeException', $provider->getExceptionClass());
        $this->assertEquals('emptyArray', $provider->getBadReturnValue());
        $this->assertEquals('fallbackSourceId#1', $provider->getFallbackSourceId());
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $provider->getMethod());
        $this->assertEquals('class', $provider->getMethod()->getClass());
        $this->assertEquals('method', $provider->getMethod()->getFunction());
        $this->assertEquals('arg#1', $provider->getMethod()->getArgs()[0]);
    }

    /**
     * @test
     */
    public function providersStoreMultiplesDependsValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\ProvidersStoreMultiplesDepends'
        );

        /**
         * @var ClassMetadata\Model\Provider $dataSource
         */
        $provider = $metadata->findProviderById('personSource');

        $this->assertEquals(array('#dependsFirst', '#dependsSecond', '#dependsThird'),  $provider->getDepends());
    }

    /**
     * @test
     */
    public function providersStoreMultiplesProcessorsValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\ProvidersStoreMultiplesProcessors'
        );

        /**
         * @var ClassMetadata\Model\Provider $provider
         */
        $provider = $metadata->findProviderById('personSource');

        $this->assertContainsOnlyInstancesOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $provider->getPreprocessors());
        $this->assertContainsOnlyInstancesOf('\Kassko\DataMapper\ClassMetadata\Model\Method', $provider->getProcessors());
    }

    /**
     * @test
     */
    public function refDefaultSourceValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\RefDefaultSource'
        );
        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('refDefaultSourceId', $metadata->getRefDefaultSource());
    }

    /**
     * @test
     */
    public function customHydratorValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\CustomHydrator'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);

        $customHydrationInfo = $metadata->getCustomHydratorInfo();
        $this->assertInternalType('array', $customHydrationInfo);
        $this->assertEquals('CustomHydratorClassName', $customHydrationInfo[0]);
        $this->assertEquals('hydrateMethod', $customHydrationInfo[1]);
        $this->assertEquals('extractMethod', $customHydrationInfo[2]);
    }

    /**
     * @test
     */
    public function preExtractValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\PreExtract'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('methodName', $metadata->getOnBeforeExtract());
    }

    /**
     * @test
     */
    public function postExtractValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\PostExtract'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('postExtractMethodName', $metadata->getOnAfterExtract());
    }

    /**
     * @test
     */
    public function preHydrateValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\PreHydrate'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('preHydrateMethodName', $metadata->getOnBeforeHydrate());
    }

    /**
     * @test
     */
    public function postHydrateValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\PostHydrate'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('postHydrateMethodName', $metadata->getOnAfterHydrate());
    }

    /**
     * @test
     */
    public function objectListenersValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\ObjectListeners'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals(array('classList#1'), $metadata->getObjectListenerClasses());
    }

    /**
     * @test
     */
    public function fieldValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\Field'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals(
            array(
                'fieldOne'  => array(
                    'field' => array(
                        'name'                       => 'FirstField',
                        'type'                       => 'string',
                        'class'                      => 'stdClass',
                        'readConverter'              => 'readConvertFirstField',
                        'writeConverter'             => 'writeConvertFirstField',
                        'readDateConverter'          => '',
                        'writeDateConverter'         => '',
                        'fieldMappingExtensionClass' => 'ExtensionClass'
                    )
                ),
                'fieldTwo'  => array(
                    'field' => array(
                        'name'                       => 'SecondField',
                        'type'                       => 'integer',
                        'class'                      => '\DateTime',
                        'readConverter'              => '',
                        'writeConverter'             => '',
                        'readDateConverter'          => 'readDateConvertSecondField',
                        'writeDateConverter'         => 'writeDateConvertSecondField',
                        'fieldMappingExtensionClass' => 'ExtensionClass'
                    )
                ),
                'dateField'  => array(
                    'field' => array(
                        'name'                       => 'DateField',
                        'type'                       => 'date',
                        'class'                      => '',
                        'readConverter'              => '',
                        'writeConverter'             => '',
                        'readDateConverter'          => '',
                        'writeDateConverter'         => '',
                        'fieldMappingExtensionClass' => ''
                    )
                )
            ),
            $metadata->getFieldsDataByKey()
        );
        $this->assertEquals(array('dateField'), $metadata->getMappedDateFieldNames());
        // @TODO: Need to verify INDEX_EXTENSION_CLASS. Possibly error, unknown attribute 'mappingExtensionClass' used.
        $this->assertEquals(
            array(
                'fieldOne'  => array(
                    ClassMetadata\ClassMetadata::INDEX_EXTRACTION_STRATEGY  => 'writeConvertFirstField',
                    ClassMetadata\ClassMetadata::INDEX_HYDRATION_STRATEGY   => 'readConvertFirstField',
                    ClassMetadata\ClassMetadata::INDEX_EXTENSION_CLASS      => ''
                )
            ),
            $metadata->getFieldsWithHydrationStrategy()
        );
    }

    /**
     * @test
     */
    public function excludeValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\Exclude'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals('include_all', $metadata->getFieldExclusionPolicy());
        $this->assertTrue($metadata->isNotManaged('excludedField'));
        $this->assertFalse($metadata->isNotManaged('field'));
    }

    /**
     * @test
     */
    public function dataSourceValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\DataSource'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);

        $this->assertEquals(
            array(
                'firstField' => array(
                    'id'                  => 'firstFieldId',
                    'lazyLoading'         => 1,
                    'supplySeveralFields' => 1,
                    'depends'             => array('depend#1', 'depend#2'),
                    'onFail'              => 'checkException',
                    'exceptionClass'      => '\RuntimeException',
                    'badReturnValue'      => 'emptyString',
                    'fallbackSourceId'    => 'firstFieldFallbackSourceId',
                    'preprocessor'        => array(
                        'class'  => '##this',
                        'method' => 'fooPreprocessor',
                        'args'   => array()
                    ),
                    'processor'           => array(
                        'class'  => '##this',
                        'method' => 'barProcessor',
                        'args'   => array()
                    ),
                    'preprocessors'       => array(),
                    'processors'          => array(),
                    'class'               => '\stdClass',
                    'method'              => 'someMethod',
                    'args'                => array('argument#1', 'argument#2')
                )
            ),
            $metadata->getDataSources()
        );
        $this->assertEquals(array(), $metadata->getProviders());
    }

    /**
     * @test
     */
    public function providerValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\Provider'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);

        $this->assertEquals(
            array(
                'providerField' => array(
                    'id'                  => 'providerFieldId',
                    'lazyLoading'         => 1,
                    'supplySeveralFields' => 1,
                    'depends'             => array('depend#1', 'depend#2'),
                    'onFail'              => 'checkException',
                    'exceptionClass'      => '\RuntimeException',
                    'badReturnValue'      => 'emptyString',
                    'fallbackSourceId'    => 'firstFieldFallbackSourceId',
                    'preprocessor'        => array(
                        'class'  => '##this',
                        'method' => 'fooPreprocessor',
                        'args'   => array()
                    ),
                    'processor'           => array(
                        'class'  => '##this',
                        'method' => 'barProcessor',
                        'args'   => array()
                    ),
                    'preprocessors'       => array(),
                    'processors'          => array(),
                    'class'               => '\stdClass',
                    'method'              => 'someMethod',
                    'args'                => array('argument#1', 'argument#2')
                )
            ),
            $metadata->getProviders()
        );
        $this->assertEquals(array(), $metadata->getDataSources());
    }



    /**
     * @test
     */
    public function excludeDefaultSourceValidateResult()
    {
        $metadata = $this->loadAnnotationMetadata(
            '\Kassko\DataMapperTest\ClassMetadataLoader\Fixture\Annotation\ExcludeDefaultSource'
        );

        $this->assertInstanceOf('\Kassko\DataMapper\ClassMetadata\ClassMetadata', $metadata);
        $this->assertEquals(array('excludeDefaultSourceField' => true), $metadata->getFieldsWithSourcesForbidden());
    }
}
