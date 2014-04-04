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
// Added quota handling.  MG 4/2011

require_once('includes/conf.php');
require_once('includes/utilities.php');

requireSystemAdmin();

/// TODO: Add Javascript Form Validation

/// getProject() - Gets the current project id as passed from the selection form
function getProject()
{
    $i = '';
    
    if (!empty($_POST['inactive']) && empty($_POST['active'])) $i = $_POST['inactive'];
    if (empty($_POST['inactive']) && !empty($_POST['active'])) $i = $_POST['active'];
    
    // only accept positive integers as project ids
    if (preg_match('/[0-9]+/', $i)) return $i;
    else return 0;
}

/// showManageForm - displays the list of projects and management actions available
function showManageForm()
{
    global $db;
    
	include_once('includes/header.php');

	echo '<h2>'.gettext('Manage Projects').'</h2>';
	?>
	<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<table class="table">
			<tr><th><?php echo gettext('Active Projects')?></th><th></th><th><?php echo gettext('Inactive Projects')?></th></tr>
			<tr><td><select name="active" class="textarea_select" multiple="multiple" onClick="inactive.selectedIndex=-1;">
	<?php 
	        // output all selected projects here
	        $results = $db->query("SELECT pid, name FROM project WHERE active='1' ORDER BY name");
	        if (!$results) error_log($db->error);
	        
	        while($r = $results->fetch_assoc())
	        {
	            echo '<option value="'.$r['pid'].'">'.htmlEscape($r['name']).'</option>';  
	        }
	?>
			</select></td>
			<td valign="middle" style="text-align: center;">
			<input type="submit" name="manage_submit" class="submit" value="<--" />
			<input type="submit" name="manage_submit" class="submit" value="-->" />
			</td><td><select name="inactive" class="textarea_select" multiple="multiple" onClick="active.selectedIndex=-1;">
	<?php     
	        // output all inactive projects here
	        $results = $db->query("SELECT pid, name FROM project WHERE active='0' ORDER BY name");
	        if (!$results) error_log($db->error);
	        
	        while($r = $results->fetch_assoc())
	        {
	            echo '<option value="'.$r['pid'].'">'.htmlEscape($r['name']).'</option>';  
	        }
	?>
			</select></td></tr>
			<tr><td colspan="3">
				<input type="submit" name="manage_submit" class="submit" value="<?php echo gettext('New Project')?>" />
				<input type="submit" name="manage_submit" class="submit" value="<?php echo gettext('Edit Project Information')?>" />
				<input type="submit" name="manage_submit" class="submit" value="<?php echo gettext('Delete Project')?>" />
			</td></tr>
		</table>
	</form>
	<?php 
	include_once('includes/footer.php');
	


}

/// showNewProjectForm - shows the form for creating a new project
function showNewProjectForm($errors = '')
{
    global $db;
    
    include_once('includes/header.php');
	echo '<h2>'.gettext('Create New Project').'</h2>';
	if(!empty($errors)) echo '<div class="error">'.gettext($errors).'</div>'; ?>
	<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<input type="hidden" name="newproject" value="true" />
   		<table>
   			<tr><td><?php echo gettext('Name')?>:</td><td><input type='text' name='name' <?php  if(isset($_POST['name'])) echo "value='".$_POST['name']."'"; ?> /></td></tr>
   			<tr><td><?php echo gettext('Description')?>:</td><td><textarea name='description'><?php  if(isset($_POST['description'])) echo $_POST['description']; ?></textarea></td></tr>
   			<tr><td><?php echo gettext('Initial Administrator')?>:</td><td><select name='admin'>
   	<?php 
	        // output all confirmed users here
	        $results = $db->query("SELECT uid, username, realName FROM user WHERE confirmed='1' AND searchAccount='0' ORDER BY username");
	        if (!$results) error_log($db->error);
	        
	        while($r = $results->fetch_assoc())
	        {
	            echo '<option value="'.$r['uid'].'"';
	            if (isset($_POST['admin']) && ($_POST['admin'] == $r['uid'])) echo ' selected="selected" ';
	            echo '>'.htmlEscape($r['username']).' ('.htmlEscape($r['realName']).')</option>';  
	        }    
   	
   	?>
   		</select>
   			</td></tr>
   			<tr>
   				<td><?php echo gettext('Active')?>:</td>
   				<td><select name='active'>
   					<option value='1' <?php  if (isset($_POST['active']) && ($_POST['active'] == '1')) echo 'selected="selected"'; ?> ><?php echo gettext('Yes')?></option>
   					<option value='-1' <?php  if (isset($_POST['active']) && ($_POST['active'] == '-1')) echo 'selected="selected"'; ?> ><?php echo gettext('No')?></option>
   				</select></td>
   			</tr>
   			<tr><td><?php echo gettext('Style')?>:</td>
   			    <td><select name='style'>
   			        <option value='0'><?php echo gettext('Default KORA Appearance')?></option>
<?php 
$styleQuery = $db->query('SELECT styleid, description FROM style ORDER BY description');
while ($s = $styleQuery->fetch_assoc())
{
	echo "<option value='".$s['styleid']."'>".htmlEscape($s['description']).'</option>';
}
?>
                </select></td>
            </tr>   			
			<tr><td><?php echo gettext('Quota');?>:</td><td><input type="text" name="quota" <?php if(isset($_POST['quota'])) echo "value='".$_POST['quota']."'";?> /> MB</td></tr>
   			<tr><td colspan='2'><input type='submit' value='<?php echo gettext('Create New Project')?>' /></td></tr>
   		</table>
   	</form>	
   	<br /><form action="<?php echo $_SERVER['PHP_SELF']?>" method="post"><input type='submit' value='<?php echo gettext('Go Back')?>' /></form>
   <?php 
   include_once('includes/footer.php'); 
}

function showEditProjectForm($pid, $errors='')
{
    global $db;
    
    include_once('includes/header.php');
    
    // Get Project Information
    $results = $db->query("SELECT name, description, defaultgid, active, styleid, quota FROM project WHERE pid=".escape($pid)." LIMIT 1");
    $results = $results->fetch_assoc();
    
    echo '<h2>'.gettext('Edit Project Information').'</h2>';
	if(!empty($errors)) echo '<div class="error">'.gettext($errors).'</div>'; ?>
	<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<input type="hidden" name="editproject" value="true" />
		<input type="hidden" name="pid" value="<?php echo $pid?>" />
   		<table>
   			<tr><td><?php echo gettext('Name')?>:</td><td><input type='text' name='name' value='<?php echo htmlEscape($results['name'])?>' /></td></tr>
   			<tr><td><?php echo gettext('Description')?>:</td><td><textarea name='description'><?php echo htmlEscape($results['description'])?></textarea></td></tr>
   			<tr>
   				<td><?php echo gettext('Active')?>:</td>
   				<td><select name='active'>
   					<option value='1' <?php  if ($results['active'] == '1') echo 'selected="selected"'; ?> ><?php echo gettext('Yes')?></option>
   					<option value='0' <?php  if ($results['active'] == '0') echo 'selected="selected"'; ?> ><?php echo gettext('No')?></option>
   				</select></td>
   			</tr>
            <tr><td><?php echo gettext('Style')?>:</td>
                <td><select name='style'>
                    <option value='0' <?php  if ($results['styleid'] == 0) echo 'selected="selected" '; ?> ><?php echo gettext('Default KORA Appearance')?></option>
<?php 
$styleQuery = $db->query('SELECT styleid, description FROM style ORDER BY description');
while ($s = $styleQuery->fetch_assoc())
{
    echo "<option value='".$s['styleid']."' ";
    if ($results['styleid'] == $s['styleid']) echo 'selected="selected" ';
    echo '>'.htmlEscape($s['description']).'</option>';
}
?>
                </select></td>
            </tr>
			<tr><td><?php echo gettext('Quota');?>:</td><td><input type="text" name="quota" value='<?php echo htmlEscape($results['quota']);?>' /> MB</td></tr>			
   			<tr><td colspan='2'><input type='submit' value='<?php echo gettext('Edit Project Details')?>' /></td></tr>
   		</table>
   	</form>	
   	<br /><form action="<?php echo $_SERVER['PHP_SELF']?>" method="post"><input type='submit' value='<?php echo gettext('Go Back')?>' /></form>
   	<?php    
    include_once('includes/footer.php');
}

function showConfirmDeleteForm($pid)
{
    global $db;
    
    $results = $db->query('SELECT name FROM project WHERE pid='.escape($pid).' LIMIT 1');
    $results = $results->fetch_assoc();
    
    include_once('includes/header.php');
    echo '<h2>'.gettext('Confirm Deletion').'</h2>';
    ?>
    <div class="error"><?php 
    echo gettext('Warning').': ';
    printf(gettext('You are about to delete the "%s" project'), htmlEscape($results['name']));
    echo '.  '.gettext('Please confirm this action').'.';
    ?>
    </div><br />
    <form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
    	<input type="hidden" name="deleteproject" value="true" />
    	<input type="hidden" name="pid" value="<?php echo $pid?>" />
    	<input type="submit" value="<?php echo gettext('Delete Project')?>" />
    </form><br /><br /> 
    <form action="<?php echo $_SERVER['PHP_SELF']?>" method="post"><input type='submit' value='<?php echo gettext('Go Back')?>' /></form>
    <?php 
    include_once('includes/footer.php');
}

if (isset($_POST['manage_submit']))
{
    switch(gettext($_POST['manage_submit'])) {
    case gettext('New Project'):
        showNewProjectForm();
        break;
    case gettext('Edit Project Information'):
        $i = getProject();
        if ($i > 0) showEditProjectForm($i);
        else showManageForm();
        break;
    case gettext('Delete Project'):
        $i = getProject();
        if ($i > 0) showConfirmDeleteForm($i);
        else showManageForm();
        break;
    case '-->':
        // move project to be inactive
        $i = getProject();
        if ($i > 0) {
            $db->query("UPDATE project SET active='0' WHERE pid='$i'");    
        }
        
        showManageForm();
        break;
    case '<--':
        // move project to be active
        $i = getProject();
        if ($i > 0) {
             $db->query("UPDATE project SET active='1' WHERE pid='$i'");           
        }
        
        showManageForm();
        break;
    default:
        showManageForm();
    }
}
else if (isset($_POST['newproject']))
{
    // syntax checks
    if (empty($_POST['name'])) showNewProjectForm(gettext('You must provide a project name.'));
    else if (empty($_POST['description'])) showNewProjectForm(gettext('You must provide a project description.'));
    else if (empty($_POST['active'])) showNewProjectForm(gettext('Connection error, try again.'));
    else if (mb_strlen($_POST['description']) > 255) showNewProjectForm(gettext('Description too long.'));
    else if (mb_strlen($_POST['name']) > 255) showNewProjectForm(gettext('Name too long.'));
	else if(!is_numeric($_POST['quota'])) showNewProjectForm(gettext('Quota must be a number, use 0 for unlimited.'));
    // otherwise, inputs are acceptables
    else {
        // truncate field lengths
        $name = mb_substr($_POST['name'], 0, 255);
        $description = mb_substr($_POST['description'], 0, 255);
		//grab quota
		$quota = $_POST['quota'];
        // check for the active flag.  1 = enabled, anything else = inactive
        $active = $_POST['active'];
        if ($active != 1) $active = 0;
        $styleid = (isset($_POST['style']) ? (int)$_POST['style'] : 0);
        
        // Insert the initial information
        $query  = 'INSERT INTO project (name, description, active, styleid, quota) VALUES (';
        $query .= escape($name).', ';
        $query .= escape($description).', ';
        $query .= escape($active).',';
        $query .= $styleid.',';
		$query .= escape($quota).')';
    
        $result = $db->query($query);
        $pid    = $db->insert_id;

        // create project control and data tables
        $db->query('CREATE TABLE p'.$pid.'Control(
                        cid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                        schemeid INTEGER UNSIGNED NOT NULL,
                        collid INTEGER UNSIGNED NOT NULL,
                        type VARCHAR(30) NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        description VARCHAR(255),
                        required TINYINT(1) NOT NULL,
                        searchable TINYINT(1) NOT NULL,
			advSearchable TINYINT(1) UNSIGNED NOT NULL,
                        showInResults TINYINT(1) NOT NULL,
                        showInPublicResults TINYINT(1) NOT NULL,
                        publicEntry TINYINT(1) NOT NULL,
                        options LONGTEXT NOT NULL,
                        sequence INTEGER UNSIGNED NOT NULL,
                        PRIMARY KEY(cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
        
        $db->query('CREATE TABLE p'.$pid.'Data(
                        id VARCHAR(30) NOT NULL,
                        cid INTEGER UNSIGNED NOT NULL,
                        schemeid INTEGER UNSIGNED NOT NULL,
                        value LONGTEXT,
                        PRIMARY KEY(id,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
        
        
        // create project data table for *PUBLIC* ingestion
        $db->query('CREATE TABLE IF NOT EXISTS p'.$pid.'PublicData(
                        id INTEGER UNSIGNED NOT NULL,
                        cid INTEGER UNSIGNED NOT NULL,
                        schemeid INTEGER UNSIGNED NOT NULL,
                        value LONGTEXT,
                        PRIMARY KEY(id,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
        

        
        // create the initial groups and insert the default admin into the admin group
        $query  = 'INSERT INTO permGroup (pid, name, permissions) VALUES (';
        $query .= "'$pid', 'Administrators', '".PROJECT_ADMIN."')";
        
        $result = $db->query($query);
        $adminid= $db->insert_id;        
        
        // create the initial groups and insert the default admin into the admin group
        $query  = 'INSERT INTO permGroup (pid, name, permissions) VALUES (';
        $query .= "'$pid', 'Default', '0')";
        
        $result = $db->query($query);
        $defid  = $db->insert_id;

        // Insert the Initial Admin User
        $query  = 'INSERT INTO member (uid, pid, gid) VALUES (';
        $query .= escape($_POST['admin']).", '$pid', '$adminid')";
        $result = $db->query($query);
        
        // update the project table to reflect the group ids
        $query  = "UPDATE project SET admingid='$adminid', defaultgid='$defid' WHERE pid='$pid'";
        $result = $db->query($query);
        
        showManageForm();
    }
}
else if (isset($_POST['editproject']))
{
    // verify all forms passed are correct
    //die('SELECT name FROM permGroup WHERE pid='.escape($_POST['pid']).' AND gid='.escape($_POST['defaultgid']));
    //$checkResults = $db->query('SELECT name FROM permGroup WHERE pid='.escape($_POST['pid']).' AND gid='.escape($_POST['defaultgid']));
    
    if (empty($_POST['pid'])) showManageForm();
    else if (empty($_POST['name'])) showEditProjectForm($_POST['pid'], gettext('You must provide a project name.'));
    else if (empty($_POST['description'])) showEditProjectForm($_POST['pid'], gettext('You must provide a project description.'));
	else if(!is_numeric($_POST['quota'])) showEditProjectForm($_POST['pid'], gettext('Quota must be a number, use 0 for unlimited.'));
    else if (!isset($_POST['active'])) showEditProjectForm($_POST['pid'], gettext('Connection error, try again.'));
    //else if ($checkResults->num_rows <= 0) showEditProjectForm($_POST['pid'], 'Invalid Group Selected.');
    else {
		$name = mb_substr($_POST['name'], 0, 255);
        $description = mb_substr($_POST['description'], 0, 255);
        $styleid = (isset($_POST['style']) ? (int)$_POST['style'] : 0);
		$quota = $_POST['quota'];
        
        $query  = 'UPDATE project SET name='.escape($name).', description='.escape($description);
        $query .= ', active='.escape($_POST['active']).", styleid=".$styleid.", quota=".$quota;
        $query .= ' WHERE pid='.escape($_POST['pid']);
        $updateResults = $db->query($query);
        
        showManageForm();
    }
}
else if (isset($_POST['deleteproject']))
{
    if (intval($_POST['pid'])) deleteProject(intval($_POST['pid']));
    
    showManageForm();
}
else showManageForm();

?>

