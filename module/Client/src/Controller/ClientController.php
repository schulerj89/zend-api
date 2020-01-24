<?php

namespace Client\Controller;

use Client\Table\ClientNotesTable;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;

class ClientController extends AbstractActionController
{
	// Add this property:
    private $clientNotesTable;

    // Add this constructor:
    public function __construct(ClientNotesTable $clientNotesTable)
    {
        $this->clientNotesTable = $clientNotesTable;
    }

    public function indexAction()
    {
    	$notes = array("error" => "Unknown controller or action");
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode($notes));
        return $response;
    }

    public function notesAction()
    {
    	$id = (int) $this->params()->fromRoute('clientid', 0);

    	if (0 === $id) {
            return $this->getResponse()->setStatusCode(404);
        }

    	$notes = $this->clientNotesTable->getClientNotes($id);
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode($notes));
        return $response;
    }

    public function cancelClientAction()
    {
    	$id = (int) $this->params()->fromRoute('clientid', 0);

    	$response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode(array($id)));
        return $response;
    }

    public function contactLogAction()
    {
    	$response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode(array('contact log')));
        return $response;
    }

    public function checkLoginAction()
    {
    	$id = (int) $this->params()->fromRoute('divisionid', 0);

    	$response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode(array($id)));
        return $response;
    }

    public function latestClientContactAction()
    {
    	$id = (int) $this->params()->fromRoute('clientid', 0);

    	$response = $this->getResponse();
        $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
        $response->setContent(json_encode(array($id)));
        return $response;
    }
}