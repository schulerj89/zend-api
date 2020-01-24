<?php

namespace Client\Model;

class ClientNotes
{
    public function exchangeArray(array $data)
    {
        $this->clientid  = !empty($data['clientid']) ? $data['clientid'] : null;
        $this->notes 	 = !empty($data['notes']) ? $data['notes'] : null;
    }
}