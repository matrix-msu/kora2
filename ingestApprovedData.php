<?php
/*
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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Initial Version: Rob Allie, 2010

require_once('includes/utilities.php');
require_once('includes/conf.php');
if (@$solr_enabled) require_once('includes/solrUtilities.php');
//require_once('includes/ingestionClass.php');
requireLogin();
requirePermissions(MODERATOR, 'schemeLayout.php');
requireScheme();
//if(!isset($_REQUEST['approved']) || !isset($_REQUEST['rid'])) 
//{
//	header("Location: reviewPublicIngestions.php");
//}

//get data
$pid = $_SESSION['currentProject'];
$sid = $_SESSION['currentScheme'];
$rid = mysqli_real_escape_string($db, $_REQUEST['rid']);
$approved = ($_REQUEST['approved'] == '1') ? true : false;
$viewall = (isset($_REQUEST['viewall']) && $_REQUEST['viewall'] == 1) ? true : false;
$page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '1';
$dbError = false;


$deleteAllRecords = false;	
$ridQuery = "WHERE id='$rid'";
if($rid == "all")
{
	//User clicked Deny All from reviewPublicIngestions, so we don't want to access individual record IDs.
	$deleteAllRecords = true;	
	//$rid = '%';
	$ridQuery = "";
}


// only get a new kid if we are ingesting the object.
if($approved)
{
	//get the value for the new kid
	$newKid = getNewRecordID($sid);
}

//query data from public table
$dataTable = 'p'.$pid.'PublicData';
//$publicQuery = "SELECT * FROM $dataTable WHERE id='$rid'";
$publicQuery = "SELECT * FROM $dataTable $ridQuery";
$datas = $db->query($publicQuery);

//get collection type from control table and built array with data
$typeArray = array();
$dataTable = 'p'.$pid.'Control';
$types = $db->query("SELECT cid, schemeid, type FROM $dataTable WHERE schemeid='$sid'");
while($type = $types->fetch_assoc())
{
	$typeArray[$type['cid']] = $type['type'];
}

//create an array with cid and value for each control
$dataArray = array();//holds the data to be inserted into the db
$filePaths = array();//holds the file paths to be deleted later
$fileError = array();//holds the new file paths to be deleted upon an error
$i=0;
while($data = $datas->fetch_assoc())
{
	if($typeArray[$data['cid']]=="ImageControl" || $typeArray[$data['cid']]=="FileControl")
	{
		$controlType = $typeArray[$data['cid']]=="ImageControl";
		$fileArray = array();
		$fileValue = $data['value'];
		$p = xml_parser_create();
    	xml_parse_into_struct($p, $fileValue, $vals, $index);
    	//echo "<pre>"; print_r($vals); echo "</pre>";
    	foreach($vals as $val)
    	{
    		if(array_key_exists('value', $val))
    		{
    			$fileArray[$val['tag']] = $val['value'];
    		}
    	}
    	// information about the current files (awaiting approval)
    	$oldLocalName = $fileArray['LOCALNAME'];
        $path = basePath.awaitingApprovalFileDir;
    	$oldPath = $path.$pid."/".$sid."/";
    	$oldThumbPath = $oldPath."thumbs/";
		
    	if($approved)
    	{
	    	//echo "<pre>"; print_r($fileArray); echo "</pre>";
	    	$originalName = $fileArray['ORIGINALNAME'];
	    	$localName = $newKid."-".$data['cid']."-".$originalName;
	    	$size = $fileArray["SIZE"];
	    	$type = $fileArray["TYPE"];
	    	$newXML = "<file><originalName>".$originalName."</originalName><localName>".$localName.
	    			"</localName><size>".$size."</size><type>".$type."</type></file>";
	    	$dataArray[$i]['cid'] = $data['cid'];
	    	$dataArray[$i]['value'] = $newXML;
		}
    	
    	//copy files to appropriate directory
    	$newPath = basePath.fileDir.$pid."/".$sid."/";
    	$projPath = basePath.fileDir.$pid."/";
    	$newThumbPath = $newPath."thumbs/";
    	
    	// we must make directories if they don't exist
    	if(!file_exists($projPath))
    	{
    		mkdir($projPath);
    	}
    	if(!file_exists($newPath))
    	{
    		mkdir($newPath);
    	}
    	// only the Image Control creates thumbs for its files
    	if($controlType == "ImageControl")
    	{
			if(!file_exists($newThumbPath))
	    	{
	    		mkdir($newThumbPath);
	    	}
    	}
    	if($approved)
    	{
    		$copied = copy($oldPath.$oldLocalName, $newPath.$localName);//copy file over
    		$fileError[] = $newPath.$localName;
    	}
    	$filePaths[] = $oldPath.$oldLocalName;
    	if($controlType == "ImageControl")
    	{
    		if($approved)
    		{
    			$copied = copy($oldThumbPath.$oldLocalName, $newThumbPath.$localName);//copy thumb over
    			$fileError[] = $newThumbPath.$localName;
    		}
    		$filePaths[] = $oldThumbPath.$oldLocalName;
    	}
	}
	else//all non file/image control tyes
	{
		$dataArray[$i]['cid'] = $data['cid'];
		$dataArray[$i]['value'] = $data['value'];
	}
	$i++;
}

if($approved)
{
	//echo "<pre>"; print_r($dataArray); echo "</pre>";
	//update the regular data table
	$dataTable = 'p'.$pid.'Data';
	foreach($dataArray as $d)
	{
		$cid = $d['cid'];
		$value = $d['value'];
		$result = $db->query("INSERT INTO $dataTable (id, cid, schemeid, value)
					VALUES ('$newKid', '$cid', '$sid', '$value')");
		
        // ADD TO INDEX //
		// If the particular control just added to the database was a file control, we now can use
		// getFilenameFromRID() as called in solrUtilities, because the table entry exists now.
		// So now that nothing will break, let's get that file added to the Solr index.
		if ($typeArray[$cid]=="FileControl" && @$solr_enabled)
		{
			addToSolrIndexByRID($newKid, $cid);
		}
        
        if(!$result)
		{
			echo gettext("A database error has occurred. Please refresh the page and try again.");
			$dbError = true;
			//in the case of a db error, we should delete the files that were copied over
			foreach($fileError as $f)
			{
				unlink($f);
			}
		}
	}
}


//if no db error, delete the record from the db and the old files
if(!$dbError)
{
	// whether we ingested the data or not, we need to delete it from the public database.
	$dataTable = 'p'.$pid.'PublicData';
	
	if($deleteAllRecords)
	{
		//delete all records from PublicData
		$deleteQuery = "DELETE FROM $dataTable WHERE schemeid='$sid'";
	}
	else
	{
		//delete an individual record from PublicData
		$deleteQuery = "DELETE FROM $dataTable WHERE id='$rid'";
	}
	$result = $db->query($deleteQuery);
	if(!$result)
	{
		echo gettext("A database error has occurred. Please refresh the page and try again.");
		$dbError = true;
	}
	else
	{
		//delete files
		foreach($filePaths as $fileToDelete)
		{
			unlink($fileToDelete);
		}
	}
	
}


if(!$dbError)
{
	if($approved)
	{
		echo "<br /><strong>".gettext('Record Approved')."</strong><br /><br />";
	}
	else
	{
		if($deleteAllRecords) 
		{
			echo "<br /><strong>".gettext('All Records Denied and Erased')."</strong><br /><br />";
		}
		else
		{
			echo "<br /><strong>".gettext('Record Denied and Erased')."</strong><br /><br />";
		}
	}
}

?>

