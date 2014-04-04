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

require_once('utilities.php');
include_once("xmlDataHandler.php");
include_once("schemeFunctions.php");


/**
 * Adds an additional mapping table for associations
 * @param $fromSid -- scheme id from the original scheme
 * @param $fromName -- control name of the assocator 
 * @param $toSid -- schemeid to associate to $fromName
 * @param $toName -- name of project/scheme of $toSid
 */
function addNewMappingTable($fromSid,$fromName,$toSid,$toName) {
	global $db;
	
	$drawAdditionalTable = false;
	
	$fileUploaded = (isset($_FILES['zipFolder']) && $_FILES['zipFolder']['error'] != 4) ? true : false;
	
	$pid = getProjectBySchemeId($toSid);
	
	$xmlDataObject = new XMLDataHandler($pid,$toSid,$fileUploaded);
	
	for ($i=0 ; $i<sizeof($_SESSION['xmlRecordData'][$fromSid]) ; ++$i) {
		//store data in $record to easily read 
		$record = $_SESSION['xmlRecordData'][$fromSid][$i];
		
		if(isset($record[$fromName]) && !empty($record[$fromName])) {
			$isXml = false;
			$recordArray = array();	
			foreach ($record[$fromName] as $assoc) {
				if (preg_match('/^[0-9A-F]+-[0-9A-F]+-[0-9A-F]+$/',$assoc)) {
					$recordArray[] = $assoc;
				} else {					
					$drawAdditionalTable = true;
					$isXml = true;
				 	//foreach xml string, load it into the xmlDataHandler
					$xml = simplexml_load_string($assoc);
					foreach($xml->children() as $sub) {
						$xmlDataObject->loadSpecificData($sub); 
						$recordArray[] = $xmlDataObject->getRecordData();
					}
				}
			}
			
			//store new format into data array
			$_SESSION['xmlRecordData'][$fromSid][$i][$fromName] = $recordArray;
		} 
	}
	
	//if xml was loaded, then show the mapping table
	if($drawAdditionalTable) {
		echo '<div id="'.$fromName.'_'.$fromSid.'">';
		echo "Associate $fromName -> $toName";
		$xmlDataObject->drawControlMappingTable(false);
		echo '</div>';
	}
}

/**
 * Remove all files and directories from given file path
 * @param $basedir - file path to remove files and directories from 
 */
function clearUploadedFiles($baseDir = extractFileDir) {
	$dirExceptions = array('.','..','.svn');
	$fileExceptions = array('index.php');
	
	$files = scandir($baseDir);
	foreach ($files as $f) {
		if (is_dir($baseDir.$f)) {
			if(!in_array($f,$dirExceptions)) {
				clearUploadedFiles($baseDir.$f."/");
				rmdir($baseDir.$f);
			}
		} else if (!in_array($f,$fileExceptions)) {
			unlink($baseDir.$f);
		}
	}
}

/**
 * Add autofill rule to a control
 * @param xml - xml options of control to autofill
 * @param fillValue - value to autofill  based on paramRules
 * @param paramRules - rules to autofill fillValue
 * @param fromType - control type of the paramControl
 * @param ruleNum - id attribute of the param tag 
 */
function createAutoFillRule($xml,$fillValue,$paramRules,$fromType,$ruleNum) {
	$rules = $xml->autoFillRules;
	$rule = $rules->addChild('param');
	$rule->addAttribute('id',$ruleNum);
	
	$rule->addChild('to',$fillValue);
	$from = $rule->addChild('from');
	
	$i = 0;
	while (isset($paramRules["val$i"])) {
		if (is_array($paramRules["val$i"]) && $fromType == 'DateControl') {
			$val = $from->addChild("val$i");
			foreach ($paramRules["val$i"] as $type=>$value) {
				$val->addChild($type,trim($value));
			}
		}
		else {
			$from->addChild("val$i",$paramRules["val$i"]);
		}
		++$i;
	}
	$from->addChild('op',$paramRules["op"]);
	
	return $xml;
}

/**
 * Find auto fill rule based on control options
 * @param paramOptions 
 * @param paramData 
 * @param value
 */
function findAutoFill($paramOptions,$paramData,$value) {
	global $db;
	
	$autoFillValue = "";
	
	$query = "SELECT name,options FROM p".$_SESSION['currentProject']."Control WHERE schemeid=".$_SESSION['currentScheme']." AND cid=".$paramOptions->autoFill." LIMIT 1";
	$query = $db->query($query);
	$data = $query->fetch_assoc();
	$xml = simplexml_load_string($data['options']);
	 
	$autoFillRules = $xml->autoFillRules;
	
	foreach($autoFillRules->children() as $param) {
		$from = $param->from;
		switch($from->op) {
			case "like":
				break;
			case "equals":
				break;
			case "between":
				if ($paramData['type'] == "DateControl") {
					$inputValue = explode(" ",$value[0]);
					list($detMon,$detDay,$detYear) = explode('/',$inputValue[0]);
					
					$inputValue = array();
					$val0 = array();
					$val1 = array();
					if ($detYear != 0) { 
						$inputValue[] = $detYear;
						$val0[] = $from->val0->year;
						$val1[] = $from->val1->year; 
					}
					if ($detMon != 0) { 
						$inputValue[] = $detMon;
						$val0[] = $from->val0->month;
						$val1[] = $from->val1->month; 
					}
					if ($detDay != 0) { 
						$inputValue[] = $detDay;
						$val0[] = $from->val0->day;
						$val1[] = $from->val1->day; 
					}
					$inputValue = implode('-',$inputValue);
					$val0 = implode('-',$val0);
					$val1 = implode('-',$val1);
					
					$between = false;
					if(($inputValue >= $val0 && $inputValue <= $val1) || ($inputValue >= $val1 && $inputValue <= $val0)) {
						$between = true;
					}
				} else {
					$between = false;
					//code for between, but not a date
				}
				
				if ($between) {
					$autoFillValue = array(strip_tags($param->to->asXML()));
				}
				break;
		}
	}
	return array($data['name'],$autoFillValue);
}

/**
 * Find file control to insert based on file type
 */
function findProperFileControl($file) {
	global $db;	
	$returnValue = false;
	
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
    $fileType = finfo_file($finfo, extractFileDir.$file);
    finfo_close($finfo);
    
	//TODO: add extractvalue into mysql statement
	$query = "SELECT name,options FROM p".$_SESSION['currentProject']."Control WHERE schemeid=".$_SESSION['currentScheme']." AND type IN ('ImageControl','FileControl')";
	
	$result = $db->query($query);
	while ($data = $result->fetch_assoc()) {
		//extractValue(options,'/options/allowedMIME/mime/option[self:text()=""])
		//extractValue(doc,'/book/author/surname[self:text()="Date"]')
		if( in_array($fileType,getOptions($data['options'])) ) {
			$returnValue = $data['name'];
			break;
		}
	}
	
	return $returnValue;
}

/**
 * Get associations for control id
 */
function getAllowedAssociations($cid) {
	global $db;
	
	// Get the list of currently allowed schemes from the pXControl table
	$currentSchemes = array();
        
	// The escape clause below IS necessary because $cid comes right from a POST variable which could
	// presumably be spoofed.
	$query = 'SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' LIMIT 1';
	$results = $db->query($query);
	if ($results->num_rows != 0) {
		$array = $results->fetch_assoc();
		if ($array['options'] == 'none') $array['options'] = '<options />';
		$xml = simplexml_load_string($array['options']);
		if (!empty($xml->scheme)) {
			foreach($xml->scheme as $scheme) $currentSchemes[] = (string)$scheme;
		}
	}
	
	return $currentSchemes;
}

/**
 * Get control type by control name
 */
function getControlType($controlName,$sid) {
	global $db;
	
	$pid = getProjectBySchemeId($sid);
	
	$query = "SELECT cid,type FROM p".$pid."Control WHERE name='$controlName' AND schemeid=$sid LIMIT 1";
	$result = $db->query($query);
	$data = $result->fetch_assoc();
	return $data['cid']."->".$data['type'];
}

/**
 * Get fields from all file and image controls
 */
function getFileControls($returnArr=array('*')) {
	global $db;	

	$controlInfo = array();
	$query = "SELECT ".implode(",",$returnArr)." FROM p".$_SESSION['currentProject']."Control WHERE schemeid=".$_SESSION['currentScheme']." AND type IN ('ImageControl','FileControl')";
	$result = $db->query($query);
	
	while ($data = $result->fetch_assoc()) {
		foreach($data as $name=>$value){
			$controlInfo[] = "$name->$value";
		}
	}
	
	echo implode('///',$controlInfo);
}

/**
 * get all options from controls
 */
function getOptions($xmlOptions) {
	$xml = simplexml_load_string($xmlOptions);
	
	$options = array();
	foreach($xml->allowedMIME->mime as $mime) {
		array_push($options,$mime);
	}
	
	return $options;
}

/**
 * get all schemenames from scheme ids
 */
function getSchemeNames($schemeIds) {
	global $db;

	$schemeIds[] = 0;
	
	$nameQuery =  'SELECT scheme.schemeName AS schemeName, scheme.schemeid AS id,';
	$nameQuery .= ' project.name AS projectName FROM scheme LEFT JOIN project USING (pid)';
	$nameQuery .= ' WHERE scheme.schemeid IN ('.implode(',',$schemeIds).')';
       
	$schemeNames = array();
	$nameQuery = $db->query($nameQuery);
	while($result = $nameQuery->fetch_assoc()) {
		$schemeNames[$result['id']] = array('project' => $result['projectName'],
											'scheme'  => $result['schemeName']);
	}
	
	return $schemeNames;
}

/**
 * remove an xml tag based on attribute
 */
function removeXMLByAttribute($xml,$attName,$attValue) {
	$returnStr = "";
	foreach($xml->children() as $childType=>$childValue)
	{
		$keep = true;
		$attArray = array();
		foreach($childValue->attributes() as $a=>$b) {
		   	if($a == $attName && $b == $attValue) {
		   		$keep = false;
		   	} else {
		   		$attArray[] = "$a=\"$b\""; 
		   	}
		}
		
		if ($keep) {
			if (sizeof($childValue->children()) > 0) {
		   		$returnStr .= "<$childType ".implode(" ",$attArray).">".removeXMLByAttribute($childValue,$attName,$attValue)."</$childType>";
		   	}
		   	else {
				$returnStr .= "<$childType ".implode(" ",$attArray).">$childValue</$childType>";
		   	}
		}
	}
	
	return $returnStr;
}

/**
 * Remove xml tag by value
 */
function removeXMLByValue($xml,$value) {
	$returnStr = "";
	foreach($xml->children() as $childType=>$childValue)
	{
		if ($childValue != $value) {
		   	if (sizeof($childValue->children()) > 0) {
		   		$child = removeXMLByValue($childValue,$value);
		   		$returnStr .= "<$childType>$child</$childType>";
		   	}
		   	else {
		   		// $childValue is unescaped now, probably from foreach, so we 
		   		// need to escape it again.  Fortunately, $value is also unescaped, 
		   		// so the comparison in the previous if statement still works.
		   		$childValue = xmlEscape($childValue);
		   		$returnStr .= "<$childType>$childValue</$childType>";
		   	}
		} else {
			//once the value is found, set the value to an empty string to be 
			//sure that if there are duplicates, that only one gets removed.
			$value = "";
		}
	}
	return $returnStr;
}


if(isset($_POST['action'])) {
	if($_POST['action'] == 'getFileControls') {
	    getFileControls($_POST['controls'],true);
	} else if ($_POST['action'] == 'loadRecordData') {
		loadRecordData(simplexml_load_string($_POST['xmlString']),$_POST['cd'],$_POST['sd'],$_POST['uploadedFiles'],$_POST['schemeId']);
	} else if ($_POST['action'] == 'getControlType') {
		print getControlType($_POST['controlName'],$_POST['schemeId']);
	} else if ($_POST['action'] == 'getAllowedAssociations' && isset($_POST['cid'])) {
		$allowedSchemeIds = getAllowedAssociations($_POST['cid']);
		$schemeNames = getSchemeNames($allowedSchemeIds);

		$jsFormattedSchemes = array();
		foreach ($schemeNames as $id=>$data) {
			$jsFormattedSchemes[] = "$id->".$data['project']."\\".$data['scheme'];
		}
		print implode('///',$jsFormattedSchemes);
	} else if ($_POST['action'] == 'addNewMappingTable' && isset($_POST['toSchemeId']) 
				&& isset($_POST['fromSchemeId']) && isset($_POST['fromTagname'])) {

		addNewMappingTable($_POST['fromSchemeId'],$_POST['fromTagname'],$_POST['toSchemeId'],$_POST['toSchemeName']);
	}
}
?>