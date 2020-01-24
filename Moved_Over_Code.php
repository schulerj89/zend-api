<?php

case 'client':
	// require_once 'classes/Client.php';
	require_once 'controllers/ClientController.php';
	require_once 'classes/ContactLog.php';
    require_once 'classes/ClientReferral.php';

	$controller = new ClientController($db, $params['id']);

	switch ($verb) {
		case "notes":
			$client = new Client();
			$client->load($params['id'], $db);
			$controller->getNotes();
			break;
		case "contact_log":
			$client = new Client();
			$client->load($id, $db);
			$controller->contact_log($client, $db);
			break;
		case 'cancel_client':
			$params = json_decode(file_get_contents('php://input') ,true);
	        if ($params == null) {
	            $params = $_POST;
	        }

	        define(OPRID, $params['oprId']);

			$client = new Client();
			$client->load($params['clientid'], $db);
			$controller->cancelClientAccount($params, $client);
			break;
		case 'latest_client_contact':
            $client = new ClientController();
            $latest = $controller->get_latest_contact($params['clientid']);
            echo json_encode($latest);
            break;
		case "check_login":
			parse_str(file_get_contents('php://input'), $params);
			$client = new Client();

			if(isset($id) && (!isset($params['divisionid']) || $params['divisionid'] == '' || $params['divisionid'] == 0)) {
				$a                    = "SELECT divisionid from client where (web_username = '" . $id . "' OR clientid = '" . $id . "' )";
				$ares                 = $db->query($a);
				$ares2                = reset($ares);
				$params['divisionid'] = $ares2['divisionid'];
			}

			$id = urldecode($id);
			$q  = "SELECT clientid FROM client WHERE (web_username = '" . $id . "' OR clientid = '" . $id . "' )
				AND divisionid = " . $params['divisionid'] . "
				AND (client_status IN ('ATTAPPROVED','CAGRIN','ATTWAIT','ATTREJECT','LWREJ','PFPMIN',
					'PFPNW','C','TS','A','AMI','ANW','NWM','AFP','LXPENDC','PS','PH','PR','CC')
					OR (client_status = 'G' AND client_status_dt >= DATE(DATE_SUB(NOW(), INTERVAL 30 DAY)) ) )";

			$res = $db->query($q);
			$cl  = reset($res);

			if (!$cl) {
				#            mail('mramsey@landmarktx.com','failed login step 1',"Username: $id DivisionID: ". $params['divisionid'] . var_export($params, true));
				Controller::sendHeader('HTTP/1.1 400 Bad Request');
				$errors = array('errors' => "Invalid Login");

				echo json_encode($errors);

				return;
				} else {
					$client->load($cl['clientid'], $db);

					// we don't need to send junk back attributes back to the client, remove db
					if(isset($client->db))
	                    unset($client->db);

					$controller->login($client, $db);
				}
				break;
	}