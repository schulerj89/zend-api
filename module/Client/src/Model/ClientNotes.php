<?php

namespace Client\Model;

class ClientNotes
{
    public function exchangeArray(array $data)
    {
    	foreach($data as $key => $_data) {
        	$this->{$key}  = !empty($data[$key]) ? $data[$key] : null;
    	}
    }
}