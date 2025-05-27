<?php

// vi: ts=4 sw=4 nowrap
//
// solaredge_monitor.php -  API call to monitor current power
// 
// This script is designed to run on a cron job and check the current power
// production of a SolarEdge system. It checks the current power and total power
// production, and if they fall below a certain threshold, it sends an email alert.
// Generally run this 3 times per day, but you can adjust the cron job as needed. 
// 5 9,12,15 * * * /usr/bin/php ~/bin/solaredge_monitor.php 
/*
MIT License

Copyright (c) 2021 Mike Tremaine

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/*

Step 1: Call overview
Do we have power?

Step 2: Call list
Loop for Serial Number:

Step 3: Call S/N Data 
Do we have Inventor power... is the % differnce with in acceptable levels?

Step 4:
Send Alert if anything above it wrong.

*/

//Check system state - off switch is simple file check
if (is_file("solaredge.down")) {
   exit;
}

//GLOBALS
date_default_timezone_set('America/Los_Angeles'); //We use time test to make sure the sun is up
$api_key = "#######################"; //Master Key generated in your Dashboard
$site_id = "#######"; // Get from Solaredge Dashboard
$site_name = "Name - Place"; //Any description you want
$api_url = "https://monitoringapi.solaredge.com";
$alert_to = "me@example.com"; //For Multiple Recpt use , to add more
$alert_from = "you@example.com";
$debug = 0;

//Useless to run at night
if ((idate('H') < 9) || (idate('H') > 16)) {
    echo "$site_id Script called during non-productive hours.";
    exit;
}

//Arrays to hold results
$data = array();
$edata = array();
$idata = array();

//BUILD URL and Call API Step 1
$overview = "$api_url/site/$site_id/overview?api_key=$api_key";
$data = get_data($overview);

//Check Data
$alert = 0;

/*
{"overview":{"lastUpdateTime":"2021-11-01 12:21:07",
	"lifeTimeData":{"energy":7.012552E7},
	"lastYearData":{"energy":2.5930344E7},
	"lastMonthData":{"energy":19681.0},
	"lastDayData":{"energy":19681.0},
	"currentPower":{"power":9418.498},
	"measuredBy":"INVERTER"}}
 */

$cpower = $data['overview']['currentPower']['power'];
$tpower = $data['overview']['lastDayData']['energy'];
$lastupdate = $data['overview']['lastUpdateTime'];

if(empty($cpower) && empty($tpower) && empty($lastupdate)) {
	//Assume comm error needs improvement but hard to catch -mgt
	exit;
}
//TIMESTAMPS
$sdate = new DateTime($lastupdate);
$edate = new DateTime($lastupdate);
$sdate->modify('-5 minutes');
$edate->modify('+5 minutes');
$s_time = urlencode($sdate->format('Y-m-d H:i:s'));
$e_time = urlencode($edate->format('Y-m-d H:i:s'));
//Check last update
$_compare = $ndate->diff($sdate);
$_counter = $_compare->i;
//Make thresholds a variable 
if ($tpower < 1.00)
    $alert = 1;
if ($cpower < 1.00)
    $alert = 1;
if ($_counter > 120)
    $alert = 1;

//Step 2: call equipment and data
$equipment = "$api_url/equipment/$site_id/list?api_key=$api_key"; 
$edata = get_data($equipment);

//LOOP foreach invertor 
foreach ($edata['reporters']['list'] as $i => $_data) {
	$serial_num = $_data['serialNumber'];
	$inverter = "$api_url/equipment/$site_id/$serial_num/data?api_key=$api_key&startTime=$s_time&endTime=$e_time";
	$idata[$i] = get_data($inverter);

	//LOOP for telemetries we tried to limit to a 10min window 
	foreach ($idata[$i]['data']['telemetries'] as $i => $_idata) {
		$tapower = $_idata['totalActivePower'];	
		$actpower = $_idata['L1Data']['apparentPower'];	
		if ($tapower < 1.00)
    		$alert = 1;
		if ($actpower < 1.00)
    		$alert = 1;
	}

}

//Verbose in debug mode
if ($debug) {
    echo "Total Power: $tpower - Current Power: $cpower \n";
	echo "Last Update: $lastupdate \n";
}	

//Notification 
if ($alert) {
	$mailto = $alert_to;
    $mailfrom = $alert_from;
    echo "Total Power: $tpower - Current Power: $cpower \n";
	mailalert($mailto, $mailfrom, $data, $idata);
}

//Done
exit;

/*
#############################
    Function get_data: 
#############################
*/
function get_data($URL) {

    //Call  API 
    $ch = curl_init($URL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $results = curl_exec($ch); //combine then check
    $output = json_decode($results, true); //obj or assoc? , $assoc = TRUE
    //close connection
    curl_close($ch);

    return $output;

}

/*
    #####################
    MAILALERT FUNCTION - mailalert called from import_data notifies ai_alertto after each file
    This uses php mail function - If running on Windows set the SMTP to a valid smtp server
    Or make changes to the mail() code below.
    #####################
*/
function mailalert($to_name, $from_name, $data, $idata) {

    global $site_id;
    global $site_name;
    global $lastupdate;

//Pull details
	$cpower = $data['overview']['currentPower']['power'];
	$tpower = $data['overview']['lastDayData']['energy'];


    $subject = "SolarEdge Notification: $site_id $site_name";
    $headers = "From: $from_name" . '\r\n';
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

    $message = "<html><head><title>SolarEdge Notification $site_id</title></head>\n";
    $message .= "<p><h2>Power Production at $site_name is below warning levels</h2></p>\n";
    $message .= "<p><ul>\n";
    $message .= "<li>Current Power: $cpower \n";
    $message .= "<li>Total Power (Today): $tpower \n";
//LOOP for idata
	$inv = 1;
	foreach ($idata as $_idata) {
		foreach ($_idata['data']['telemetries'] as $i => $_tdata) {
			$entry = $_tdata['date'];
			$tapower = $_tdata['totalActivePower'];	
			$actpower = $_tdata['L1Data']['apparentPower'];	
    		$message .= "<li>Total Active Power [Inverter $inv at $entry]: $tapower \n";
    		$message .= "<li>Apparent Power [Inverter $inv at $entry] : $actpower \n";
		}
		$inv++;
	}
    $message .= "<li>LastUpdate: $lastupdate \n";
    $message .= "</ul></p> \n";
    $message .= "</body></html>\n";
    mail($to_name, $subject, $message, $headers);
    return 1;
}

?>

