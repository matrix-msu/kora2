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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 
 * This page takes a list of KIDs and displays them in the order it received them.
 * The KIDs must be formatted in this way to be unpacked:
 * 	"'8-2C-0','8-2C-1','8-2C-5','8-2C-7','8-2C-E'"
 * 
 * 
 * POST arguments:
 *   kids: imploded array of kids to be displayed             (REQUIRED)
 * 
 */

// Initial Version: Cassi Miller, 2010

require_once('includes/utilities.php');
//include_once('includes/header.php');
if(!isset($_POST['kids'])) {
	die("No KIDs specified for display.");
	//echo '$_Request[kids] not provided<br/>';
}
if(!isset($_SESSION['currentProject']) || !isset($_SESSION['currentScheme'])) {
	die("You must select a project and a scheme.");
}
		
$pid = $_SESSION['currentProject'];
$sid = array($_SESSION['currentScheme']);
$kidLink='href="viewObject.php?rid=%s"';
$kids = $_POST['kids'];
//$kids = "'8-2C-0','8-2C-E','8-2C-5','8-2C-7','8-2C-1'";

// get the list of controls that should be shown in the results list
$controlQuery = 'SELECT cid, schemeid FROM p'.$pid.'Control WHERE showInResults=1';
if (!empty($sid))
{
    $controlQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
}
$controlQuery .= ' ORDER BY schemeid, cid';
$controlQuery = $db->query($controlQuery);
$controls = array(-1);
while ($cid = $controlQuery->fetch_assoc())
{
	$controls[] = $cid['cid'];
}

$controlType = getControlTypes($pid, $controls);

// Get the Data
$dataTable = 'p'.$pid.'Data';
$controlTable = 'p'.$pid.'Control';
$dataQuery  = "SELECT $dataTable.id AS id, $dataTable.cid AS cid, ".$pid." AS pid, $dataTable.schemeid AS schemeid, $dataTable.value AS value FROM $dataTable ";
$dataQuery .= "LEFT JOIN $controlTable USING (cid) ";
$dataQuery .= "LEFT JOIN collection ON ($controlTable.collid = collection.collid) ";
$dataQuery .= 'WHERE id IN ('.$kids.') ';
$dataQuery .= 'AND cid IN ('.implode(',', $controls).') ';
$dataQuery .= "ORDER BY SUBSTRING_INDEX($dataTable.id, '-', 2), CAST(CONV( SUBSTRING_INDEX($dataTable.id, '-', -1), 16, 10) AS UNSIGNED), collection.sequence, $controlTable.sequence";
$dataQuery = $db->query($dataQuery);

// build a record array
$records = array();
while($data = $dataQuery->fetch_assoc()) {
	$records[$data['id']][$data['cid']] = $data['value'];
}

// Display records
$kids = explode(',', $kids);
foreach($kids as $kid) {
	$kid = preg_replace("/'/","", $kid); 
	
	// print out the initial kid link
    echo '<table class="table"><tr><td colspan="2">';
    echo '<a '.sprintf($kidLink,$kid).'>'.$kid.'</a></td></tr>';
    
    // print out all the controls and their values
    if(sizeof($records)>0 && !empty($records[$kid]) )
    {
	    foreach($records[$kid] as $cid => $value) {
	    	echo '<tr><td class="kora_ccLeftCol">'.htmlEscape($controlType[$cid]['name']).'</td><td>';
	            
	        // Instantiate an empty control of the necessary class and use it to convert the
	        // value (potentially in XML) to a pretty display format
	        $theControl = new $controlType[$cid]['class'];
	        echo $theControl->storedValueToDisplay($value, $pid, $cid);
	        echo "</td></tr>\n";
	    }
    }
    echo '</table>';
}
