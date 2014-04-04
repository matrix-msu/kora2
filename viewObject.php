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

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');

requireLogin();

// make sure a record id has been passed
if (empty($_REQUEST['rid'])) {
	include_once('includes/header.php');
	echo gettext('No record ID specified').'.';
	include_once('includes/footer.php');
	die();
}



// make sure the rid is valid
$recordInfo = parseRecordID(trim($_REQUEST['rid']));
if ($recordInfo === false) {
	//header('Location: projectIndex.php');
	include_once('includes/header.php');
	echo gettext('Invalid Record ID format').'.';
	include_once('includes/footer.php');
	die();
}


// make sure the user has rights to a group within the project of the object.
// instead of requiring an overall requireProject() check, we do this so that
// people can hyperlink to objects and people can just follow the hyperlinks.
$accessQuery = 'SELECT pid FROM member WHERE uid='.$_SESSION['uid'].' AND pid='.$recordInfo['project'].' LIMIT 1';
$accessQuery = $db->query($accessQuery);
if ($accessQuery->num_rows != 1 && !isSystemAdmin()) {
	//header('Location: selectProject.php');

//	echo gettext('Invalid Authentication to Search').'.';
	include_once('includes/header.php');
	echo gettext('Invalid Project/Scheme ID').'.';
	include_once('includes/footer.php');
	die();
}




// check if the record exists
$query = "SELECT * FROM p$recordInfo[project]Data WHERE id=".escape($_REQUEST['rid'])." LIMIT 1";
$results = $db->query($query);

if($db->error || $results->num_rows < 1){
	include_once('includes/header.php');
	echo gettext('Record not found').'.';
	include_once('includes/footer.php');
	die();
}

// now that we have verified they have permissions and that the record exists, 
// set project and scheme so menu links do what you would expect. 
$results = $db->query("SELECT schemeName,name AS projectName FROM scheme LEFT JOIN project USING(pid) WHERE schemeid=$recordInfo[scheme]");
$result = $results->fetch_assoc();
$_SESSION['currentProject'] = $recordInfo['project'];
$_SESSION['currentProjectName'] = $result['projectName'];
$_SESSION['currentScheme'] = $recordInfo['scheme'];
$_SESSION['currentSchemeName'] = $result['schemeName'];

// we include the header after session and project are set.
include_once('includes/header.php');

echo '<h2>'.gettext('Viewing Record').': '.$_REQUEST['rid'].'</h2>';

// overall pseudocode:

// get list of all controls for the scheme of which this object is a part
// instantiate all those controls with the object's record identifier and 
// call the showData() method on them to display the values of the object.
// note that ALL fields, even empty ones, are to be shown.

// Get list of controls for scheme of which it's a part
$cTable = 'p'.$recordInfo['project'].'Control';
$controlQuery  = "SELECT $cTable.cid AS cid, $cTable.name AS name, $cTable.type AS class, ";
$controlQuery .= "control.file AS file FROM $cTable LEFT JOIN control";
$controlQuery .= " ON control.class = $cTable.type ";
$controlQuery .= " LEFT JOIN collection ON ($cTable.collid = collection.collid)";
$controlQuery .= " WHERE $cTable.schemeid=".$recordInfo['scheme'];
$controlQuery .= " ORDER BY collection.sequence, $cTable.sequence";
$controlQuery = $db->query($controlQuery);


echo '<a href="editObject.php?rid='.$_REQUEST['rid'].'">'.gettext('edit').'</a> | ';
echo '<a href="deleteObject.php?rid='.$_REQUEST['rid'].'">'.gettext('delete').'</a> | ';
echo '<a href="ingestObject.php?preset='.$_REQUEST['rid'].'">'.gettext('clone').'</a><br />';

echo '<table class="table">';

while($cInfo = $controlQuery->fetch_assoc())
{
	// make sure the control class is loaded
	if (!empty($cInfo['file']))
	{
		require_once(basePath.CONTROL_DIR.$cInfo['file']);
		$theControl = new $cInfo['class']($recordInfo['project'], $cInfo['cid'], $_REQUEST['rid']);
	
		echo '<tr><td class="kora_ccLeftCol">'.htmlEscape($cInfo['name']).'</td><td>';
		echo $theControl->showData();
		echo '</td></tr>';
	}
	else
	{
		echo '<tr><td class="kora_ccLeftCol">'.htmlEscape($cInfo['name']).'</td><td>'.gettext('Can not load control; please ensure all controls are properly installed.').'</td></tr>';
	}
}

$assocQuery = $db->query('SELECT value FROM p'.$recordInfo['project'].'Data WHERE id='.escape($_REQUEST['rid']).' AND cid=0 LIMIT 1');
if ($assocQuery->num_rows > 0)
{
    $assocQuery = $assocQuery->fetch_assoc();
    $xml = simplexml_load_string($assocQuery['value']);
    
    if (isset($xml->assoc->kid))
    {
	echo '<tr><td colspan="2"><strong>'.gettext('The following records associate to this record').':</strong><br /><br />';
	foreach($xml->assoc as $assoc)
	{
		echo '<a href="viewObject.php?rid='.(string)$assoc->kid.'">'.(string)$assoc->kid.'</a><br />';
	}

	echo '<br /><em>'.gettext('Note: You may not have authorization to view any or all of these records').'</em><br />';
        echo '</td></tr>';    	
    }
}

echo '</table>';

echo '<a href="editObject.php?rid='.$_REQUEST['rid'].'">'.gettext('edit').'</a> | ';
echo '<a href="deleteObject.php?rid='.$_REQUEST['rid'].'">'.gettext('delete').'</a> | ';
echo '<a href="ingestObject.php?preset='.$_REQUEST['rid'].'">'.gettext('clone').'</a><br />';

// If the user has authority to create presets, show that dialog
if (hasPermissions(EDIT_LAYOUT))
{
?>
<br />
<script type="text/javascript">
//<![CDATA[
function addPreset()
{
    var varname = $("#presetName").val();
	$.post('includes/presetFunctions.php',{action:'addRecordPreset',source:'PresetFunctions',kid:<?php echo "'".$_REQUEST['rid']."'"?>,name:varname},function(resp){$("#ajax").html(resp);}, 'html');
}

$.post('includes/presetFunctions.php',{action:'showAddRecordPresetForm',source:'PresetFunctions'},function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>
<form id="preset" action="">
<table class="table">
<tr>
    <td colspan="3"><strong><?php echo gettext('Save this record as a preset');?> </strong></td>
</tr>
<tr>    
    <td style="width:25%"><?php echo gettext('Name');?>:</td><td colspan="2"><input type="text" name="presetName" id="presetName" /></td>
</tr>
<tr>    
    <td></td><td style="width:25%"><input type="button" value="<?php echo gettext('Create');?>" onclick="addPreset()" /></td><td style="width:50%"><div id="ajax"></div></td>
</tr>
<tr>
    <td colspan="3"><?php echo gettext('Note: Records saved as presets will no longer appear in search results or as distinct records.');?></td>
</tr>
</table>
</form>
<?php 
}

include_once('includes/footer.php');

?>
