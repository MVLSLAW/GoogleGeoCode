<?php
/**
* Google GeoCoder
* @copyright  Copyright (c) 2016 Maryland Volunteer Lawyers Service
* @version    Release: 1.0
* @Author Matthew Stubneberg
* @Last Updated August 4, 2016
* 
* This is designed to be used with Server Side Google GeoCode's API. It will work with either the free version or the premium version.
*
* Basic Usage
* 	$getcords = new GoogleGeoCoder('YOUR-GOOGLE-CLIENT-KEY'); //Free
*	$getcords = new GoogleGeoCoder('YOUR-GOOGLE-CLIENT-ID','YOUR-GOOGLE-CRYPTOKEY'); //Premium
* 	$resultarray = $getcords->GeoCode('201 North Charles Street, Baltimore, MD');
*/
class GoogleGeoCoder{
	
	private $clientkey;
	private $cryptokey;
	public $premium;
	public $verbose = false;
	
	public function __construct($clientkey,$cryptokey = false){
		//If just the clientkey is set then it is one of the free 2500/day ones. If the cryptokey is set it is the business account.
		if($cryptokey != false){
			//This is a premium account
			$this->premium = true;
			$this->cryptokey = $cryptokey;
		} else{
			//This is a regular free requset
			$this->premium = false;
		}
		$this->clientkey = $clientkey;
	}
	public function setVerbose($verbose){
		if($verbose){
			echo "VERBOSE SELECTED DEBUGGING ENABLED";
			$this->verbose = true;
		}
	}

	public function geoCode($address){
		//This is the main function. It will return an array (not the google results but my parsing of the google results)
		$url = $this->createURL($address);
		try{
			$this->responsearray = $this->sendCurl($url); //Send the request and save the response
		} catch(Exception $e){
			throw new Exception("Error in Curl " . $e->getMessage());
		}
		if($this->verbose) $this->printArray($this->responsearray);
		
		$this->finalarray = $this->analyzeGeoCodeResults($this->responsearray);
		
		if($this->verbose) $this->printArray($this->finalarray);
		return $this->finalarray;
	}
	public function createURL($address){
		
		if($this->premium){
			$requestaddress = '/maps/api/geocode/json?address=' . $this->encodeAddress($address) .  '&client=' . $this->clientkey;  //Creates the Google GET Request
			$requestaddress = $this->encodeHash($requestaddress);
		} else{
			$requestaddress = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $this->encodeAddress($address) . '&key=' . $this->clientkey; //Creates the Google GET Request
		}
		if($this->verbose) echo "Request Address: " + $requestaddress;
		return $requestaddress;
	}
	public function encodeHash($url){
		$domain = 'https://maps.googleapis.com';
		$my_sign = hash_hmac("sha1", $url, base64_decode(strtr($this->cryptokey, '-_', '+/')), true);
		$my_sign = strtr(base64_encode($my_sign), '+/', '-_');
		if($this->verbose) echo "<br> Hash Signature: " . $my_sign;
		return $domain . $url . '&signature=' . $my_sign;
	}
	public function sendCurl($request){
		//Send CURL. Note this will only work for GET requests.
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $request));
		$resp = curl_exec($curl);
		$decoderesponse = json_decode($resp, true); //Since I only deal with JSON returns I go ahead and convert from JSON to a regular PHP array.
		if($this->verbose && !$resp) echo "<br> CURL Error: " . curl_error($curl);
		curl_close($curl); 
		return $decoderesponse;
	}
	function encodeAddress($address){
		$address = preg_replace('/\s+/', '+', $address); //Turn spaces into '+'
		$address = str_replace(str_split('/.\,'),'',$address); 	//Remove weird characters that are sometimes in the address
		return $address;
	}
	function analyzeGeoCodeResults($responsearray){
		/*
		First looks to see what the google response status was. 
		If it is 'OK' then we cycle through all the locations found looking for 'ROOFTOP' which is best or 'RANGE_INTERPOLATED' which is second best.
		*/
		if(strcasecmp($responsearray['status'],'OK')==0){
			//Means Google was able to find "something"
			for($x=0;$x<count($responsearray['results']);$x++){
				//Cycles through all the locations found by Google.
				$locationtype = $responsearray['results'][$x]['geometry']['location_type'];
				if($locationtype == 'ROOFTOP' || $locationtype == 'RANGE_INTERPOLATED'){
					$returnarray['StreetNumber'] = $this->findAddressElement($responsearray['results'][$x]['address_components'],'street_number');
					$returnarray['Street'] = $this->findAddressElement($responsearray['results'][$x]['address_components'],'route');
					$returnarray['City'] = $this->findAddressElement($responsearray['results'][$x]['address_components'],'locality');
					$returnarray['County'] = $this->findCounty($responsearray['results'][$x]['address_components']); //Google hides the County so there's a seperate function to find the county.
					$returnarray['State'] = $this->findAddressElement($responsearray['results'][$x]['address_components'],'administrative_area_level_1');
					$returnarray['Zip'] = $this->findAddressElement($responsearray['results'][$x]['address_components'],'postal_code');
					$returnarray['FullAddress'] = $responsearray['results'][$x]['formatted_address'];
					$returnarray['Latitude'] = $responsearray['results'][$x]['geometry']['location']['lat'];
					$returnarray['Longitude'] = $responsearray['results'][$x]['geometry']['location']['lng'];
					$returnarray['Status'] = 200;
					$returnarray['Reason'] = 'Success';
					if($locationtype == 'ROOFTOP') break; //Once we find a rooftop we can break. Otherwise it returns a "range_interpolated"
				}
			}
			if(!isset($returnarray)){
				//Means we couldn't find a location type of either "Rooftop" or "Range_interpolated" and anything else isn't accurate enough.
				$returnarray['Status'] = 100;
				$returnarray['Reason'] = 'Results not accurate enough';
			}
		}			
		else if (strcasecmp($responsearray['status'],'OVER_QUERY_LIMIT')==0){
			//If the result is an over query limit I add a seperate status code so that the person can stop sending requests on their end.
			$returnarray['Status'] = 300;
			$returnarray['Reason'] = 'OVER_QUERY_LIMIT';
		}
		else if (strcasecmp($responsearray['status'],'ZERO_RESULTS')==0){
			//If the result is an over query limit I add a seperate status code so that the person can stop sending requests on their end.
			$returnarray['Status'] = 500;
			$returnarray['Reason'] = 'ZERO_RESULTS';
		}
		else{
			$returnarray['Status'] = 400;
			$returnarray['Reason'] = $responsearray['status'];
			if(isset($responsearray['error_message'])) $returnarray['Reason'] .= " " . $responsearray['error_message'];

		}
		return $returnarray;
	}
	function findCounty($address_components_array){
		//Cycles through all the address_components looking at whether the "type" is administrative_area_level_2 which is Google talk for county.
		//The problem is Google doesn't recognize Baltimore City as a county and so it registers Baltimore under locality.
		foreach($address_components_array as $value){
			if($value['types'][0] == 'administrative_area_level_2'){
				$county = $value['long_name'];
				break; // Since Admin Area Level 2 is the best we can just break.
			} else if($value['types'][0] == 'locality' && $value['long_name'] = 'Baltimore'){
				//Checks to make sure it is Baltimore since locality anywhere could be a city. Keeps cycling just to be sure there isn't an admin area level 2.
				$county = 'Baltimore City';
			}
		}
		return (isset($county) ? trim($county) : 'Unknown'); //If county isn't set return "unknown";
	}
	function findAddressElement($address_components_array,$element){
		//Cycle through the address elements to find the requested element ('street_number','route','locality','postal_code')
		//County is the only element that should go through it's own funciton above because of the Baltimore City problem.
		//Not sure what would be fast to cycle through the array 5 times looking for 1 thing each time or 1 time looking for 5 things each time.
		foreach($address_components_array as $value){
			if($value['types'][0] == $element){
				$returnelement = $value['long_name'];
			}
		}
		return (isset($returnelement) ? trim($returnelement) : 'Unknown'); 
	}
	function printArray($array){
		echo "<pre>";
		print_r($array);
		echo "</pre>";
	}
	public function getLongitude(){
		return $this->finalarray['Latitude'];
	}
	public function getLatitude(){
		return $this->finalarray['Longitude'];
	}
	public function getStatus(){
		return $this->finalarray['Status'];
	}
	public function getReason(){
		return $this->finalarray['Reason'];
	}
	public function getFullAddress(){
		return $this->finalarray['FullAddress'];
	}
	public function getCounty(){
		return $this->finalarray['County'];
	}
	public function getStreetNumber(){
		return $this->finalarray['StreetNumber'];
	}
	public function getStreet(){
		return $this->finalarray['Street'];
	}
	public function getCity(){
		return $this->finalarray['City'];
	}
	public function getState(){
		return $this->finalarray['State'];
	}
	public function getZip(){
		return $this->finalarray['Zip'];
	}
	
}