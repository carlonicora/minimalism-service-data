<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Services\Exceptions\ServiceNotFoundException;
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
     * @throws ServiceNotFoundException
     */
    public function __construct(servicesFactory $services)
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->services = $services;

        $this->resourceBuilder = $services->service(ResourceBuilder::class);
        $this->cacher = $services->service(Cacher::class);
    }

    /**
     * @param int $id
     * @return ResourceObject|null
     * @throws ElementNotFoundException
     */
    abstract public function getById(int $id): resourceObject;

    /**
     * @param string $resourceBuilder
     * @param array $data
     * @param EncrypterInterface|null $encrypter
     * @param bool $addRelationships
     * @return resourceObject
     */
    final protected function generateResourceObject(
        string $resourceBuilder,
        array $data,
        ?EncrypterInterface $encrypter=null,
        bool $addRelationships=true
    ): resourceObject
    {
        $response = $this->resourceBuilder->create($resourceBuilder, $data, $encrypter)->buildResource();

        if ($addRelationships) {
            $this->addRelationships($response, $data);
        }

        $this->addLinks($response, $data);
        $this->addMeta($response, $data);

        return $response;
    }

    /**
     * @param resourceObject $object
     * @param array $data
     */
    protected function addLinks(resourceObject $object, array $data) : void
    {
    }

    /**
     * @param resourceObject $object
     * @param array $data
     */
    protected function addMeta(resourceObject $object, array $data) : void
    {
    }

    /**
     * @param resourceObject $object
     * @param array $data
     */
    protected function addRelationships(resourceObject $object, array $data) : void
    {
    }

    /**
     * @param array $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param EncrypterInterface|null $encrypter
     * @param bool $addRelationship
     * @return resourceObject
     * @throws Exception
     */
    protected function getSingle(array $cacheParameters, callable $dataMethod, array $dataParameters, ?EncrypterInterface $encrypter=null, bool $addRelationship=true): resourceObject
    {
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
            $data = call_user_func_array($dataMethod, $dataParameters);

            if ($data === null) {
                DataErrorEvent::DATA_NOT_FOUND($e)->throw();
            }

            $response = $this->generateResourceObject($this->resourceBuilderClass, $data, $encrypter, $addRelationship);

            if ($addRelationship) {
                $this->cacher->create($cache, serialize($response));
            }
        }

        return $response;
    }

    /**
     * @param array $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param EncrypterInterface|null $encrypter
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    protected function getList(array $cacheParameters, callable $dataMethod, array $dataParameters, ?EncrypterInterface $encrypter=null, bool $addRelationship=true): array
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
            $data = call_user_func_array($dataMethod, $dataParameters);

            if ($data === null) {
                DataErrorEvent::DATA_NOT_FOUND($e)->throw();
            }

            $response = [];

            foreach ($data as $record){
                $response[] = $this->generateResourceObject($this->resourceBuilderClass, $record, $encrypter, $addRelationship);
            }

            if ($addRelationship) {
                $this->cacher->create($cache, serialize($response));
            }
        }

        return $response;
    }
}