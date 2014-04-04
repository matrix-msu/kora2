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



require_once('conf.php');
require_once('utilities.php');

///TODO: Check if can re-write to use the getDublinCoreFields() function in utilities


function addDublinCore($cid,$dctype) {
    if($cid == "") {
       loadDublinCore();
       return;
    }
    global $db;
    $query = "SELECT dublinCoreFields FROM scheme WHERE schemeid=$_SESSION[currentScheme] AND pid=$_SESSION[currentProject] LIMIT 1";
    $result = $db->query($query);
    $array = $result->fetch_assoc();
    if($array['dublinCoreFields'] == '') { //nothing there yet
        $xmlstring = "<dublinCore><$dctype><id>$cid</id></$dctype></dublinCore>";
        $query = "UPDATE scheme SET dublinCoreFields='$xmlstring' WHERE schemeid=$_SESSION[currentScheme] AND pid=$_SESSION[currentProject] LIMIT 1";
        $db->query($query);
    }
    else {   //not empty
        $xmlstring = simplexml_load_string($array['dublinCoreFields']);
        $found = false;
        foreach($xmlstring->children() as $dcfield ) {
            if($dcfield->getName() == $dctype) {  //adding another cid to a existing field tag
                $found = true;
                $dcfield = $dcfield->addChild('id',xmlEscape($cid));
            }
        }
        if(!$found) { //add field tag AND cid
            $newfield = $xmlstring->addChild($dctype);
            $newfield->addChild('id',xmlEscape($cid));
        }
        $query = 'UPDATE scheme SET dublinCoreFields='.escape($xmlstring->asXML()).' WHERE schemeid='.$_SESSION['currentScheme'].' AND pid='.$_SESSION['currentProject'].' LIMIT 1';
        $db->query($query);   
    }
    loadDublinCore();
}

function removeDublinCore($cid,$dctype) {
    global $db;
    
    $query = "SELECT dublinCoreFields FROM scheme WHERE pid=$_SESSION[currentProject] AND schemeid=$_SESSION[currentScheme] LIMIT 1";
    $result = $db->query($query);
    $result = $result->fetch_assoc();
    $xml = simplexml_load_string($result['dublinCoreFields']);
    
    $newXML = simplexml_load_string('<dublinCore />');
       
    foreach($xml as $dcType)
    {
        $idsToAdd = array();
        if (isset($dcType->id))
        {
        	foreach($dcType->id as $id)
        	{
        		if ((string)$id != $cid)
        		{
        			$idsToAdd[] = (string)$id;
        		}
        	}
        }
        if (!empty($idsToAdd))
        {
        	$type = $newXML->addChild($dcType->getName());
        	foreach($idsToAdd as $id)
        	{
        		$type->addChild('id', $id);
        	}
        }
    }
    $query = "UPDATE scheme SET dublinCoreFields=".escape($newXML->asXML()).",dublinCoreOutOfDate=1 WHERE pid=$_SESSION[currentProject] AND schemeid=$_SESSION[currentScheme]";
    $db->query($query);
    loadDublinCore();
}

function loadDublinCore() {
    global $db;
    echo gettext('Add Field').': ';
    $dcxml = simplexml_load_file(DUBLIN_CORE_CONFIG);
    echo gettext('Dublin Core Field/Control Name').'<br /><br /> <select id="dcfield" name="dcfield">';
    foreach($dcxml->children() as $dctype) {
        echo '<option value="'.$dctype->getName().'">'.gettext($dctype).'</option>'; 
    }
    echo '</select> '; 
    echo '<select id="controlmap" name="controlmap">';
    $query = "SELECT cid,name,type from p$_SESSION[currentProject]Control where schemeid=$_SESSION[currentScheme]";
    $result = $db->query($query);
    $controlList = array();
    $inuse = array();
    $query = "SELECT dublinCoreFields FROM scheme WHERE schemeid=$_SESSION[currentScheme] 
                AND pid=$_SESSION[currentProject] AND dublinCoreFields IS NOT NULL LIMIT 1";
    $result2 = $db->query($query);
    $record = array();
    if($result2->num_rows > 0) {
        $record = $result2->fetch_assoc();
        $xml = simplexml_load_string($record['dublinCoreFields']);
        foreach($xml->children() as $dctypes) {
            foreach ($dctypes->children() as $ids) {
                $inuse[] = $ids;
            }
        }
    }
    while($array = $result->fetch_assoc()) {
        if($array['type'] != "FileControl" && $array['type'] != "AssociatorControl" && $array['name'] != "systimestamp" && (array_search($array['cid'],$inuse) === false ) ) {
            echo '<option value="'.$array['cid'].'">'.htmlEscape($array['name']).'</option>';
        }
    }
    echo '</select> ';
    echo '<input type="button" onclick="addDublinCore()" value="'.gettext('Add Field to Dublin Core').'" /> <br /> <br />';
    //show the current fields assigned to what...
    if( $record ) {
        $xml = simplexml_load_string($record['dublinCoreFields']);
        if (sizeof($xml->children()) > 0)
        {
        	echo "<h3>".gettext('Current Dublin Core fields for this scheme')."</h3><table class='table'>";
	        foreach($xml->children() as $dctypes) {
	            $names = array();
	            
	            if(count($dctypes->children()) != 0 ) {
	                foreach($dcxml->children() as $dcxmltype) {
	                  if($dctypes->getName() == $dcxmltype->getName())
	                     echo "<tr class='scheme_index'><td colspan='3'>".$dcxmltype.':</td></tr><tr><td>'; 
	               }
	               foreach($dctypes->children() as $id) { 
	                 $names[] = $id;
	               }
	               $query = "SELECT cid,name FROM p$_SESSION[currentProject]Control WHERE cid IN (".implode(',',$names).")";
	               $result = $db->query($query);
	               $firstchild = true;
	               while($array = $result->fetch_assoc()) {
	               	   if($firstchild)$firstchild=false;
	               	   else echo ', ';
	                   echo ''.htmlEscape($array['name']).' <sup>
	<a class="link x" onclick="removeDublinCore('.$array['cid'].',\''.$dctypes->getName().'\')">x</a></sup>'; 
	               }
	            }
	        }
	        echo "</table>";
        }        
    }
    else {
        echo "<br />".gettext('No Dublin Core fields for this scheme yet')."<br />";
    } 
}

// updateDublinCoreData function for refreshing DC data.  This function is unimplemented at current - see updateDublinCore.php .
///TODO: Determine utility of implementing updateDublinCoreData, remove if not worthwhile 
//function updateDublinCoreData($project,$scheme) {
//}


if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'DublinFunctions'){
    $action = $_POST['action'];
    if($action == 'loadDublinCore')
        loadDublinCore();
    elseif($action == 'addDublinCore') 
        addDublinCore($_POST['cid'],$_POST['dctype']);
    elseif($action == 'removeDublinCore')
        removeDublinCore($_POST['cid'],$_POST['dctype']);
    elseif($action == 'updateDublinCoreData')
        updateDublinCoreData($_SESSION['currentProject'],$_SESSION['currentScheme']);
}

?>
