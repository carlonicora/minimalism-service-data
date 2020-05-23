<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Core\Services\Exceptions\ServiceNotFoundException;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Cacher;
use CarloNicora\Minimalism\Services\Cacher\Exceptions\CacheNotFoundException;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Services\Data\Exceptions\ElementNotFoundException;
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

    /**
     * abstractWrapper constructor.
     * @param ServicesFactory $services
     * @throws ServiceNotFoundException
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;

        $this->redis = $services->service(Redis::class);
        $this->database = $services->service(MySQL::class);
        $this->cacher = $services->service(Cacher::class);
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
        try {
            $this->redis->set(
                $key,
                $value
            );
        } catch (RedisConnectionException $e) {}
    }

    /**
     * @param string $cacheName
     * @param array $cacheParameters
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @return int|string|array|null
     * @throws ElementNotFoundException
     */
    public function getGeneric(string $cacheName, array $cacheParameters, callable $dataLoader, array $dataLoaderParameters)
    {
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
                $response = call_user_func_array($dataLoader, $dataLoaderParameters);
                if (is_array($response)){
                    $this->cacher->createArray($cache, $response);
                } else {
                    $this->cacher->create($cache, (string)$response);
                }
            } catch (Exception $e) {
                throw new ElementNotFoundException($e->getMessage());
            }
        }

        return $response;
    }

    /**
     * @param string $cacheName
     * @param array $cacheParameters
     * @param callable $dataLoader
     * @param array $dataLoaderParameters
     * @param array $childCacheParameters
     * @param string $recordId
     * @return array|null
     * @throws ElementNotFoundException
     */
    public function getGenericWithChildren(string $cacheName, array $cacheParameters, callable $dataLoader, array $dataLoaderParameters, array $childCacheParameters , string $recordId) : ?array
    {
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
                $response = call_user_func_array($dataLoader, $dataLoaderParameters);

                if ($response === null || count($response) === 0){
                    throw new ElementNotFoundException('');
                }

                $this->cacher->createArray($cache, $response);

                foreach ($response as $record){
                    /** @var cacheInterface $cache */
                    $childCacheParameters[] = $record[$recordId];
                    $cache = $cacheClass->newInstanceArgs($childCacheParameters);
                    $this->cacher->createArray($cache, $record);
                }
            } catch (Exception $e) {
                throw new elementNotFoundException($e->getMessage());
            }
        }

        return $response;
    }
}