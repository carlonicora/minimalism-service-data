<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;

interface LoaderInterface
{
    /**
     * @return ServiceInterface|null
     */
    public function getDefaultService(): ?ServiceInterface;

    /**
     * @return CacheInterface|null
     */
    public function getCacher(): ?CacheInterface;
}