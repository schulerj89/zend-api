<?php

namespace Client;

use Laminas\Router\Http\Segment;;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    // The following section is new and should be added to your file:
    'router' => [
        'routes' => [
            'client' => [
                'type'    => Segment::class,
                'options' => [
                    'route' => '/client/:action/clientid/:clientid[/divisionid/:divisionid]',
                    'constraints' => [
                        'action'        => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'clientid'      => '[0-9]+',
                        'divisionid'    => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\ClientController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
        ],
    ],


    'view_manager' => [
        'template_path_stack' => [
            'album' => __DIR__ . '/../view',
        ],
    ],
];