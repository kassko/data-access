<?php

namespace Kassko\DataMapper\Annotation;

/**
* @Annotation
* @Target("PROPERTY")
*/
final class DataSource
{
    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    public $method;

    /**
     * @var array
     */
    public $args = [];

    /**
     * Loading strategy to use for this provider.
     *
     * @var bool
     */
    public $lazyLoading = false;

    /**
     * @var string
     */
    public $objectClass = null;
}