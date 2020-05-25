<?php
namespace CarloNicora\Minimalism\Services\Data\Factories;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheFactoryInterface;
use CarloNicora\Minimalism\Services\Data\Facade\DataCallerFacade;
use CarloNicora\Minimalism\Services\MySQL\Interfaces\TableInterface;
use Exception;

class DataCallersFactory
{
    /** @var ServicesFactory  */
    private ServicesFactory $services;

    /**
     * DataCallersFactory constructor.
     * @param ServicesFactory $services
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;
    }

    /**
     * @param TableInterface $table
     * @param string $functionName
     * @param array $functionParameters
     * @param CacheFactoryInterface|null $dataCache
     * @return DataCallerFacade
     * @throws Exception
     */
    public function create(
        TableInterface $table,
        string $functionName,
        array $functionParameters = [],
        CacheFactoryInterface $dataCache = null
    ) : DataCallerFacade
    {
        return new DataCallerFacade(
            $this->services,
            $table,
            $functionName,
            $functionParameters,
            $dataCache
        );
    }
}