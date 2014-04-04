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

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');

function showNewSchemeForm($errors = '')
{
	global $db;
	
    include_once('includes/header.php');
?>
	<h2><?php echo gettext('Add New Scheme to ');?><?php echo htmlEscape($_SESSION['currentProjectName'])?></h2>
	<?php  if(!empty($errors)) echo '<div class="error">'.gettext($errors).'</div>'; ?>
	<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
	<input type="hidden" name="schemeSubmit" value="true" />
	<table class="table_noborder">
		<tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" name="schemeName" <?php  if(isset($_POST['schemeName'])) echo ' value="'.$_POST['schemeName'].'" ';?> /></td></tr>
		<tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea name="description"><?php  if(isset($_POST['description'])) echo $_POST['description'];?></textarea></td></tr>
		<tr><td align="right"><?php echo gettext('Load Controls From');?>:</td><td>
<?php 
$presetSchemes = $db->query('SELECT CONCAT(project.name, \'/\', scheme.schemeName) AS name, scheme.schemeid AS id FROM scheme LEFT JOIN project USING (pid) WHERE scheme.allowPreset=1');
if ($presetSchemes)
{
?>
        <select name="preset"><option value="0"><?php echo gettext('None (Empty Layout)');?></option>
<?php 
    while ($p = $presetSchemes->fetch_assoc())
    {        	
    	echo '<option value="'.$p['id'].'">'.htmlEscape($p['name']).'</option>';
    }
?>		
		</select></td></tr>
		<tr><td align="right"><?php echo gettext('Public Ingestible?');?>:</td><td><input type="checkbox" name="publicIngestion" <?php if(isset($_POST['publicIngestion'])) echo 'checked="checked"';?>/></td></tr>
    <tr><td align="right"><?php echo gettext('Legal Notice');?>:</td><td><textarea name="legal" ><?php if(isset($_POST['legal'])) echo $_POST['legal'];?></textarea></td></tr>
<?php  } // endif presetSchemes ?>		
		<tr><td colspan="2" align="right"><input type="submit" value="<?php echo gettext('Create New Scheme');?>" /></td></tr>
	</table>		
	</form>    
<?php 
    include_once('includes/footer.php');      
}

requireProject();
requirePermissions(CREATE_SCHEME, 'projectIndex.php');

// see if data was submitted
if (isset($_POST['schemeSubmit']))
{
	//check if checkbox was checked
    $public = 0;
    if(isset($_POST['publicIngestion']))
    {
    	$public = 1;
    }
	
	if (empty($_POST['schemeName'])) showNewSchemeForm(gettext('You must provide a name.'));
    else {
        $query = "INSERT INTO scheme (pid, schemeName, sequence, publicIngestion, legal, description, nextid) ";
        $query .= "SELECT ".escape($_SESSION['currentProject']).", ";
        $query .= escape($_POST['schemeName']).", COUNT(sequence) + 1, ";
        $query .= $public.", ".escape($_POST['legal']).", ";
        $query .= escape($_POST['description']).", 0 FROM scheme ";
        $query .= "WHERE pid=".escape($_SESSION['currentProject']);
        $result = $db->query($query);
        
        
        if(!$result) showNewSchemeForm($db->error);
        else {
            $_SESSION['currentScheme'] = $db->insert_id;
            
            //Add timestamp TextControl to every scheme created
            //timestamp is not editable and not displayed on ingest
            //on ingestion or when a record is edited, timestamp stores the current time
        	$tempReq = $_REQUEST;
        	$_REQUEST['name'] = 'systimestamp';
        	$_REQUEST['type'] = 'TextControl';
        	$_REQUEST['description'] = '';
        	$_REQUEST['submit'] = true;
        	$_REQUEST['collectionid'] = 0;
        	$_REQUEST['publicentry'] = "on"; //Not used
        	require('addControl.php');
        	$_REQUEST = $tempReq;
        	//End add timestamp control
        	
        	//Add a record owner to every scheme created
        	//owner is not editable and not displayed on ingest
        	$tempReq = $_REQUEST;
        	$_REQUEST['name'] = 'recordowner';
        	$_REQUEST['type'] = 'TextControl';
        	$_REQUEST['description'] = '';
        	$_REQUEST['submit'] = true;
        	$_REQUEST['searchable'] = "on";
        	$_REQUEST['advanced'] = "on";
        	$_REQUEST['collectionid'] = 0;
        	$_REQUEST['publicentry'] = "on"; //Not used
        	require('addControl.php');
        	$_REQUEST = $tempReq;
        	//End add owner control
        	
            if (!empty($_POST['preset']))
	        {
	            // Make sure this is a valid preset
	            $presetInfo = $db->query('SELECT schemeid, pid FROM scheme WHERE schemeid='.escape($_POST['preset']).' AND allowPreset=1 LIMIT 1');
	            if ($presetInfo->num_rows > 0)
	            {
	                $presetInfo = $presetInfo->fetch_assoc();
	                
	                // Recreate the Collections and Store the mapping of old->new
	                // collectionMap is an associative mapping of oldID => newID
	                $collectionMap = array();
	                $collQuery = $db->query('SELECT collid, schemeid, name, description, sequence FROM collection WHERE schemeid='.$presetInfo['schemeid']);
	                while($c = $collQuery->fetch_assoc())
	                {
	                	$insertQuery = $db->query('INSERT INTO collection (schemeid, name, description, sequence) VALUES ('.escape($_SESSION['currentScheme']).','.escape($c['name']).','.escape($c['description']).','.escape($c['sequence']).')');
	                	$collectionMap[$c['collid']] = $db->insert_id;
	                }
	                
	                // Recreate the Controls
	                $controlQuery = $db->query('SELECT collid, type, name, description, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence FROM p'.$presetInfo['pid'].'Control WHERE schemeid='.$presetInfo['schemeid']);
	                while ($c = $controlQuery->fetch_assoc())
	                {					
	                	// don't clone the systimestamp(it has a collection ID of 0)
	                    if($c['collid'] != 0)
	                    {
		                    // Clone the row, correcting the collection ID.
		                    $query  = 'INSERT INTO p'.$_SESSION['currentProject'].'Control (schemeid, collid, type, name, description, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence) ';
		                    $query .= 'VALUES ('.$_SESSION['currentScheme'].','.$collectionMap[$c['collid']].','.escape($c['type']).',';
		                    $query .= escape($c['name']).','.escape($c['description']).',';
		                    $query .= escape($c['required']).','.escape($c['searchable']).','.escape($c['advSearchable']).',';
		                    $query .= escape($c['showInResults']).','.escape($c['showInPublicResults']).','.escape($c['publicEntry']).',';
		                    $query .= escape($c['options']).','.escape($c['sequence']).')';
							
		                    $db->query($query);

	                    }
	                }
	            }
	        }            
            header('Location: schemeLayout.php?schemeid='.$_SESSION['currentScheme']);
        }
    }
}
else
{
    showNewSchemeForm();
}

?>
