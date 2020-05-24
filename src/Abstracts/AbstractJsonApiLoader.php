<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Services\Cacher\Abstracts\AbstractCache;
use CarloNicora\Minimalism\Services\Cacher\Cacher;
use CarloNicora\Minimalism\Services\Cacher\Exceptions\CacheNotFoundException;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Services\Data\Events\DataErrorEvent;
use CarloNicora\Minimalism\Services\Data\Exceptions\ElementNotFoundException;
use CarloNicora\Minimalism\Services\ResourceBuilder\ResourceBuilder;
use Exception;
use ReflectionClass;
use ReflectionException;

abstract class AbstractJsonApiLoader
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var ResourceBuilder  */
    protected ResourceBuilder $resourceBuilder;

    /** @var EncrypterInterface|null  */
    protected ?EncrypterInterface $encrypter=null;

    /** @var Cacher  */
    protected Cacher $cacher;

    /** @var string  */
    protected string $cache;

    /** @var string  */
    protected string $name;

    /** @var string  */
    protected string $resourceBuilderClass;

    /**
     * campaignLoader constructor.
     * @param ServicesFactory $services
     * @param EncrypterInterface|null $encrypter
     */
    public function __construct(servicesFactory $services, ?EncrypterInterface $encrypter=null)
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->services = $services;

        $this->resourceBuilder = $services->service(ResourceBuilder::class);
        $this->cacher = $services->service(Cacher::class);
        $this->encrypter = $encrypter;
    }

    /**
     * @param int $id
     * @return ResourceObject|null
     * @throws ElementNotFoundException
     */
    abstract public function getById(int $id): ResourceObject;

    /**
     * @param string $resourceBuilder
     * @param array $data
     * @param bool $addRelationships
     * @return ResourceObject
     */
    final protected function generateResourceObject(
        string $resourceBuilder,
        array $data,
        bool $addRelationships=true
    ): ResourceObject
    {
        $response = $this->resourceBuilder->create($resourceBuilder, $data, $this->encrypter)->buildResource();

        if ($addRelationships) {
            $this->addRelationships($response, $data);
        }

        $this->addLinks($response, $data);
        $this->addMeta($response, $data);

        return $response;
    }

    /**
     * @param ResourceObject $object
     * @param array $data
     */
    protected function addLinks(ResourceObject $object, array $data) : void
    {
    }

    /**
     * @param ResourceObject $object
     * @param array $data
     */
    protected function addMeta(ResourceObject $object, array $data) : void
    {
    }

    /**
     * @param ResourceObject $object
     * @param array $data
     */
    protected function addRelationships(ResourceObject $object, array $data) : void
    {
    }

    /**
     * @param array|null $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return ResourceObject
     * @throws Exception
     */
    protected function getSingle(?array $cacheParameters, callable $dataMethod, array $dataParameters, bool $addRelationship=true): ResourceObject
    {
        if ($this->cacher->useCaching()) {
            $response = null;
            $cache = null;

            try {
                $cacheClass = new ReflectionClass($this->cache);

                /** @var CacheInterface $cache */
                $cache = $cacheClass->newInstanceArgs($cacheParameters);
            } catch (ReflectionException $e) {
                $this->services->logger()
                    ->error()
                    ->log(
                        DataErrorEvent::CACHE_CLASS_NOT_FOUND($this->cache, $e)
                    )
                    ->throw();
            }

            try {
                /** @noinspection UnserializeExploitsInspection */
                $response = unserialize($this->cacher->read($cache));
            } catch (CacheNotFoundException $e) {

                $this->getSingleFromDatabase($dataMethod, $dataParameters, $addRelationship);

                $this->cacher->create($cache, serialize($response));
            }
        } else {
            $response = $this->getSingleFromDatabase($dataMethod, $dataParameters, $addRelationship);
        }

        return $response;
    }

    /**
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return ResourceObject
     * @throws Exception
     */
    private function getSingleFromDatabase(callable $dataMethod, array $dataParameters, bool $addRelationship=true) : ResourceObject
    {
        $data = call_user_func_array($dataMethod, $dataParameters);

        if ($data === null) {
            DataErrorEvent::DATA_NOT_FOUND()->throw();
        }

        return $this->generateResourceObject($this->resourceBuilderClass, $data, $addRelationship);
    }

    /**
     * @param array|null $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    protected function getList(?array $cacheParameters, callable $dataMethod, array $dataParameters, bool $addRelationship=true): array
    {
        $response = null;
        $cache = null;

        try {
            $cacheClass = new ReflectionClass($this->cache);

            /** @var abstractCache $cache */
            $cache = $cacheClass->newInstanceArgs($cacheParameters);
        } catch (ReflectionException $e) {
            $this->services->logger()
                ->error()
                ->log(
                    DataErrorEvent::CACHE_CLASS_NOT_FOUND($this->cache, $e)
                )
                ->throw();
        }

        try {
            /** @noinspection UnserializeExploitsInspection */
            $response = unserialize($this->cacher->read($cache));
        } catch (CacheNotFoundException $e) {
            $response = $this->getListFromDatabase($dataMethod, $dataParameters, $addRelationship);

            $this->cacher->create($cache, serialize($response));
        }

        return $response;
    }

    /**
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    private function getListFromDatabase(callable $dataMethod, array $dataParameters, bool $addRelationship=true): array
    {
        $data = call_user_func_array($dataMethod, $dataParameters);

        if ($data === null) {
            DataErrorEvent::DATA_NOT_FOUND()->throw();
        }

        $response = [];

        foreach ($data as $record){
            $response[] = $this->generateResourceObject($this->resourceBuilderClass, $record, $addRelationship);
        }

        return $response;
    }
}