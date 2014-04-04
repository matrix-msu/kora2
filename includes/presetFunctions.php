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

require_once('utilities.php');

// This is simply a requireLogin rather than a requirePermissions because this
// page is used to manage all presets a user has control over.  The functions
// themselves have to do their own permissions checks once they know what project
// the specified preset is from.
requireLogin();

// getPermsForPreset - takes a presetID and returns the permissions bit vector for
//                     the current user for the project the preset is from
function getPermsForPreset($presetID)
{
	global $db;
	
	if (isSystemAdmin())
	{
		return PROJECT_ADMIN;
	}
	$query = 'SELECT permGroup.permissions AS permissions FROM permGroup RIGHT JOIN member USING (gid) WHERE member.uid = '.$_SESSION['uid'].' AND member.pid IN (SELECT project FROM controlPreset WHERE presetid='.escape($presetID).') LIMIT 1';
	$query = $db->query($query);
	if ($query->num_rows == 0)
	{
		return 0;
	}
	else
	{
		$perms = $query->fetch_assoc();
		return $perms['permissions'];
	}
}

function updateControlPresetName($presetID, $newName)
{
	global $db;
	
	if (getPermsForPreset($presetID) & PROJECT_ADMIN)
    {
    	$nameQuery = $db->query('SELECT name FROM controlPreset WHERE name='.escape($newName).' AND class IN (SELECT class FROM controlPreset WHERE presetid='.escape($presetID).') LIMIT 1');
        if ($nameQuery->num_rows > 0)
        {
        	echo gettext('There is already a control of that type with that name').'.';    	
        }
        else
        {
        	$db->query('UPDATE controlPreset SET name='.escape($newName).' WHERE presetid='.escape($presetID).' LIMIT 1');
        }
    }
	
	showControlPresetDialog();
}

function updateControlPresetGlobal($presetID, $global)
{
	global $db;
	
	// Make sure the user has admin rights to the project and that the new value for global
	// is valid
	if (getPermsForPreset($presetID) & PROJECT_ADMIN && in_array($global, array(0, 1)))
	{
		$db->query("UPDATE controlPreset SET global=$global WHERE presetid=".escape($presetID).' LIMIT 1');
	}
	
	showControlPresetDialog();
}

function deleteControlPreset($presetID)
{
	global $db;
	
    // Make sure the user has admin rights to the project and that the new value for global
    // is valid
    if (getPermsForPreset($presetID) & PROJECT_ADMIN)
    {
    	$db->query('DELETE FROM controlPreset WHERE presetid='.escape($presetID).' LIMIT 1');	
    }
	
	showControlPresetDialog();
}

function showControlPresetDialog()
{
	global $db;

	// call isSystemAdmin once and store it so we don't query the database
	// a billion times inside the loop
	$isSysAdmin = isSystemAdmin();
	
	// Get the list of presets which this user has control over.
	if ($isSysAdmin)
	{
		$presetQuery = 'SELECT controlPreset.presetid AS id, controlPreset.name AS name, control.name AS class, controlPreset.global AS global, project.name AS project, '.PROJECT_ADMIN.' AS permissions FROM controlPreset LEFT JOIN project ON (controlPreset.project = project.pid) LEFT JOIN control ON (controlPreset.class = control.class) ORDER BY class, project, name';
	}
	else
	{
	   $projectQuery = 'SELECT member.pid FROM member RIGHT JOIN permGroup USING (gid) WHERE ( member.uid = '.$_SESSION['uid'].' AND (permGroup.permissions & '.PROJECT_ADMIN.') > 0)';
	   $presetQuery = 'SELECT
	                       controlPreset.presetid AS id,
	                       controlPreset.name AS name,
	                       control.name AS class,
	                       controlPreset.global AS global,
	                       project.name AS project,
	                       permGroup.permissions AS permissions
	                   FROM controlPreset
	                       LEFT JOIN project ON (controlPreset.project = project.pid)
	                       LEFT JOIN control ON (controlPreset.class = control.class)
	                       LEFT JOIN member ON (member.uid='.$_SESSION['uid'].' AND member.pid = controlPreset.project)
	                       LEFT JOIN permGroup ON (permGroup.gid = member.gid)
	                   WHERE controlPreset.project IN ('.$projectQuery.')
	                   ORDER BY class, project, name';
	}

	$presetQuery = $db->query($presetQuery);
	if ($presetQuery->num_rows == 0)
	{
		echo gettext('There are no presets which you have control over').'.';
	}
	else
	{
		echo '<table class="table">';
		echo '<tr><td><b>'.gettext('Type').'</b></td><td><b>'.gettext('Preset Name').'</b></td><td><b>'.gettext('Original Project').'</b></td><td><b>'.gettext('Global').'</b></td><td><b>'.gettext('New Name').'</b></td><td><b>'.gettext('Delete').'</b></td></tr>';
		while($preset = $presetQuery->fetch_assoc())
		{
            echo '<tr>';
            echo '<td>'.$preset['class'].'</td>';
            echo '<td>'.htmlEscape($preset['name']).'</td>';
            echo '<td>'.(empty($preset['project']) ? gettext('Stock Preset') : $preset['project']).'</td>';
            // If the user is a project admin, we give them a checkbox to make a control
            // global or not.  If not, we just display "yes" or "no"
          	echo '<td><input type="checkbox" name="global'.$preset['id'].'" id="global'.$preset['id'].'" ';
          	if ($preset['global']) echo ' checked="checked" ';
           	echo 'onclick="setGlobal('.$preset['id'].')" /></td>';
            echo '<td><input type="text" name="newName'.$preset['id'].'" id="newName'.$preset['id'].'" /><input type="button" value="'.gettext('Rename').'" onclick="renameControlPreset('.$preset['id'].')" /></td>';
            echo '<td><a class="delete" onclick="deletePreset('.$preset['id'].')">X</a></td>';
            echo '</tr>';
		}
		echo '</table>';
	}
}

function addRecordPreset($kid, $name)
{
	global $db;
	
	// Make sure the object is in the current scheme
	$objectInfo = parseRecordID($kid);
	
	if (!$objectInfo)
	{
		die('<div class="error">'.gettext('Invalid').' KID</div>');
	}
	if ($objectInfo['scheme'] != $_SESSION['currentScheme'])
	{
		die('<div class="error">'.gettext('Object is not in current scheme').'</div>');
	}
	if (strlen($name) < 1)
	{
		die('<div class="error">'.gettext('You cannot have an empty name').'</div>');
	}
	
	// Make sure the current user has permission to create a preset in
	// the current scheme
	if (!hasPermissions(EDIT_LAYOUT))
	{
		die('<div class="error">'.gettext('You do not have permission to create presets').'.</div>');
	}
	
	// Make sure the name is available
	$availableQuery = $db->query('SELECT recpresetid FROM recordPreset WHERE schemeid='.$objectInfo['scheme'].' AND name='.escape($name).' LIMIT 1');
	if ($availableQuery->num_rows > 0)
	{
		die('<div class="error">'.gettext('There is already a preset with that name').'.</div>');
	}
	
	// Make sure the KID is available
    $availableQuery = $db->query('SELECT recpresetid FROM recordPreset WHERE kid='.escape($kid).' LIMIT 1');
    if ($availableQuery->num_rows > 0)
    {
        die('<div class="error">'.gettext('This record is already a preset').'.</div>');
    }
	
	// Add the kid to the recordPreset table
	$db->query('INSERT INTO recordPreset (schemeid, name, kid) VALUES ('.$objectInfo['scheme'].', '.escape($name).', '.escape($kid).')');
	
	echo gettext('Preset Added');
}

function showRecordPresetDialog()
{
	global $db;
	
	requireScheme();
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');	
	
    // Get the list of Presets for the current scheme
    $presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$_SESSION['currentScheme'].' ORDER BY name ASC');
    
    if ($presetQuery->num_rows > 0)
    {
?>
<table class="table">
<tr>
    <td><strong><?php echo gettext('Name');?></strong></td>
    <td><strong><?php echo gettext('ID');?></strong></td>
    <td><strong><?php echo gettext('Demote');?></strong></td>
    <td><strong><?php echo gettext('Delete');?></strong></td>
</tr>
<?php 
    	while($preset = $presetQuery->fetch_assoc())
    	{
    	   echo '<tr><td>'.htmlEscape($preset['name']).'</td><td>'.$preset['kid'].'</td>';
    	   echo '<td><a class="link" onclick="demotePreset(\''.$preset['kid'].'\');">X</a></td>';
    	   echo '<td><a href="deleteObject.php?rid='.$preset['kid'].'">X</a></td></tr>';
    	}
    	
    	echo '</table>';
    }
    else
    {
    	echo gettext('This scheme currently has no presets').'.';
    }
}

function demoteRecordPreset($kid)
{
	global $db;
	
    requireScheme();
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');

    // Make sure the kid is valid and from the current scheme
    $kidInfo = parseRecordID($kid);
    if ($kid && $kidInfo['scheme'] == $_SESSION['currentScheme'])
    {    
        $db->query('DELETE FROM recordPreset WHERE kid='.escape($kid));
    }
}

function renameRecordPreset($kid, $name)
{
	global $db;
	
    requireScheme();
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
    
    // Make sure the kid is valid and from the current scheme
    $kidInfo = parseRecordID($kid);
    if ($kid && $kidInfo['scheme'] == $_SESSION['currentScheme'])
    {
    	// Make sure no other preset in this scheme already uses this name
    	$nameQuery = $db->query('SELECT name FROM recordPreset WHERE schemeid='.$_SESSION['currentScheme'].' AND name='.escape($name).' LIMIT 1');
    	if ($nameQuery->num_rows == 0)
    	{
            $db->query('UPDATE recordPreset SET name='.escape($name).' WHERE kid='.escape($kid));
    	}
    	else
    	{
    		echo '<div class="error">'.gettext('There is already a preset with that name').'!</div><br />';
    	}
    }
}

function loadRecordPresetList()
{
    global $db;
    
    requireScheme();
    requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');

    $presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$_SESSION['currentScheme'].' ORDER BY name ASC');

    while($preset = $presetQuery->fetch_assoc())
    {
    	echo '<option value="'.$preset['kid'].'">'.htmlEscape($preset['name']).'</option>';
    }
}

if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'PresetFunctions'){
    $action = $_POST['action'];

    if($action == 'updateControlPresetName') {
        updateControlPresetName($_POST['preset'],$_POST['name']);
    } elseif($action == 'updateControlPresetGlobal') {
        updateControlPresetGlobal($_POST['preset'], $_POST['global']);
    } elseif($action == 'deleteControlPreset') {
        deleteControlPreset($_POST['preset']);
    } elseif($action == 'showControlPresetDialog') {
        showControlPresetDialog();
    } elseif($action == 'addRecordPreset') {
    	addRecordPreset($_POST['kid'], $_POST['name']);
    } elseif($action == 'showRecordPresetDialog') {
    	showRecordPresetDialog();
    } elseif($action == 'demoteRecordPreset') {
    	demoteRecordPreset($_POST['kid']);
        showRecordPresetDialog();    	
    } elseif($action == 'renameRecordPreset') {
    	renameRecordPreset($_POST['kid'], $_POST['name']);
        showRecordPresetDialog();    	
    } elseif($action == 'loadRecordPresetList') {
    	loadRecordPresetList();
    } 
}

?>
