<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;

class AbstractWrapperFactory
{
    /** @var ServicesFactory  */
    private ServicesFactory $services;

    /** @var array  */
    private array $wrappers=[];

    /**
     * WrapperFactory constructor.
     * @param ServicesFactory $services
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;
    }

    final protected function getWrapper(string $wrapperName) : AbstractWrapper
    {
        if (!array_key_exists($wrapperName, $this->wrappers)) {
            $this->wrappers[$wrapperName] = new $wrapperName($this->services);
        }

        /** @var AbstractWrapper $response */
        $response = $this->wrappers[$wrapperName];

        return $response;
    }
}