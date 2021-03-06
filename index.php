<?php

include_once("projection.php");
include_once("imagemap_projection.php");
include_once("map.php.inc");
include_once("limits.php.inc");
include_once("layers.php.inc");

error_reporting (E_ALL ^ E_NOTICE);

# Standard fields
$Fields = array(
	"lat"=>array('name'=>"Latitude", 'type'=>'numeric', 'default'=>45, 'min'=> -90, 'max'=> 90),
	"lon"=>array('name'=>"Longitude", 'type'=>'numeric',  'default'=>0, 'min'=> -180, 'max'=> 180),
	"z"=>array('name'=>"Zoom", 'type'=>'numeric', 'default'=> 1, 'min'=> 1, 'max'=> 18),
	"w"=>array('name'=>"Width, px", 'type'=>'numeric', 'default'=> 600, 'min'=> 40, 'max'=> 2000),
	"h"=>array('name'=>"Height, px", 'type'=>'numeric', 'default'=> 450, 'min'=> 30, 'max'=> 2000),
	"layer"=>array('name'=>"Base map", 'type'=>'option', 'options'=>array_keys(getLayers())),
	"filter"=>array('name'=>"Filter for base-image", 'type'=>'option', 'options'=>array('none','grey','lightgrey','darkgrey','invert','bright','dark','verydark')),
	"mode"=>array('name'=>"Edit mode", 'type'=>'tab', 'options'=>array('Location', 'Resize', 'Style', 'Icons', 'Draw', 'API')),
	"show_icon_list"=>array('name'=>"Show choice of icons", 'type'=>'numeric', 'default'=> 0, 'min'=> 0, 'max'=> 1),
);


//----------------------------------------------------------------------------
// Generate the fields used to specify and edit map-markers
//----------------------------------------------------------------------------
$MaxIcons = MaxIcons();;
for($i = 0; $i < $MaxIcons; $i++) {
	$Fields["mlat$i"] = array(
		'name'=>"Marker $i latitude", 
		'type'=>'numeric',  
		'default'=> 0,  
		'min'=> -90, 
		'max'=> 90);
	$Fields["mlon$i"] = array(
		'name'=>"Marker $i longitude", 
		'type'=>'numeric',  
		'default'=> 0,  
		'min'=> -180, 
		'max'=> 180);
	$Fields["mico$i"] = array(
		'name'=>"Marker $i icon (see list in <a href='symbols/'>symbols</a> directory)", 
		'type'=>'numeric',  
		'default'=> 0,  
		'min'=> 0, 
		'max'=> 65535);
}

$Fields["choose_marker_icon"] = array('name'=>"Which marker's icon to modify", 'type'=>'numeric', 'default'=> 0, 'min'=> 0, 'max'=> $MaxIcons);

//----------------------------------------------------------------------------
// Generate the fields used to specify and edit lines and polygons
//----------------------------------------------------------------------------
$MaxDrawings = MaxDrawings();
$MaxPoints = MaxPoints(); // per drawing

for($i = 0; $i < $MaxDrawings; $i++) {
	$Fields["d${i}_style"] = array('name'=>"Style of drawing $i", 'type'=>'option', 'options'=> array('line','polygon'));
	$Fields["d${i}_colour"] = array('name'=>"Colour of drawing $i", 'type'=>'colour', 'default'=> "008");

	for($j = 0; $j < $MaxPoints; $j++) {
		$Fields["d${i}p${j}lat"] = array('name'=>"Latitude of point $j in drawing $i", 'type'=>'numeric', 'default'=> 0, 'min'=> -90, 'max'=> 90);
		$Fields["d${i}p${j}lon"] = array('name'=>"Longitude of point $j in drawing $i", 'type'=>'numeric', 'default'=> 0, 'min'=> -180, 'max'=> 180);
	}
}

$Fields["d_num"] = array('name'=>"Which drawing to modify", 'type'=>'numeric', 'default'=> 0, 'min'=> 0, 'max'=> $MaxDrawings);
$Fields["dp_num"] = array('name'=>"Which point to insert next in drawing", 'type'=>'numeric', 'default'=> 0, 'min'=> 0, 'max'=> $MaxPoints);

# Option to export the API documentation
if($_REQUEST['api'] == "json") {
	header("content-type: text/plain");
	print json_encode($Fields);
	exit;
}

if(0 && $_GET["clear_cache"] == "yes") {
	walkCache('del');
	walkCache('stat');
	exit;
}

# if mlat/mlon but not lat/lon supplied, then use those for map centre
if((array_key_exists('mlat', $_REQUEST) && array_key_exists('mlon', $_REQUEST)) && !(array_key_exists('lat', $_REQUEST) && array_key_exists('lon', $_REQUEST))) {
	$_REQUEST['lat'] = $_REQUEST['mlat'];
	$_REQUEST['lon'] = $_REQUEST['mlon'];
}

# Handle 'standard' field names as used in other slippy-maps
foreach(array("zoom"=>"z", "mlat" => "mlat0", "mlon" => "mlon0") as $k => $v) {
	if(array_key_exists($k, $_REQUEST)) {
		$_REQUEST[$v] = $_REQUEST[$k];
	}
}

# if zoom supplied but not lat/lon, kill it!!!
if(array_key_exists('z', $_REQUEST) && !(array_key_exists('lat', $_REQUEST) && array_key_exists('lon', $_REQUEST))) {
	$_REQUEST['z'] = FieldDefault('z');
}

# Handle imagemaps
if(preg_match("{\&\?(\d+),(\d+)$}", $_SERVER['QUERY_STRING'], $Matches)) {
	switch($_REQUEST['mode']) {
	case 'Resize': {
		$_REQUEST['w'] = $Matches[1] * 4;
		$_REQUEST['h'] = $Matches[2] * 4;
		$_REQUEST['mode'] = "Edit";
		break;
	}
	case 'Location': {
		$Data = ReadFields($_REQUEST);
		list($_REQUEST['lat'], $_REQUEST['lon']) = imagemap_xy2ll($Matches[1], $Matches[2], $Data);
      break;
	}
	case 'Draw': {
		$Data = ReadFields($_REQUEST);
		$FieldBase = sprintf("d%dp%d", $Data["d_num"], $Data["dp_num"]);
		list($_REQUEST[$FieldBase.'lat'], $_REQUEST[$FieldBase.'lon']) = imagemap_xy2ll($Matches[1], $Matches[2], $Data);
		$_REQUEST['dp_num'] = min($Data["dp_num"]+1, $MaxPoints);
		break;
	}
	case 'Icons': {
		$Data = ReadFields($_REQUEST);
		list($mlat, $mlon) = imagemap_xy2ll($Matches[1], $Matches[2], $Data);

		for($i = 0; $i < $MaxIcons; $i++) {
			if($Data["mlat$i"] == 0 && $Data["mlon$i"] == 0) {
				$_REQUEST["mlat$i"] = $mlat;
				$_REQUEST["mlon$i"] = $mlon;
				break;
			}
		}
		break;
	}
	default:
		print "Unrecognised imagemap";
	}
}

$Data = ReadFields($_REQUEST);

if($_REQUEST['show']) {
	doMap($Data['lat'], $Data['lon'], $Data['z'], $Data['w'], $Data['h'], $Data['layer'], 'jpg', true, $Data);
	exit;
}

printf("<html><head><title>%s</title>\n", T(title()));
printf("<link rel='stylesheet' href='style.css' />");
printf("</head>\n");
printf("<p style='float:right'><a href='./'>Restart</a></p>");
printf("<h1>%s</h1>\n", T(title()));
printf("<div>\n");
printf("<div class='tabs'>\n");

foreach($Fields['mode']['options'] as $Mode) {
	printf(" <a href='%s' class='tab%s'>%s</a>\n", 
	LinkSelf(array('mode' => $Mode)), $Mode == $Data['mode'] ? '_selected' : '', $Mode);
}

print "</div>\n\n<div class='main'>\n\n";

switch($Data['mode']) {
	case "Debug": {
		ShowImage();

		printf("<form action='.' method='get'>");
		foreach($Fields as $Field => $Details) {
			printf("<p>%s:", $Details['name']);
			switch($Details['type']) {
				case "numeric":
					printf("<input type='text' name='%s' value='%s'/></p>\n", $Field, htmlentities($Data[$Field]));
				break;
				case 'option':
					printf("<select name='%s'>\n", $Field);
					foreach($Details['options'] as $Option) {
						printf(" <option%s>%s</option>\n", $Data[$Field]==$Option ? " selected":"", $Option);
					}
					printf("</select>");
				break;
			}
		printf("</p>\n");
		}

		printf("<p><input type='submit' value='Apply'></p>");
		printf("</form>");
	break;
	}
	case "Start":
		printf("<p>TODO: slippy-map here</p>");
	case 'Location': {
		printf("<p>");
		printf("<a class='zoom' href='%s'>Zoom Out</a> ", LinkSelf(array('z'=>$Data['z']-1)));
		printf("<a class='zoom' href='%s'>Zoom In</a>", LinkSelf(array('z'=>$Data['z']+1)));
		printf("</p>");

		ShowImage(true);
	break;
	}
	case 'Resize': {
		printf("<p>\n");
		printf("<form action='.' method='get'>");
		printf("<input type='text' name='w' value='%u' size='4' /> &times; \n", $Data['w']);
		printf("<input type='text' name='h' value='%u' size='4' />\n", $Data['h']);
		HiddenFields(array('h','w'));
		printf("<p><input type='submit' value='OK'></p>\n");
		printf("</form>");
	break;
	}
	case 'Style': {
		$SampleSize = 200;
		printf("<table border=0>\n");
		foreach(getLayers() as $Layer => $LayerData) {
			printf("<tr%s>", $Layer == $Data['layer'] ? " id='selected_style'":"");
			printf("<td><h2>%s</h2></td>", $Layer);
			printf("<td><a href='%s'><img src='%s' width='%d' height='%d'/></a></td>\n", LinkSelf(array('layer'=>$Layer)),	LinkSelf(array('w'=>$SampleSize, 'h'=>$SampleSize, 'layer'=>$Layer)). "show=1", $SampleSize,	$SampleSize);
			printf("<td>");
			foreach($LayerData as $FieldName => $FieldValue) {
				printf("%s: %s<br/>", $FieldName, htmlentities($FieldValue));
			}
			printf("</td></tr>\n");
		}
		printf("</table>\n");
		printf("\n\n<hr>\n<h2>Filters</h2>\n<p>%s</p>\n", OptionList('filter'));

	break;
	}
	case 'Icons': {
		if($Data['show_icon_list']) {
			iconSelector(sprintf("mico%d", $Data['choose_marker_icon']));
		}
		else {
			ShowImage(true);
			printf("<p>Click map to add a new marker</p>");
			$Count = 0;
			for($i = 0; $i < $MaxIcons; $i++) {
				if(markerInUse($i)) {
					// TODO: image-align no longer in HTML spec?
					$Icon = sprintf("<a href='%s'><img src='%s' align='middle' border='0' title='Click to change icon'/></a>", LinkSelf(array("choose_marker_icon" => $i, 'show_icon_list'=>1)), iconName($Data["mico$i"]));

				printf("<p>%s marker %d: Location (%1.5f, %1.5f)  <a href='%s'>delete</a></p>\n", $Icon, $i, $Data["mlat$i"], $Data["mlon$i"], LinkSelf(array("mlat$i" => 0, "mlon$i" => 0)));
				$Count++;
				}
			}

			if($Count > 0)
				printf("<hr/><p>&nbsp;&nbsp;<b>&uarr;</b> <i>click icons to change them</i></p>\n");

			if($Count == $MaxIcons)	{
				printf("<p>Reached the limit of %d markers</p>\n", $MaxIcons);
			}

			if($Count > 1)	{
				$DelAll = array();
				for($i = 0; $i < $MaxIcons; $i++) {
					$DelAll["mlat$i"] = 0;
					$DelAll["mlon$i"] = 0;
				}
				printf("<p><a href='%s'>Delete all markers</a></p>\n", LinkSelf($DelAll));
			}
		}
	break;
	}
	case 'Draw': {
		ShowImage(true);
		printf("<p>Click image to add point %d to drawing %d</p>\n", $Data["dp_num"], $Data["d_num"]);
    
		for($i = 0; $i < $MaxDrawings; $i++) {
			$Html = "";
			$Count = 0;
			$DelAll = array();
			for($j = 0; $j < $MaxPoints; $j++) {
				$FieldLat = "d${i}p${j}lat";
				$FieldLon = "d${i}p${j}lon";

				$Lat = $Data[$FieldLat];
				$Lon = $Data[$FieldLon];
				if($Lat != 0 && $Lon != 0) {
					$Html .= sprintf("<p>%d: %f, %f</p>\n", $j, $Lat, $Lon);
					$Count++;
				}
				$DelAll[$FieldLat] = 0;
				$DelAll[$FieldLon] = 0;
			}
			if($Count) {
				printf("<h2>Drawing %d: (<a href='%s'>delete</a>)</h2>\n", $i, LinkSelf($DelAll));
				printf("<p>Style: %s</p>", OptionList("d${i}_style"));
				printf("%s", ColourChooser("d${i}_colour"));
				printf("%s\n", $Html);
			}
			else {
				printf("<h2>Drawing %d:</h2>\n<p><a href='%s'>Start</a></p>\n", $i, LinkSelf(array('d_num'=>$i, 'dp_num'=>0)));
			}
		}
	break;
	}
	case 'API': {
		printf("<h2>API for accessing these maps</h2>\n");
		printf("<p>All aspects of this site are available through HTTP GET requests.  The fields are described below:</p>");
		printf("<p>Some of these fields are for navigating the website, and would not typically be used when requesting an image (e.g. show_icon_list)</p>");
		printf("<p>Be sure to include show=1 to get the image instead of this website!</p>");
		printf("<p><a href='./?api=json'>Get this API as JSON</a></p>");
		printf("<hr/>\n<div class='api'>");
		printf("<p><b>show</b> (Returns the image rather than this web interface)</p><ul><li>0 = view image-editing tools</li><li>1 = view as image</li></ul>");
		foreach($Fields as $Field => $Details) {
			printf("<p><b>%s</b> (%s): ", $Field, $Details['name']);
			switch($Details['type']) {
			case "numeric":
				printf("numeric (%1.2f to %1.2f)\n", $Details['min'], $Details['max']);
			break;
			case 'option':
				printf("</p><ul>\n");
				foreach($Details['options'] as $Option) {
					printf("<li>%s</li>\n", $Option);
				}
				printf("</ul>\n");
			break;
			case 'tab':
				print "one of the tab names</p>\n";
			break;
			case 'colour':
				print "in 3-character hexadecimal RGB format, from 000 = black to F00 = red to FFF = white</p>\n";
			default:
				print "</p>\n";
			}
		}
		printf("<p><b>&?123,456</b> (imagemap coordinates, must be at end of query string): handles actions caused by clicking on a server-side imagemap.  The action taken depends on <b>mode=</b> (e.g. <i>mode=Location</i> causes the map to be centred on this pixel, and possibly zoomed-in if <i>zoom_to_clicks=on</i>)</p>");
		printf("</div>"); // api
	break;
	}
}

printf("</div><!-- main -->\n");
printf("</div><!-- everything -->\n");
printf("<div class='footer'>%s</div>\n</body>\n</html>\n", footer());

function markerInUse($i) {
	global $Data;
	return($Data["mlat$i"] != 0 && $Data["mlon$i"] != 0);
}

function ShowImage($IsMap = false) {
	global $Data;
	printf("<p>%s<img src='%s' width='%d' height='%d' %s/>%s</p>\n", $IsMap?"<a href='".LinkSelf()."'>":"", imageUrl(), $Data['w'], $Data['h'], $IsMap?"ismap":"",$IsMap?"</a>":"");
}

function T($EnglishText) {
	global $Data;
	$Lang = $Data["lang"];
	return($EnglishText);
}

function title() {
	return("StaticMapMaker");
}

function footer() {
	$URL = "http://www.openstreetmap.org/copyright";
	return("<p class='footer'>Map data &copy; OpenStreetMap contributors. <a href=\"$URL\">$URL</a>.</p>");
}

function LinkSelf($Changes = array(), $Base = "./") {
	global $Data;
	global $Fields;
	$NewData = $Data;
	foreach($Changes as $k => $v) {
		$NewData[$k] = $v;
	}
	$Query = "";
	foreach($Fields as $Field => $Details) {
		if($NewData[$Field] != FieldDefault($Field) || $Field == "mode") {
			$Query .= sprintf("%s=%s&", urlencode($Field), urlencode($NewData[$Field]));
		}
	}

	return($Base . "?" . $Query);
}
function imageUrl($Changes = array(), $Base="./") {
	return(LinkSelf($Changes, $Base) . "show=1");
}

function HiddenFields($Omit = array()) {
	global $Data;
	global $Fields;
	foreach($Fields as $Field => $Details) {
		if(!in_array($Field, $Omit)) {
			if($Data[$Field] != FieldDefault($Field)) {
				printf("<input type='hidden' name='%s' value='%s'/>\n", 
				htmlentities($Field), 
				htmlentities($Data[$Field]));
			}
		}
	}
	return("./?" . $Query);
}

function ReadFields($Req) {
	global $Fields;
	$Data = array();
	foreach($Fields as $Field => $Details) {
		$Value = $Req[$Field];
		switch($Details['type']) {
		case "numeric":
			if($Value < $Details['min'] || $Value > $Details['max'])
				$Value = FieldDefault($Field);
		break;
		case "tab":
		case "option":
			if(!in_array($Value, $Details['options']))
				$Value = FieldDefault($Field);
		break;
		case "colour":
			if(!preg_match("/^[0-9A-F]{3}$/", $Value))
				$Value = FieldDefault($Field);
		break;
//		case "text":
//			#TODO Add a test?
//		break;
		default:
			printf("<p>Unrecognised field type %s (default-deny means you need to specify what values are valid!)</p>", htmlentities($Details['type']));
			$Value = 0;
		break;
		}
		$Data[$Field] = $Value;
	}
	return($Data);
}

function FieldDefault($Field) {
	global $Fields;
	if(array_key_exists('default', $Fields[$Field]))
		return($Fields[$Field]['default']);
  
	switch($Fields[$Field]['type']) {
	case "tab":
	case "option":
		return($Fields[$Field]['options'][0]);
	case "color":
		return("00F");
	}
	return(0);
}

function OptionList($Field) {
	global $Fields;
	global $Data;
	$Html = "";
	foreach($Fields[$Field]['options'] as $Style) {
		$Html .= sprintf("<a %s href='%s'>%s</a> ", $Data[$Field] == $Style ? " class='selected_span'":"", LinkSelf(array($Field => $Style)), $Style);
	}
	return($Html);
}

function ColourChooser($Field) {
	$Html = "<div class='colour_chooser'><table border='0'><tr><td>";
	global $Data;
	$Stops = array(0,4,8,12,15);
	foreach($Stops as $g) {
		foreach($Stops as $b) {
			foreach($Stops as $r) {
				$Colour = sprintf("%X%X%X", $r,$g,$b);
				$Html .= sprintf("<a class='colour' href='%s' style='background-color:#%s;'>&nbsp;</a>", LinkSelf(array($Field=>$Colour)), $Colour);
			}
		}
		$Html .= "<br/>";
	}
	$Html .= sprintf("</td><td style='background-color:#%s;width:3em;'>&nbsp;", $Data[$Field]);;
	$Html .= "</td></tr></table></div>";
	return($Html);
}

function iconSelector($OutputSymbol) {
	$SymbolDir = "symbols";
	printf("<p>Choose an image for %s<br/>\n", htmlentities($OutputSymbol));
	if($fp = opendir($SymbolDir)) {
		while(($File = readdir($fp)) !== false) {
			if(preg_match("{(\d+)\.png}", $File, $Matches)) {
				$FullFile = sprintf("%s/%s", $SymbolDir, $File);
				$IconID = $Matches[1];

				printf("<span style='symbol'><a href='%s'><img src='%s' border=0 alt='icon $IconID' title='icon $IconID' /></a></span>\n", 
				LinkSelf(array('show_icon_list'=>0, 'choose_marker_icon'=>0, $OutputSymbol=>$IconID)), $FullFile);
			}
		}
    
		closedir($fp);
	}

	printf("</p>");
}

function iconName($IconID) {
	return(sprintf("symbols/%d.png", $IconID));
}

?>
