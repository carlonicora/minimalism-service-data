<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Cacher;
use CarloNicora\Minimalism\Services\Cacher\Exceptions\CacheNotFoundException;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Services\Data\Exceptions\ElementNotFoundException;
use CarloNicora\Minimalism\Services\MySQL\Interfaces\TableInterface;
use CarloNicora\Minimalism\Services\MySQL\MySQL;
use CarloNicora\Minimalism\Services\Redis\Exceptions\RedisConnectionException;
use CarloNicora\Minimalism\Services\Redis\Exceptions\RedisKeyNotFoundException;
use CarloNicora\Minimalism\Services\Redis\Redis;
use Exception;
use JsonException;
use ReflectionClass;
use ReflectionException;

abstract class AbstractWrapper
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var Redis  */
    protected Redis $redis;

    /** @var MySQL  */
    protected MySQL $database;

    /** @var Cacher  */
    protected Cacher $cacher;

    /** @var TableInterface  */
    protected TableInterface $table;

    /** @var string  */
    protected string $configurationTable;

    /**
     * abstractWrapper constructor.
     * @param ServicesFactory $services
     * @throws Exception
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;

        $this->redis = $services->service(Redis::class);
        $this->database = $services->service(MySQL::class);
        $this->cacher = $services->service(Cacher::class);

        $this->table = $this->database->create($this->configurationTable);
    }

    /**
     * @param string $data
     * @return array
     */
    final protected function buildFromJson(string $data) : array
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [];
        }
    }

    /**
     * @param array $data
     * @return string
     */
    final protected function buildFromArray(array $data) : string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR, 512);
        } catch (JsonException $e) {
            return '';
        }
    }

    /**
     * @param string $string
     * @return bool
     */
    private function isJSON(string $string): bool
    {
        try {
            json_decode($string, true, 512, JSON_THROW_ON_ERROR);
            return (json_last_error() === JSON_ERROR_NONE);
        } catch (JsonException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     * @return array|null
     */
    final protected function getFromRedis(string $key) :?array
    {
        if (!$this->cacher->useCaching()) {
            return null;
        }

        try {
            $recordOrKey = $this->redis->get($key);

            if (!$this->isJSON($recordOrKey)){
                return $this->getFromRedis($recordOrKey);
            }

            return $this->buildFromJson(
                $this->redis->get($key)
            );
        } catch (RedisConnectionException|RedisKeyNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $key
     * @param array $value
     */
    final protected function setInRedis(string $key, array $value) : void
    {
        if (!$this->cacher->useCaching()) {
            return;
        }

        try {
            $this->redis->set(
                $key,
                $this->buildFromArray($value)
            );
        } catch (RedisConnectionException $e) {}
    }

    /**
     * @param string $key
     * @param string $value
     */
    final protected function setLinkInRedis(string $key, string $value) : void
    {
        if (!$this->cacher->useCaching()) {
            return;
        }

        try {
            $this->redis->set(
                $key,
                $value
            );
        } catch (RedisConnectionException $e) {}
    }

    /**
     * @param string|null $cacheName
     * @param array|null $cacheParameters
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @return int|string|array|null
     * @throws ElementNotFoundException
     */
    public function getGeneric(?string $cacheName, ?array $cacheParameters, callable $dataLoader, array $dataLoaderParameters)
    {
        if ($this->cacher->useCaching()) {
            try {
                $cacheClass = new ReflectionClass($cacheName);
                /** @var CacheInterface $cache */
                $cache = $cacheClass->newInstanceArgs($cacheParameters);
            } catch (ReflectionException $e) {
                return null;
            }

            try {
                $response = $this->cacher->readArray($cache);
            } catch (CacheNotFoundException $e) {
                try {
                    $response = $this->getGenericFromDatabase($dataLoader, $dataLoaderParameters);
                    if (is_array($response)) {
                        $this->cacher->createArray($cache, $response);
                    } else {
                        $this->cacher->create($cache, (string)$response);
                    }
                } catch (Exception $e) {
                    throw new ElementNotFoundException($e->getMessage());
                }
            }
        } else {
            $response = $this->getGenericFromDatabase($dataLoader, $dataLoaderParameters);
        }

        return $response;
    }

    /**
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @return mixed
     */
    private function getGenericFromDatabase(callable $dataLoader, array $dataLoaderParameters)
    {
        return call_user_func_array($dataLoader, $dataLoaderParameters);
    }

    /**
     * @param string|null $cacheName
     * @param array|null $cacheParameters
     * @param array|null $childCacheParameters
     * @param string|null $recordId
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @return array|null
     * @throws ElementNotFoundException
     */
    public function getGenericWithChildren(?string $cacheName, ?array $cacheParameters, ?array $childCacheParameters , ?string $recordId, callable $dataLoader, array $dataLoaderParameters) : ?array
    {
        if ($this->cacher->useCaching()) {
            try {
                $cacheClass = new ReflectionClass($cacheName);
                /** @var cacheInterface $cache */
                $cache = $cacheClass->newInstanceArgs($cacheParameters);
            } catch (ReflectionException $e) {
                return null;
            }

            try {
                $response = $this->cacher->readArray($cache);
            } catch (cacheNotFoundException $e) {
                try {
                    $response = $this->getGenericListWithChildrenFromDatabase($dataLoader, $dataLoaderParameters);

                    $this->cacher->createArray($cache, $response);

                    foreach ($response as $record) {
                        /** @var cacheInterface $cache */
                        $childCacheParameters[] = $record[$recordId];
                        $cache = $cacheClass->newInstanceArgs($childCacheParameters);
                        $this->cacher->createArray($cache, $record);
                    }
                } catch (Exception $e) {
                    throw new elementNotFoundException($e->getMessage());
                }
            }
        } else {
            $response = $this->getGenericListWithChildrenFromDatabase($dataLoader, $dataLoaderParameters);
        }

        return $response;
    }

    /**
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @return array
     * @throws ElementNotFoundException
     */
    public function getGenericListWithChildrenFromDatabase(callable $dataLoader, array $dataLoaderParameters) : array
    {
        $response = call_user_func_array($dataLoader, $dataLoaderParameters);

        if ($response === null || count($response) === 0) {
            throw new ElementNotFoundException('');
        }

        return $response;
    }

    /**
     * @param int $adId
     * @return array
     * @throws ElementNotFoundException
     */
    public function loadFromId(int $adId) : array
    {
        return $this->getGeneric(
            null,
            null,
            [$this->table, 'loadFromId'],
            [$adId]
        );
    }
}