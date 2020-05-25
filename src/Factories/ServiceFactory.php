<?php
namespace CarloNicora\Minimalism\Services\Data\Factories;

use CarloNicora\Minimalism\Core\Services\Abstracts\AbstractServiceFactory;
use CarloNicora\Minimalism\Core\Services\Exceptions\ConfigurationException;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Data\Configurations\DataConfigurations;
use CarloNicora\Minimalism\Services\Data\Data;

class ServiceFactory extends AbstractServiceFactory {
    /**
     * serviceFactory constructor.
     * @param ServicesFactory $services
     * @throws ConfigurationException
     */
    public function __construct(ServicesFactory $services) {
        $this->configData = new DataConfigurations();

        parent::__construct($services);
    }

    /**
     * @param ServicesFactory $services
     * @return Data
     */
    public function create(ServicesFactory $services) : Data {
        return new Data($this->configData, $services);
    }
}