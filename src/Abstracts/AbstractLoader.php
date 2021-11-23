<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Exceptions\RecordNotFoundException;
use CarloNicora\Minimalism\Factories\MinimalismFactories;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheBuilderFactoryInterface;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataInterface;
use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataLoaderInterface;
use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataObjectInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Services\Data\Data;
use CarloNicora\Minimalism\Services\Data\Interfaces\BuilderInterface;
use Exception;

abstract class AbstractLoader implements DataLoaderInterface
{
    /** @var DataInterface  */
    protected DataInterface $data;

    /** @var BuilderInterface|null  */
    protected ?BuilderInterface $builder=null;

    /** @var CacheInterface|null  */
    protected ?CacheInterface $cache=null;

    /** @var CacheBuilderFactoryInterface|null  */
    protected ?CacheBuilderFactoryInterface $cacheFactory=null;

    /** @var ServiceInterface|null  */
    protected ?ServiceInterface $defaultService=null;

    /**
     * UsersLoader constructor.
     * @param MinimalismFactories $factories
     * @throws Exception
     */
    public function __construct(
        protected MinimalismFactories $factories,
    )
    {
        /** @var Data $data */
        $data = $this->factories->getServiceFactory()->create(Data::class);

        $this->data = $data->getData();
        $this->builder = $data->getBuilder();

        $this->cache = $data->getCache();
        $this->cacheFactory = $data->getCacheFactory();
        $this->defaultService = $data->getDefaultService();
    }

    /**
     * @param array $response
     * @param string|null $recordType
     * @return array
     * @throws RecordNotFoundException
     */
    protected function returnSingleValue(
        array $response,
        ?string $recordType=null,
    ): array
    {
        if ($response === [] || $response === [[]]){
            throw new RecordNotFoundException(
                $recordType === null
                    ? 'Record Not found'
                    : $recordType . ' not found'
            );
        }

        return $response[0];
    }

    /**
     * @param array $recordset
     * @param string $objectType
     * @param int|null $levelOfChildrenToLoad
     * @return DataObjectInterface
     * @throws Exception
     */
    protected function returnSingleObject(
        array $recordset,
        string $objectType,
        ?int $levelOfChildrenToLoad=0,
    ): DataObjectInterface
    {
        if ($recordset === [] || $recordset === [[]]){
            throw new RecordNotFoundException('Record Not found');
        }

        return new $objectType(
            data: $recordset[0],
            levelOfChildrenToLoad: $levelOfChildrenToLoad,
        );
    }

    /**
     * @param array $recordset
     * @param string $objectType
     * @param int|null $levelOfChildrenToLoad
     * @return DataObjectInterface[]
     */
    protected function returnObjectArray(
        array $recordset,
        string $objectType,
        ?int $levelOfChildrenToLoad=0,
    ): array
    {
        $response = [];

        foreach ($recordset ?? [] as $record){
            $response[] = new $objectType(
                data: $record,
                levelOfChildrenToLoad: $levelOfChildrenToLoad,
            );
        }

        return $response;
    }
}