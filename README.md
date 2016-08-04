# GoogleGeoCode
PHP GeoCoders using Google's API
Created by Matthew Stubenberg
Copyright Maryland Volunteer Lawyers Service 2016

##Description
This class is desinged to work with Google Geocoding API. It will work with both the free or the premium plans.
You will still need to sign up with Googles API to get a client key or the premium id and cyrptokey.
For Google Documentation: https://developers.google.com/maps/documentation/

##Free GeoCodes (Limit 2500/day)
  <pre>
  $getcords = new GoogleGeoCoder('YOUR-GOOGLE-CLIENT-KEY');
  $resultarray = $getcords->GeoCode('210 North Charles Street, Baltimore, MD');
  print_r($resultarray);
  </pre>

##Premium GeoCodes (Based on pricing)
  Same as above except for construction
  <pre>
  $getcords = new GoogleGeoCoder('YOUR-GOOGLE-CLIENT-ID','YOUR-GOOGLE-CRYPTOKEY');
  </pre>
  
##Custom Parser
  Want to build your own parser for the JSON returned by Google. No Problem!
  <pre>
  $getcords = new GoogleGeoCoder('YOUR-GOOGLE-CLIENT-KEY');
  $requesturl = $getcords->createURL('201 North Charles Street, Baltimore, MD');
  $google_return_array = $getcords->sendCurl($requesturl);
  </pre>
  
##Status Codes
  To get the reason for a status code call the getReason() method.
  100 = Accuracy not good enough. Means google found something but it could just be "somewhere in maryland"
  200 = Address found and accuracy good enough.
  300 = Overy your query limit
  400 = Unknown

##Specific get Functions
If you don't want to parse through the return array you can get the return values individually.
<pre>
  getLongitude()
  getLatitude()
  getStatus()
  getReason()
  getFullAddress()
  getCounty()
  getStreetNumber()
  getStreet()
  getCity()
  getState()
  getZip()
</pre>
##Return Array:
Results returned by the geoCode($address) function.
<pre>
  [StreetNumber] => 201
  [Street] => North Charles Street
  [City] => Baltimore
  [County] => Baltimore City
  [State] => Maryland
  [Zip] => 21201
  [FullAddress] => Saint Paul Plaza, 201 N Charles St, Baltimore, MD 21201, USA
  [Latitude] => 39.2913541
  [Longitude] => -76.6146181
  [Status] => 200
  [Reason] => Success
</pre>
