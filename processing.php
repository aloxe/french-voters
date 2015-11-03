<?php

ini_set('display_errors', 1); 
error_reporting(E_ALL);

require_once( 'geocoding.php' );
require_once( 'log.php' );

class ProcessingResult
{
	public $ProcessingId;
	public $Status;
	
	public $NextLineToProcess;
	public $FileLinesCount;
	public $LinesConverted;
	public $LinesConversionFailed;
	
	public $ReevCsvNextLineToProcess;
	public $ReevCsvLinesCount;
}

// class contains code for reading CSV, geocoding the addresses, storing temporary CSV and conversion to geoJson
class Processing
{
	// === Internal - handling of other process may be running ===
	
	const StatusStarted = "STARTED"; // just started, first item
	const StatusProcessing = "PROCESSING"; // working on it
	const StatusProcessed = "PROCESSED"; // worked on batch, batch finished
	const StatusFinishedGeoCsv = "FINISHEDGEOCSV"; // finished - all data processed to state 1
	const StatusFinishedGeoJson = "FINISHEDGEOJSON"; // finished - all data processed to state 2
	const StatusProcessingErrorCsv = "PROCESSING-ERROR-CSV"; // working on error CSV again
	const StatusProcessedErrorCsv =  "PROCESSED-ERROR-CSV"; // working on error CSV again
	
	const ItemsToProcessInOneRun = 5;
	const DataFolderName = "data";
	const UploadDataFolderName = "data/upload";
	
	// returns path of full input file name
	function InputDataFileName( $id )
	{
		return self::UploadDataFolderName . "/" . $id . ".csv";
	}
	
	// returns full path of status file name
	function StatusFileName( $id )
	{
		return self::DataFolderName . "/" . $id . ".status";
	}
	
	function ProcessedDataFileName( $id )
	{
		return self::DataFolderName . "/" . $id . "_processed.csv";
	}
	
	function ErrorsDataFileName( $id )
	{
		return self::DataFolderName . "/" . $id . "_errors.csv";
	}
	
	function ReevaluatingErrorsDataFileName( $id )
	{
		return self::DataFolderName . "/" . $id . "_err_trying_again.csv";
	}
	
	// === Internal - transformation processing ====
	
	function ReadStatus( $id )
	{
		$s = file_get_contents( self::StatusFileName( $id ) );
		$status = unserialize( $s );
		return $status;
	}
	
	function WriteStatus( $status )
	{
		$s = serialize( $status );
		file_put_contents( self::StatusFileName( $status->ProcessingId ) , $s);
	}

	// $status is ProcessingResult
	// $maxRows = how many rows to read (at maximum)
	// returns ProcessingResult
	function ParseCsvLines( $status, $maxRows = 5 )
	{
		$id = $status->ProcessingId;
		$f = file( self::InputDataFileName( $id ) );
		
		// read either X rows, or number of rows left, whichever is smaller/closer
		$startRow = $status->NextLineToProcess;
		$toRead = $status->FileLinesCount - $startRow;
		if ( $maxRows < $toRead )
			$toRead = $maxRows;
		Logger::Log( "Parsing started, from line ". $startRow ." to line ". ($startRow + $toRead) );
		
		// open output files
		$errorCsv = fopen( self::ErrorsDataFileName( $id ), 'a+');
		$processedCsv = fopen( self::ProcessedDataFileName( $id ), 'a+');
		
		// read all rows of input file
		for ( $i = 0; $i < $toRead; ++$i )
		{
			$rowNumber = $startRow + $i;
			
			$items = str_getcsv( $f[ $rowNumber ] );
			
			// geocode the address, which is in 4th cell
			$geocoded = GeoCoding::GeocodeLocation( $items[ 3 ] );
			Logger::Log( "Parsing line ". $rowNumber .", result: ". ($geocoded->valid ? "OK" : "Failed") );
			
			// add row number to start, to the first cell of the row
			array_unshift( $items, $rowNumber );
			
			if ( $geocoded->valid )
			{
				// add coordinates to end
				$items[] = $geocoded->lat;
				$items[] = $geocoded->lon;
				
				// store items to 'good' csv
				fputcsv( $processedCsv, $items );
				
				++$status->LinesConverted;
			}
			else
			{
				// store items to 'bad' csv
				fputcsv( $errorCsv, $items );
				++$status->LinesConversionFailed;
			}
			
			$status->NextLineToProcess = $rowNumber + 1;
		}
		
		fclose( $errorCsv );
		fclose( $processedCsv );
		
		return $status;
	}
	
	// === used from outside ===
	
	// returns information if there is already some process in progress
	// returns null if there is none
	function GetRunningStatus()
	{
		// look if there are any status files
		$files = glob(self::DataFolderName . "/*.status");
		if ( count( $files ) == 0 )
			return null;
		
		// find last one and find its status
		sort( $files );
		$filename = end( $files );
		
		// read status and return
		$s = file_get_contents( $filename );
		$status = unserialize( $s );
		return $status;
	}
	
	function Init( $filename )
	{
		$status = new ProcessingResult();
		$dt = new DateTime();
		$status->ProcessingId = $dt->format('Y-m-d_G-i-s');
		$status->Status = self::StatusStarted;
		$status->NextLineToProcess = 0;
		$status->LinesConverted = 0;
		$status->LinesConversionFailed = 0;
		$status->ReevCsvNextLineToProcess = 0;
		$status->ReevCsvLinesCount = 0;
	
		$newfilename = self::InputDataFileName( $status->ProcessingId );
		Logger::Log( "Renaming file: ". $filename ." to file: ". $newfilename );
		rename( $filename, $newfilename );
		
		$f = file( $newfilename );
		
		$status->FileLinesCount = count( $f );
		
		Logger::Log( "Init on file ". $filename .", items count ". $status->FileLinesCount ." id ". $status->ProcessingId );
		self::WriteStatus( $status );
		
		return $status;
	}
	
	// returns ProcessingResult
	function DoProcessing( $processingId )
	{
		$status = self::ReadStatus( $processingId );
		
		if ( $status->Status == self::StatusProcessing )
		{
			return $status;
		}
		
		if ( $status->NextLineToProcess < $status->FileLinesCount )
		{
			// write status while working
			$status->Status = self::StatusProcessing;
			self::WriteStatus( $status );
			
			// there is still something left to process
			$status = self::ParseCsvLines( $status, self::ItemsToProcessInOneRun );
		}
		
		$status->Status = ( $status->NextLineToProcess < $status->FileLinesCount ) ?
			self::StatusProcessed : self::StatusFinishedGeoCsv;
		
		self::WriteStatus( $status );
		
		return $status;
	}
	
	function Anonymize( $coord )
	{
		// 1km = roughly 0.008
		$r = (rand( 0, 41 ) - 20) / 10;
		$difference = $r > 0 ? 0.008 : -0.008;
		$difference = $difference + ($difference * abs($r));
		
		return $coord + $difference;
	}
	
	function ProcessingGeoCsvToGeoJson( $processingId )
	{
		$status = self::ReadStatus( $processingId );
		if ( $status->Status != self::StatusFinishedGeoCsv && $status->Status != self::StatusFinishedGeoJson )
		{
		// Function won't do anything if it is not in Finished1 already
			Logger::Log( "Can't move to phase GeoJson as it is not in phase GeoCSV" );
			return $status;
		}
		
		$id = $processingId;
		$f = file( self::ProcessedDataFileName( $id ) );
		$lines = count( $f );
		Logger::Log( "Moving from GeoCSV to GeoJson, ". $lines ." records." );
		
		$catCoords = array( array(), array(), array() );
		$gjson = array(
			"type" => "GeometryCollection",
			"geometries" => array(
				0 => array(
					"type" => "MultiPoint",
					"voteType" => 0,
					"coordinates" => $catCoords[0],
					),
				1 => array(
					"type" => "MultiPoint",
					"voteType" => 1,
					"coordinates" => $catCoords[1],
					),
				2 => array(
					"type" => "MultiPoint",
					"voteType" => 2,
					"coordinates" => $catCoords[2],
					),
				),
			);
		
		for ( $i = 0; $i < $lines; ++$i )
		{
			$items = str_getcsv( $f[ $i ] );
			
			// item[1] can have value "", 1 or 2. "" should be changed to 0.
			$categoryOk = true;
			$category = $items[ 1 ];
			if ( is_numeric( $category ) ) 
			{
				$category = intval( $category );
				if ( $category < 0 || $category > 2 )
				{
					$categoryOk = false;
				}
			} 
			else
			{
				if ( empty( $category ) )
					$category = 0;
				else
					$categoryOk = false;
			}
			
			if ( $categoryOk == false )
			{
				Logger::Log( "Category '". $category ."' on line ". $i ." is not correct." );
				continue;
			}
			
			// add coordinates into valid category
			$lat = self::Anonymize( $items[ 6 ] );
			$lon = self::Anonymize( $items[ 7 ] );
			// $coords = $catCoords[ $category ];
			$gjson[ "geometries" ][ $category ][ "coordinates" ][] = array( $lat, $lon );
			Logger::Log( "Added ". $lat .", ". $lon ." to category ". $category ."." );
		}
		
		Logger::Log( "Writing GeoJson file" );
		$fp = fopen('data/geojson/data.gjson', 'w');
		fwrite($fp, json_encode( $gjson,  JSON_PRETTY_PRINT ));
		fclose($fp);
		
		$status->Status = self::StatusFinishedGeoJson;
		self::WriteStatus( $status );
		
		return $status;
	}
	
	// starts up the re-evaluation process - handles uploaded file and initializes the progress
	function InitFixCsv( $processingId, $filename )
	{
		$status = self::ReadStatus( $processingId );
		if ( $status->Status != self::StatusFinishedGeoCsv && $status->Status != self::StatusFinishedGeoJson )
		{
		// Function won't do anything if it is not in Finished1 already
			Logger::Log( "Can't move to phase FixCsv as it is not in phase FinishedGeoCSV" );
			return $status;
		}
		
		$status->Status = self::StatusProcessedErrorCsv;
		$status->ReevCsvNextLineToProcess = 0;
		$status->ReevCsvLinesCount = 0;
		
		$newfilename = self::ReevaluatingErrorsDataFileName( $processingId );
		Logger::Log( "Renaming file: ". $filename ." to file: ". $newfilename );
		rename( $filename, $newfilename );
		
		$f = file( $newfilename );
		$status->ReevCsvLinesCount = count( $f );
		
		Logger::Log( "InitFixCsv on file ". $filename .", items count ". $status->ReevCsvLinesCount ." id ". $status->ProcessingId );
		self::WriteStatus( $status );
		
		// delete error file, because now it will be filled with next possible errors
		if (! rename( self::ErrorsDataFileName( $processingId ), self::ErrorsDataFileName( $processingId ) ."-old" ) )
		{
			Logger::Log( "InitFixCsv error renaming errors-csv file to old '". self::ErrorsDataFileName( $processingId ) ."'!" );
		}
		
		return $status;
	}
	
	function GetIndexesFromCsvLines( $lines )
	{
		$indexes = array();
		$lines_count = count ( $lines );
		for ( $i = 0; $i < $lines_count; ++$i )
		{
			$csvLineItems = str_getcsv( $lines[ $i ] );
			$indexes[] = $csvLineItems[ 0 ];
		}
		
		return $indexes;
	}
	
	// Takes few lines from 'csv with fixed lines' and processes them. It starts to create 
	function ProcessingErrorCsv( $processingId )
	{
		$status = self::ReadStatus( $processingId );
		if ( $status->Status != self::StatusProcessedErrorCsv )
		{
			Logger::Log( "ProcessingErrorCsv: wrong state :" . $status->Status );
			return $status;
		}
		
		if ( $status->ReevCsvNextLineToProcess >= $status->ReevCsvLinesCount )
		{
			$status->Status = self::StatusFinishedGeoCsv;
			self::WriteStatus( $status );
			return $status;
		}
		
		$status->Status = self::StatusProcessingErrorCsv;
		self::WriteStatus( $status );
		
		$id = $processingId;
		$maxRows = 2;

		$startRow = $status->ReevCsvNextLineToProcess;
		$toRead = $status->ReevCsvLinesCount - $startRow;
		if ( $maxRows < $toRead )
			$toRead = $maxRows;
		Logger::Log( "ProcessingErrorCsv started, from line ". $startRow ." to line ". ($startRow + $toRead) );
		
		// Find indexes in the files. This is important as it will show us which items are already processed
		// and should not be added again (because this can happen, human can make mistake :)
		$finished = file( self::ProcessedDataFileName( $id ) );
		$finishedIndexes = self::GetIndexesFromCsvLines( $finished );
		fclose( $finished );
		
		$toReevaluate = file( self::ReevaluatingErrorsDataFileName( $id ) );
		$toReevaluateIndexes = self::GetIndexesFromCsvLines( $toReevaluate );
		
		$errorCsv = fopen( self::ErrorsDataFileName( $id ), 'a+');
		$processedCsv = fopen( self::ProcessedDataFileName( $id ), 'a+');
		
		$endRow = $startRow + $toRead;
		
		for ( $rowNumber = $startRow; $rowNumber < $endRow; ++$rowNumber )
		{
			$status->ReevCsvNextLineToProcess = $rowNumber + 1;
			
			$toReevaluateIndex = $toReevaluateIndexes[ $rowNumber ];
			if ( in_array( $toReevaluateIndex, $finishedIndexes ) )
			{
				// skip this item, it is already processed, instead process another.
				Logger::Log( "Skipping line ". $rowNumber .", because index ". $toReevaluateIndex ." is already in output." );
				continue;
			}
			
			$csvLineItems = str_getcsv( $toReevaluate[ $rowNumber ] );
			$geocoded = GeoCoding::GeocodeLocation( $csvLineItems[ 4 ] );
			Logger::Log( "Parsing line ". $rowNumber .", result: ". ($geocoded->valid ? "OK" : "Failed") );
			
			if ( $geocoded->valid )
			{
				// add coordinates to end
				$csvLineItems[] = $geocoded->lat;
				$csvLineItems[] = $geocoded->lon;
				
				// store items to 'good' csv
				fputcsv( $processedCsv, $csvLineItems );
				
				// increase number of correct count, decrease it from error count where it was already
				++$status->LinesConverted;
				--$status->LinesConversionFailed;
			}
			else
			{
				// store items to 'bad' csv
				fputcsv( $errorCsv, $csvLineItems );
				
				// no need to increase error count, it is already written there
			}
		}
		
		fclose( $toReevaluate );
		fclose( $errorCsv );
		fclose( $processedCsv );
		
		$status->Status = self::StatusProcessedErrorCsv;
		self::WriteStatus( $status );
		
		return $status;
	}
}

/*
	$p = new Processing();
	$result = $p->GetRunningStatus();
	// $result = $p->Init( "/nfsmnt/hosting1_2/b/7/b79009f8-4f60-4728-a6c4-67484c2431e0/mypage.sk/sub/fv/test2.csv" );
	// $result = $p->DoProcessing( $result->ProcessingId );
	$result = $p->ProcessingGeoCsvToGeoJson( $result->ProcessingId );
	
	echo "Status: ";
	print_r( $result );
*/

	/*
	$coord = 50.05256;
	for ( $a = 0; $a < 10; ++$a )
	{
		$b = $p->Anonymize( $coord );
		echo ( $b." difference is ". ( $b - $coord ) ."<br/>" );
	}
	*/
	
	// This is a connection from JS AJAX call to PHP code. It takes parameters and calls PHP functions.
	if(isset($_POST['action']))
	{
		$action = $_POST['action'];
		$result = null;
		
		// $fileName = isset($_POST['filename']) ? $_POST['filename'] : null;
		$fileName = isset($_FILES['upfile']['tmp_name']) ? $_FILES['upfile']['tmp_name'] : null;
		$id = isset($_POST['id']) ? $_POST['id'] : null;
		
		Logger::Log( "processing.php got request '$action', id:$id , file: $fileName" );
		
		$p = new Processing();
		
		if ( $action == "STATUS" )
		{
			$result = $p->GetRunningStatus();
		}
		
		if ( $action == "INIT" )
		{
			if ( $fileName == null )
				Logger::Log( "filename not specified for INIT" );
			else
				$result = $p->Init( $fileName );
		}
		
		if ( $action == "PROCESS" )
		{
			if ( $id == null )
				Logger::Log( "id not specified for PROCESS" );
			else
				$result = $p->DoProcessing( $id );
		}
		
		if ( $action == "PROCESSGEOJSON" )
		{
			if ( $id == null )
				Logger::Log( "id not specified for PROCESSGEOJSON" );
			else
				$result = $p->ProcessingGeoCsvToGeoJson( $id );
		}
		
		if ( $action == "INITFIXCSV" )
		{
			if ( $id == null )
				Logger::Log( "id not specified for INITFIXCSV" );
			else
				$result = $p->InitFixCsv( $id, $fileName );
		}
		
		if ( $action == "PROCESS-FIX-CSV" )
		{
			if ( $id == null )
				Logger::Log( "id not specified for PROCESS-FIX-CSV" );
			else
				$result = $p->ProcessingErrorCsv( $id );
		}
		
		echo json_encode( $result );
	}
	
?>