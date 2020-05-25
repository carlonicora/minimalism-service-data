<?php
namespace CarloNicora\Minimalism\Services\Data\Events;

use CarloNicora\Minimalism\Core\Events\Abstracts\AbstractErrorEvent;
use CarloNicora\Minimalism\Core\Events\Interfaces\EventInterface;
use CarloNicora\Minimalism\Core\Modules\Interfaces\ResponseInterface;

class DataErrorEvent extends AbstractErrorEvent
{
    /** @var string  */
    protected string $serviceName='data';

    public static function DATA_NOT_FOUND() : EventInterface
    {
        return new self(
            2,
            ResponseInterface::HTTP_STATUS_500,
            'Data not found'
        );
    }
}