<!--
*
* This php script is part of the eedomus scripting tools.
* It helps create a new user for the hue bridge.
* This user will be used later to control the hue lights.
*
-->
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>hue and eedomus</title>

<style>
	html
	{
		background:#222;
		color: #fff;
		font-family: "proxima-nova","Helvetica Neue",Helvetica,Arial,sans-serif;
	}

	header, section {width: 100%; display: block;}
	header img, header h1 {display: inline; margin: 25px; vertical-align: middle;}
	section {margin-left: 25px;}
	form label, form input, form button {margin: 15px 0px; display: block;}
	a:link, a:visited { color: #0078e7; }
	a:hover { color: red; }

	fieldset
	{
		border: 0 none;
		margin: 0;
		padding: 0.35em 0 0.75em;
	}

	input[type="text"]
	{
		-moz-box-sizing: border-box;
		border: 1px solid #ccc;
		border-radius: 4px;
		box-shadow: 0 1px 3px #ddd inset;
		padding: 0.5em 0.6em;
	}

	button
	{
		font-size: 100%;
		padding: 0.5em 1em;
		text-decoration: none;
		-moz-user-select: none;
		cursor: pointer;
		line-height: normal;
		text-align: center;
		vertical-align: baseline;
		white-space: nowrap;
		border: 0 none rgba(0, 0, 0, 0);
		border-radius: 2px;
		background-color: #0078e7;
		color: #fff;
	}
</style>

</head>
<body>

<header>
	<img alt="Hue Personal Wireless Lighting" src="https://secure.eedomus.com/sdk/plugins/hue/hue-logo.png">
	<h1>Philips hue and eedomus</h1>
</header>

<section>
<?php

$exec = $_GET['exec'];
$hue_bridge_ip = $_GET['hue-bridge-ip'];
$hue_username = getArg('user_name');
$hue_controller_id = $_GET['controller_id'];
$step = $_GET['step'];

if($exec === null || $exec === "") {
	
	echo "<p>The script cannont execute correctly</p>";
}
else {

	if($hue_bridge_ip === null || $hue_bridge_ip === "") {
	
		// Ask for hue bridge ip address
		echo "<form>";
		echo "<fieldset>";
		echo "<input type=\"hidden\"  name=\"exec\"  value=\"$exec\">";
		echo "<input type=\"hidden\"  name=\"step\"  value=\"1\">";
		//echo "<input type=\"hidden\"  name=\"user_name\"  value=\"$hue_username\">";
		if($hue_controller_id !== null)
			echo "<input type=\"hidden\"  name=\"controller_id\"  value=\"$hue_controller_id\">";
		echo "<label for=\"hue-bridge-ip\">Please enter your hue ip address:</label>";
		echo "<input id=\"hue-bridge-ip\" type=\"text\" name=\"hue-bridge-ip\" placeholder=\"192.168.0.*\">";
		echo "<button type=\"submit\">Submit</button>";
		echo "</fieldset>";
		echo "</form>";

	}
	else {
		// Path to hue api
		$hue_http_begin = 'http://'.$hue_bridge_ip.'/api';

		// Create user "eedomushue"
		// Notice: username contains at least 10 characters
		$jsonResponse = httpQuery($hue_http_begin, 'POST', "{\"devicetype\":\"domotique\"}");
		
		//var_dump($step, $jsonResponse);
		
		$jsonResponse = substr( $jsonResponse , 1 , -1 );

		// From JSON to XML format
		$xmlResponse = jsonToXML($jsonResponse);

		// Search useful info thanks to XPATH and print
		$error = null;
		$success = null;

		if($step == 1) 
		{
			$error = xpath($xmlResponse, "/root/error/description");
		}
		else if($step == 2)
		{
			$success = xpath($xmlResponse, "/root/success/username");
			if ($success == '') // TICKET #76805
			{
				$error = xpath($xmlResponse, "/root/error/description");
			}
			else
			{
        $hue_username = $success;
			}
		}
		
		if($error === "link button not pressed") {

			$url_to_call = "http://localhost/script/?exec=$exec&step=2&hue-bridge-ip=$hue_bridge_ip&user_name=$hue_username";
			if($hue_controller_id !== null)
				$url_to_call .= "&controller_id=$hue_controller_id";

			// The bridge central button needs to be pressed
			echo "<form>";
			echo "<fieldset>";
			echo "<input type=\"hidden\"  name=\"url\"  value=\"$url_to_call\">";
			if($hue_controller_id !== null)
				echo "<input type=\"hidden\"  name=\"controller_id\"  value=\"$hue_controller_id\">";
			echo "<label>Please press the button on the bridge and confirm.</label>";
			echo "<button type=\"submit\">Confirm</button>";
			echo "</fieldset>";
			echo "</form>";
		}
		else if($success == $hue_username) {

			// The user has been created successfully

			// Message to display
			echo "<p>";
			echo "User successfully created or already exists.<br/>";
			echo "The HUE bridge is ready and accepts user \"$hue_username\".<br/>";
			echo "You can now use \"$hue_username\" to control your lights!<br/>";
			echo "Please see the official hue documentation at: ";
			echo "<a href=\"http://developers.meethue.com/\">http://developers.meethue.com/</a>";
			echo "</p>";

			// List available lamps

			echo "<p>Here is a list of the available lamps with their id:</p>";
			echo "<ul>";
			
			// Get lamp list
			$hue_http_begin = 'http://'.$hue_bridge_ip.'/api/'.$hue_username.'/lights';
			$jsonResponse = httpQuery($hue_http_begin);
			$json = sdk_json_decode($jsonResponse);
			
			foreach($json as $lamp_id => $lamp) {
				
				echo "<li>Id : ".$lamp_id.", Name : ".$lamp['name']."</li>";
			}
			
			echo "</ul>";
			
			echo "<br><p>Here is a list of the available groups with their id:</p>";
			echo "<ul>";
			
			// Get group list
			$hue_http_begin = 'http://'.$hue_bridge_ip.'/api/'.$hue_username.'/groups';
			$jsonResponse = httpQuery($hue_http_begin);
			$json = sdk_json_decode($jsonResponse);
			
			foreach($json as $group_id => $group) {
				
				echo "<li>Id : ".$group_id.", Name : ".$group['name']."</li>";
			}

			echo "</ul>";
		}
		else {

			// Unknown state or error
			// Button provided to try again
			
			$url_to_call = "http://localhost/script/?exec=$exec&step=1&hue-bridge-ip=$hue_bridge_ip&user_name=$hue_username";
			if($hue_controller_id !== null)
				$url_to_call .= "&controller_id=$hue_controller_id";

			echo "<p>An error occured. Please try again. [$error]</p>";
			echo "<form>";
			echo "<fieldset>";
			echo "<input type=\"hidden\"  name=\"url\"  value=\"$url_to_call\">";
			if($hue_controller_id !== null)
				echo "<input type=\"hidden\"  name=\"controller_id\"  value=\"$hue_controller_id\">";
			echo "<button type=\"submit\">Try again</button>";
			echo "</fieldset>";
			echo "</form>";
		}

	}
}

?>
</section>

</body>
</html>
