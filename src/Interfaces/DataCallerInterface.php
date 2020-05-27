<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheFactoryInterface;
use CarloNicora\Minimalism\Services\MySQL\Exceptions\DbRecordNotFoundException;
use CarloNicora\Minimalism\Services\MySQL\Interfaces\TableInterface;

interface DataCallerInterface
{
    /**
     * DataCallerInterface constructor.
     * @param ServicesFactory $services
     * @param TableInterface $table
     * @param string $functionName
     * @param array $functionParameters
     * @param CacheFactoryInterface $dataInterface
     */
    public function __construct(
        ServicesFactory $services,
        TableInterface $table,
        string $functionName,
        array $functionParameters = [],
        CacheFactoryInterface $dataInterface = null
    );

    /**
     * @return array
     * @throws DbRecordNotFoundException
     */
    public function getSingle() : array;

    /**
     * @return array|null
     */
    public function getList() : ?array;

    /**
     * @return int
     */
    public function getCount() : int;
}