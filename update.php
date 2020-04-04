<?php


// Update these values with your own information.
$apiKey       = "";                         // Your CloudFlare API Key.
$myDomain     = "";                              // Your domain name.
$myDomain     = "";                              // Your domain name.
//$myDomain     = "";                              // Your domain name.
$emailAddress = "";            // The email address of your CloudFlare account.

// These values do not need to be changed.

$ddnsAddress  = $myDomain;                              // If no subdomain is given, update the domain itself.

//$ip           = $_SERVER['REMOTE_ADDR'];                    // The IP of the client calling the script.
//$ip         = $_SERVER['HTTP_CF_CONNECTING_IP'];          // Replace the above line with this one if the DDNS server is behind Cloudflare
$baseUrl      = 'https://api.cloudflare.com/client/v4/';    // The URL for the CloudFlare API.

$ip           = file_get_contents('http://ipecho.net/plain');

// Array with the headers needed for every request
$headers = array(
	"X-Auth-Email: ".$emailAddress,
	"X-Auth-Key: ".$apiKey,
	"Content-Type: application/json"
);

// Sends request to CloudFlare and returns the response.
function send_request($requestType) {
	global $url, $fields, $headers;

	$fields_string="";
	if ($requestType == "POST" || $requestType == "PUT") {
		$fields_string = json_encode($fields);
	}

	// Send the request to the CloudFlare API.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, "curl");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if ($requestType == "POST" || $requestType == "PUT") {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);

	return json_decode($result);
}

// Prints errors and messages and kills the script
function print_err_msg() {
	global $data;

	if (!empty($data->errors)) {
		echo "Errors:\n";
		print_r($data->errors);
		echo "\n";
	}
	if (!empty($data->messages)) {
		echo "Messages:\n";
		print_r($data->messages);
		echo "\n";
	}
	die();
}

// Determine protocol version and set record type.
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
	$type = 'AAAA';
} else {
	$type = 'A';
}

//Update $baseUrl
$baseUrl .= 'zones';

// Build the request to fetch the zone ID.
// https://api.cloudflare.com/#zone-list-zones
$url = $baseUrl.'?name='.$myDomain;

$data = send_request("GET");

// Continue if the request succeeded.
if ($data->success) {
	// Extract the zone ID (if it exists) and update $baseUrl
	if (!empty($data->result)) {
		$zoneID = $data->result[0]->id;
		$baseUrl .= '/'.$zoneID.'/dns_records';
	} else {
		die("Zone ".$myDomain." doesn't exist\n");
	}

// Print error message if the request failed.
} else {
	print_err_msg();
}

// Build the request to fetch the record ID.
// https://api.cloudflare.com/#dns-records-for-a-zone-list-dns-records
$url = $baseUrl.'?type='.$type;
$url .= '&name='.$ddnsAddress;

$data = send_request("GET");

// Continue if the request succeeded.
if ($data->success) {
	// Extract the record ID (if it exists) for the subdomain we want to update.
	$rec_exists = false;					// Assume that the record doesn't exist.
	if (!empty($data->result)) {
			$rec_exists = true;			// If this runs, it means that the record exists.
			$id = $data->result[0]->id;
			$cfIP = $data->result[0]->content;	// The IP Cloudflare has for the subdomain.
	}

// Print error message if the request failed.
} else {
	print_err_msg();
}

// Create a new record if it doesn't exist.
if (!$rec_exists) {
	// Build the request to create a new DNS record.
	// https://api.cloudflare.com/#dns-records-for-a-zone-create-dns-record
	$fields = array(
		'type' => $type,
		'name' => $ddnsAddress,
		'content' => $ip,
		'proxied' => true,
	);
	$url = $baseUrl;

	$data = send_request("POST");

	// Print success/error message.
	if ($data->success) {
		echo $ddnsAddress."/".$type." record successfully created\n";
	} else {
		print_err_msg();
	}

// Only update the entry if the IP addresses do not match.
} elseif ($ip != $cfIP) {
	// Build the request to update the DNS record with our new IP.
	// https://api.cloudflare.com/#dns-records-for-a-zone-update-dns-record
	$fields = array(
		'name' => $ddnsAddress,
		'type' => $type,
		'content' => $ip,
		'proxied' => true,

	);
	$url = $baseUrl.'/'.$id;

	$data = send_request("PUT");

	// Print success/error message.
	if ($data->success) {
		echo $ddnsAddress."/".$type." successfully updated to ".$ip."\n";
	} else {
		print_err_msg();
	}
} else {
	echo $ddnsAddress."/".$type." is already up to date\n";
}
?>
