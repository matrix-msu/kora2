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

function badXML($details) {
	echo gettext("Bad XML Format - ").$details;
	include_once('includes/footer.php');
    die();
}
function showForm() {
	?>
		<form enctype="multipart/form-data" name="xmlUploadForm" id="xmlUploadForm" action="" method="post">
			<p>
		    <label for="xmlFileName"><?php echo gettext('XML File to Load: ');?></label><input id="xmlFileName" name="xmlFileName" type="file" /><br/>
		    </p>
		    <p><input type="submit" name="submit" value="<?php echo gettext('Create Scheme From File');?>"/></p>
		</form>
	<?php 
}
require_once('includes/utilities.php');
requireProjectAdmin();
include_once('includes/header.php');

//
if (isset($_POST['submit']) && !empty($_FILES['xmlFileName']['tmp_name'])) {
	//Extract dataz from the xml file
	$xmlObject = simplexml_load_file($_FILES['xmlFileName']['tmp_name']);
	if (!$xmlObject) {
		echo gettext("Failed to parse XML file.");
		include_once('includes/footer.php');
    	die();
		
	}
	if($xmlObject->getName() != 'Scheme'){
		badXML(gettext("incorrect document root."));
	}
	
	//Continue with extracting the SchemeDesc data
	if (!$xmlObject->SchemeDesc) {
		badXML("");
	}
	$schemeDesc = $xmlObject->SchemeDesc;
	$schName = (string)$schemeDesc->Name;
	$schDesc = (string)$schemeDesc->Description;
	//$schNextId = (string)$schemeDesc->NextId; //Probably shouldn't be used
	
	//Create the scheme itself
	echo gettext("Creating scheme ...");
	$query = "INSERT INTO scheme (pid, schemeName, sequence, description, nextid) ";
    $query .= "SELECT ".escape($_SESSION['currentProject']).", '";
    $query .= $schName."', COUNT(sequence) + 1, '";
    $query .= $schDesc."', 0 FROM scheme ";
    $query .= "WHERE pid=".escape($_SESSION['currentProject']);
    $result = $db->query($query);
    
    if($result !== false){
    	echo gettext("Succeeded!")."<br/>";
    	$sid = $db->insert_id;
    }
    else{
    	echo gettext("Failed - scheme could not be created.")."<br/>";
    	include_once('includes/footer.php');
    	die();
    }
	//Creating collections and collection mapping
	if (!$xmlObject->Collections) {
		badXML(gettext("No collections"));
	}
	echo "Creating Collections ... <br/>";
	$collections = $xmlObject->Collections;
	$colMapping = array(0=>0); //Collection 0 is special, just set the mapping for it here
	foreach($collections->children() as $col){
		$colName = escape((string)$col->Name, false);
		$colDesc = escape((string)$col->Description, false);
		$colSeq = (string)$col->Sequence;
		echo gettext("Creating Collection ")."$colName ... ";
		$colQuery = "INSERT INTO collection (schemeid, name, description,sequence) ";
		$colQuery .= "VALUES ($sid, '$colName','$colDesc',$colSeq)";
		//echo $colQuery;
		$success = $db->query($colQuery);
		if(!$success){
			echo gettext("Failed - collection ").$colName.gettext(" could not be created.");
			deleteScheme($sid);
			include_once('includes/footer.php');
    		die();
		}
		echo gettext("Succeeded!")."<br/>";
		$colMapping[(int)$col->id] = $db->insert_id;
	}
	echo gettext("All Collections successfully created")."<br/>";
	//Creating scheme controls
	$conTable = "p".$_SESSION['currentProject']."Control";
	if (!$xmlObject->Controls) {
		badXML(gettext("No collections"));
	}
	echo gettext("Creating Controls")."... "."<br/>";
	foreach($xmlObject->Controls->children() as $con){
		$conName = str_replace('_',' ',(string)$con->getName());
		$conDesc = (string)$con->Description;
		$conCollId = $colMapping[(int)$con->CollId];
		$conType = (string)$con->Type;
		$required = (int)$con->Required;
		$searchable = (int)$con->Searchable;
		$advSearchable = (int)$con->advSearchable;
		$showRes = (int)$con->showInResults;
		$showPub = (int)$con->showInPublicResults;
		$pubEntry = (int)$con->publicEntry;
		$options = (string)$con->options;
		$sequence = (int)$con->sequence;
		echo gettext("Creating Control ")."$conName ... ";
		$result = createControl(false,$conName, $conType, $sid, $conCollId, $conDesc, $required, $searchable, $advSearchable, $showRes, $showPub, $pubEntry, $options, $sequence);
		if ($result !== true){
			echo gettext("Failed - ").$result."<br/>";
			deleteScheme($sid);
			include_once('includes/footer.php');
    		die();
		}
		else echo gettext("Succeeded.")."<br/>";
	}
	echo gettext("All controls created successfully.")."<br/>";
	echo gettext("Structure for scheme ").$schName.gettext(" fully imported!")."<br/>";
}
else {
	showForm();
	include_once('includes/footer.php');
}

?>
