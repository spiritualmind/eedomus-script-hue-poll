<?php
// script créé par Connected Object pour eedomus

// les données sont mises à jour 1x par jour entre 7h et 8h
// Enedis nous demande d'être raisonnable et de ne réaliser qu'un appel par jour
// merci de ne pas modifier les valeurs de fréquences et de cache pour le maintien du service

$api_url = 'https://gw.prd.api.enedis.fr/';

$prev_code = loadVariable('code');

// on reprend le dernier refresh_token seulement s'il correspond au même code
$refresh_token = loadVariable('refresh_token');
$expire_time = loadVariable('expire_time');
// s'il n'a pas expiré, on peut reprendre l'access_token
if (time() < $expire_time)
{
	$access_token = loadVariable('access_token');
}

// on a déjà un token d'accés non expiré pour le code demandée
if ($access_token == '' || $_GET['oauth_code'] != '')
{
  if ($_GET['oauth_code'] != '')
  {
    $code = $_GET['oauth_code'];
  }
  else
  {
    $code = $prev_code;
  }

	if (strlen($refresh_token) > 1 && $_GET['oauth_code'] == '')
	{
		// on peut juste rafraichir le token
		$grant_type = 'refresh_token';
		$postdata = 'grant_type='.$grant_type.'&refresh_token='.$refresh_token;
	}
	else
	{
		if ($code == '')
		{
			echo "## ERROR: Empty code for grant_type=".$grant_type;
			die();
		}
		
		// 1ère utilisation aprés obtention du code
		$grant_type = 'authorization_code';
		$redirect_uri = 'https://secure.eedomus.com/sdk/plugins/enedis_app/callback';
		$postdata = 'grant_type='.$grant_type.'&code='.$code.'&redirect_uri='.($redirect_uri);
	}
  
	$url = $api_url.'v1/oauth2/token';
	$response = httpQuery($url, 'POST', $postdata, 'enedis_oauth');
	//var_dump($url, $postdata, $response);
	$params = sdk_json_decode($response);

	if ($params['error'] != '')
	{
		die("Erreur lors de l'authentification:"." [".$params['error'].'] (grant_type='.$grant_type.'),<br>vous pouvez lier à nouveau votre compte en cliquant sur [Lier à nouveau] depuis la configuration de votre périphérique<br><br>'.$response);
	}

	// on sauvegarde l'access_token et le refresh_token pour les authentifications suivantes
	if (isset($params['refresh_token']))
	{
		$access_token = $params['access_token'];
		saveVariable('access_token', $access_token);
		saveVariable('refresh_token', $params['refresh_token']);
		saveVariable('expire_time', time()+$params['expires_in']);
		if ($code != '')
		{
			saveVariable('code', $code);
		}

	}
	else if ($access_token == '')
	{
		die("Erreur lors de l'authentification,<br>vous pouvez lier à nouveau votre compte en cliquant sur [Lier à nouveau] depuis la configuration de votre périphérique\n\n".$response);
	}
}

if ($_GET['mode'] == 'verify')
{
	?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <title>eedomus</title>
  <style type="text/css">
  
  body,td,th {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
  }
  </style>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
  </head><?
	
	$usage_point_id = $_GET['usage_point_id'];
	saveVariable('last_daily_consumption_'.$usage_point_id, '');
	saveVariable('last_consumption_load_curve_'.$usage_point_id, '');
	
  echo '<br>';
	echo "Voici votre identifiant de point d'usage Enedis, à copier/coller dans votre périphérique eedomus :";
  echo '<br>';
  echo '<br>';
	echo '<input type="text" name="usage_point_id" value="'.$usage_point_id.'" onclick="this.select();" readonly>';
	die();
}

	$headers = array("Accept: application/json", "Authorization: Bearer $access_token");
	$usage_point_id = getArg('usage_point_id');

	$last_xml_success = loadVariable('last_xml_success_'.$usage_point_id);
	if (date('G') >= 8 && date('G') <= 23 && date('Ymd') != date('Ymd', $last_xml_success))
	{
		$is_cache = 0;
		// sinon à minuit : "The end date parameter must be earlier than the current date."
		$tms = mktime() - 60 * 60;
		$today = date('Y-m-d', $tms);

		$yesterday = $today;
		while ($yesterday == $today)
		{
			$tms -= 24*60*60;
			$yesterday = date('Y-m-d', $tms);
		}


		$url = $api_url.'v3/customers/usage_points/contracts?usage_point_id='.$usage_point_id;
		$response = httpQuery($url, 'GET', NULL, NULL, $headers);
		//var_dump($url, $response);
		$xml .= jsonToXML($response);
		saveVariable('contracts_'.$usage_point_id, $response);

		$url = $api_url.'v3/metering_data/daily_consumption?start='.$yesterday.'&end='.$today.'&usage_point_id='.$usage_point_id;
		$response = httpQuery($url, 'GET', NULL, NULL, $headers);
		saveVariable('response_daily_consumption_'.$usage_point_id, $response);
		//var_dump($url, $response);
		$xml .= jsonToXML($response);
	}
	else
	{
		$is_cache = 1;
		$response = loadVariable('contracts_'.$usage_point_id);
		$xml .= jsonToXML($response);

		$response = loadVariable('response_daily_consumption_'.$usage_point_id);
    $xml .= jsonToXML($response);
	}

	$json = sdk_json_decode($response);
	$consumption_date = $json['usage_point'][0]['meter_reading']['end'];
	$consumption_value = $json['usage_point'][0]['meter_reading']['interval_reading'][0]['value'];

	$last_daily_consumption = loadVariable('last_daily_consumption_'.$usage_point_id);
	$daily_consumption_count = 0;
	if ($consumption_date != '' && $last_daily_consumption != $consumption_date)
	{
		$list = getPeriphList($show_notes = false, $filter_device_id = $_GET['eedomus_controller_module_id']);
		foreach ($list as $device)
		{
			if ($device['unit'] == 'Wh')
			{
				$daily_consumption_controller_module_id = $device['device_id'];
			}
		}

		if ($daily_consumption_controller_module_id > 0)
		{
			$cur_consumption_time = strtotime($consumption_date);
			$cur_consumption_time_txt = date('Y-m-d H:i:s', $cur_consumption_time);
			
			if ($consumption_value !== '')
			{
				setValue($daily_consumption_controller_module_id, $consumption_value, $verify_value_list = false, $update_only = false, $cur_consumption_time_txt);
				saveVariable('last_daily_consumption_'.$usage_point_id, $consumption_date);
				$daily_consumption_count++;
			}
		}
	}

	if ($is_cache)
	{
		$response = loadVariable('consumption_load_curve_'.$usage_point_id);
	}
	else
	{
		$url = $api_url.'v3/metering_data/consumption_load_curve?start='.$yesterday.'&end='.$today.'&usage_point_id='.$usage_point_id;
		$response = httpQuery($url, 'GET', NULL, NULL, $headers);
		saveVariable('consumption_load_curve_'.$usage_point_id, $response);
	}

	//var_dump($url, $response);
	$xml .= jsonToXML($response);

	$json = sdk_json_decode($response);
	$consumption_date = $json['usage_point'][0]['meter_reading']['start'];

	$last_consumption_load_curve = loadVariable('last_consumption_load_curve_'.$usage_point_id);
	$consumption_load_count = 0;

	if ($consumption_date != '' && $last_consumption_load_curve != $consumption_date)
	{
		$list = getPeriphList($show_notes = false, $filter_device_id = $_GET['eedomus_controller_module_id']);
		foreach ($list as $device)
		{
			if ($device['unit'] == 'W')
			{
				$consumption_load_curve_controller_module_id = $device['device_id'];
			}
		}

		if ($consumption_load_curve_controller_module_id > 0)
		{
			foreach($json['usage_point'][0]['meter_reading']['interval_reading'] as $reading)
			{
				$consumption_rank = $reading['rank'];

				$cur_consumption_time = strtotime($consumption_date) + $consumption_rank * 30 * 60;
				$cur_consumption_time_txt = date('Y-m-d H:i:s', $cur_consumption_time);
				
				if ($consumption_value !== '')
				{
					$consumption_value = $reading['value'];
					setValue($consumption_load_curve_controller_module_id, $consumption_value, $verify_value_list = false, $update_only = false, $cur_consumption_time_txt);
					$consumption_load_count++;
				}
			}
			if ($consumption_load_count > 0)
			{
				saveVariable('last_consumption_load_curve_'.$usage_point_id, $consumption_date);
			}
		}
	}

	$debug = "\ndaily_consumption_controller_module_id=$daily_consumption_controller_module_id,\ndaily_consumption_count=$daily_consumption_count,\nconsumption_load_curve_controller_module_id=$consumption_load_curve_controller_module_id,\nconsumption_date=$consumption_date,\nconsumption_load_count=$consumption_load_count\n";

	sdk_header('text/xml');
	
	$xml = str_replace("</root><?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n<root>", '', $xml);
	$xml = str_replace('</root>', "<debug>".$debug."</debug>\n</root>", $xml);
	
	if ($is_cache)
	{
		$xml = str_replace('<root>', "<root><cached>1</cached>", $xml);
	}
	else
	{
		$xml = str_replace('<root>', "<root><cached>0</cached>", $xml);
    if ($xml != '' && strpos($xml, 'Invalid_request') === false) // non vide
    {
      saveVariable('cached_xml_'.$usage_point_id, $xml);
      saveVariable('last_xml_success_'.$usage_point_id, time());
    }
	}

	echo $xml;
?>