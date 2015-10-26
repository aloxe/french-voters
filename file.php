<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>French voters (TEST)</title>

  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/stepbar.css">

<script src="lib/jquery/jquery-1.11.3.min.js" type="text/javascript"></script>
<script src="lib/jquery/jquery.form.js" type="text/javascript"></script>

<script type="text/javascript">

var processingStatus;

$(document).ready(function() { 
	
	$('#button-process').hide();
	$("#upload-progress-bar").width('0%');
	$("#progress-progress-bar").width('0%');
	setInterval( CheckStatus, 3000 );
	
	 $('#uploadForm').submit(function(e) {	
		if($('#upfile').val()) {
			e.preventDefault();
			$('#loader-icon').show();
			$(this).ajaxSubmit({ 
				target:   '#targetLayer', 
				beforeSubmit: function() {
				  $("#upload-progress-bar").width('0%');
				},
				uploadProgress: function (event, position, total, percentComplete){	
					$("#upload-progress-bar").width(percentComplete + '%');
					$("#upload-progress-bar").html('<div id="progress-status">' + percentComplete +' %</div>')
				},
				success:function (){
					// $('#loader-icon').hide();
				},
				resetForm: true 
			}); 
			return false; 
		}
	});

}); 

function CheckStatus()
{
	// get me information about current status on server:
	$.ajax({
		method: "POST",
		url: "processing.php",
		data: { action: "STATUS" },
		dataType: "json",
	})
		.done(function( result ) {
			// update GUI accordingly
			processingStatus = result;
			UpdateStatusInfo();
			
			if ( processingStatus.Status == "STARTED" || processingStatus.Status == "PROCESSED" )
			{
				$.ajax({
					method: "POST",
					url: "processing.php",
					data: { action: "PROCESS", id : processingStatus.ProcessingId },
				})
			}
		});
}

/*
function FileUploaded( fileName )
{
	$.ajax({
		method: "POST",
		url: "processing.php",
		data: { action: "INIT", file: fileName },
		dataType: "json",
	})
		.done( 
			function( resJson ) {
				processingStatus = $.parseJSON( resJson );
				UpdateStatusInfo();
			});
}
*/

function UpdateStatusInfo()
{
	if ( processingStatus == null )
		return;
	
	$("#status-info").html(
	' Status: ' + processingStatus.Status + ' Id: ' + processingStatus.ProcessingId +
	'<br/> Items: ' + processingStatus.LastProcessedLine + ' / ' + processingStatus.FileLinesCount +
	'<br/> Converted fine: ' + processingStatus.LinesConverted +
	'<br/> Conversion failed for: ' + processingStatus.LinesConversionFailed );
	
	if ( processingStatus.Status == "FINISHEDGEOCSV" || processingStatus.Status == "FINISHEDGEOJSON" )
	{
		$('#button-process').show(); 
	}

	if ( processingStatus.Status != "STARTED" )
	{
		percentComplete = processingStatus.LastProcessedLine * 100 / processingStatus.FileLinesCount;
		$("#process-progress-bar").width(percentComplete + '%');
		$("#process-progress-bar").html('<div id="progress-status" >' + processingStatus.LastProcessedLine + '/' + processingStatus.FileLinesCount +'</div>')
	}
}

function CsvToGeoJson()
{
	$.ajax({
		method: "POST",
		url: "processing.php",
		data: { action: "PROCESSGEOJSON", id : processingStatus.ProcessingId },
	})
}

</script>

</head>
<body>

<h1>French voters abroad (TESTING)</h1>

<div class="clear steps">
<?php
$step = 1;
include('stepbar.php');
?>
<div class="clear"></div>
</div>

<div class="clear">
	<div id="status-info" class="section"> Waiting to read status... </div>
	
	<form id="uploadForm" action="processing.php" method="post">
		<!-- On limite le fichier à 1000Ko -->
		<input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
		<input type="hidden" name="action" value="INIT" />
		
		<label for="upfile">1) Upload CSV File:</label>
		<div class="section">
			<input name="upfile" id="upfile" type="file" class="InputBox" />
			<input type="submit" id="btnSubmit" value="Submit" class="btnSubmit" />
		</div>
		
		<label>2) Wait for processing to finish</label>
		<div id="progress-div" class="section">
			<div id="upload-progress-bar"></div>
			<div id="process-progress-bar"></div>
		</div>
		<!-- <div id="targetLayer" class="section"></div> -->
	</form>

	<label>3) Finish to GeoJson </label>
	<button id="button-process" type="button" onclick="CsvToGeoJson()" > Finalize CSV to GeoJSON </button>
	
</div>

</body>
</html>
