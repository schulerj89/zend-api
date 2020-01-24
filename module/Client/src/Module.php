<?php

namespace Client;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;

class Module implements ConfigProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                Table\ClientNotesTable::class => function($container) {
                    $tableGateway = $container->get(Table\ClientNotesGateway::class);
                    return new Table\ClientNotesTable($tableGateway);
                },
                Table\ClientNotesGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\ClientNotes());
                    return new TableGateway('client_notes', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\ClientController::class => function($container) {
                    return new Controller\ClientController(
                        $container->get(Table\ClientNotesTable::class)
                    );
                },
            ],
        ];
    }
}