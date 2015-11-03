<?php

ini_set('display_errors', 1); 
error_reporting(E_ALL);

/*
	These are geocoders that I tried and I could not use:
	* http://geocoder-php.org / https://github.com/geocoder-php/Geocoder - requires Composer (not available on websupport)
	* Nominatim OSM - returns error codes, not meant for massive requests (although one time bulk conversion should be fine)
	* http://geocoder.opencagedata.com - works, limit 2500 a day.
	// http://maps.google.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&sensor=false
*/

require_once('lib/php-opencage-geocode-modified/OpenCage.Geocoder.php');
require_once('log.php');

class GeocodingResult
{
	public $valid;
	public $lat;
	public $lon;
}

class GeoCoding
{
/*
	// Let class to be Singleton
	private static $instance;

	public static function getInstance()
	{
		if (null === static::$instance) {
				static::$instance = new static();
		}
		
		return static::$instance;
	}
	
	protected function __construct() {}
	private function __clone() {}
	private function __wakeup() {}
*/

	// Miro has registered for API key on GeoCage:
	const	Key = 'c4ca1681f8c14e96b5f6fb195ca3dfaa';
	
	// Options to simplify the returned structure a little
	const	Options = "limit=2&fields=status,geometry";
		
	// single instance
	private static $geocoder;
	
	// input - location for example "Mnetes 86, 413 01 MNETES"
	// returns array of two doubles - lat, lon
	public static function GeocodeLocation($locationName)
	{
		if ( static::$geocoder == null )
		{
			static::$geocoder = new OpenCage\Geocoder( self::Key, self::Options);
		}
		
		Logger::Log( "Resolving : " . $locationName );

		$result = static::$geocoder->geocode($locationName);
		
		$ret = new GeocodingResult();
		
		if ( !isset( $result ) ) {
			Logger::Log( "Address: " . $locationName . " was not resolved: No result recieved" );		  
		}
		else if ( isset( $result ) && $result[ 'status' ][ 'message' ] == "OK" &&
				count( $result[ 'results' ] ) > 0 )
		{
			$ret->valid = true;
			$res = $result[ 'results' ][ 0 ][ 'geometry' ];
			$ret->lat = $res[ 'lat' ];
			$ret->lon = $res[ 'lng' ];
			
			Logger::Log( "Address: " . $locationName . " was resolved as: " . $res[ 'lat' ] . " , " . $res[ 'lng' ] );
		}
		else 
		{
			$ret->valid = false;
			$message = "«". $result[ 'status' ][ 'code' ] . " " . $result[ 'status' ][ 'message' ]. "»";
			Logger::Log( "Address: " . $locationName . " was not resolved: " . $message . ".");
		}
		
		// Logger::Log( print_r ( $result, true ) );
		return $ret;
	}

}
