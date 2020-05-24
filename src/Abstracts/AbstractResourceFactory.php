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

abstract class AbstractResourceFactory
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var ResourceBuilder  */
    private ResourceBuilder $serviceResourceBuilder;

    /** @var EncrypterInterface|null  */
    private ?EncrypterInterface $serviceEncrypter;

    /** @var Cacher  */
    private Cacher $serviceCacher;

    /** @var string  */
    protected string $configurationCacheName;

    /** @var string  */
    protected string $configurationMicroserviceName;

    /** @var string  */
    protected string $configurationResourceBuilderClassName;

    /**
     * campaignLoader constructor.
     * @param ServicesFactory $services
     * @param EncrypterInterface|null $encrypter
     */
    public function __construct(servicesFactory $services, ?EncrypterInterface $encrypter=null)
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->services = $services;

        $this->serviceResourceBuilder = $services->service(ResourceBuilder::class);
        $this->serviceCacher = $services->service(Cacher::class);
        $this->serviceEncrypter = $encrypter;
    }

    /**
     * @param int $id
     * @return ResourceObject|null
     * @throws ElementNotFoundException
     */
    abstract protected function getById(int $id): ResourceObject;

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
        $response = $this->serviceResourceBuilder->create($resourceBuilder, $data, $this->serviceEncrypter)->buildResource();

        if ($addRelationships) {
            $this->addRelationships($response, $data);
        }

        $this->addLinks($response, $data);
        $this->addMeta($response, $data);

        return $response;
    }

    /**
     * @param array|null $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return ResourceObject
     * @throws Exception
     */
    final protected function getSingle(?array $cacheParameters, callable $dataMethod, array $dataParameters, bool $addRelationship=true): ResourceObject
    {
        if ($this->serviceCacher->useCaching()) {
            $response = null;
            $cache = null;

            try {
                $cacheClass = new ReflectionClass($this->configurationCacheName);

                /** @var CacheInterface $cache */
                $cache = $cacheClass->newInstanceArgs($cacheParameters);
            } catch (ReflectionException $e) {
                $this->services->logger()
                    ->error()
                    ->log(
                        DataErrorEvent::CACHE_CLASS_NOT_FOUND($this->configurationCacheName, $e)
                    )
                    ->throw();
            }

            try {
                /** @noinspection UnserializeExploitsInspection */
                $response = unserialize($this->serviceCacher->read($cache));
            } catch (CacheNotFoundException $e) {

                $this->getSingleFromDatabase($dataMethod, $dataParameters, $addRelationship);

                $this->serviceCacher->create($cache, serialize($response));
            }
        } else {
            $response = $this->getSingleFromDatabase($dataMethod, $dataParameters, $addRelationship);
        }

        return $response;
    }

    /**
     * @param array|null $cacheParameters
     * @param callable $dataMethod
     * @param array $dataParameters
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    final protected function getList(?array $cacheParameters, callable $dataMethod, array $dataParameters, bool $addRelationship=true): array
    {
        if ($this->serviceCacher->useCaching()) {
            $response = null;
            $cache = null;

            try {
                $cacheClass = new ReflectionClass($this->configurationCacheName);

                /** @var abstractCache $cache */
                $cache = $cacheClass->newInstanceArgs($cacheParameters);
            } catch (ReflectionException $e) {
                $this->services->logger()
                    ->error()
                    ->log(
                        DataErrorEvent::CACHE_CLASS_NOT_FOUND($this->configurationCacheName, $e)
                    )
                    ->throw();
            }

            try {
                /** @noinspection UnserializeExploitsInspection */
                $response = unserialize($this->serviceCacher->read($cache));
            } catch (CacheNotFoundException $e) {
                $response = $this->getListFromDatabase($dataMethod, $dataParameters, $addRelationship);

                $this->serviceCacher->create($cache, serialize($response));
            }
        } else {
            $response = $this->getListFromDatabase($dataMethod, $dataParameters, $addRelationship);
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

        return $this->generateResourceObject($this->serviceResourceBuilder, $data, $addRelationship);
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
            $response[] = $this->generateResourceObject($this->serviceResourceBuilder, $record, $addRelationship);
        }

        return $response;
    }
}