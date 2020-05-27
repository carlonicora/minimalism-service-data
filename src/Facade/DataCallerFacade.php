<?php /** @noinspection PhpDocRedundantThrowsInspection */

namespace CarloNicora\Minimalism\Services\Data\Facade;

use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Cacher\Cacher;
use CarloNicora\Minimalism\Services\Cacher\Exceptions\CacheNotFoundException;
use CarloNicora\Minimalism\Services\Cacher\Interfaces\CacheFactoryInterface;
use CarloNicora\Minimalism\Services\Data\Interfaces\DataCallerInterface;
use CarloNicora\Minimalism\Services\MySQL\Exceptions\DbRecordNotFoundException;
use CarloNicora\Minimalism\Services\MySQL\Interfaces\TableInterface;
use CarloNicora\Minimalism\Services\MySQL\MySQL;
use CarloNicora\Minimalism\Services\Redis\Redis;
use Exception;

class DataCallerFacade implements DataCallerInterface
{
    /** @var ServicesFactory  */
    protected ServicesFactory $services;

    /** @var TableInterface  */
    private TableInterface $table;

    /** @var string  */
    public string $functionName;

    /** @var array  */
    public array $functionParameters;

    /** @var Redis  */
    protected Redis $redis;

    /** @var MySQL  */
    protected MySQL $database;

    /** @var Cacher  */
    protected Cacher $cacher;

    /** @var CacheFactoryInterface|null  */
    public ?CacheFactoryInterface $dataCache;

    /**
     * DataCallerFacade constructor.
     * @param ServicesFactory $services
     * @param TableInterface $table
     * @param string $functionName
     * @param array $functionParameters
     * @param CacheFactoryInterface $dataCache
     * @throws Exception
     */
    public function __construct(
        ServicesFactory $services,
        TableInterface $table,
        string $functionName,
        array $functionParameters = [],
        CacheFactoryInterface $dataCache = null
    ) {
        $this->services = $services;
        $this->table = $table;
        $this->functionName = $functionName;
        $this->functionParameters = $functionParameters;
        $this->dataCache = $dataCache;

        $this->redis = $services->service(Redis::class);
        $this->database = $services->service(MySQL::class);
        $this->cacher = $services->service(Cacher::class);
    }

    /**
     * @return array
     * @throws DbRecordNotFoundException
     */
    public function getSingle() : array
    {
        if ($this->dataCache !== null && $this->cacher->useCaching() && ($cache = $this->dataCache->generateCache()) !== null) {
            try {
                $response = $this->cacher->readArray($cache);
            } catch (CacheNotFoundException $e) {
                $response = $this->table->{$this->functionName}(...$this->functionParameters);
                if (is_array($response)) {
                    $this->cacher->createArray($cache, $response);
                } else {
                    $this->cacher->create($cache, (string)$response);
                }
            }
        } else {
            $response = $this->table->{$this->functionName}(...$this->functionParameters);
        }

        return $response;
    }

    /**
     * @return array|null
     */
    public function getList() : ?array
    {
        if ($this->dataCache !== null && $this->cacher->useCaching() && ($cache = $this->dataCache->generateCache()) !== null) {
            try {
                $response = $this->cacher->readArray($cache);
            } catch (cacheNotFoundException $e) {
                $response = $this->table->{$this->functionName}(...$this->functionParameters);

                if ($response !== null) {
                    $this->cacher->createArray($cache, $response);

                    foreach ($response ?? [] as $record) {
                        $primaryKey = $this->table->getPrimaryKey();

                        if ($primaryKey !== null && count($primaryKey) === 1) {
                            $recordId = array_keys($primaryKey)[0];
                            $childCacheParameters = [$record[$recordId]];

                            if (($subCache = $this->dataCache->generateGranularCache($childCacheParameters)) !== null) {
                                $this->cacher->createArray($subCache, $record);
                            }
                        }
                    }
                }
            }
        } else {
            $response = $this->table->{$this->functionName}(...$this->functionParameters);
        }

        return $response;
    }

    /**
     * @return int
     */
    public function getCount() : int
    {
        if ($this->dataCache !== null && $this->cacher->useCaching() && ($cache = $this->dataCache->generateCache()) !== null) {
            try {
                $response = (int)$this->cacher->read($cache);
            } catch (cacheNotFoundException $e) {
                $response = (int)$this->table->{$this->functionName}(...$this->functionParameters);

                if ($response !== null) {
                    $this->cacher->create($cache, $response);
                }
            }
        } else {
            $response = (int)$this->table->{$this->functionName}(...$this->functionParameters);
        }

        return $response;
    }
}