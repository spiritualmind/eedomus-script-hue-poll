<?php

//Edit by @AntorFR - 2017-11-01
//Fix by @spiritualmind - 2020-07-25 : light state within "groups" use a different xpath (than "lights")

// We need two arguments: ip address, light/group id and rgb color to compare in hexadecimal notation
$hue_bridge_ip = getArg('bridge_ip');
$hue_light_id = getArg('lamp_id',false,NULL);
$hue_group_id = getArg('group_id',false,NULL);
$hue_username = getArg('user_name');
// Then we can construct the to the light api
if ($hue_light_id != NULL) {
    $mode= "lights"; 
    $hue_api_path = "http://$hue_bridge_ip/api/$hue_username/lights/$hue_light_id";
    $xpath_on = "/root/state/on";
    $xpath_bri = "/root/state/bri";
    $xpath_alert = "/root/state/alert";
}
else if ($hue_group_id != NULL) { 
    $mode= "groups"; 
    $hue_api_path = "http://$hue_bridge_ip/api/$hue_username/groups/$hue_group_id";
    $xpath_on = "/root/state/any_on";
    $xpath_bri = "/root/action/bri";
    $xpath_alert = "/root/action/alert";
}
else {
    echo "Erreur, you must provide a lamp_id or group_id"; 
    exit;
}


// Query to get the light state
$jsonResponse = httpQuery($hue_api_path, 'GET', "");
//$jsonResponse = substr( $jsonResponse , 1 , -1 );

// From JSON to XML format
$xmlResponse = jsonToXML($jsonResponse);

//var_dump($xmlResponse);

// Get the light state
// Valid values for alert: "none", "select", "lselect"
// Valid values for effect: "none", "colorloop"
$on = 0;
$hue = null;
$sat = null;
$brightness_raw = 0;
$alert_raw = null;
$alert_on = 0;
$effect = null;

$on = xpath($xmlResponse, $xpath_on);
$brightness_raw = xpath($xmlResponse, $xpath_bri);
$alert_raw = xpath($xmlResponse, $xpath_alert);
if(strpos($xmlResponse, "<hue>") !== false) {
	$hue = xpath($xmlResponse, "/root/state/hue");
	$sat = xpath($xmlResponse, "/root/state/sat");
}
if(strpos($xmlResponse, "<effect>") !== false) {
	$effect = xpath($xmlResponse, "/root/state/effect");
}

// Apply brightness scale 
$brightness_scale = $brightness_raw / 254;
$sat = $sat / 255;

//*****************************
// Convert HSV brightness to RGB
//*****************************

$r = round($brightness_scale*100);
$g = $r;
$b = $r;

if($hue !== null) {
	$hue6 = $hue * 6 / 65535;
	$i = floor($hue6);
	if($i%2 == 0)
		$f = 1 - $hue6 + $i;
	else 
		$f = $hue6 - $i;
	$m = $brightness_scale - $brightness_scale * $sat;
	$n = $brightness_scale - $brightness_scale * $sat * $f;
	switch($i) {
		case 6:
		case 0:
			$r = $brightness_scale;
			$g = $n;
			$b = $m;
			break;
		case 1:
			$r = $n;
			$g = $brightness_scale;
			$b = $m;
			break;
		case 2:
			$r = $m;
			$g = $brightness_scale;
			$b = $n;
			break;
		case 3:
			$r = $m;
			$g = $n;
			$b = $brightness_scale;
			break;
		case 4:
			$r = $n;
			$g = $m;
			$b = $brightness_scale;
			break;
		case 5:
			$r = $brightness_scale;
			$g = $m;
			$b = $n;
			break;
	}
}

if ($r >= $b && $r >= $g) {
	// red is biggest
	if ($r != 100) {
		$g = round($g / $r * 100);
		$b = round($b / $r * 100);
		$r = 100;
	}
}
else if ($g >= $b && $g >= $r) {
	// green is biggest
	if ($g != 100) {
		$r = round($r / $g * 100);
		$b = round($b / $g * 100);
		$g = 100;
	}
}
else if ($b >= $r && $b >= $g) {
	// blue is biggest
	if ($b != 100) {
		$r = round($r / $b *100);
		$g = round($g / $b * 100);
		$b = 100;
	}
}


if($r < 0)
	$r = 0;
if($g < 0)
	$g = 0;
if($b < 0)
	$b = 0;

if($r > 100)
	$r = 100;
if($g > 100)
	$g = 100;
if($b > 100)
	$b = 100;

// multiples de 10 uniquement sinon on se retrouve avec des valeurs comme 79...
$brightness_on = round($brightness_scale*10) * 10 * $on;
$brightness_raw *= $on; //la valeur de brightness est conservée dans l'action du groupe, il faut la corréler avec la valeur de $on (state/any_on)

// XML response
sdk_header('text/xml');
echo "<hue>"
    ."<brightness>$brightness_on</brightness>"
    ."<brightness_raw>$brightness_raw</brightness_raw>"
    ."<rgb_color>$r,$g,$b</rgb_color>"
    ."</hue>";

//debug
//echo "<div style=\"height:100px;width:200px;background-color:rgb($r,$g,$b);\"/>"

?>
