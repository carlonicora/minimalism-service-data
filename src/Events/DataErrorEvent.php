<?php
namespace CarloNicora\Minimalism\Services\Data\Events;

use CarloNicora\Minimalism\Core\Events\Abstracts\AbstractErrorEvent;
use CarloNicora\Minimalism\Core\Events\Interfaces\EventInterface;
use CarloNicora\Minimalism\Core\Modules\Interfaces\ResponseInterface;
use Exception;

class DataErrorEvent extends AbstractErrorEvent
{
    /** @var string  */
    protected string $serviceName='data';

    public static function CACHE_CLASS_NOT_FOUND(string $className, Exception $e) : EventInterface
    {
        return new self(
            1,
            ResponseInterface::HTTP_STATUS_500,
            'Cache class not found: %s',
            [$className],
            $e
        );
    }

    public static function DATA_NOT_FOUND(Exception $e) : EventInterface
    {
        return new self(
            2,
            ResponseInterface::HTTP_STATUS_500,
            'Data not found',
            [],
            $e
        );
    }
}