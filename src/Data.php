<?php
namespace CarloNicora\Minimalism\Services\Data;

use CarloNicora\Minimalism\Core\Services\Abstracts\AbstractService;
use CarloNicora\Minimalism\Services\Data\Factories\DataCallersFactory;

class Data extends AbstractService {
    /** @var DataCallersFactory|null  */
    private ?DataCallersFactory $dataCallersFactory=null;

    /**
     * @return DataCallersFactory
     */
    public function dataCallers() : DataCallersFactory
    {
        if ($this->dataCallersFactory === null) {
            $this->dataCallersFactory = new DataCallersFactory($this->services);
        }

        return $this->dataCallersFactory;
    }
}