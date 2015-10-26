<?php

class Logger
{
	public static function Log( $message )
	{
		$timenow = new DateTime();
		$timestamp = $timenow->format("Y-m-d\TG:i:s:u");
		file_put_contents ( "processing.log", $timestamp . ": ". $message . "\n", FILE_APPEND );
	}
}

?>