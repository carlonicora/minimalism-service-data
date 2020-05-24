<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;

abstract class AbstactResourceLoaderFactory
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var array  */
    protected array $loaders=[];

    /**
     * AdsLoaders constructor.
     * @param ServicesFactory $services
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;
    }

    /**
     * @param string $resourceFactoryName
     * @return AbstractResourceFactory
     */
    final protected function getResourceFactory(string $resourceFactoryName) : AbstractResourceFactory
    {
        if (!array_key_exists($resourceFactoryName, $this->loaders)) {
            $this->loaders[$resourceFactoryName] = new $resourceFactoryName($this->services);
        }

        /** @var AbstractResourceFactory $response */
        $response = $this->loaders[$resourceFactoryName];

        return $response;
    }
}