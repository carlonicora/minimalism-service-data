<?php
namespace CarloNicora\Minimalism\Services\Data\Abstracts;

use CarloNicora\Minimalism\Services\MySQL\MySQL;

abstract class AbstractTableFactory
{
    /** @var MySQL  */
    protected MySQL $database;

    /** @var array  */
    protected array $tables=[];

    /**
     * AdsDb constructor.
     * @param MySQL $databse
     */
    public function __construct(MySQL $databse)
    {
        $this->database = $databse;
    }
}