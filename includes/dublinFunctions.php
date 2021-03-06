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



require_once('includes.php');

///TODO: Check if can re-write to use the getDublinCoreFields() function in utilities

function addDublinCore($cid,$dctype,$pid,$sid) {
    if($cid == "") {
       loadDublinCore();
       return;
    }
    global $db;
    $query = "SELECT dublinCoreFields FROM scheme WHERE schemeid=".$sid." AND pid=".$pid." LIMIT 1";
    $result = $db->query($query);
    $array = $result->fetch_assoc();
    if($array['dublinCoreFields'] == '') { //nothing there yet
        $xmlstring = "<dublinCore><$dctype><id>$cid</id></$dctype></dublinCore>";
        $query = "UPDATE scheme SET dublinCoreFields='$xmlstring' WHERE schemeid=".$sid." AND pid=".$pid." LIMIT 1";
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
        $query = 'UPDATE scheme SET dublinCoreFields='.escape($xmlstring->asXML()).' WHERE schemeid='.$sid.' AND pid='.$pid.' LIMIT 1';
        $db->query($query);   
    }
    loadDublinCore($pid,$sid);
}

function removeDublinCore($cid,$dctype,$pid,$sid) {
    global $db;
    
    $query = "SELECT dublinCoreFields FROM scheme WHERE pid=".$pid." AND schemeid=".$sid." LIMIT 1";
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
    $query = "UPDATE scheme SET dublinCoreFields=".escape($newXML->asXML()).",dublinCoreOutOfDate=1 WHERE pid=".$pid." AND schemeid=".$sid."";
    $db->query($query);
    loadDublinCore($pid,$sid);
}

function loadDublinCore($pid,$sid) {
    global $db;
    echo gettext('Add Field').': ';
    $dcxml = simplexml_load_file(DUBLIN_CORE_CONFIG);
    echo gettext('Dublin Core Field/Control Name').'<br /><br /> <select id="dcfield" name="dcfield">';
    foreach($dcxml->children() as $dctype) {
        echo '<option value="'.$dctype->getName().'">'.gettext($dctype).'</option>'; 
    }
    echo '</select> '; 
    echo '<select id="controlmap" name="controlmap">';
    $query = "SELECT cid,name,type from p".$pid."Control where schemeid=".$sid."";
    $result = $db->query($query);
    $controlList = array();
    $inuse = array();
    $query = "SELECT dublinCoreFields FROM scheme WHERE schemeid=".$sid." 
                AND pid=".$pid." AND dublinCoreFields IS NOT NULL LIMIT 1";
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
    echo "<button class='mdc_add'>".gettext('Add Field to Dublin Core')."</button> <br /> <br />";
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
	               $query = "SELECT cid,name FROM p".$pid."Control WHERE cid IN (".implode(',',$names).")";
	               $result = $db->query($query);
	               $firstchild = true;
	               while($array = $result->fetch_assoc()) {
	               	   if($firstchild)$firstchild=false;
	               	   else echo ', ';
	                   echo ''.htmlEscape($array['name'])." <sup><u><a style='color:red' class='mdc_rem' cid='".$array['cid']."' dctype='".$dctypes->getName()."'>x</a></u></sup>"; 
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


if(isset($_REQUEST['action']) && isset($_REQUEST['source']) && $_REQUEST['source'] == 'DublinFunctions'){
    $action = $_REQUEST['action'];
    if($action == 'loadDublinCore')
        loadDublinCore($_REQUEST['pid'],$_REQUEST['sid']);
    elseif($action == 'addDublinCore') 
        addDublinCore($_REQUEST['cid'],$_REQUEST['dctype'],$_REQUEST['pid'],$_REQUEST['sid']);
    elseif($action == 'removeDublinCore')
        removeDublinCore($_REQUEST['cid'],$_REQUEST['dctype'],$_REQUEST['pid'],$_REQUEST['sid']);
    elseif($action == 'updateDublinCoreData')
        updateDublinCoreData($_REQUEST['pid'],$_REQUEST['sid']);
}

?>
