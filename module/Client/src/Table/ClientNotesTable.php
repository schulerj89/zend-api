<?php

namespace Client\Table;

use RuntimeException;
use Laminas\Db\TableGateway\TableGatewayInterface;

class ClientNotesTable
{
    private $tableGateway;

    public function __construct(TableGatewayInterface $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function fetchAll()
    {
        return $this->tableGateway->select();
    }

    public function getClientNotes($id)
    {
        $id = (int) $id;
        $rowset = $this->tableGateway->select(['clientid' => $id]);
        $row = $rowset->current();
        if (! $row) {
            throw new RuntimeException(sprintf(
                'Could not find row with identifier %d',
                $id
            ));
        }

        return $row;
    }

    public function saveClientNotes(ClientNotes $clientnotes)
    {
        $data = [
            'notes' => $clientnotes->notes,
            'clientid'  => $clientnotes->clientid,
        ];

        $id = (int) $clientnotes->id;

        if ($id === 0) {
            $this->tableGateway->insert($data);
            return;
        }

        try {
            $this->getClientNotes($id);
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf(
                'Cannot update album with identifier %d; does not exist',
                $id
            ));
        }

        $this->tableGateway->update($data, ['clientid' => $id]);
    }

    public function deleteClientNotes($id)
    {
        $this->tableGateway->delete(['clientid' => (int) $id]);
    }
}