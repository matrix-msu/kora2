<?php 
/**
Copyright (2008) Matrix: Michigan State University

This file is part of KORA.

KORA is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

KORA is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

// Initial Version: Meghan McNeil, 2009

/**
 * Ingests record data into KORA from a XML file
 */
require_once('includes/utilities.php');
include_once('includes/ingestionClass.php');
include_once('includes/controlDataFunctions.php');
requireLogin();

function ingestByScheme($startArray,$schemeId,$mapping) {
	global $db;

	$injestedRecords = array();
	
	$query = "SELECT pid FROM scheme WHERE schemeid=".$schemeId;
	$query = $db->query($query);
	$pid = $query->fetch_assoc();
	$pid = $pid['pid'];
	
	//load control mapping
	foreach ($startArray as $record) {
		//echo "Creating record $count of $total...<br/>";
		$dataArray = array();
		
		$keyfieldMatch = false;
		$matchRecordId = "";
		foreach ($record as $key=>$value) {
			
			if (isset($mapping[$schemeId][$key])) { $new_key = $mapping[$schemeId][$key]; } 
			//this should almost never be executed because all tags must
			//have a mapped control name to continue with ingesting objects, 
			//this is just additional error handling
			else { $new_key = $key; }
			
			if ($new_key == " -- Ignore -- ") {
				continue;
				
			// removed because the "All File Controls" functionality
			// only allowed one control in a scheme to be mapped to uploaded files.
			// Also, we already know the filetype from the mapped scheme control.
//			} else if($new_key == "All File Controls") {
//				// get the correct control based on the mimetype
//				foreach ($value as $k=>$v) {
//					
//					// skip the _attributes array
//					if (is_array($v)){
//						continue;
//					}
//					
//					$new_key = findProperFileControl($v);
//					if($new_key) {
//						$dataArray[$new_key] = array($v);
//						if (isset ($value['_attributes'])) {
//                            $dataArray[$new_key]['_attributes']=$value['_attributes'];
//						}
//					} else {
//                        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
//                        $mimetype = finfo_file($finfo, extractFileDir.$v);
//                        finfo_close($finfo);
//						print '<div class="error">ERROR: There is no control that matches the file type '.$mimetype.'. Could not upload '.$v.'.</div>';
//					}
//				}
			} else {
				$dataArray[$new_key] = $value;
			}
			
			$query = "SELECT cid,type,options FROM p".$pid."Control WHERE schemeid=".$schemeId." AND name='".$new_key."' LIMIT 1";
			$query = $db->query($query);
			$paramData = $query->fetch_assoc();

			if (isset($_SESSION['keyfield']) && $new_key == $_SESSION['keyfield'])
			{
				$keyQuery = "SELECT id FROM p".$pid."Data WHERE schemeid=".$schemeId." AND cid='".$paramData['cid']."' AND value='".$dataArray[$new_key][0]."' LIMIT 1";
				$keyQuery = $db->query($keyQuery);
				$keyData = $keyQuery->fetch_assoc();
				
				if (!empty($keyData))
				{
					$keyfieldMatch = true;
					$matchRecordId = $keyData['id'];
				}
			}
			
			$paramOptions = simplexml_load_string($paramData['options']);
			if (isset($paramOptions->autoFill) && !empty($paramOptions->autoFill)) {
				$autoFillVal = findAutoFill($paramOptions,$paramData,$value);
				if ($autoFillVal) {
					$dataArray[$autoFillVal[0]] = $autoFillVal[1];
				}
			} else if ($paramData['type'] == "AssociatorControl") { 
				$xmlObjects = array();
				$kidObjects = array();
				foreach($dataArray[$new_key] as $obj) {
					if(is_array($obj)) {
						// if there were attributes, unset them.
						// attributes on AssociatorControl are currently unhandled and could cause problems.
						if (isset($xmlObjects['_attributes'])){
							unset($xmlObjects['_attributes']);
						}
						array_push($xmlObjects,$obj);	
					} else {
						array_push($kidObjects,$obj);
					}
				}
				
				if (!empty($xmlObjects)) {
					$schemes = getAllowedAssociations($paramData['cid']); 
					if(isset($mapping[$schemes[0]])) {
						// call this function recursively on the associated object
						$xmlObjects = ingestByScheme($xmlObjects,$schemes[0],$mapping);
					} 
				}
				
				$dataArray[$new_key] = array_merge($kidObjects,$xmlObjects);
			}
		} 
		
		if (!$keyfieldMatch)
		{
			$newRecordId = getNewRecordID($schemeId);
			
			//echo print_r($dataArray);
			echo gettext("Ingesting object ")."$newRecordId... <br/>";
			//ingest record data
			$ingestion = new ingestionForm($pid,$schemeId,$newRecordId);
			$ingestion->ingest($dataArray);
			
			array_push($injestedRecords,$newRecordId);
			
			echo "<br/>";
			//$count++;
		}
		else
		{
			echo gettext("Updating object ")."$matchRecordId... <br/>";
			$ingestion = new ingestionForm($pid,$schemeId,$matchRecordId);
			$ingestion->ingest($dataArray);
			
			array_push($injestedRecords, $matchRecordId);
			
			echo "<br/>";
			
		}
		
	}
	
	return $injestedRecords;
}

// a project an scheme ID must be passed; if it is not, 
// something is wrong so fall back to the project index page
if (!isset($_SESSION['currentProject']) || !isset($_SESSION['currentScheme'])) {
	echo gettext("No project or scheme id specified. Could not ingest records, please try again.");
} 
else if (isset($_POST['controlMapping']) && isset($_SESSION['xmlRecordData'])) {

	
	//parse the mapping string
	$oldMapping = explode("///",$_POST['controlMapping']);
	$mapping = array();
	for ( $i=(sizeof($oldMapping)-1) ; $i>=0 ; --$i ) {
		list($sid,$tag,$control) = explode("->",$oldMapping[$i]);
		
		//add tag and control back into array with tag as the key
		$mapping[$sid][$tag] = $control;
	}
	
	// start output buffering so we can save the output
	ob_start();
	foreach($mapping as $sid=>$maps) {
		//save the scheme mappings for other injections that may happen later on
		$_SESSION['controlMapping_'.$sid] = $maps;	
	}
	//free up memory
	unset($_POST['controlMapping']);
	unset($oldMapping);
	
	//$count = 1;
	//$total = sizeof($_SESSION['xmlRecordData'][$_SESSION['currentScheme']]);
	ingestByScheme($_SESSION['xmlRecordData'][$_SESSION['currentScheme']],$_SESSION['currentScheme'],$mapping);
	
	// store output in session so we can come back to this page with a get request
	$_SESSION['ob']=ob_get_contents();
	ob_end_clean();

	// redirect to results page
	$resultsPage="importMultipleRecords.php?pid=$_SESSION[currentProject]&sid=$_SESSION[currentScheme]&page=results";
	echo '<script type="text/javascript">window.location="'.$resultsPage.'"</script>';
	echo 'Import Complete!<br/><br/><a href="'.$resultsPage.'">Click to view results</a>';
	//remove record data from $_SESSION after ingested
	unset($_SESSION['xmlRecordData']);
	clearUploadedFiles();
	

	//echo "Ingested $count record(s) successfully!";
}

?>
