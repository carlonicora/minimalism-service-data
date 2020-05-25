<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Services\Cacher\Cacher;
use CarloNicora\Minimalism\Services\Cacher\Exceptions\CacheNotFoundException;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheFactoryInterface;
use CarloNicora\Minimalism\Services\Data\Events\DataErrorEvent;
use CarloNicora\Minimalism\Services\Data\Exceptions\ElementNotFoundException;
use CarloNicora\Minimalism\Services\Data\Interfaces\DataCallerInterface;
use CarloNicora\Minimalism\Services\MySQL\Interfaces\TableInterface;
use CarloNicora\Minimalism\Services\ResourceBuilder\ResourceBuilder;
use Exception;

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

    /** @var string|null  */
    protected ?string $configurationCacheName=null;

    /** @var string  */
    protected string $configurationMicroserviceName;

    /** @var string  */
    protected string $configurationResourceBuilderClassName;

    /** @var TableInterface  */
    protected TableInterface $configurationTable;

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
     * @param CacheFactoryInterface|null $resourceCache
     * @param DataCallerInterface $dataCaller
     * @param bool $addRelationship
     * @return ResourceObject
     * @throws Exception
     */
    final protected function getSingle(
        ?CacheFactoryInterface $resourceCache,
        DataCallerInterface $dataCaller,
        bool $addRelationship=true
    ): ResourceObject
    {
        $response = null;

        if ($resourceCache !== null && $this->serviceCacher->useCaching() && ($cache = $resourceCache->generateCache()) !== null) {
            try {
                /** @noinspection UnserializeExploitsInspection */
                $response = unserialize($this->serviceCacher->read($cache));
            } catch (CacheNotFoundException $e) {
                $response = $this->getSingleFromDatabase($dataCaller, $addRelationship);

                $this->serviceCacher->create($cache, serialize($response));
            }
        } else {
            $response = $this->getSingleFromDatabase($dataCaller, $addRelationship);
        }

        return $response;
    }

    /**
     * @param CacheFactoryInterface|null $resourceCache
     * @param DataCallerInterface $dataCaller
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    final protected function getList(
        ?CacheFactoryInterface $resourceCache,
        DataCallerInterface $dataCaller,
        bool $addRelationship=true
    ): array
    {
        $response = null;

        if ($resourceCache !== null && $this->serviceCacher->useCaching() && ($cache = $resourceCache->generateCache()) !== null) {
            try {
                /** @noinspection UnserializeExploitsInspection */
                $response = unserialize($this->serviceCacher->read($cache));
            } catch (CacheNotFoundException $e) {
                $response = $this->getListFromDatabase($dataCaller, $addRelationship);

                $this->serviceCacher->create($cache, serialize($response));
            }
        } else {
            $response = $this->getListFromDatabase($dataCaller, $addRelationship);
        }

        return $response;
    }

    /**
     * @param DataCallerInterface $dataCaller
     * @param bool $addRelationship
     * @return ResourceObject
     * @throws Exception
     */
    private function getSingleFromDatabase(
        DataCallerInterface $dataCaller,
        bool $addRelationship=true
    ) : ResourceObject
    {
        if (($data = $dataCaller->execute()) === null) {
            DataErrorEvent::DATA_NOT_FOUND()->throw();
        }

        return $this->generateResourceObject($this->configurationResourceBuilderClassName, $data, $addRelationship);
    }

    /**
     * @param DataCallerInterface $dataCaller
     * @param bool $addRelationship
     * @return array
     * @throws Exception
     */
    private function getListFromDatabase(
        DataCallerInterface $dataCaller,
        bool $addRelationship=true
    ) : array
    {
        if (($data = $dataCaller->execute()) === null) {
            DataErrorEvent::DATA_NOT_FOUND()->throw();
        }

        $response = [];

        foreach ($data as $record){
            $response[] = $this->generateResourceObject($this->configurationResourceBuilderClassName, $record, $addRelationship);
        }

        return $response;
    }
}