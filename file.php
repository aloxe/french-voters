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
	$('#button-process').hide();
	$("#upload-progress-bar").width('0%');
	$("#progress-progress-bar").width('0%');
	
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

// get me information about current status on server:
function CheckStatus()
{
	// send asynchronous (not blocking user) request for status
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
		});
}

function UpdateStatusInfo()
{
	if ( processingStatus == null )
	{
		$("#status-info").html(' Status: no information');
		return;
	}
	
	$("#status-info").html(
	' Status: ' + processingStatus.Status + ' Id: ' + processingStatus.ProcessingId +
	'<br/> Items: ' + processingStatus.LastProcessedLine + ' / ' + processingStatus.FileLinesCount +
	'<br/> Converted fine: ' + processingStatus.LinesConverted +
	'<br/> Conversion failed for: ' + processingStatus.LinesConversionFailed );
	
	// show the button only after CSV => GEOCSV is finished
	if ( processingStatus.Status == "FINISHEDGEOCSV" || processingStatus.Status == "FINISHEDGEOJSON" )
	{
		$('#button-process').show(); 
	}
	
	// update progress bar with information how far is the CSV => GEOCSV
	if ( processingStatus.Status != "STARTED" )
	{
		percentComplete = processingStatus.LastProcessedLine * 100 / processingStatus.FileLinesCount;
		$("#process-progress-bar").width(percentComplete + '%');
		$("#process-progress-bar").html('<div id="progress-status" >' + processingStatus.LastProcessedLine + '/' + processingStatus.FileLinesCount +'</div>')
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

<div class="clear section">
	<div class="label">3) Get the map</div>
	<tt>file will be converted to GeoJson</tt>
	<button id="button-process" type="button" onclick="CsvToGeoJson()" > Finalize CSV to GeoJSON </button>
</div>

</body>
</html>
