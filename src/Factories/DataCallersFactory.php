<?php
namespace CarloNicora\Minimalism\Services\Data\Factories;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheFactoryInterface;
use CarloNicora\Minimalism\Services\Data\Facade\DataCallerFacade;
use CarloNicora\Minimalism\Services\MySQL\MySQL;
use Exception;

class DataCallersFactory
{
    /** @var ServicesFactory  */
    private ServicesFactory $services;

    /** @var MySQL  */
    private MySQL $mysql;

    /**
     * DataCallersFactory constructor.
     * @param ServicesFactory $services
     * @throws Exception
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;
        $this->mysql = $services->service(MySQL::class);
    }

    /**
     * @param string $tableName
     * @param string $functionName
     * @param array $functionParameters
     * @param CacheFactoryInterface|null $dataCache
     * @return DataCallerFacade
     * @throws Exception
     */
    public function create(
        string $tableName,
        string $functionName,
        array $functionParameters = [],
        CacheFactoryInterface $dataCache = null
    ) : DataCallerFacade
    {
        $table = $this->mysql->create($tableName);
        return new DataCallerFacade(
            $this->services,
            $table,
            $functionName,
            $functionParameters,
            $dataCache
        );
    }
}