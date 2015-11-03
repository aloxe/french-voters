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

// This code is called when page is initialized.
$(document).ready(function() { 
	
	// initialize buttons and elements on page in state we want it to
	$('#button-CsvToGeoJson').hide();
	$("#upload-progress-bar").width('0%');
	$("#progress-progress-bar").width('0%');
	$("#fix-csv-upload-progress-bar").width('0%');
	$("#fix-csv-process-progress-bar").width('0%');
	
	// setup 3 second interval in which it calls CheckStatus repeadetly. 
	// This will cause page to update about current status
	setInterval( CheckStatus, 3000 );
	
	// also check for status right away.
	CheckStatus();
	
	// scripted way to handle the progress of uploading of file.
	// This code handles only displaying of the progressbar.
	// After file is uploaded the <form> defines which script should be called
	 $('#uploadForm').submit(function(e) {	
		if($('#upfile').val()) {
			e.preventDefault();
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
				},
				resetForm: true 
			}); 
			return false; 
		}
	});

	 $('#fix-csv-uploadForm').submit(function(e) {	
		if($('#fix-csv-upfile').val()) {
			e.preventDefault();
			$(this).ajaxSubmit({ 
				target:   '#targetLayer', 
				beforeSubmit: function() {
				  $("#fix-csv-upload-progress-bar").width('0%');
				},
				uploadProgress: function (event, position, total, percentComplete){	
					$("#fix-csv-upload-progress-bar").width(percentComplete + '%');
					$("#fix-csv-upload-progress-bar").html('<div id="progress-status">' + percentComplete +' %</div>')
				},
				success:function (){
				},
				resetForm: true 
			}); 
			return false; 
		}
	});
}); 

// get me information about current status on server:
function CheckStatus()
{
	// send asynchronous (not blocking user) request for status
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
				// If process status says that there is a file that should be processed
				// invoke the processing. This is done repeadetly because script can always do only 
				// part of the job.
				// This call is asynchronous again to avoid blocking of user.
				$.ajax({
					method: "POST",
					url: "processing.php",
					data: { action: "PROCESS", id : processingStatus.ProcessingId },
				})
			}
			
			if ( processingStatus.Status == "PROCESSED-ERROR-CSV")
			{
				$.ajax({
					method: "POST",
					url: "processing.php",
					data: { action: "PROCESS-FIX-CSV", id : processingStatus.ProcessingId },
				})
			}
		});
}

function UpdateStatusInfo()
{
	if ( processingStatus == null )
	{
		$("#status-info").html(' Status: no information');
		return;
	}
	
	var processedLine = processingStatus.NextLineToProcess;
	$("#status-info").html(
	' Status: ' + processingStatus.Status + ' Id: ' + processingStatus.ProcessingId +
	'<br/> Items: ' + processedLine + ' / ' + processingStatus.FileLinesCount +
	'<br/> Converted fine: ' + processingStatus.LinesConverted +
	'<br/> Conversion failed for: ' + processingStatus.LinesConversionFailed );
	
	if ( processingStatus.Status != "STARTED" )
	{
		percentComplete = processedLine * 100 / processingStatus.FileLinesCount;
		$("#process-progress-bar").width(percentComplete + '%');
		$("#process-progress-bar").html('<div id="progress-status" >' + processedLine + '/' + processingStatus.FileLinesCount +'</div>')
	}
	
	if ( processingStatus.Status == "FINISHEDGEOCSV" || processingStatus.Status == "FINISHEDGEOJSON" || 
		processingStatus.Status == "PROCESSING-ERROR-CSV" || processingStatus.Status == "PROCESSED-ERROR-CSV" )
	{
		$('#button-CsvToGeoJson').show(); 
		
		if ( processingStatus.LinesConversionFailed > 0 || 
			processingStatus.Status == "PROCESSING-ERROR-CSV" || processingStatus.Status == "PROCESSED-ERROR-CSV" )
		{
			// display way to fix the CSV
			document.getElementById('fix-csv').style.display = 'block';
			
			var csvFixInfo = 'Failed ' + processingStatus.LinesConversionFailed + ' from ' + processingStatus.FileLinesCount +
			' lines. <a href="data/' + processingStatus.ProcessingId + '_errors.csv">Records to fix</a>' +
			', <a href="data/' + processingStatus.ProcessingId + '_processed.csv">Correctly processed records</a>.';
			
			document.getElementById('fix-csv-info').innerHTML = csvFixInfo;
			document.getElementById('fix-csv-process-id').setAttribute("value", processingStatus.ProcessingId);
			
			processedLine = processingStatus.ReevCsvNextLineToProcess;
			linesCount = processingStatus.ReevCsvLinesCount;
			percentComplete = processedLine * 100 / linesCount;
			$("#fix-csv-process-progress-bar").width(percentComplete + '%');
			$("#fix-csv-process-progress-bar").html('<div id="progress-status" >' + processedLine + '/' + linesCount +'</div>')
		}
	}
}

// Converts GeoCSV (CSV with coordinates) to GeoJSON (which is displayable by map)
// This call is quick, but we call it async anyhow.
// We check status repeadetly so we will find out there if it was processed OK
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

<div class="clear section">
	<form id="uploadForm" action="processing.php" method="post">
		<!-- On limite le fichier à 1000Ko -->
		<input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
		<input type="hidden" name="action" value="INIT" />
		
		<label for="upfile">1) Upload CSV File:</label>
		<div class="section">
			<input name="upfile" id="upfile" type="file" class="InputBox" />
			<input type="submit" id="btnSubmit" value="Submit" class="btnSubmit" />
		</div>
	</form>

	<div class="progress-div section">
	  <div id="upload-progress-bar"></div>
	</div>
</div>

<div class="clear section">
	<div class="label">2) Convert addresses</div>
	<tt>leave this page open untill the end of the process</tt>
	<div id="status-info" class="section">Fetching current status... </div>

	<div class="progress-div section">
	  <div id="process-progress-bar"></div>
	</div>
</div>

<div class="clear section" id="fix-csv" style="display:none" >
	<div class="label">2.1) Fix wrong addresses</div>
	<tt>There are addresses where geocoding failed. You can find them in file below.
Download the file, fix addresses, and upload it here below to geocode them again. </tt>
	<div id="fix-csv-info"> Failed: x/y. <a href="link">Records to fix</a> </div>

	<form id="fix-csv-uploadForm" action="processing.php" method="post">
		<input type="hidden" name="action" value="INITFIXCSV" />
		<input type="hidden" name="id" value="not filled" id="fix-csv-process-id" />

		<!-- On limite le fichier à 1000Ko -->
		<input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
		
		<div class="section">
			<input name="upfile" id="fix-csv-upfile" type="file" class="InputBox" />
			<input type="submit" id="btnSubmit" value="Submit" class="btnSubmit" />
		</div>
	</form>

	<div class="progress-div section">
	  <div id="fix-csv-upload-progress-bar"></div>
	</div>
	<div class="progress-div section">
	  <div id="fix-csv-process-progress-bar"></div>
	</div>
</div>

<div class="clear section">
	<div class="label">3) Get the map</div>
	<tt>file will be converted to GeoJson</tt>
	<button id="button-CsvToGeoJson" type="button" onclick="CsvToGeoJson()" > Finalize CSV to GeoJSON </button>
</div>

</body>
</html>
