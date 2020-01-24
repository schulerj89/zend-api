<?php
#echo "OK";
#echo $_SERVER['SERVER_NAME'];
#if ($_SERVER['SERVER_NAME'] != 'calapi' && $_SERVER['SERVER_NAME'] != '10.10.1.52') {
#echo 'yes';
#} else {
#echo 'no';
#}
#print_r($_SERVER);
#exit;

/**
 * API bootstrapper.
 *
 * Direct the request to the appropriate controller.
 * @author Omni Adams <patrick.adams@creditanswers.com>
 * @copyright 2010 CreditAnswers, LLC.
 * @package api
 * @subpackage controllers
 */

#phpinfo();
#die;
#ini_set('display_errors', 1);  ini_set('display_startup_errors', 1);  error_reporting(E_ALL);
#ini_set('display_errors', 0);

/**
 * Requires.
 */
#require_once('mysql_connect/MySQL_Definitions.php');
// Include the object
#require_once('mysql_connect/MySQL.php');
// Include the mysql_* functions
#require_once('mysql_connect/MySQL_Functions.php');
require_once 'classes/Log.php';
require_once 'classes/ext/JsonEncode.php';
require_once 'classes/Router.php';
require_once 'include/config.php';
require 'include/db.conf.php';
require_once 'classes/Base.php';
require_once 'classes/Notify.php';
require_once 'classes/General.php';
require_once 'third-party/portal/includes/class.smtp.php';
require_once 'classes/Models/ObjectModel.php';
require_once 'classes/Models/Database.php';

function autoloadClass($className) {
	$extensions = array(".php", ".class.php", ".inc");
	$paths = explode(PATH_SEPARATOR, get_include_path());
	$paths[] = 'classes';
	$paths[] = 'third-party/portal/includes';
	$filefound = false;
	foreach ($paths as $path) {
		$filename = $path . DIRECTORY_SEPARATOR . $className;
		foreach ($extensions as $ext) {
			if (is_readable($filename . $ext)) {
				require_once $filename . $ext;
				$filefound = true;
				break;
		   }
		}

		if($filefound) {
			break;
		}
	}
}

function autoloadController($className) {
	$extensions = array(".php", ".class.php", ".inc");
	$paths = explode(PATH_SEPARATOR, get_include_path());
	$paths[] = 'controllers';
	foreach ($paths as $path) {
		$filename = $path . DIRECTORY_SEPARATOR . $className;
		foreach ($extensions as $ext) {
			if (is_readable($filename . $ext)) {
				require_once $filename . $ext;
				break;
		   }
	   }
	}
}


spl_autoload_register("autoloadClass");
spl_autoload_register("autoloadController");

// Short circuit favicon requests.
if ('/favicon.ico' == $_SERVER['REDIRECT_URL']) {
	Controller::notFound();
	exit();
}

$query = null;
if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
	$query = $_SERVER['REDIRECT_QUERY_STRING'];
}
parse_str($query, $filters);

#$db2 = new Database($db_config);
$db = new Db($db_config);
$log = new Log();
if (defined('LOG_FILE')) {
	$log->setLogLevel(LOG_LEVEL)->setLogFile(LOG_FILE);
}

$router = new Router();
list($noun, $verb, $id, $params) = $router->parseUrl($_SERVER['REDIRECT_URL']);

if (count($_GET) > 0) {
	$params = array_merge($params, $_GET);
}
if (count($_POST) > 0) {
	$params = array_merge($params, $_POST);
}
if (count($_FILES) > 0) {
	$params = array_merge($params, $_FILES);
}

$log->write($_SERVER['REQUEST_METHOD'] . ' ' . $noun . '-' . $verb . '-' . $id,
	Log::INFO);

// Most of our APIs return JSON.
Controller::sendHeader('Content-type: application/json; Charset=UTF-8;');
Controller::sendHeader('Access-Control-Allow-Origin: *');
Controller::sendHeader('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
Controller::sendHeader('Access-Control-Allow-Methods: GET,PUT,POST');

$oprId = 'API';
if($noun == 'certification' && $verb == 'save-record') {
	$params = json_decode(file_get_contents('php://input') ,true);
}


if (isset($_POST['oprId'])) {
	$oprId = $_POST['oprId'];
} elseif (isset($_POST['oprid'])) {
	$oprId = $_POST['oprid'];
} elseif (isset($_POST['owner'])) {
	$oprId = $_POST['owner'];
} elseif (isset($params['oprid'])) {
	$oprId = $params['oprid'];
} elseif (isset($params['oprId'])) {
	$oprId = $params['oprId'];
}


if ( ($noun == 'client' && $verb == 'cancel_client') || $verb == 'saveclient' || ($noun == 'clientcreditor' && $_SERVER['REQUEST_METHOD'] == 'PUT') ) {

} else if ($_SERVER['REQUEST_METHOD'] != 'POST' || !in_array($noun, array('billing', 'bank', 'settlement', 'warning', 'tasknew',
	'financial', 'plan', 'service', 'client_new', 'creditor', 'agency', 'agencyinfo', 'ach-directory', 'billing-schedule',
	'template', 'contact'))) {
		define('OPRID', $oprId);
}

if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	echo json_encode(array());
	$db->close();
	die;
}

//ROUTING
switch ($noun) {
case 'ipaddress':
	echo $_SERVER['REMOTE_ADDR'];
	break;
case 'verify-signature':
	$clientid = $_GET['clientid'];
	$docId = $_GET['docid'];
	$e = new ESignController();
	$e->verify_signature($clientid,$docId,$_GET['div']);
	break;
case 'mc-journey':
	require_once 'classes/ext/Client.php';
	$client = new ExtClient($db, $params['clientid']);
	$cData = $client->getRaw();
	switch($cData['divisionid']) {
	case 9:
		$params['smsMessage'] = str_replace('COMPANYNAME', 'NetDebt', $params['smsMessage']);
		break;
	case 10:
		$params['smsMessage'] = str_replace('COMPANYNAME', 'Worden & Associates', $params['smsMessage']);
        break;
    case 13:
        $params['smsMessage'] = str_replace('COMPANYNAME', 'American Debt Relief', $params['smsMessage']);
        break;
    case 14:
        $params['smsMessage'] = str_replace('COMPANYNAME', 'CreditAssociates', $params['smsMessage']);
        break;
	}
	$message = $params['smsMessage'];
	$number = $params['number'];
	$lid = $params['leadId'];
	$controller = new TextController();
	$curl = new Curl();
	$twilio = $controller->sendToTwilio($message, $number, $curl, $lid, 'mc-journey');
	echo json_encode(array('success'=>true));
	break;
case 'journey-test':
	$controller = new TextController();
	$curl = new Curl();
	$message = 'Testing';
	$number = '4699645715';
	#$number = '7022072288';
	$twilio = $controller->sendToTwilio($message, $number, $curl, 'MIKE TEST');
	if($twilio)
		echo json_encode(array('success'=>true));
	else
		echo json_encode(array('success'=>false));
	break;
case 'log-sms':
	$l = new SFDCLead($params['id']);
	$message = $params['msg'];
	$l->createActivityHistory("SMS Sent: $message");
	#mail('mramsey@landmarktx.com','log-sms',var_export($params,true));
	break;
case 'journey':
	$type = $_REQUEST['type'];
	if(strpos($_REQUEST['step'],'missed') === false) {
		$step = $_REQUEST['step'];
		$missed=false;
	} else {
		$step = str_replace('missed','',$_REQUEST['step']);
		$missed=true;
	}
if(strpos($_REQUEST['step'], 'rescheduled') !== false) {
	$step = str_replace('rescheduled','',$_REQUEST['step']);
	$remind = true;
}
	$sql = "SELECT * from lead_tracking.journey_schedule_config where step = $step and type = '$type'";
	$res = $db->query($sql);
	$res=reset($res);
	$l = new SFDCLead($_GET['id']);
	$eventid = $_GET['eventid'];
	$query = "SELECT * from lead_tracking.journey_events where eventid = $eventid";
	$eventInfo = $db->query($query);
	$eventInfo = reset($eventInfo);
	if(strtolower($l->status) != 'new' || $l->isConverted == "True") {
/*		$pdField = 'Credit_Associates___Contact_Journey_Drip_Campaign';
		$pardot = new PardotController($l->email, $pdField);
		$pardot->updateField('False');
		$pdField = 'Credit_Associates___Info_Kit_Contact_Journey_Drip';
		$pardot = new PardotController($l->email, $pdField);
		$pardot->updateField('False');*/
		echo 'contacted';
	} else {
		//mail('mramsey@landmarktx.com','lead',var_export($res,true));
		$ltype=$l->leadsource;
		if($res['sms'] || ($res['call'] && $missed) || ($res['call'] && $remind)) {
			$controller = new TextController();
			if($res['call'] && $missed)
				$message = $res['missed_call_message'];
			else
				$message = $res['sms_message'];

			if($res['call'] && $remind)
				$message = $res['remind_sms_message'];

			if($res['sms'] && $step ==0 && $eventInfo['afterhours_sms']) {
				$message = $res['afterhours_sms_message'];
			}
			if(strtolower($l->leadsource) == 'afterhours overflow') {
				$message = str_replace('Hi FNAME, s','S',$message);
				$message = str_replace('Hi FNAME, w', 'W', $message);
				$message = str_replace('Hi FNAME, j', 'J', $message);
			} else {
				$message = str_replace('FNAME',substr($l->firstname,0,10), $message);
			}
			$number = $l->phone;
			$curl = new Curl();
			$twilio = $controller->sendToTwilio($message, $number, $curl, $_GET['id']);
/*			if($twilio)
	$l->createActivityHistory("SMS Sent: $message");*/
		}
		if($res['call'] && !$missed && !$remind) {
			$phone = preg_replace("/[^0-9]/", '', $l->phone);
			//mail('mramsey@landmarktx.com','number vs phone',var_export(array('number'=>$number,'phone'=>$phone),true));
			if($type == 'infokit')
				$lv_campaign = 'Infokit';
			else
				$lv_campaign = 'Journey';
			if($type == 'lending_market')
				$lv_campaign = 'Lending_Market';
			if($type == 'creditanswers')
				$lv_campaign = 'creditanswers';
			$sql = "Insert into livevox.livevox_calls (phone_number, campaign, type, lead_received_dt,eventid) VALUES ('$phone','$lv_campaign','$ltype',now(),$eventid);";
			//mail('mramsey@landmarktx.com','query',$sql);
			$db->query($sql);
			$db->close();
			$l->createActivityHistory($res['step_name']);
		}
		if($res['send_vcard'] && $missed) {
			$controller = new TextController();
			if($type == 'lending_market')
				$message = 'lm_vcard';
			else
				$message = '';
			if($type == 'creditanswers')
				$message = 'creditanswers_vcard';
			$number = $l->phone;
			$curl = new Curl();
			$twilio = $controller->sendToTwilio($message, $number, $curl, $_GET['id']);
/*			if($twilio)
	$l->createActivityHistory("SMS Sent: vcard sent.");*/
		}
		if(isset($res['pardot_field_update']) && $res['pardot_field_update'] != '') {
				$uRes = $l->update(array('CreditAssociates_Email_Drip__c'=>'True'));
				//mail('mramsey@landmarktx.com','update results',var_export($uRes, true));
				/*$pardot = new PardotController($l->email, $res['pardot_field_update']);
				$pardot->updateField('True');*/
		}
		if($missed)
			echo 'missed';
		elseif($remind)
			echo 'remind';
		else
			echo 'success';
	}
	break;
case 'purl_journey':
	if(strpos($_REQUEST['step'],'missed') === false) {
		$step = $_REQUEST['step'];
		$missed=false;
	} else {
		$step = str_replace('missed','',$_REQUEST['step']);
		$missed=true;
	}
if(strpos($_REQUEST['step'], 'rescheduled') !== false) {
	$step = str_replace('rescheduled','',$_REQUEST['step']);
	$remind = true;
}
	$sql = "SELECT * from lead_tracking.purl_journey_schedule_config where step = $step";
	$res = $db->query($sql);
	$res=reset($res);
	$l = new SFDCLead($_GET['id']);
	$eventid = $_GET['eventid'];
	if(strtolower($l->status) != 'new' || $l->isConverted == "True") {
		echo 'contacted';
	} else {
		//mail('mramsey@landmarktx.com','lead',var_export($res,true));
		$type=$l->leadsource;
		if($res['sms'] || ($res['call'] && $missed) || ($res['call'] && $remind)) {
			$controller = new TextController();
			if($res['call'] && $missed)
				$message = $res['missed_call_message'];
			else
				$message = $res['sms_message'];

			if($res['call'] && $remind)
				$message = $res['remind_sms_message'];

			if(strtolower($l->leadsource) == 'afterhours overflow') {
				$message = str_replace('Hi FNAME, s','S',$message);
				$message = str_replace('Hi FNAME, w', 'W', $message);
			} else {
				$message = str_replace('FNAME',substr($l->firstname,0,10), $message);
			}
			$number = $l->phone;
			$curl = new Curl();
			$twilio = $controller->sendToTwilio($message, $number, $curl, $_GET['id']);
/*			if($twilio)
	$l->createActivityHistory("SMS Sent: $message");*/
		}
		if($res['call'] && !$missed && !$remind) {
			$phone = preg_replace("/[^0-9]/", '', $l->phone);
			//mail('mramsey@landmarktx.com','number vs phone',var_export(array('number'=>$number,'phone'=>$phone),true));
			$sql = "Insert into livevox.livevox_calls (phone_number, campaign, type, lead_received_dt, eventid) VALUES ('$phone','Purl Journey','$type',now(),$eventid);";
			//mail('mramsey@landmarktx.com','query',$sql);
			$db->query($sql);
			$db->close();
			$l->createActivityHistory($res['step_name']);
		}
		if($res['send_vcard'] && $missed) {
			$controller = new TextController();
			$message = '';
			$number = $l->phone;
			$curl = new Curl();
			$twilio = $controller->sendToTwilio($message, $number, $curl, $_GET['id']);
/*			if($twilio)
	$l->createActivityHistory("SMS Sent: vcard sent.");*/
		}
		if(isset($res['pardot_field_update']) && $res['pardot_field_update'] != '') {
			$pardot = new PardotController($l->email, $res['pardot_field_update']);
			$pardot->updateField('True');
		}
		if($missed)
			echo 'missed';
		elseif($remind)
			echo 'remind';
		else
			echo 'success';
	}
	break;
case 'broadcast1':
	var_export($params);exit;
	$controller = new StraticsController();
	$now = new DateTime('10:30 am');
	$file = $controller->buildFile($now, $params['campaign']);

	$result = $controller->uploadList($file);
	if(isset($result['list_id'])) {
//		$new_campaign_id = $controller->cloneCampaign(138153);
		$list_result = $controller->assignListToCampaign($result['list_id'], $params['stratics_id']);
	}
	echo json_encode(array('result'=>$result['result']));
	break;
case 'broadcast2':
	$controller = new StraticsController();
	$now = new DateTime('12:30 pm');
	$file = $controller->buildFile($now, $params['campaign']);

	$result = $controller->uploadList($file);
	if(isset($result['list_id'])) {
		$list_result = $controller->assignListToCampaign($result['list_id'], $params['stratics_id']);
	}
	echo json_encode(array('result'=>$result['result']));
	break;
case 'broadcast3':
	$controller = new StraticsController();
	$now = new DateTime('5:05 pm');
	$file = $controller->buildFile($now, $params['campaign']);

	$result = $controller->uploadList($file);
	if(isset($result['list_id'])) {
		$list_result = $controller->assignListToCampaign($result['list_id'], $params['stratics_id']);
	}
	echo json_encode(array('result'=>$result['result']));
	break;
case 'broadcast4':
	$controller = new StraticsController();
	$now = new DateTime('9:05 am');
	$file = $controller->buildFile($now, $params['campaign']);
	$result = $controller->uploadList($file);
	if(isset($result['list_id'])) {
		$list_result = $controller->assignListToCampaign($result['list_id'], $params['stratics_id']);
	}
	echo json_encode(array('result'=>$result['result']));
	break;
case 'broadcast5':
	$controller = new StraticsController();
	$now = new DateTime('12:05 pm');
	$file = $controller->buildFile($now, $params['campaign']);
	$result = $controller->uploadList($file);
	if(isset($result['list_id'])) {
		$list_result = $controller->assignListToCampaign($result['list_id'], $params['stratics_id']);
	}
	echo json_encode(array('result'=>$result['result']));
	break;
case 'agentpool':
	$controller = new StraticsController();
	$controller->createOutboundCampaign();
	echo json_encode(array('result'=>'success'));
	break;
case 'ext':
	require_once 'classes/Transaction.php';
	require_once 'controllers/GlobalController.php';
	switch ($verb) {
	case 'setup':
		$controller = new UtilityController($db);
		$controller->getRedraftConfig($params);
		break;
	case 'savesetup':
		$params = json_decode(file_get_contents('php://input'), true);
		$controller = new UtilityController($db);
		$controller->saveRedraftConfig($params);
		break;
	case 'authenticate':
		require_once 'classes/ext/Auth.php';
		require_once 'controllers/ext/AuthController.php';
		$auth = new Auth($db);

		if(empty($params) && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$params = json_decode(file_get_contents('php://input') ,true);
				}
		$params['callback'] = null;

				$controller = new AuthController($params['callback']);
				if (!empty($params['token'])) {
					$controller->authenticate($auth, $params['token']);
				} else {
					$controller->authenticate($auth, false, $params['user'], $params['password']);
				}
				break;
case 'userpermissiondata':
	$controller = new UserController($db);

	switch($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		if(empty($params)) {
			$params = json_decode(file_get_contents('php://input') ,true);
						}

						$controller->saveUserPermissionData($params);
						break;
				}
				break;
case 'usergroupdata':
	$controller = new UserController($db);

	switch($_SERVER['REQUEST_METHOD']) {
	case 'PUT':
		if(empty($params)) {
			$params = json_decode(file_get_contents('php://input') ,true);
						}

						$controller->deleteUserGroup($params);
						break;
case 'POST':
	if(empty($params)) {
		$params = json_decode(file_get_contents('php://input') ,true);
						}

						$controller->saveUserGroupData($params);
						break;
				}

				break;
case 'copyuserdata':
	$controller = new UserController($db);
	switch($_SERVER['REQUEST_METHOD']) {
		case 'PUT':
			if(empty($params)) {
				$params = json_decode(file_get_contents('php://input') ,true);
			}
			$controller->copyUser($params);
			break;
	}
	break;
case 'userdata':
	$controller = new UserController($db);

	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$oprid = $params['oprid'];
		$controller->getOpridData($oprid);
		break;
	case 'PUT':
		if(empty($params)) {
			$params = json_decode(file_get_contents('php://input') ,true);
		}
		$controller->createUser($params);

		break;
case 'POST':
	if(empty($params)) {
		$params = json_decode(file_get_contents('php://input') ,true);
						}

						if($params['password'] == '1') {
							$controller->updateUserPassword($params);
						} else {
							$controller->saveUserData($params);
						}
						break;
				}

				break;
case 'processbulk':
	if(empty($params) && $_SERVER['REQUEST_METHOD'] == 'POST') {
		$params = json_decode(file_get_contents('php://input') ,true);
				}

				$controller = new BulkImportController();
				$controller->processCSV($params);
				break;
case 'bulk_start_over':
	$controller = new BulkImportController();
	$controller->startOver();
	break;
case 'create_bulk_offers':
	if(empty($params) && $_SERVER['REQUEST_METHOD'] == 'POST') {
		$params = json_decode(file_get_contents('php://input'), true);
				}

				$controller = new BulkImportController();
				$controller->createBulkOffers($params);
				break;
case 'bulkfiles':
	$controller = new BulkImportController();
	$controller->getBulkFiles();
	break;
case 'delete_bulk_files':
	if(empty($params) && $_SERVER['REQUEST_METHOD'] == 'PUT') {
		$params = json_decode(file_get_contents('php://input') ,true);
				}

				$controller = new BulkImportController();
				$controller->deleteBulkFiles($params);
				break;
case 'bulk_in_progress':
	$controller = new BulkImportController();
	$controller->checkInProgress();
	break;
case 'uploadbulk':
	$controller = new BulkImportController();
	$controller->uploadFile($params);
	break;
case 'docudata':
	$docudata = new Docudata();
	$docudata->loadByParams($params);
	$docudata->getExceptions(true);
	break;
case 'docudatasave':
	if(empty($params) && $_SERVER['REQUEST_METHOD'] == 'POST') {
		$params = json_decode(file_get_contents('php://input') ,true);
			   }

				$docudataController = new DocudataController();
				$docudataController->save($params['rowsToSave']);
				break;
case 'docudatafile':
	$docudataController = new DocudataController();
	$docudataController->getDocudataFile($params['file'], $params['fetch']);
	break;
case 'getclientdoc':
	$controller = new ClientDocsController();
	$clientid = $params['clientid'];
	$fileName = urldecode($params['file']);
	$fetch = $params['fetch'];

	$mobile_uploads_dir = false;
	if(isset($params['mobileuploaddir'])){
        $mobile_uploads_dir = $params['mobileuploaddir'];
    }
	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$controller->getClientDoc($clientid, $fileName, $fetch, $mobile_uploads_dir);
		break;

	default:
		Controller::badRequest();
		break;
				}
				break;
case 'extractpage':
	$controller = new ClientDocsController();
	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$controller->extractPage($params);
		break;
				}
				break;
case 'validdoc':
	$class = new ClientDocs();
	echo $class->awsFileExists($params['clientid'], $params['filename']);
	break;
case 'clientdocs':
	$controller = new ClientDocsController();
	$clientid = $params['clientid'];
	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$controller->getDocs($clientid);
		break;
	case 'POST':
	case 'PUT':

		if(empty($params)) {
			$params = json_decode(file_get_contents('php://input') ,true);
						}

						$controller->processFile($params);
						break;
				}
				break;
case 'getclient':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';
	$params['callback'] = null;
	$client = new ExtClient($db, $params['clientid']);
	$controller = new ClientController($params['callback']);
	$controller->getClient($client);
	break;
case 'lastclient':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';

	if(empty($params)) {
		$params = json_decode(file_get_contents('php://input') ,true);
		}
		$params['callback'] = null;
		//$client = new ExtClient($db, $params['clientid']);
		$controller = new ClientController($params['callback']);
		switch($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			$client = new ExtClient($db, '');
			$controller->getLastClient($client, $params);

			break;
		case 'POST':
			$client = new ExtClient($db, $params['clientid']);
			$controller->setLastClient($client, $params);

			break;
				}
				break;
case 'getoffersbyclient':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Offer.php';
	require_once 'controllers/ext/OfferController.php';
	require_once 'controllers/OffersController.php';
	$offerController = new OffersController();
	$filters['status'] = 'either-accepted';
	$offer = new ExtOffer($db, $params['clientid'], new Offer(), $offerController, $filters);
	$controller = new OfferController($offer);
	$controller->getOffers();

	break;
case 'getoffersbycreditor':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Offer.php';
	require_once 'controllers/ext/OfferController.php';
	require_once 'controllers/OffersController.php';
	$offerController = new OffersController();
	$filters = array(array('status' => 'either-accepted'), array('status' => 'both-accepted'));
	$offer = new ExtOffer($db, $params['client_creditor_id'], new Offer(), $offerController, $filters, 'client_creditor');
	$controller = new OfferController($offer);
	$controller->getOffers();

	break;
case 'getoffershistory':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Offer.php';
	require_once 'controllers/ext/OfferController.php';
	require_once 'controllers/OffersController.php';
	$offerController = new OffersController();
	$filters['status'] = array(array('status' => 'closed'));
	$offer = new ExtOffer($db, $params['clientid'], new Offer(), $offerController, $filters);
	$controller = new OfferController($offer);
	$controller->getOffers();

	break;
case 'getoffershistorybycreditor':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Offer.php';
	require_once 'controllers/ext/OfferController.php';
	require_once 'controllers/OffersController.php';
	$offerController = new OffersController();
	$filters = array(array('status' => 'rejected'), array('status' => 'expired'));
	$offer = new ExtOffer($db, $params['client_creditor_id'], new Offer(), $offerController, $filters, 'client_creditor');
	$controller = new OfferController($offer);
	$controller->getOffers();

	break;
case 'getwarnings':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';
	$params['callback'] = null;
	$client = new ExtClient($db, $params['clientid']);
	$controller = new ClientController($params['callback']);
	$controller->getWarnings($client, $params);

	break;
case 'getcontactlog':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';
	$params['callback'] = null;
	$client = new ExtClient($db, $params['clientid']);
	$controller = new ClientController($params['callback']);
	$controller->getContactLog($client, $params);

	break;
case 'getdatahistory':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';
	$params['callback'] = null;
	$client = new ExtClient($db, $params['clientid']);
	$controller = new ClientController($params['callback']);
	$controller->getDataHistory($client, $params);

	break;
case 'getcreditors':
	require_once 'classes/ext/Client.php';
	require_once 'controllers/ext/ClientController.php';
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Offer.php';
	$params['callback'] = null;
	$offer = new ExtOffer($db, $params['clientid'], new Offer());
	$client = new ExtClient($db, $params['clientid'], $offer);
	$controller = new ClientController($params['callback']);
	$controller->getCreditors($client, $params);

	break;
case 'getusers':
	require_once 'classes/ext/User.php';
	require_once 'controllers/ext/UserController.php';
	$params['callback'] = null;
	$user = new User($db);
	$controller = new UserController($params['callback']);
	$controller->getUsers($user, $params);

	break;
case 'getuseractivity':
	require_once 'classes/User.php';
	require_once 'controllers/ext/UserController.php';
	$params['callback'] = null;
	$user = new User($db, $params['oprid']);
	$controller = new UserController($params['callback']);
	$controller->getUserActivity($user);

	break;
case 'getclienthistory':
	require_once 'classes/User.php';
	require_once 'controllers/ext/UserController.php';
	$params['callback'] = null;
	$user = new User($db, $params['oprid']);
	$controller = new UserController($params['callback']);
	$controller->getClientHistory($user);

	break;
case 'clientsearch':
	require_once 'controllers/ext/UtilityController.php';
	$controller = new UtilityController($db);
	$controller->searchClients($params);

	break;
case 'searchclientcreditors':
	require_once 'controllers/ext/UtilityController.php';
	$controller = new UtilityController($db);
	$controller->searchClientCreditors($params);

	break;
case 'marksent':
	$params = json_decode(file_get_contents('php://input') ,true);
	$controller = new ReportController($params);
	$controller->markSent($params);
	break;
case 'clientcomm':
	$controller = new ReportController($params);
	$controller->getReport();
	break;
case 'scrubreport':
	switch($_SERVER['REQUEST_METHOD']) {
	case "POST":
		$params = json_decode(file_get_contents('php://input') ,true);

		require_once 'controllers/ext/UtilityController.php';
		$controller = new UtilityController($db);
		$controller->scrubReport($params);
		break;
				}

				break;
case 'searchbyclientid':
	require_once 'classes/ext/QuickSearch.php';
	require_once 'controllers/ext/QuickSearchController.php';
	$params['callback'] = null;
	$quicksearch = new QuickSearch($db);
	$controller = new QuickSearchController($params['callback']);
	$controller->getClientsBy($quicksearch, "clientid", $params['clientid']);

	break;
case 'searchbyclientname':
	require_once 'classes/ext/QuickSearch.php';
	require_once 'controllers/ext/QuickSearchController.php';
	$params['callback'] = null;
	$quicksearch = new QuickSearch($db);
	$controller = new QuickSearchController($params['callback']);
	$controller->getClientsBy($quicksearch, "name", $params['name']);

	break;
case 'searchbyclientphone':
	require_once 'classes/ext/QuickSearch.php';
	require_once 'controllers/ext/QuickSearchController.php';
	$params['callback'] = null;
	$quicksearch = new QuickSearch($db);
	$controller = new QuickSearchController($params['callback']);
	$controller->getClientsBy($quicksearch, "phone", $params['phone']);

	break;
case 'searchbyuser':
	require_once 'classes/ext/QuickSearch.php';
	require_once 'controllers/ext/QuickSearchController.php';
	$params['callback'] = null;
	$quicksearch = new QuickSearch($db);
	$controller = new QuickSearchController($params['callback']);
	$controller->getUsers($quicksearch, $params['user']);

	break;
case 'getcreditornotes':
	require_once 'controllers/ext/ClientCreditorController.php';
	$clientCreditorController = new ClientCreditorController($db);
	$clientCreditorController->getClientCreditorNotes($params['id']);

	break;
case 'getsmartschedule':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'controllers/ext/OfferController.php';
	$offerController = new OfferController();
	$offerController->getSmartPaymentSchedule($params, $db);

	break;
case 'getbulksmartschedule':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'controllers/ext/OfferController.php';
	$offerController = new OfferController();
	$offerController->getBulkSmartSchedule($params, $db);

	break;
case 'paymentschedule':
	require_once 'classes/ext/ExtOffer.php';
	require_once 'classes/Client.php';
	require_once 'classes/ClientCreditor.php';
	require_once 'classes/PaymentOption.php';
	require_once 'controllers/ext/OfferController.php';
	$offerController = new OfferController();
	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$offerController->getPaymentSchedule($params, $db);

		break;
	case 'POST':
		$offerController->deletePaymentSchedule($params, $db);

		break;
	case 'PUT':
		$params = null;
		parse_str(file_get_contents('php://input'), $params);
		$offerController->savePaymentSchedule($params, $db);

		break;
				}
				break;
case 'process_settlement':
	require_once 'classes/ext/Gcs.php';
	$gcs = new Gcs();
	$gcs->processSettlement($params, $db);

	break;
case 'sendemail':
	require_once 'controllers/ext/UtilityController.php';
	$utilityController = new UtilityController($db);
	$utilityController->sendEmail($params);

	break;
case 'sendtext':
	$notify = new Notify();
	$notify->sendText($params['clientid'], '', $params, true);

	break;
case 'agency':
	switch($_SERVER['REQUEST_METHOD']) {
	case 'PUT':
		require_once 'classes/ext/Agency.php';
		require_once 'classes/ext/AgencyBank.php';
		require_once 'controllers/ext/AgencyController.php';
		$agencyController = new AgencyController($db);
		$agency = new Agency();
		$agencyBank = new AgencyBank();
		$params = null;
		parse_str(file_get_contents('php://input'), $params);
		$agency->load($params['agencyid'], $db);
		$agencyController->updateAgency($agency, $agencyBank, $params);

		break;
	case 'GET':
		require_once 'classes/ext/Agency.php';
		require_once 'classes/ext/AgencyBank.php';
		require_once 'controllers/ext/AgencyController.php';
		$agencyController = new AgencyController($db);
		$agency = new Agency();
		$agency->load($params['id'], $db);
		$agencyController->getAgency($agency);

		break;
				}
				break;
case 'clientcreditor':
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$controller = new ClientCreditorController();
		$clientCreditor = new ClientCreditor();
		$id = $params['id'];
		$clientCreditor->setAgencyStats($params['agencystats']);
		$clientCreditor->load($id, $db);
		$clientCreditor->getSuccessFeePaid();
		$controller->view($clientCreditor);
		break;
	case 'PUT':
		require_once 'controllers/ext/ClientCreditorController.php';
		$controller = new ClientCreditorController($db);
		$clientCreditor = new ClientCreditor();
		$params = null;
		parse_str(file_get_contents('php://input'), $params);
		//mail('ly.nguyen@landmarktx.com', 'accept 3', var_export($params, true));
		$controller->updateCreditor($clientCreditor, $params);
		break;
				}
				break;
case 'saveclient':
	switch($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		$params=(file_get_contents('php://input', true));
		$data = json_decode($params);
        if ($data == NULL) {
            $data = $_POST;
        }

        define(OPRID, $data->row_update_oprid);

		$client = new Client();
		$result = $client->updateClient($data);
		if($result === true)
			echo json_encode(array('success'=>$result));
		else
			echo json_encode(array('success'=>false, 'errors'=>$result));
		break;
			}
		break;
case 'offer':
	require_once 'classes/Offer.php';
	$offer = new Offer();
	$offer->setDb($db);
	$type = $params['type'];
	switch($type) {
	case 'payment_type':
		$offer->updatePaymentTypes($params);
		echo json_encode(array('success'=>true));
		break;
				}
				break;
case 'settlement_fee_sign':
	require_once 'classes/Settlement.php';
	require_once 'classes/GlobalApp.php';
	include_once 'lib/forge_fdf.php';
	$settlement = new Settlement($params);
	$settlement->sendSettlementFeeSign($esignWhitelist);
	break;
case 'esign_doc':
	$type = $params['type'];
	include_once 'lib/forge_fdf.php';
	switch($type) {
	case 'global_atd':
		$settlement = new Settlement($params);
		$settlement->sendGlobalAtd($esignWhitelist);
		break;
	case 'global_atd_non_client':
		$settlement = new Settlement($params);
		$settlement->sendGlobalAtdNonClient($esignWhitelist);
		break;
	case 'pre_auth':
		$settlement = new Settlement($params);
		$settlement->sendPreAuth($esignWhitelist);
		break;
	case 'pts_sign':
		/*	$settlement = new Settlement($params);
			$settlement->sendPtsSign($esignWhitelist);*/
		try {
                        require_once('/var/www/calapi/classes/ext/Client.php');
                        $c = new ExtClient($db, $params['clientid']);
                        $e = new ESignClient($c, $params['oprid'], $db);
                        $controller = new ESignController();
                        if($e->spanishSpeaker)
                                $type = 'spanish pts';
                        else
                                $type = 'pts';
                        $isCoApp = false;
                        $doc = $controller->prepareDocument($e, $type, false, $isCoApp);
                        $docArray = array($type => $doc);
                        $res = $controller->esignDocument_v2($e, $docArray, $type, false, $isCoApp, 'dm', '');
                        echo json_encode(array('success'=>true));
                } catch (Exception $ex) {
                        echo json_encode(array('success'=>false, 'message' => 'Something went wrong. Please try again. If error persists contact IT.'));
		}
		break;
	case 'pts_sign_co':
		/*$settlement = new Settlement($params);
		$settlement->sendPtsSignCo($esignWhitelist);
		break;
	case 'pts_sign_co_new':*/
		try {
			require_once('/var/www/calapi/classes/ext/Client.php');
			$c = new ExtClient($db, $params['clientid']);
			$e = new ESignClient($c, $params['oprid'], $db);
			$controller = new ESignController();
			if($e->spanishSpeaker)
				$type = 'spanish pts';
			else
				$type = 'pts';
			$isCoApp = true;
			$doc = $controller->prepareDocument($e, $type, false, $isCoApp);
			$docArray = array($type => $doc);
			$res = $controller->esignDocument_v2($e, $docArray, $type, false, $isCoApp, 'dm', '');
			echo json_encode(array('success'=>true));
		} catch (Exception $ex) {
			echo json_encode(array('success'=>false));
		}
		break;
	case 'global_application':
		/*$settlement = new Settlement($params);
		$settlement->sendGlobalApplication($esignWhitelist);
		break;
	case 'global_app2':*/
		try {
			require_once('/var/www/calapi/classes/ext/Client.php');
			//mail('mramseydd@landmarktx.com','db global2',var_export($db,true));
		$c = new ExtClient($db, $params['clientid']);
		$e = new ESignClient($c, $params['oprid'], $db);
		//mail('mramsey@landmarktx.com','dm global',var_export($e,true));
		$controller = new ESignController();
		if($e->spanishSpeaker)
			$type = 'spanish global';
		else
			$type = 'global';
		$gDoc = $controller->prepareDocument($e, 'global', false, false);
		$docArray = array($type=>$gDoc);
		$res = $controller->esignDocument_v2($e, $docArray, $type, false, false, 'dm', '');
		//mail('mramsey@landmarktx.com','global2',var_export($res, true));
		echo json_encode(array('success'=>true));
		} catch (Exception $ex) {
			echo json_encode(array('success'=>false));
		}
		break;
				}
				break;
case 'utility':
	require_once 'classes/ext/Auth.php';
	require_once 'classes/Client.php';
	require_once 'classes/ext/Gcs.php';
	require_once 'controllers/ext/UtilityController.php';
	$utilityController = new UtilityController($db);
	$gcs = new GCS();
	$client = new Client();
	$type = $params['type'];
	switch ($type) {
	case 'getallfields':
		$utilityController->getAllFieldValues();

		break;
	case 'getallusers':
		$utilityController->getAllUsers();

		break;
	case 'getusers':
		$utilityController->getUsersBy($params);

		break;
	case 'updateuseractivity':
		$utilityController->updateUserActivity($params);

		break;
	case 'cancelreasons':
		$utilityController->getCancelReasons();

		break;
	case 'paymentoptions':
		$utilityController->getPaymentOptions();

		break;
	case 'creditorstatus':
		$utilityController->getCreditorStatus();

		break;
	case 'clientstatus':
		$utilityController->getClientStatus();

		break;
	case 'states':
                $utilityController->getStates();

                break;
	case 'servicestatus':
		$utilityController->getServiceStatus();

		break;
	case 'phonetype':
		$utilityController->getPhoneType();

		break;
	case 'xlat':
		$utilityController->getXlattable();

		break;
	case 'divisions':
		$utilityController->getDivisions();
		break;
	case 'divisionsinfo':
		$utilityController->getDivisionsInfo();

		break;
	case 'agencies':

		$utilityController->getAgencies();

		break;
	case 'clients':
		$utilityController->getClients($params);

		break;
	case 'contactlog':
		$utilityController->getContactLog($params['clientid']);

		break;
	case 'groupdefn':
		$utilityController->getGroupDefn();

		break;
	case 'emailtemplates':
		$utilityController->getEmailTemplates($params);

		break;
	case 'smstemplates':
		$utilityController->getSmsTemplates($params);

		break;
	case 'opridsalesreps':
		$utilityController->getOpridSalesReps();

		break;
	case 'opridcss':
		$utilityController->getCustomerServiceReps();

		break;
	case 'opridnegs':
		$utilityController->getNegotiators();

		break;
	case 'opridpass':
		$utilityController->getOpridPas();

		break;
	case 'emailbydivision':
		$utilityController->getEmailByDivision($params);

		break;
	case 'contactinfo':
		$utilityController->getContactInfo();

		break;
	case 'creditors':
		$utilityController->getCreditors();

		break;
	case 'allcreditors':
		$utilityController->getAllCreditors();
		break;

	case 'opridpas':
		$utilityController->getOpridPas();

		break;
	case 'negotiators':
		$utilityController->getNegotiators();

		break;
	case 'priority':
		$utilityController->getPriority();

		break;
	case 'userdata':
		$utilityController->getUserData($db, $params);

		break;
	case 'directpayees':
		$utilityController->getDirectPayees($params, $gcs, $client);

		break;
	case 'refreshcreditornotes':
                        $utilityController->refreshCreditorNotes($params);
                        break;
				}
				break;
		}
		break;

		/**
		 * Bitbucket syncing
		 */
case 'bitbucket':
	$controller = new BitbucketController();

	switch($verb) {
	case 'sync':
		$params = file_get_contents('php://input');
		$controller->sync($params);
		break;
	}
	break;

	/*
	 * Client Creditor Routing
	 * Options GET PUT
	 */
case 'clientcreditor':
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$controller = new ClientCreditorController();
		switch ($verb) {
		case 'getstats':
			$controller->getStats($db, $params['clientcreditorid']);
			break;
		case 'update':
			$clientCreditor = new ClientCreditor();
			$clientCreditor->load($params['id'], $db);
			$controller->update($clientCreditor, $params, $db);
			break;
		case 'offer-ready-to-accept':
			$id = $params['id'];
			$controller->getAcceptableOffer($db, $id);
			break;
		default:
			$clientCreditor = new ClientCreditor();
			if (isset($params['agencystats']) && $params['agencystats'] == 1) {
				$id = $params['id'];
				$clientCreditor->setAgencyStats(true);
						}
						$clientCreditor->load($id, $db);
						$controller->view($clientCreditor);
						break;
				}

				break;
case 'POST':
	switch($verb) {
	case 'addnote':
		require_once 'classes/ClientCreditor.php';
		require_once 'classes/ContactLog.php';
		$contactLog = new ContactLog();
		$clientCreditor = new ClientCreditor();
		$id = $params['client_creditor_id'];
		$clientCreditor->load($id, $db);
		$creditorNote = $clientCreditor->addCreditorNote($db, $params['creditor_notes'], $params['oprid']);

		if($params['copy_notes'] == 1) {
			$clientCreditor->addClientNote($db, $params['creditor_notes'], $params['oprid']);
			$contact_method = (!empty($params['contact_method'])) ? $params['contact_method'] : 'C';
			$contact_data = NULL;

			if($contact_method == 'C' && !empty($params['contact_method'])) {
				if(!empty($params['phone_other_data'])) {
					$contact_data = $params['phone_other_data'];
				} else {
					$contact_data = $params['phone_data'];
				}
			}

			$contactLog->setOprid($params['oprid'])
				->setContactMethod($contact_method)
				->setContactReason('AU')
				->setContactType($params['contact_type'])
				->setContactData($contact_data)
				->setClientId($params['client_id'])
				->setNotes($creditorNote)
				->save($db);
						}

						break;
				}
				break;
case 'PUT':
	require_once 'classes/ClientCreditor.php';
	require_once 'controllers/ClientCreditorController.php';
	$params = null;
	parse_str(file_get_contents('php://input'), $params);
	define(OPRID, $params['oprid']);
	$controller = new ClientCreditorController();
	$clientCreditor = new ClientCreditor();
	$clientCreditor->load($id, $db);
	$controller->update($clientCreditor, $params, $db);
	break;
default:
	Controller::badRequest();
	break;
		}
		break;


		/*
		 * Help Directory Routing
		 */
case 'help':
	require_once 'controllers/HelpController.php';
	$helpController = new HelpController();
	$method = strtolower($_SERVER['REQUEST_METHOD']);
	if (!method_exists($helpController, $method)) {
		$helpController->badRequest();
		break;
		}
		$helpController->sendHeader('Content-type: text/html');
		$helpController->$method($verb);
		break;


		/*
		 * Mail Routing
		 * Options GET PUT HEAD POST
		 */
case 'mail':
	require_once 'controllers/MailController.php';
	$mailController = new MailController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'DELETE':
		$mailController->delete();
		break;
	case 'GET':
		$mailController->get();
		break;
	case 'HEAD':
		$mailController->head();
		break;
	case 'POST':
		$new_mail = (!empty($_POST['new']) && $_POST['new'] == '1') ? true : false;
		if ($new_mail) {
			require 'include/PHPMailerAutoload.php';
			$smtp = new PHPMailer;
				} else {
					require_once 'third-party/portal/includes/class.smtp.php';
					$smtp = new SMTP();
				}

				$mailController->post($_POST, $mailCredentials, $smtp, $db, SEND_MAIL);
				break;
case 'PUT':
	$mailController->put();
	break;
default:
	Controller::badRequest();
	break;
		}
		break;


		/*
		 * Offer Routing
		 * Options GET POST PUT DELETE
		 */
case 'offer':
	require_once 'classes/Client.php';
	require_once 'classes/Offer.php';
	require_once 'classes/PaymentOption.php';
	require_once 'controllers/OfferController.php';
	require_once 'classes/Transaction.php';
	require_once 'third-party/dm/includes/BankHoliday.php';
	require_once 'classes/Client.php';
	require_once 'classes/ContactLog.php';
	require_once 'classes/Task.php';
	$controller = new OfferController();
	$json_encode = new JsonEncode();
	if ($verb == "math") {
		$offer = new Offer();
		$id = $params['id'];
		$offer->load($id, $db);
		$oprid = $params['oprid'];
		if(!isset($params['isoverride']))
			$params['isoverride'] = 0;
		$controller->refreshmath($offer, $db, new Curl(), $oprid, true, $params['isoverride']);
		} else if($verb == "preauth") {
			$offer = new Offer();
			$offer = $controller->populateOfferFromArray($params, $offer);
			$offer = $controller->populatePaymentOptionsFromArray($params, $offer);
			$offer->setDb($db);
			$isBulk = ($params['oprId'] == 'bulk') ? true : false;

			echo $json_encode->toJson($offer->preAuth(false, $isBulk));
			#die();
		} else {
			switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				$offer = new Offer();
				$offer->load($id, $db);
				$controller->view($offer);
				break;
			case 'POST':
				if (!isset($_POST['clientId'])) {
					Controller::sendHeader('HTTP/1.1 400 Bad Request');
					echo json_encode(
						array('errors' => array('Missing clientId')));
					break;
					}
					$offer = new Offer();

					$client = new Client();
					$client->load($_POST['clientId'], $db);

					$controller->create($_POST, $offer, $client, $db, new Curl(), $log);
					break;
case 'PUT':
	$params = null;
	parse_str(file_get_contents('php://input'), $params);

	$offer = new Offer();
	$offer->load($params['offerId'], $db);
	$client = new Client();
	$client->load($offer->getClientId(), $db);
	$skip_accept = false;
	// skip because offer is not acceptable
	if ($params['clientStatus'] == "AC" && $params['oprId'] == "portal") {
		$acceptable = false;
		// portal offer accepts directly from client do NOT allow accepting based on solutions
		$acc = $offer->isAcceptable($client, $db, false, false);
		foreach ($acc as $a) {
			if ($a) {
				$acceptable = true;
							}
						}
						if (!$acceptable) {
							//$skip_accept = true;
						}
					}
					if (!$skip_accept) {
						$controller->update($params, $offer, $db, new Curl());
					}
					break;
case 'DELETE':
	Controller::notAllowed();
	break;
default:
	Controller::badRequest();
	break;
			}
		}
		break;

case 'global':
	require_once 'controllers/GlobalController.php';

	$controller = new GlobalController($params['clientid'], $db);

	switch ($verb) {
	case 'callapi':
		if (!empty($_POST['url'])) {
			$controller->callApi($_POST['xmlRequest'], $params['soapaction'], $_POST['url']);
				} else {
					$controller->callApi($_POST['xmlRequest'], $params['soapaction'], null);
				}
				break;
        case 'get-transactions':
            $controller->getTransactions($params['start-date'], $params['end-date']);
            break;
case 'getagencypayee':
	$controller->getPayeeIdFromAgency($params['agencyid']);
	break;
case 'getclientpayee':
	$controller->getPayeeIdFromClient();
	break;
case 'deleteglobalaccountnumber':
	$controller->deleteGlobalAccountNumber();
	break;
case 'addpayment':
	$controller->addPayment($_POST, $params['offerid']);
	break;
case 'updatepayment':
	$controller->updatePayment($_POST, $params['paymentid'], false);
	break;
case 'addfee':
	$controller->addFee($_POST, $params['offerid']);
	break;
case 'updatefee':
	$controller->updateFee($_POST, $params['debitid'], false);
	break;
case 'clonepayment':
	$controller->updatePayment($_POST, $params['paymentid'], true);
	break;
case 'updatebankaccount':
	$controller->updateBankAccount($params['clientid']);
	break;
case 'getclient':
	$controller->getClient($params['clientid']);
	break;
case 'getclientslist':
	$controller->getClientsList('', '', $params['clientid'], '');
	break;
case 'getclientinfo':
	$controller->getClientInfo($params['clientid']);
	break;
case 'getpaymentinfo':
	$controller->getPaymentInfo();
	break;
case 'getdeposit':
	$controller->getDeposit($params['amount'], $params['date']);
	break;
case 'getaccountinfo':
	$controller->getAccountInfo();
	break;
case 'updateclientinfo':
	$controller->updateClientInfo($params['updateinfoarray']);
	break;
case 'updateallaccountstatus':
	$controller->updateAllGCSAccountStatus();
	break;
case 'closecancelclientaccounts':
	if (!isset($params['cleanup'])) {
		$cleanUp = false;
	} else {
		$cleanUp = $params['cleanup'];
	}
	$controller->closeCancelClientAccounts($params['canceldate'], $cleanUp);
	break;
case 'clearclosedate':
	$controller->clearCloseDateByClientStatusChange();
	break;
case 'getpayeeinfo':
	if ($params['isdirectpay']) {
		$controller->getDirectPayPayeeInfo($params['payeeid']);
				} else {
					$controller->getPayeeInfo($params['payeeid']);
				}
				break;
case 'getpayees':
	if ($params['isdirectpay']) {
		$controller->getDirectPayPayees();
				} else {
					//$controller->getPayeeInfo($params['payeeid']);
				}
				break;
case 'getpayment':
	$controller->getPayment($params['paymentid']);
	break;
case 'updatecreationdate':
	$tableArray = array('gcs_account_numbers', 'gcs_jlg_account_numbers', 'gcs_carb_account_numbers',
		'gcs_mso_account_numbers', 'gcs_cra_account_numbers', 'gcs_adm_account_numbers',
		'gcs_wlf_account_numbers', 'gcs_nd_account_numbers', 'gcs_sko_account_numbers', 'gcs_cas_account_numbers', 'gcs_adr_account_numbers');

	$feeStr1 = " fee_ach = '0.00', fee_direct_pay = '0.00',
		fee_manual_check = '0.00', fee_2_day = '10.00',
		fee_overnight = '20.00', fee_phone_pay = '1.50',
		fee_wire_transfer = '20.00' ";

	$feeStr2 = " fee_ach = '0.00', fee_direct_pay = '2.00',
		fee_manual_check = '3.00', fee_2_day = '10.00',
		fee_overnight = '20.00', fee_phone_pay = '3.00',
		fee_wire_transfer = '20.00' ";

	foreach ($tableArray as $table) {
		echo "Start Table " . $table;
		$sql = "SELECT clientid FROM " . $table . " WHERE clientid IS NOT NULL AND creation_date IS NULL AND active = 1";
		$result = $db->query($sql);

		foreach ($result as $row) {
			$globalObj = new GlobalController($row['clientid'], $db);
			$globalObj->updateCreationDate($row['clientid']);
					}

					$sql = "UPDATE " . $table . " SET " . $feeStr1 . " WHERE creation_date < '2015-09-01'";
					$db->query($sql);

					$sql = "UPDATE " . $table . " SET " . $feeStr2 . " WHERE creation_date >= '2015-09-01'";
					$db->query($sql);
					echo "End Table " . $table;
				} //end tableArray loop
				break;
case 'get-payment-by-clientid':
	$controller->getPaymentByClientId();
	break;

        case 'compare-bank-info':
            $controller->compareBankAccount();
            break;
		}


		break;

		/*
		 * Offers Routing
		 * Options GET(client,clientcreditor,clientaccept,settlementinformation,bulkcomplete)
		 */
case 'offers':
	if ('GET' != $_SERVER['REQUEST_METHOD']) {
		Controller::notAllowed();
		exit();
		}
		require_once 'classes/Offer.php';
		require_once 'classes/Client.php';
		require_once 'classes/ClientCreditor.php';
		require_once 'classes/Transaction.php';
		require_once 'classes/PaymentOption.php';
		require_once 'controllers/OffersController.php';
		require_once 'third-party/dm/includes/BankHoliday.php';
		$controller = new OffersController();
		switch ($verb) {
		case 'client':
			$controller->viewByClientId($id, $db, new Offer(), $filters);
			break;
		case 'clientcreditor':
			$controller->viewByClientCreditorId($id, $db, new Offer(),
				$filters);
			break;
		case 'clientaccept':
			$controller->viewAcceptableByClientId($id, $db, new Offer(),
				$filters);
			break;
		case 'settlementinformation':
			$controller->settlementInformation($id, $db);
			break;
		case 'nsfrefresh':
			$controller->refreshAfterNSF($db);
			break;
		case 'siprefresh':
			$controller->refreshMathSIP($db);
			break;
		default:
			Controller::badRequest();
			break;
		}
		break;

		/*
		 * Style Routing
		 * Options GET
		 */
case 'style':
	Controller::sendHeader('Content-type: text/css');
	include 'include/style.css';
	break;


	/*
	 * Task Routing
	 * Options
	 */
case 'task':
	switch($_SERVER['REQUEST_METHOD']) {
	case "POST":
		$controller = new TaskController();
		$controller->create(new Task(), $db, $_POST, $oprId, $log);
		break;
	case "GET":
		switch($verb) {
		case "view":
			$controller = new TaskController();
			$task = new Task();
			if(isset($params['oprid'])) {
				$task->loadByOprid($params['oprid'], isset($params['future']));
						} else {
							$task->load($params['clientid'], $db);
						}

						$controller->view($task);

						break;
				}
				break;
		}
		break;


		/*
		 * Text Routing
		 * Options
		 */
case 'text':
	require_once 'controllers/TextController.php';
	$textController = new TextController();
	$method = strtolower($_SERVER['REQUEST_METHOD']);

	switch ($_SERVER['REQUEST_METHOD']) {
	    case "GET":
            switch ($verb) {
            case "bulkump":
                $sql = "SELECT clientid FROM test.ump";
                $result = $db->query($sql);
                foreach ($result as $client) {
                    $params = array("clientId" => $client['clientid'],
                        "templateId" => '43',
                        "oprId" => 'system',
                        "notificationType"  => "billing-ump");
                    $textController->postForBilling($params, new Curl(), $db, SEND_SMS, $smsWhiteList);
                    sleep(5);
                            }
                            break;
                    }
            break;
        case "POST":
            switch($verb) {
                case "update-twilio-status":
                    $textController->updateTwilioStatus($params['sid'], $params['status']);
                    break;

                case "update-twilio-response":
                    $textController->updateTwilioResponse($params['from'], $params['to'], $params['message']);
                    break;
                default:
                    if (!method_exists($textController, $method)) {
                        $textController->badRequest();
                        break;
                    }
                    $textController->$method($_POST, new Curl(), $db, SEND_SMS, $smsWhitelist);
                    break;
            }
            break;
        default:
            if (!method_exists($textController, $method)) {
                $textController->badRequest();
                break;
			}
			$textController->$method($_POST, new Curl(), $db, SEND_SMS, $smsWhitelist);
			break;
	}

	break;

		/*
		 * Queue Routing
		 * Options
		 */
case 'queue':
	require_once 'classes/PaymentOption.php';
	require_once 'classes/Client.php';
	require_once 'classes/ClientCreditor.php';
	require_once 'classes/Offer.php';
	require_once 'controllers/OfferController.php';
	require_once 'classes/QueueNegotiator.php';
	require_once 'classes/QueueSA.php';
	require_once 'classes/QueueBulk.php';
	require_once 'classes/QueueWelcomeCall.php';
	require_once 'classes/QueuePAS.php';

	$controller = new QueueController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case "GET":
		switch ($verb) {
		case "view":
			$queue = new Queue($db);
			$queue->loadByGroupName($params['group'], $params['id'], $db);
			$controller->getQueueByClientId($queue);
			break;
		case "refreshmath":
			$controller->refreshmath($params["queuename"], $db);
			break;
		case "getqueue":
			$controller->getQueue($params["queuename"], $db, $params["varyingpercent"]);
			break;
		case "getqueueitem":
			$controller->getQueueItem($params["queueid"], $db, $params["varyingpercent"]);
			break;
		case "getqueueitembycc":
			$controller->getQueueItemByCC($params["clientid"], $db, $params["client_creditor_id"]);
			break;
		case "refreshmathqueueitem":
			$controller->refreshMathQueueItem($params["queueid"], $db);
			break;
		case "addnote":
			$controller->addNote($params['queueid'], $params['note'], $db);
			break;
		case "updatequeueitem":
			$controller->updateQueueItem($params['queueid'], $params, $db);
			break;
		case "processqueueitem":
			$controller->processQueueItem($params['queueid'], $params, $db);
			break;
		case "getworkingqueueitem":
			$controller->getWorkingQueueItem($db);
			break;
		case "getqueueoptions":
			$controller->getQueueOptions($db);
			break;
		case "removesipclients":
			$controller->removeSIPClients($db);
			break;
		default:
			Controller::badRequest();
			break;
					}
					break;

case "POST":
	switch ($verb) {
	case "insert":
		$queueObj = new Queue($db);
		$queue_id = $queueObj->insert($params);

		// send back the queue_id when added/inserted
		echo json_encode($queue_id);
		break;

	case "processqueueitem":
		$controller->processQueueItem($params['queueid'], $params, $db);
		break;
	default:
		Controller::badRequest();
		break;
			}
			break;

default:
	Controller::badRequest();
	break;
	}
break;


/*
 * Client Routing
 * Options GET PUT
 */
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
	case "track_login":
		$client = new Client();
		$client->load($params['id'], $db);
		$controller->track_login($params['platform'], $client, $db);
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
	case 'cancel_reason':
		$cancelreason = new CancelReason($params['clientid']);
		$controller->getCancelReason($cancelreason);
		break;
	case "contact_log":
		$client = new Client();
		$client->load($id, $db);
		$controller->contact_log($client, $db);
		break;
	case "clear_badge":
		$client = new Client();
		$client->load($id, $db);
		$controller->clearBadge($client, $db);
		break;
	case "client_site":
		$client = new Client();
		$client->load($params['clientid'], $db);
		$controller->setClientSiteUser($client, $params['oprid']);
		break;
	case "increase_badge":
		$client = new Client();
		$client->load($id, $db);
		$controller->increaseBadge($client, $db);
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
		break;

        case 'latest_client_contact':
            $client = new ClientController();
            $latest = $controller->get_latest_contact($params['clientid']);
            echo json_encode($latest);
            break;

    case 'check_autologin':
        $client = new Client();
        $client->load($id, $db);
        $controller->login($client, $db);
        break;

    case 'fund':
        $client = new Client();
        $client->load($params['id'], $db);
        $funds = $client->getMonthlyFund($db, $params['numfund'], $params['startdate']);
        echo json_encode($funds);
        break;
    case 'pendingdraft':
        $client = new Client();
        $client->load($params['id'], $db);
        $pendingAmount = $client->getPendingDraft($db);
        var_dump($pendingAmount);
        break;
    case 'getavailablefund':
        $client = new Client();
        $client->load($params['id'], $db);
        $pendingAmount = $client->getAvailableFund($db);
        echo $pendingAmount;
        break;
    case 'getmonthlydrafts':
        $client = new Client();
        $client->load($params['id'], $db);
        $drafts = $client->getMonthlyDrafts($db, 5, '', false, true);
        var_dump($drafts);
        break;
    case 'getwarnings':
        $controller->getWarnings();
        break;

    case 'globalbal':
        $client = new Client($params['id']);
        $client->load($params['id'], $db);

        echo $client->getGCSPendingBalance($db);
        break;

    case 'getreferrals':
        $referral = new ClientReferral($params['clientid']);
        $response = $referral->ledger();

        echo json_encode($response);
        break;

    case 'addreferral':
        $referral = new ClientReferral($params['clientid']);
        $response = $referral->add($params);

        echo json_encode($response);
        break;

    case 'editreferral':
        $referral = new ClientReferral($params['clientid']);
        $response = $referral->edit($params);

        echo json_encode($response);
        break;

    case 'sendreferral':
        $referral = new ClientReferral($params['clientid']);
        $referral->set_id($params['id']);
        $response = $referral->send(true, null);

        echo json_encode($response);
        break;

    case 'joinreferral':
        $referral = new ClientReferral();
        $response = $referral->join($params['code']);

        echo json_encode($response);
        break;

    case 'sendintro':
        $referral = new ClientReferral($params['clientid']);
        $referral->set_id($params['id']);
        $response = $referral->sendinto();

        echo json_encode($response);
        break;
    case 'accounting':
            $clientController = new ClientController($db, $params['id']);
            $response = $clientController->getClientAccountingSummary($params['id']);
            echo json_encode($response);
            break;
default:
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$client = new Client();
		$client->load($id, $db);
		$controller->view($client);
		break;
	case 'PUT':
		$params = null;
		parse_str(file_get_contents('php://input'), $params);
		if (!isset($params['id'])) {
			Controller::sendHeader('HTTP/1.1 400 Bad Request');
			echo json_encode(
				array('errors' => array('Missing Clients id')));
			print_r($params);
			break;
							}
		//echo '<pre>'; print_r($params);
	//if ($params['id'] == '') mail('ly.nguyen@landmarktx.com', 'client', var_export($params, true));
							$client = new Client();
							$client->load($params['id'], $db);
							$controller->update($params, $client, $db, new Curl());
							break;
default:
	Controller::badRequest();
	break;
					}
					break;
		}
		break;


		/*
		 * SMS Session Routing
		 * Options GET PUT POST
		 */
case 'smssession':
	require_once 'classes/SMSSession.php';
	require_once 'controllers/SMSSessionController.php';
	$controller = new SMSSessionController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$sms_session = new SMSSession();
		$sms_session->load($id, $db);
		$controller->view($sms_session);
		break;
	case 'PUT':
		$params = null;
		parse_str(file_get_contents('php://input'), $params);
		if (!isset($params['id'])) {
			Controller::sendHeader('HTTP/1.1 400 Bad Request');
			echo json_encode(
				array('errors' => array('Missing Session id')));
			break;
				}
				$sms_session = new SMSSession();
				$sms_session->loadId($params['id'], $db);
				$controller->update($params, $sms_session, $db, new Curl());
				break;
case 'POST':
	if (!isset($_POST['session_number'])) {
		Controller::sendHeader('HTTP/1.1 400 Bad Request');
		echo json_encode(
			array('errors' => array('Missing Session Number')));
		break;
				}
				$sms_session = new SMSSession();
				$controller->create($_POST, $sms_session, $db, new Curl(), $log);
				break;
default:
	Controller::badRequest();
	break;
		}
		break;


		/*
		 * API Session Routing
		 * Options GET PUT POST
		 */
case 'apisession':
	require_once 'classes/ApiSession.php';
	require_once 'controllers/ApiSessionController.php';
	$controller = new ApiSessionController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case "delete_session":
			$api_session = new ApiSession();
			$api_session->loadId($id, $db);
			$controller->deleteSession($api_session, $db);
			break;
		default:
			$api_session = new ApiSession();
			$api_session->loadId($id, $db);
			$controller->view($api_session);
			break;
				}
				break;
case 'PUT':
	$params = null;
	parse_str(file_get_contents('php://input'), $params);
	if (!isset($params['session_key'])) {
		Controller::sendHeader('HTTP/1.1 400 Bad Request');
		echo json_encode(
			array('errors' => array('Missing Session key')));
		break;
				}
				$api_session = new ApiSession();
				$api_session->loadId($params['id'], $db);
				$controller->update($params, $api_session, $db, new Curl());
				break;
case 'POST':
	if (!isset($_POST['session_key'])) {
		Controller::sendHeader('HTTP/1.1 400 Bad Request');
		echo json_encode(
			array('errors' => array('Missing Session Key')));
		break;
				}
				$api_session = new ApiSession();
				$controller->create($_POST, $api_session, $db, new Curl(), $log);
				break;
default:
	Controller::badRequest();
	break;
		}
		break;


		/*
		 * SMS Template Routing
		 * Options GET PUT
		 */
case 'sms_template':
	require_once 'classes/SMSTemplate.php';
	require_once 'controllers/SMSTemplateController.php';
	require_once 'classes/ContactLog.php';
	$controller = new SMSTemplateController();
	switch ($verb) {
	default:
		switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			$template = new SMSTemplate();
			$template->load($id, $db);
			$controller->view($template);
			break;
		case 'POST':
			$params = json_decode(file_get_contents('php://input') ,true);

			$template = new SMSTemplate();
			if(isset($params['id'])) {
				$template->load($params['id'], $db);
			}
			$template->save($params);
			break;
		default:
			Controller::badRequest();
			break;
				}
		}
		break;

		/*
		 * Asterisk Call Creation
		 * Using AMI
		 * Options POST
		 */
case 'asterisk':
	require_once 'controllers/AsteriskController.php';
	require_once 'classes/Asterisk.php';
	$controller = new AsteriskController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		if (empty($_POST['number'])) {
			Controller::sendHeader('HTTP/1.1 400 Bad Request');
			echo json_encode(
				array('errors' => array('Missing Phone Number'))
			);
			break;
				}
				if (empty($_POST['extension'])) {
					Controller::sendHeader('HTTP/1.1 400 Bad Request');
					echo json_encode(
						array('errors' => array('Missing Extension'))
					);
				}
				$asterisk = new Asterisk();
				$controller->create($_POST, $asterisk);
				break;
default:
	Controller::badRequest();
	break;
		}
		break;
		/*
		 * DEFAULT Routing
		 * Options ALL
		 *
		 * Aspect DataDip
		 * Options GET
		 */
case 'datadip':
	require_once 'classes/DataDip.php';
	require_once 'controllers/DataDipController.php';
	$controller = new DataDipController();
	switch($verb) {
	case 'isclient':
		switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			if (empty($params['ani'])) {
				Controller::sendHeader('HTTP/1.1 400 Bad Request');
				echo json_encode(
					array('errors' => 'Missing ANI')
				);
				break;
				}
				if (empty($params['dnis'])) {
					Controller::sendHeader('HTTP/1.1 400 Bad Request');
					echo json_encode(
						array('errors' => 'Missing DNIS')
					);
					break;
				}
		if (empty($params['transid'])) {
/*		    Controller::sendHeader('HTTP/1.1 400 Bad Request');
			echo json_encode(
			array('errors' => 'Missing TransID');
}*/
			$params['transid'] = null;
			break;
		}
				$datadip = new DataDip();
				$datadip->load($params['ani'], $params['dnis'], $params['transid']);
				$controller->isClient($datadip);
				break;
		}
	break;
case 'extvm':
	$datadip = new DataDip();
	$datadip->load($params['ani'], $params['dnis'], $params['serviceid']);
	$controller->extVm($datadip);
	break;
case 'fax':
	$datadip = new DataDip();
	$datadip->load($params['ani'],$params['dnis'],$params['transid']);
	$controller->fax($datadip);
	break;
}
break;

case 'cron':
	require_once 'controllers/CronController.php';
	require_once 'classes/Client.php';
	$controller = new CronController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
            case "import-client-transactions":
                $controller->importTransactions($db);
                break;
		case "sendt90letter":
			$controller->sendT90Letter($db);
			break;
		case "sendoptinletter":
			$controller->sendOptInLetter($db);
			break;
		case "sendt90letter_fixdrafts":
			$controller->sendT90Letter_fixDrafts($db);
			break;
				}
				break;
		}
		break;

case 'clienttomail':
	require_once 'classes/ClientToMail.php';

	$clientToMailObj = new ClientToMail($db);

	switch ($verb)  {
	case "updatet90lettersent":
		$clientIdArray = explode(",", $params['clientids']);
		$clientToMailObj->updateMailSent($clientIdArray, 'T90');
		break;
	case "updateasdlettersent":
		$clientIdArray = explode(",", $params['clientids']);
		$clientToMailObj->updateMailSent($clientIdArray, 'ASD');
		break;
		}
case 'gcs1610':
	require_once 'controllers/GlobalBillingController.php';
	$gcsObj = new GlobalBillingController($db);
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch($verb) {
		case 'getach':
			$gcsObj->getACH($params['startdate'], $params['enddate'], $params['divisionid']);
			break;
		case 'getachfile':
			$gcsObj->getACHFile($params['startdate'], $params['enddate'], $params['divisionid']);
			break;
				}
case 'POST':
	$params = json_decode(file_get_contents('php://input'), true);
	break;
		}
		break;
case 'ump':
	require_once 'controllers/UMPController.php';
	$umpObj = new UMPController($db);

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'getach':
			$umpObj->getACH($params['startdate'], $params['enddate'], $params['type']);
			break;
		case 'getachfile':
			if($params['processor'] == 'paya' || $params['processor'] == 'sps') {
				$umpObj->getACHFile($params['startdate'], $params['enddate'], $params['type'], $params['processor']);
			} else {
				$umpObj->getCSVFile($params['startdate'], $params['enddate'], $params['type'], $params['processor']);
			}
			break;
            case 'process-result-file':
                $spsObj = new SPS();
                $spsObj->processResultFile();
                break;
			}
			break;
case 'POST':
	switch ($verb) {
	case 'previewumpnsf':
		$umpObj->previewUpload($params['upload']);
		break;
	case 'processnsf':
		$params = json_decode(file_get_contents('php://input'), true);
		$umpObj->processUMPNSF($params);
		break;
	case 'depositchecknsf':
		$umpObj->depositCheckNSF($params['file']);
		break;
	case 'achresult':
		$umpObj->processUMPAchResult($params['file']);
		break;
			}
			break;
		}
		break;
case 'general':
	$generalObj = new General($db);
	switch ($verb) {
	case 'getstates':
		$states = $generalObj->getStates();
		header("Access-Control-Allow-Origin: *");
		echo json_encode($states);
		break;
	case 'getbusinessdayprior':
		$date = $generalObj->getBusinessDayPrior($params['date']);
		header("Access-Control-Allow-Origin: *");
		echo json_encode($date);
		break;
	case 'get-first-date-to-draft':
		$date = $generalObj->getFirstDateToDraft();
		header("Access-Control-Allow-Origin: *");
		echo json_encode($date);
		break;
	case 'client-info':
		$result = $generalObj->getClientInfo($params['clientid']);
		header("Access-Control-Allow-Origin: *");
		echo json_encode($result);
		break;
	case 'client-statements':
		$result = $generalObj->getClientStatements($params['clientid']);
		header("Access-Control-Allow-Origin: *");
		echo json_encode($result);
		break;
	case 'mobileuploads':
	            if (!isset($params['getall']) || empty($params['getall'])) {
	                $params['getall'] = 0;
                }
                $result = $generalObj->getClientMobileUploads($params['clientid'], $params['getall']);
                header("Access-Control-Allow-Origin: *");
                echo json_encode($result);
                break;
	case 'update-available-fund':
		$generalObj->updateAvailableFund();
		break;

		// jmonroe
	case 'faq':
		$result = $generalObj->getFaq($params['clientid']);
		header("Access-Control-Allow-Origin: *");
		$json_encode = new JsonEncode();
		echo $json_encode->toJson($result);
		// echo json_encode($result);
		break;
	case 'faqreasons':
        	$result = $generalObj->getFaqReasons();
        	header("Access-Control-Allow-Origin: *");
        	echo json_encode($result);
        	break;
	case 'branding':
		$result = $generalObj->getBranding($params['divisionid']);
		header("Access-Control-Allow-Origin: *");
		echo json_encode($result);
		break;
		}

		break;

case 'bank':
	require_once 'controllers/BankController.php';
	require_once 'classes/ClientBankAccountAudit.php';

	$bankController = new BankController();
	$bankAudit = new ClientBankAccountAudit($db);

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'getbankinfo':
			$bankController->getBankInfo($params['clientid']);
			break;
		case 'getbankrequestinfo':
			$bankController->getBankInfoChangeRequest($params['clientid']);
			break;
		case 'getbankaudit':
			$bankController->getBankAudit($params['clientid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
    if ($params == NULL) {
        $params = $_POST;
    }

    define('OPRID', $params['oprId']);

	switch ($verb) {
	case 'requestbankchange':
		$bankController->requestBankChange($params);
		$params['audit_page'] = $_SERVER['REDIRECT_URL'];
		$bankAudit->insert($params);
		break;
				}
				break;
		}
		break;

case 'client_new':
	require_once 'classes/Client.php';

	switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $clientObj = new Client($params['clientid']);
            switch ($verb) {
            case 'getinfo':
                header("Access-Control-Allow-Origin: *");
                echo json_encode($clientObj->clientInfo);
                break;
                    }
                    break;
        case 'POST':
            $params = json_decode(file_get_contents('php://input') ,true);
            if ($params == null) {
                $params = $_POST;
            }

            define('OPRID', $params['oprId']);

            $clientObj = new Client($params['clientid']);

            switch ($verb) {
                case 'updatefinance':
                    $result = $clientObj->updateFinance($params);
                    echo json_encode($result);
                    break;
            }
            break;
	}
    break;

case 'billingglobal':
	$controller = new BillingController();

	switch($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		define('OPRID', $params['oprId']);

		switch ($verb) {
		case 'balanceupload':
			$controller->globalBalanceUpload($params);
			break;
		case 'previewnsfupload':
			$controller->previewNSFUpload($params);
			break;
		case 'nsfupload':
			if(isset($params['preview'])) {
				$params = json_decode(file_get_contents('php://input') ,true);
				$controller->globalNSFUpload(array(), $params);
			} else {
				$controller->globalNSFUpload($params);
			}
			break;
case 'accountnumbersupload':
	$controller->accountNumbersUpload($params);
	break;
				}
				break;
		}
		break;

case 'billing':
	require_once 'controllers/BillingController.php';
	$controller = new BillingController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case "getpayments":
			$controller->getClientPromisePayments($params['clientid'], $db);
			break;
		case "getclientaccounting":
			$controller->getClientAccounting($params['clientid'], $db);
			break;
		case "getumpclientaccounting":
			$controller->getUMPClientAccounting($params['clientid']);
			break;
		case "getdefaultcommissionoprid":
			$controller->getDefaultCommissionOprid($params['clientid'], $db);
			break;
		case "getcommissionusers":
			$controller->getCommissionUsers($db);
			break;
		case "getcreditoraccounts":
			$controller->getCreditorAccounts($params['clientid'], $db);
			break;
		case "getglobalaccounttrans":
			$controller->getGlobalAccountTrans($params);
			break;
		case "getschedulefee":
			$controller->getScheduleFee($params['clientid'], $db);
			break;

        // jmonroe - gets deposit schedule for client for the portal
        case 'getdepositschedule':
            $controller->getDepositSchedule($params['clientid'], $db);
            break;

		case "getumpschedulefee":
			$controller->getUMPScheduleFee($params['clientid'], $db);
			break;
		case "deletedraft":
			mail('ly.nguyen@landmarktx.com', 'deletedraft', var_export($params, true));
			$controller->deleteDraft($params['id'], $db);
			break;
		case "savedraft":
			$controller->saveDraft($params['id'], $params, $db);
			break;
		case "copydraft":
			$controller->copyDraft($params['clientid'], $db);
			break;
		case "adddraft":
			$controller->addDraft($params, $db);
			break;
		case "reschedule":
			$controller->rescheduleDraft($params, $db);
			break;
		case "getpositivejournaltypes":
			$controller->getPositiveJournalTypes($db);
			break;
		case "getjournaltypes":
			if (isset($params['all']) && $params['all'] == '1') {
				$all = true;
				} else {
					$all = false;
				}
				$controller->getJournalTypes($db, $all);
				break;
case "getpaymentcategories":
	if (isset($params['all']) && $params['all'] == '1') {
		$all = true;
				} else {
					$all = false;
				}
				$controller->getPaymentCategories($db, $params['clientid'], $all);
				break;
case "getpaymenttypes":
	$controller->getPaymentTypes($db);
	break;
case "changepaymentday":
	$controller->changePaymentDay($params['clientid'], $params['day'], $db);
	break;
case 'skippayment':
	$controller->skipPayment($params['clientid'], $db);
	break;
case 'skippartialpayment':
	$controller->skipPartialPayment($params['clientid'], $params['amount'], $db);
	break;
case 'deleteallpayments':
	mail('ly.nguyen@landmarktx.com', 'deleteallpayments', var_export($params, true));
	$controller->deleteAllPayments($params['clientid'], $db);
	break;
case 'getnextdraftamount':
	$controller->getNextDraftAmount($params['clientid'], $db);
	break;
case 'getfees':
	$controller->getFees($params['clientid'], $db);
	break;
case 'gettypescategories':
	$controller->getTypesCategories($db, $params['clientid'], $params['page']);
	break;
		}
		break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define('OPRID', $params['oprId']);

	switch ($verb) {
	case 'schedulemultiplepayments':
		$controller->scheduleMultiplePayments($params, $db);
		break;
	case "saveclientjournal":
		$controller->saveClientJournal($params['id'], $params, $db);
		break;
	case "reverseclientjournal":
		$controller->reverseClientJournalPayment($params, $db);
		break;
	case "newclientjournal":
		$controller->newClientJournal($params, $db);
		break;
	case "deleteclientjournal":
		$controller->deleteClientJournal($params['id'], $db);
		break;
	case "postdraft":
		$controller->postDraft($params['id'], $params, $db);
		break;
		}
		break;
}
break;

case 'settlement':
	require_once 'controllers/SettlementController.php';
	$controller = new SettlementController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getallschedule':
			$controller->getAllSchedule($params['clientid']);
			break;
		case 'getpaymenttypes':
			$controller->getPaymentTypes();
			break;
		case 'getagencies':
			$controller->getAgenciesWithActiveOffers($params['clientid']);
			break;
		case 'getalljournal':
			$controller->getAllJournal($params['clientid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	if (isset($params['oprId'])) {
		define(OPRID, $params['oprId']);
				} else {
					define(OPRID, $params[0]['oprId']);
				}

				switch ($verb)  {
				case 'addschedule':
					$controller->addSchedule($params);
					break;
				case 'updateallschedule':
					$controller->updateAllSchedule($params);
					break;
				case 'deleteschedule':
					$controller->deleteSchedule($params['journalid']);
					break;
				case 'splitschedule':
					$controller->splitSchedule($params);
					break;
				}
				break;
		}
		break;

case 'warning':
	require_once 'controllers/WarningController.php';
	$controller = new WarningController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getall':
			$controller->getAllByClientId($params['clientid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	switch ($verb)  {
	case 'add':
		$controller->add($params);
		break;
	case 'update':
		$controller->update($params);
		break;
	case 'delete':
		$controller->remove($params['id']);
		break;
				}
				break;
		}
		break;

case 'tasknew':
	require_once 'controllers/TaskNewController.php';
	$controller = new TaskNewController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getall':
			$controller->getAllByClientId($params['clientid']);
			break;
		case 'getusers':
			$controller->getUsers();
			break;
		case 'getclientcreditors':
			$controller->getClientCreditors($params['clientid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'add':
		$controller->add($params);
		break;
	case 'update':
		$controller->update($params);
		break;
	case 'delete':
		$controller->remove($params['taskid'], $params);
		break;
	case 'updatemass':
		$controller->updateMass($params);
		break;
				}
				break;
		}
		break;

case 'plan':
	require_once 'controllers/PlanController.php';
	$controller = new PlanController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getreviewboardtimeoptions':
			$controller->getReviewBoardTimeOptions();
			break;
		case 'getplaninfo':
			$controller->getPlanInfo($params['clientid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);
	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'update':
		$controller->update($params);
		break;
				}
				break;
		}
		break;

case 'financial':

	require_once 'controllers/FinancialController.php';
	$controller = new FinancialController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'gethardship':
			$controller->getHardshipByClientId($params['clientid']);
			break;
		case 'getincome':
			$controller->getIncomeByClientId($params['clientid']);
			break;
		case 'getexpense':
			$controller->getExpenseByClientId($params['clientid']);
			break;
		case 'getbudget':
			$controller->getBudgetByClientId($params['clientid']);
			break;
			}
			break;

case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);

	if (empty($params)) {
		$params = $_POST;
			}

			define(OPRID, $params['oprId']);
			$params['oprid'] = $params['user'];

			switch ($verb)  {
			case 'savehardship':
				$controller->saveHardShip($params);
				break;
			case 'saveincome':
				$controller->saveIncome($params);
				break;
			case 'saveexpense':
				$controller->saveExpense($params);
				break;
			case 'savebudget':
				$controller->saveBudget($params);
				break;
				// this method might be defunct - jmonroe
			case 'savebudgetincome':
				$controller->saveBudgetIncome($params);
				break;
				}
				break;
		}
		break;

case 'client_audit':
	require_once 'controllers/ClientAuditController.php';
	$controller = new ClientAuditController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getall':
			$controller->getAll($params['clientid']);
			break;

		case 'gethistorybyfield':
			$controller->getHistory($params['clientid'], $params['field_name']);
			break;
				}
				break;
		}
		break;

case 'service':
	require_once 'controllers/ServiceController.php';
	$controller = new ServiceController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getservicebyclientid':
			$controller->getAllByClientId($params['clientid']);
			break;
		case 'getservicestatusoptions':
			$controller->getServiceStatusOptions();
			break;
		case 'getnextpaymentdate':
			$controller->getNextPaymentDate($params['clientid']);
			break;
		case 'getservicejournal':
			$controller->getServiceJournal($params['clientid']);
			break;
		case 'getumpjournal':
			$controller->getUMPJournal($params['clientid']);
			break;
		case 'getumpschedule':
			$controller->getUMPSchedule($params['clientid']);
			break;
		case 'gettransactionstatusoptions':
			$controller->getTransactionStatusOptions();
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	switch ($verb)  {
	case 'update':
		$controller->update($params);
		break;
	case 'add':
		$controller->insert($params);
		break;
	case 'delete':
		$controller->remove($params['autoId']);
		break;
	case 'updateservicejournal':
		$controller->updateServiceJournal($params);
		break;
	case 'deleteservicejournal':
		$controller->removeServiceJournal($params['id']);
		break;
				}
				break;
		}
		break;

case 'history':
	require_once 'controllers/HistoryController.php';
	$controller = new HistoryController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getclientinfo':
			$controller->getClientInfo($params['clientid']);
			break;
				}
				break;
		}
		break;

case 'creditor':
	require_once 'controllers/CreditorController.php';
	$controller = new CreditorController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getcreditorinfo':
			$controller->getCreditorInfo($params['creditorid']);
			break;
		case 'search':
			$controller->search($params);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'add':
		$controller->add($params);
		break;
	case 'update':
		$controller->update($params);
		break;
	case 'remove':
		$controller->remove($params['creditorid']);
		break;
				}
				break;
		}
		break;

case 'agency':
	require_once 'controllers/AgencyController.php';
	$controller = new AgencyController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getagencyinfo':
			$controller->getAgencyInfo($params['agencyid']);
			break;
		case 'search':
			$controller->search($params);
			break;
        case 'import-payees':
            $controller->importGCSPayees($params['divisionid']);
            break;
        case 'get-all-active':
            $controller->getAllActive();
            break;
            }
            break;

case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'add':
		$controller->add($params);
		break;
	case 'update':
		$controller->update($params);
		break;
	case 'remove':
		$controller->remove($params['agencyid']);
		break;
				}
				break;
		}
		break;

case 'agencyinfo':
	require_once 'controllers/AgencyInfoController.php';
	$controller = new AgencyInfoController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getallname':
			$controller->getAllName($params['agencyid']);
			break;
		case 'getallphone':
			$controller->getAllPhone($params['agencyid']);
			break;
		case 'getalladdress':
			$controller->getAllAddress($params['agencyid']);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'addname':
		$controller->addName($params);
		break;
	case 'updatename':
		$controller->updateName($params);
		break;
	case 'removename':
		$controller->removeName($params['anameid']);
		break;
	case 'addphone':
		$controller->addPhone($params);
		break;
	case 'updatephone':
		$controller->updatePhone($params);
		break;
	case 'removephone':
		$controller->removePhone($params['aphoneid']);
		break;
	case 'addaddress':
		$controller->addAddress($params);
		break;
	case 'updateaddress':
		$controller->updateAddress($params);
		break;
	case 'removeaddress':
		$controller->removeAddress($params['aaddressid']);
		break;
				}
				break;
		}
		break;

case 'billing-service':
	require_once 'controllers/BillingServiceController.php';
	$serviceObj = new BillingServiceController($params['service']);

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'getach':
			$serviceObj->getACH($params['startdate'], $params['enddate'], $params['type']);
			break;
		case 'getachfile':
			$serviceObj->getACHFile($params['startdate'], $params['enddate'], $params['type']);
			break;
				}
				break;
case 'POST':
	switch($verb) {
	case 'baresult':
		$serviceObj->benefitsAssuranceResults($params['file']);
		break;
				}
				break;
		}
case 'ach-directory':
	require_once 'controllers/ACHDirectoryController.php';
	$controller = new ACHDirectoryController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb)  {
		case 'getachinfo':
			$controller->getACHDirectoryInfo($params['routing_nbr']);
			break;
		case 'search':
			$controller->search($params);
			break;
				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'add':
		$controller->add($params);
		break;
	case 'update':
		$controller->update($params);
		break;
				}
				break;
		}
		break;
case 'salesforce':
	require_once 'controllers/SFDCLeadController.php';
	require_once 'classes/SFDCLead.php';
	require_once 'controllers/SFDCClientController.php';
	require_once 'classes/SFDCClient.php';
	echo '5'; die();
	require_once 'lib/forge_fdf.php';
	var_dump($verb); die();
	switch($verb) {
	case 'lead':
		switch($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			$l=new SFDCLead($params['id']);
			$controller = new SFDCLeadController($params['id']);
			$controller->view($l);
			break;
			}
			break;
case 'client':
	switch($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		$c = new SFDCClient($params['id']);
		$controller = new SFDCClientController($c);
		$controller->view($c);
		break;
	case 'PUT':
		$c = new SFDCClient($params['id']);
		$controller = new SFDCClientController($c);
		$res = $controller->pushToDM($c, $db);
		if(!isset($res['success'])) {
			mail('mramsey@landmarktx.com','SF Push to dm Failed.','Salesforce Id: '.$params['id'] . PHP_EOL . var_export($res,true));
			mail('josh.schuler@landmarktx.com', 'SF Push to dm Failed.', 'Salesforce Id: ' . $params['id'] . PHP_EOL . var_export($res,true));
				} else {
					if($c->ump) {
						$sqlump = "select descr from client_services cs left join xlattable x on x.fieldname = 'service_status' and x.value=cs.service_status where clientid = $c->clientid";
						$resump = $db->query($sqlump);
						$resump=reset($resump);
						$c->update(array('UMP_Status__c'=>$resump['descr']));
					}
					$c->update(array('Client_Created_in_DM__c'=>'True'));
				}
				echo json_encode($res);
				break;
			}
		break;
case 'creditinfo':
	require_once 'controllers/SFDCHelperController.php';
	$controller = new SFDCHelperController();
	$res = $controller->getContactInfoByClientid($params['clientid'], $params['type']);
	echo json_encode($res);
	break;
case 'prepareagreement':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$res = $controller->prepareDocument($c, 'agreement', false);
	$c->update(array('Prepare_Agreement__c' => 'False', 'Agreement_Url__c' => $res));
	echo json_encode(array('success'=>'true'));
	break;
case 'esignagreement':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'agreement', false);
	$res = $controller->esignDocument($c, $doc, 'agreement', false);
	$c->update(array('Send_Agreement__c' => 'False', 'Agreement_Url__c' => $doc));
	$c->createActivityHistory('Agreement sent via email');
	echo json_encode(array('success'=>'true'));
    break;
case 'faxall':
    $c = new SFDCClient($params['id']);
    $e = new ESignClient($c, $params['oprid'], $db);
    $controller = new ESignController();
    $sfdc_controller = new SFDCClientController($c);
    $aDoc = $controller->prepareDocument($e, 'agreement', true);
    $gDoc = $controller->prepareDocument($e, 'global', true);
    if($c->ump)
        $uDoc = $controller->prepareDocument($e, 'ump', true);
    else
        $uDoc = '';
    $sfdc_controller->sendDocument($c, $aDoc, 'agreement', 'fax');
    $sfdc_controller->sendDocument($c, $gDoc, 'global', 'fax');
    if($c->ump) {
        $sfdc_controller->sendDocument($c, $uDoc, 'ump', 'fax');
    }
    $c->createActivityHistory('Agreement Sent via Fax');
    $c->createActivityHistory('Global App Sent Via Fax');
    if($c->ump)
        $c->createActivityHistory('UMP Agreement Sent Via Fax');
    $c->update(array('Fax_Agreement__c'=>'False', 'Agreement_Url__c' => $aDoc, 'Global_Url__c' => $gDoc, 'UMP_Agreement_Url__c' => $uDoc));
    echo json_encode(array("success"=>true));
	break;
case 'esign2':
    try {
		$type = $params['type'];
		if(!isset($params['sendtorep']) || $params['sendtorep'] == '')
			$sendtorep = false;
		else
			$sendtorep = filter_var($params['sendtorep'], FILTER_VALIDATE_BOOLEAN);

	if(!isset($params['oprid']) || $params['oprid'] == '') {
		echo json_encode(array('error'=>'Must provide oprid.'));
		exit;
    }
    if(!isset($params['sms']))
        $sms = false;
    else
        $sms = filter_var($params['sms'], FILTER_VALIDATE_BOOLEAN);

	if(!isset($params['coapp']))
		$isCoApp = false;
	else
		$isCoApp = filter_var($params['coapp'], FILTER_VALIDATE_BOOLEAN);

	if($params['from'] == 'salesforce')
		$c = new SFDCClient($params['id']);
	else {
		require_once('/var/www/calapi/classes/ext/Client.php');
		$c = new ExtClient($db, $params['id']);
	}

	$e = new ESignClient($c, $params['oprid'], $db);
	$controller = new ESignController();
	if($e->spanishSpeaker)
		$type = 'spanish ' . $type;
	if(strtolower($type) == 'both'){
		$aDoc = $controller->prepareDocument($e, 'agreement', false, $isCoApp);
		$gDoc = $controller->prepareDocument($e, 'global', false, $isCoApp);
		$docArray = array('agreement'=>$aDoc,'global' =>$gDoc);
	} elseif(strtolower($type) == 'spanish both') {
		$aDoc = $controller->prepareDocument($e, 'agreement', false, $isCoApp);
		$gDoc = $controller->prepareDocument($e, 'global', false, $isCoApp);
		$docArray = array('spanish agreement'=>$aDoc,'spanish global' =>$gDoc);
	} else {
		$doc = $controller->prepareDocument($e, $type, false, $isCoApp);
		$docArray = array($type => $doc);
	}
#	$aDoc = $controller->prepareDocument($e, $type, false);
    if($sms) {
        $sfdc_controller = new SFDCClientController($c);
        $pass = $sfdc_controller->generatePin($c);
    }
    $res = $controller->esignDocument_v2($e, $docArray, $type, $sms, $isCoApp, $params['from'], $pass, $sendtorep);
	if($sms) {
		switch($e->divisionId) {
		case '9':
			$comp = 'NetDebt';
			break;
		case '10':
			$comp = 'Worden & Associates';
			break;
		case '13':
			$comp = 'American Debt Relief';
            break;
        case '14':
            $comp = 'CreditAssociates';
            break;
		}
		$t = new TextController();
		if($e->spanishSpeaker) {
			$t->sendToTwilio("Su número de identificación personal PIN es: $pass\r\nUtilice su PIN para acceder a sus documentos de $comp y firmar electrónicamente.", $c->smsPhone, new Curl(), '', 'esign');
			$t->sendToTwilio("Haga clic a este enlace para acceder a sus documentos de $comp y firmar electrónicamente.\r\n".$res->CreateTransactionsResult->Transactions->TransactionDetailsModel->Participants->ParticipantDetailsModel->Url, $c->smsPhone,new Curl(), '', 'esign');
		} else {
			$t->sendToTwilio("Your PIN is: $pass\r\nUse this PIN to access your $comp documents for e-signing.", $c->smsPhone, new Curl(), '', 'esign');
			$t->sendToTwilio("Click the link to access your $comp documents for e-signing.\r\n".$res->CreateTransactionsResult->Transactions->TransactionDetailsModel->Participants->ParticipantDetailsModel->Url, $c->smsPhone, new Curl(), '', 'esign');
		}
    }
	//var_export($res);
	//exit;
	if($sendtorep) {
		$sfdc_controller = new SFDCClientController($c);
		$msg = $res->CreateTransactionsResult->Transactions->TransactionDetailsModel->Participants->ParticipantDetailsModel->Url;
		$sfdc_controller->mail_attachment('', '', $e->userInfo->email, $e->userInfo->email, $e->userInfo->fullname, $e->userInfo->email, 'Electronic Signature link for '.$e->clientid, $msg, false);
	}
    if($sms == false && $sendtorep == false) {
        $c->createActivityHistory('Agreement sent via email');
        $c->createActivityHistory('Global Application Sent via email');
	} elseif($sms == false && $sendtorep == true) {
		$c->createActivityHistory('Agreement sent to Owner via email');
		$c->createActivityHistory('Global Application Sent to Owner via email');
	}else {
        $c->createActivityHistory('Agreement sent via SMS');
        $c->createActivityHistory('Global Application sent via SMS');
    }
    if($c->ump) {
        $controller = new SFDCClientController($c);
        $doc = $controller->prepareDocument($c, 'ump', false);
        if(!$sms) {
            $res = $controller->esignDocument($c, $doc, 'ump', false);
            $c->createActivityHistory('UMP Application Sent via email');
        } else {
            $res = $controller->esignDocument($c, $doc, 'ump', true);
			$msg = json_decode($res, true);
			if($c->spanishSpeaker) {
				$verbiage = "Haga clic a este enlace para acceder a sus documentos de United Member Plans y firmar electrónicamente.\r\n";
			} else {
				$verbiage = "Click the link to access your United Member Plans documents for e-signing.\r\n";
			}
            $msg = $verbiage . $msg['url'];
            $t = new TextController();
            $t->sendToTwilio($msg, $c->smsPhone, new Curl(), '', 'esign');
            $c->createActivityHistory('UMP Application Sent via SMS');
        }
    } else {
        $doc = '';
    }
    if($params['from'] == 'salesforce' && strtolower($params['type']) == 'both')
        $c->update(array('Send_Agreement__c' => 'False', 'Agreement_Url__c' => $aDoc, 'Global_Url__c'=>$gDoc, 'UMP_Agreement_Url__c' => $doc, 'SMS_Docs__c' =>'False', 'Send_ESIGN_to_Owner__c'=>'False'));
    echo json_encode(array('success'=>true));
	} catch (Exception $e) {
		echo 'ERROR: ' . $e->getMessage();
	}
    break;
case 'prepareall':
    $otype = 'both';
    $c = new SFDCClient($params['id']);
    $e = new ESignClient($c, $params['oprid'], $db);
    $controller = new ESignController();
    $ares = $controller->prepareDocument($e, 'agreement', false, false);
    $gres = $controller->prepareDocument($e, 'global', false, false);
    if($c->ump) {
        $controller = new SFDCClientController($c);
        $ures = $controller->prepareDocument($c, 'ump', false);
    } else {
        $ures = '';
    }
    $c->update(array('Prepare_Agreement__c'=>'False','Agreement_Url__c' => $ares,'Global_Url__c'=>$gres, 'UMP_Agreement_Url__c'=>$ures));
    break;
/*case 'prepareglobal':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$res = $controller->prepareDocument($c, 'global', false);
	$c->update(array('Prepare_Global_App__c' => 'False', 'Global_Url__c' => $res));
	echo json_encode(array('success'=>'true'));
	break;
case 'esignglobal':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'global', false);
	$res = $controller->esignDocument($c, $doc, 'global', false);
	$c->update(array('Send_Global_App__c' => 'False', 'Global_Url__c' => $doc));
	$c->createActivityHistory('Global Application Sent via email');
	echo json_encode(array('success'=>'true'));
	break;*/
case 'prepareump':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$res = $controller->prepareDocument($c, 'ump', false);
	$c->update(array('Prepare_UMP_Agreement__c' => 'False', 'UMP_Agreement_Url__c' => $res));
	echo json_encode(array('success'=>'true'));
	break;
case 'esignump':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'ump', false);
	$res = $controller->esignDocument($c, $doc, 'ump', false);
	$c->update(array('Send_UMP_Agreement__c' => 'False', 'UMP_Agreement_Url__c' => $doc));
	$c->createActivityHistory('UMP Application Sent via email');
	echo json_encode(array('success'=>'true'));
	break;
case 'emaildocs':
    $c = new SFDCClient($params['id']);
    $e = new ESignClient($c, $params['oprid'], $db);
    $controller = new ESignController();
	$sfdc_controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($e, 'agreement', false);
	$fieldsToUpdate['Agreement_Url__c'] = $doc;
	$res = $sfdc_controller->sendDocument($c, $doc, 'agreement', 'email');
	if($c->ump) {
		$doc = $controller->prepareDocument($e, 'ump', false);
		$fieldsToUpdate['UMP_Agreement_Url__c'] = $doc;
		$res = $sfdc_controller->sendDocument($c, $doc, 'ump', 'email');
		}
			$doc = $controller->prepareDocument($e, 'global', false);
		$fieldsToUpdate['Global_Url__c'] = $doc;
			$res = $sfdc_controller->sendDocument($c, $doc, 'global', 'email');
		$fieldsToUpdate['Email_Docs__c']='False';
			$c->update($fieldsToUpdate);
			$c->createActivityHistory('Documents Emailed');
			echo json_encode(array('success'=>'true'));
			break;
case 'smsdocs':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'agreement', false);
	$fieldsToUpdate['Agreement_Url__c'] = $doc;
	$res = $controller->sendDocument($c, $doc, 'agreement', 'sms');
	$doc = $controller->prepareDocument($c, 'global', false);
	$fieldsToUpdate['Global_Url__c'] = $doc;
	$res = $controller->sendDocument($c, $doc, 'global', 'sms');
	if($c->ump) {
		$doc = $controller->prepareDocument($c, 'ump', false);
		$fieldsToUpdate['UMP_Agreement_Url__c'] = $doc;
		$res = $controller->sendDocument($c, $doc, 'ump', 'sms');
		}
		$fieldsToUpdate['SMS_Docs__c']='False';
		$c->update($fieldsToUpdate);
		$c->createActivityHistory('Agreement sent Via SMS');
		$c->createActivityHistory('Global Application sent via SMS');
		$c->createActivityHistory('UMP Application sent via SMS');
		echo json_encode(array('success'=>'true'));
		break;
case 'faxagreement':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'agreement', true);
	$res = $controller->sendDocument($c, $doc, 'agreement', 'fax');
	$c->update(array('Fax_Agreement__c'=>'False', 'Agreement_Url__c'=>$doc));
	$c->createActivityHistory('Agreement Sent Via Fax');
	echo json_encode(array('success'=>'true'));
	break;
case 'faxglobal':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'global', true);
	$res = $controller->sendDocument($c, $doc, 'global', 'fax');
	$c->update(array('Fax_Global_App__c'=>'False', 'Global_Url__c'=>$doc));
	$c->createActivityHistory('Global App Sent Via Fax');
	echo json_encode(array('success'=>'true'));
	break;
case 'faxump':
	$c = new SFDCClient($params['id']);
	$controller = new SFDCClientController($c);
	$doc = $controller->prepareDocument($c, 'ump', true);
	$res = $controller->sendDocument($c, $doc, 'ump', 'fax');
	$c->update(array('Fax_UMP_Agreement__c'=>'False', 'UMP_Agreement_Url__c'=>$doc));
	$c->createActivityHistory('UMP Agreement Sent Via Fax');
	echo json_encode(array('success'=>'true'));
	break;
		}
	break;

case 'billing-report':
	require_once 'controllers/BillingReportController.php';
	$reportObj = new BillingReportController($params['startdate'], $params['enddate']);

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'revenue-projection-samba':
			$reportObj->revenueProjectionSamba($params['divisionid']);
			break;
				}
				break;
		}
		break;

case 'client-new':
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-scheduled-payments':
			$clientObj = new Client();
			$clientObj->load($params['clientid'], $db);
			$result = $clientObj->getScheduledPayments($db, 100, '', true);
			var_dump($result);
			break;
		case 'get-transaction-fee':
			$clientObj = new Client();
			$clientObj->load($params['clientid'], $db);
			$result = $clientObj->getTransactionFee();
			var_dump($result);
			break;
		case 'get-savings-journal':
			$sjObj = new SavingsJournal($db);
			$result = $sjObj->getSavingsDetail($params['clientid']);
			echo json_encode($result);
			break;
		}
		break;
	}
	break;

case 'offer-new':
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'check-math':
			$offerObj = new Offer();
			$offerObj->load($params['id'], $db);
			$result = $offerObj->isPassMath();
			var_dump($result);
			break;
				}
				break;
		}
		break;

case 'billing-schedule':
	require_once 'controllers/BillingScheduleController.php';
	$bsObj = new BillingScheduleController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'current-drafting-plan':
			$bsObj->getCurrentDraftingPlan($params['clientid']);
			break;
		case 'draft-date-options':
			$bsObj->getDraftDateOptions($params['clientid']);
			break;
		case 'new-drafting-plan':
			$bsObj->getNewDraftingPlan($params['clientid'], $params['action'],
				$params['changeamount'], $params['numdrafts'], $params['startdate'],
				$params['increaseaction']);
			break;
		case 'defer-plan':
			$bsObj->getDeferPlan($params['clientid'], $params['num-defer-draft']);
			break;
		case 'add-freq': // add ALL clients' frequency
			$bsObj->updateClientFreq($params['temp']);
			break;
		case 'modified-draft-schedule':
			$bsObj->getModifiedDraftSchedule($params['clientid'], $params['frequency'],$params['dayofweek'], $params['dateofmonth1'], $params['dateofmonth2'], $params['startdate']);
			break;
		case 'get-creditor-recap':
			$bsObj->getCreditorRecap($params['clientid']);
			break;
		case 'get-billing-recap':
			$bsObj->getBillingRecap($params['clientid']);
			break;
		case 'get-nacha-info':
			$bsObj->getNachaInfo($params['clientid']);
			break;
		case 'get-client-accounting-recap':
			$bsObj->getClientAccountingRecap($params['clientid']);
			break;
		case 'add-single-commitment':
			$bsObj->getAdditionalCommitmentSchedule($params['clientid'], $params['startdate'], $params['draftamount']);
			break;
		case 'get-client-additional-drafts':
			$bsObj->getClientAdditionalDrafts($params['clientid']);
			break;
		case 'modify-single-date':
			$bsObj->getModifiedCommitmentSchedule($params['clientid'], $params['modifydate'], $params['newdate']);
			break;
				}
				break;
case 'POST':
	if(empty($params)) {
		$params = json_decode(file_get_contents('php://input') ,true);
	}
	define(OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];

	switch ($verb)  {
	case 'validate-plan':
		$bsObj->validatePlan($params['clientid'], $params['schedule'], $params['action'], $params['freq_descr']);
		break;
	case 'get-changed-plan':
		$bsObj->getChangedPlan($params['oldschedule'], $params['newschedule']);
		break;
	case 'get-nacha-recap-plan':
		$bsObj->getNachaRecapPlan($params['clientid'], $params['schedule']);
		break;
	case 'save-drafting-schedule':
		if (!isset($params['sipaction'])) {
			$params['sipaction'] = 0;
						}
						$bsObj->savingDraftingScheduleAmount($params['clientid'], $params['schedule'], $params['sipaction'], $params['action'], $params['oprId'], $params['postData'], $params['requestStr']);
						break;
case 'save-additional-draft':
	$bsObj->savingAdditionalDraft($params['clientid'], $params['addedCommitment'], $params['action'], $params['oprId'], $params['requestStr']);
	break;

    case 'save-deposit-schedule-audit':
        // save data to the draft deposit schedule audit table
        $response = $bsObj->save_deposit_schedule_audit($params['clientid'], $params['amount'], $params['draft_date'], $params['acct_type'], $params['acct_no'], $params['oprId'], $params['session_id'], $params['ip_address'], $params['user_agent']);
        echo json_encode(array('status' => $response));
        break;
    case 'delete-additional-draft':
        $bsObj->deleteAdditionalDraft($params['clientId'], $params['draftDate'], $params['oprId'], $db);
        break;
    case 'modify-addl-commitment':
        $bsObj->getNewModifiedSchedule($params['clientId'], $params['singleCommitment']);
        break;
				}
				break;
		}
		break;

case 'certification':
	require_once 'controllers/CertificationRecordsController.php';
	$recordObj = new CertificationRecordsController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'latest-gcs':
			$recordObj->getLatestRecordByClientId($params['clientid'], 'gcs');
			break;
		case 'get-records':
			$recordObj->getRecordsByClientId($params['clientid']);
			break;
			}
			break;
case 'POST':
	switch ($verb) {
	case 'save-record':
		$recordObj->saveRecord($params['clientid'], $params);
		break;
			}
			break;
	}
	break;


case 'mail-letter':
	require_once 'controllers/MailLetterController.php';
	$letterObj = new MailLetterController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'create-letter':
			if (isset($params['production']) && $params['production'] == '1') {
				$isProduction = true;
						} else {
							$isProduction = false;
						}
						$letterObj->createLetter($isProduction);
						break;
				}
				break;
		 }
		 break;

case 'contact':
	require_once 'controllers/ContactController.php';
	$contactObj = new ContactController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-all':
			$contactObj->getAllContactType();
			break;
		case 'get-queue':
			$contactObj->getQueue($params['type']);
			break;
		case 'send-sms': // this was for testing purpose only
			$cObj = new Contact();
			$cObj->sendSMS($params['number'], $params['content']);
			break;
		case 'get-log':
			$contactObj->getLog($params['start'], $params['end']);
			break;
		case 'process-queue':
			$contactObj->processQueue($params['type']);
			break;
		case 'get-contact-method':
			$contactObj->getAllContactMethods();
			break;

				}
				break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	if (empty($params)) {
		$params = $_POST;
	}
	define(OPRID, $params['oprId']);
	define(INSERT_OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];
	switch ($verb) {
	case 'save-contact-type':
		$contactObj->saveContact($params['contactType']);
		break;
	case 'delete-contact-type':
		$contactObj->deleteContactType($params['typeId']);
		break;
	case 'save-sms-response':
		$contactObj->saveSMSResponse($params['number'], $params['response']);
		break;
	case 'process-contact':
		$contactObj->processContact($params['clientId'], $params['typeId']);
		break;
	case 'process-queue':
		$contactObj->processQueue($params['type']);
		break;
	case 'send-poa':
		$contactObj->sendPOA($params['clientid'], $params['clientCreditorId'], $params['creditorid'], $params['agencyid'], $params['filename'],
			$params['creditorLookupId'], $params['creditorNewContact'], $params['agencyLookupId'], $params['agencyNewContact'],
			$params['additionalInfo']);
		break;
				}
				break;
		}
		break;
case 'template':
	require_once 'controllers/TemplateController.php';
	$templateObj = new TemplateController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-all-templates':
			$templateObj->getAllTemplates();
			break;
		case 'get-template-info':
			$templateObj->getTemplateInfo($params['templateid']);
			break;
		case 'get-template-content':
			$templateObj->getTemplateContentByClientId($params['templateid'], $params['clientid'], $params['clientcreditorid']);
			break;
					}
					break;
case 'POST':
	$params = json_decode(file_get_contents('php://input') ,true);
	define(OPRID, $params['oprId']);
	define(INSERT_OPRID, $params['oprId']);

	$params['oprid'] = $params['user'];
	switch ($verb) {
	case 'save-template':
		$templateObj->saveContactTemplate($params);
		break;
	case 'save-template-division':
		$templateObj->saveContactTemplateDivision($params);
		break;
			}
			break;
	}
	break;

case 'manual':
	require_once "controllers/ManualController.php";
	$manualObj = new ManualController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'update-bulk-agency':
			$manualObj->updateBulkAgency($params['old'], $params['new']);
			break;
		 case 'update-payment-dates':
                $manualObj->updateSettlementPaymentDates();
                break;
				}
				break;
		}
		break;

case 'contact-lookup':
	require_once 'controllers/ContactLookupController.php';
	$lookupObj = new ContactLookupController();

	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-contact-list':
			$lookupObj->getContactList($params['tablename'], $params['tableid']);
			break;
				}
				break;
		}
		break;
case 'slide':
	require_once "controllers/SlideController.php";
	$slideObj = new SlideController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-slides':
			$slideObj->getSlidesByClientId($params['clientid']);
			break;
		case 'get-all':
			$slideObj->getAll();
			break;
				}
				break;
		}
		break;

case 'marketing':
	require_once "controllers/MarketingController.php";
	$marketingObj = new MarketingController();
	switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		switch ($verb) {
		case 'get-ad-sources':
			$marketingObj->getAllAdSources();
			break;
				}
				break;
		}
		break;

    case 'transaction':
        require_once "controllers/TransactionController.php";
        require_once "controllers/GlobalController.php";
        $transObj = new TransactionController();
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                switch ($verb) {
                    case 'import-transactions-by-client':
                        $transObj->importTransactions($params['clientid']);
                        break;
                    case 'get-transactions':
                        $transObj->getTransactions($params['clientid']);
                        break;
                    case 'import-transactions-by-company':
                        $transObj->importTransactionsByCompany($params['divisionid']);
                        break;
                    case 'import-inactive-transactions':
                        $transObj->importInactiveTransactions();
                        break;

                    case 'new-get-transactions':
                        if (empty($params['debug'])) {
                            $params['debug'] = false;
                        }
                        $transObj->newgetTransactions($params['clientid'], $params['debug']);
                        break;

                    case 'update-transaction-amounts':
                        $transObj->updateTransactionAmounts($params['clientid']);
                        break;

                    case 'fetch-gcs-transactions':
                        if (empty($params['debug'])) {
                            $params['debug'] = false;
                        }
                        $transObj->fetchGcsTransactions($params['clientid'], $params['debug']);
                        break;

                    case 'update-transaction-clientids':
                        $transObj->updateTransactionClientIds();
                        break;


                    case 'sf-client':
                        $controller = new SFClientController($params['clientid']);

                        switch ($_SERVER['REQUEST_METHOD']) {
                            case 'GET':
                                switch ($verb) {
                                    case 'calculator-info':
                                        $controller->getCalculatorInfo();
                                        break;
                                }
                                break;
                            case 'POST':
                                break;
                        }
                }
                break;

        }
    case 'test':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                switch ($verb) {
                    case 'get-next-file-id':
                        $fileObj = new NachaFile(0, 2);
                        echo $fileObj->getNextFileIdModifier();
                        break;


                    case 'process-result-file':
                        $spsObj = new SPS();
                        $spsObj->processResultFile();
                        break;
                }
                break;
        }
        break;

default:
	Controller::badRequest();
	break;
}

$db->close();

