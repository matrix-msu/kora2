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

// Initial Version: Brian Beck, 2008

function checkUsername($uname) {
    global $db;
    if($uname == "") {
        echo " ";
    }
    else {
        $results = $db->query("SELECT username FROM user WHERE username=".escape($uname)." LIMIT 1");
        if($results) { 
            $array = $results->fetch_assoc();
            if($array) {
               echo gettext('Username already in use').'!';
            }
            else
                echo gettext('Username available');
        }
        else
          echo $db->error;
    }
}

/**
 * loadNames() is a function used with AJAX calls to populate a table
 * for the manageUsers page
 *
 */
function loadNames() {
    requireLogin();
    global $db;
    $results = $db->query("SELECT uid,username,realName,organization,confirmed,admin FROM user WHERE searchAccount=0 ORDER BY username ASC");
    echo '<table class="table"><tr><td><strong>'.gettext('Username').'</strong></td><td><strong>'.gettext('Real Name').'</strong></td><td>
    <strong>'.gettext('Organization').'</strong></td><td><strong>'.gettext('Activated').'?</strong></td><td><strong>'.gettext('Admin').'</strong></td><td><strong>'.gettext('Delete').'?</strong></td></tr>';
    if(!$results) {
        print $db->error;  
    }
    else {
        while($array = $results->fetch_assoc()) {
            echo '<tr><td>'.htmlEscape($array['username']).'</td><td>'.$array['realName'].'</td><td>'.$array['organization'].'</td>';
            echo '<td><input type="checkbox" name="activatedbox_'.$array['uid'].'" id="activatedbox_'.$array['uid'].'"';
            if($array['confirmed']){echo " checked ";}
            echo ' onclick="updateActivated('.$array['uid'].')" /></td>';
            echo '<td><input type="checkbox" name="adminbox_'.$array['uid'].'" id="adminbox_'.$array['uid'].'"';
            if($array['admin']){echo " checked ";}
            echo ' onclick="updateAdmin('.$array['uid'].')" /></td><td><a class="delete" onclick="deleteUser('.$array['uid'].')">X</a></td></tr>';
        }
    }
    echo "</table>";
?>    
<br /><br /><strong><?php echo gettext('Reset User Password');?></strong><br />
<table class="table">
    <tr>
        <td><strong><?php echo gettext('Username');?></strong></td>
        <td><select name="username" id="username">
<?php 
        $results = $db->query('SELECT uid,username FROM user WHERE searchAccount=0 ORDER BY username ASC');
        while ($user = $results->fetch_assoc())
        {
        	echo '<option value="'.$user['uid'].'">'.htmlEscape($user['username']).'</option>';
        }
?>
        </select></td>
    </tr>
    <tr>
        <td><strong><?php echo gettext('Password');?></strong></td>
        <td><input type="password" name="password1" id="password1" /></td>
    </tr>
    <tr>
        <td><strong><?php echo gettext('Confirm Password');?></strong></td>
        <td><input type="password" name="password2" id="password2" /></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="button" onclick="resetPassword();" value="<?php echo gettext('Reset Password');?>" /></td>
    </tr>
</table>
<?php 
}

/**
 * Function called via AJAX to update the administrator flag on a user. 
 *
 * @param int $uid
 * @param string $checked
 */
function updateAdmin($uid,$admin) {
    requireSystemAdmin();

    global $db;
    if($admin == "true") {
        $db->query("UPDATE user SET admin=1 WHERE uid=".escape($uid)." LIMIT 1");
    }
    if($admin == "false") {
        $db->query("UPDATE user SET admin=0 WHERE uid=".escape($uid)." LIMIT 1");
    }
}

/**
 * Function called via AJAX to update the activated flag on a user. 
 *
 * @param int $uid
 * @param string $checked
 */
function updateActivated($uid,$admin) {
    requireSystemAdmin();
    
    global $db;
    if($admin == "true") {
        $db->query("UPDATE user SET confirmed=1 WHERE uid=".escape($uid)." LIMIT 1");
    }
    if($admin == "false") {
        $db->query("UPDATE user SET confirmed=0 WHERE uid=".escape($uid)." LIMIT 1");
    }
}

/**
 * Function called via AJAX to delete a user from KORA
 *
 * @param int $uid
 */
function deleteUser($uid) {
    requireSystemAdmin();
    global $db;
    if($uid && $uid != 1){  //don't delete the first user - its koraadmin
        $db->query("DELETE FROM user WHERE uid=".escape($uid)." LIMIT 1");
        $db->query("DELETE FROM member WHERE uid=".escape($uid));    
    }
    loadNames();
}

/**
 * Function called via AJAX to modify permissions for a group in a project
 *
 * @param string $permission
 * @param int $checked
 * @param int $gid
 */
function updateGroupPerms($permission, $checked, $gid) {
    requireProjectAdmin();
    global $db;
    //$currentPerms = $array['permissons'];
    $perms = array();
    if($permission == "adminbox")
       $perms = PROJECT_ADMIN;
    if($permission == "ingestbox")
        $perms = INGEST_RECORD;
    if($permission == "deleteobjbox")
        $perms = DELETE_RECORD;
    if($permission == "editbox")
        $perms = EDIT_LAYOUT;
    if($permission == "createbox")
        $perms = CREATE_SCHEME;
    if($permission == "deleteschemebox")
        $perms = DELETE_SCHEME;
    if($permission == "exportbox")
        $perms = EXPORT_SCHEME;
    if($permission == "moderatorbox")
        $perms = MODERATOR;
    if($checked) {
        $db->query("UPDATE permGroup SET permissions=(permissions+$perms) WHERE gid=".escape($gid)." LIMIT 1");
    }
    else {
        $db->query("UPDATE permGroup SET permissions=(permissions-$perms) WHERE gid=".escape($gid)." LIMIT 1");
    }
    showGroups();
}

/**
 * outputs the groups for a project, used via AJAX call.  Also dissalows deletion of admin and default groups for project.
 * Also includes the form for adding a new group at the end of the display of current groups.
 */
function showGroups() {
    requireProjectAdmin();
    global $db;
    if(isset($_SESSION['currentProject']) && !empty($_SESSION['currentProject'])) {
        $results = $db->query('SELECT defaultgid,admingid FROM project WHERE pid='.escape($_SESSION['currentProject']).' LIMIT 1');
        $array = $results->fetch_assoc();
        $admingid = $array['admingid'];
        $defaultgid = $array['defaultgid'];
        $results = $db->query('SELECT * FROM permGroup WHERE pid='.escape($_SESSION['currentProject']));
        echo '<table class="table">
        	<th>'.gettext('Name').'</th>
        	<th>'.gettext('Admin').'</th>
        	<th>'.gettext('Ingest Obj').'</th>
        	<th>'.gettext('Delete Obj').'</th>
        	<th>'.gettext('Edit Layout').'</th>
        	<th>'.gettext('Create Scheme').'</th>
        	<th>'.gettext('Delete Scheme').'</th>
        	<th>'.gettext('Export Scheme').'</th>
        	<th>'.gettext('Moderate Public Ingestion').'</th>
        	<th>'.gettext('Action').'</th>';
        while($array = $results->fetch_assoc()) {
            echo '<tr><td><div style="width: 200px; overflow: hidden;">'.htmlEscape($array['name']).'</td>
            <td><input type="checkbox" name="adminbox_'.$array['gid'].'" id="adminbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & PROJECT_ADMIN )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="ingestbox_'.$array['gid'].'" id="ingestbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & INGEST_RECORD )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="deleteobjbox_'.$array['gid'].'" id="deleteobjbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & DELETE_RECORD )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="editbox_'.$array['gid'].'" id="editbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & EDIT_LAYOUT )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="createbox_'.$array['gid'].'" id="createbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & CREATE_SCHEME )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="deleteschemebox_'.$array['gid'].'" id="deleteschemebox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & DELETE_SCHEME )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td><td><input type="checkbox" name="exportbox_'.$array['gid'].'" id="exportbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & EXPORT_SCHEME )
                echo ' checked="true" ';
            echo ' /></td><td><input type="checkbox" name="moderatorbox_'.$array['gid'].'" id="moderatorbox_'.$array['gid'].'" onclick="modperms(this)" ';
            if($array['permissions'] & MODERATOR )
                echo ' checked="true" ';
            if($array['gid'] == $admingid)
                echo ' disabled="true" ';
            echo ' /></td>';
            if($array['gid'] != $defaultgid && $array['gid'] != $admingid )
                echo '<td><a class="link" onclick="deleteGroup('.$array['gid'].')">X</a></td>';
            echo '</tr>';
        }
        echo '<br /><th>'.gettext('Add New Group').'</th>
        <tr><td><input type="textbox" name="groupname" id="groupname" /></td>
        <td><input type="checkbox" name="newadmin" id="newadmin" /></td>
        <td><input type="checkbox" name="newingestobj" id="newingestobj" /></td>
        <td><input type="checkbox" name="newdelobj" id="newdelobj" /></td>
        <td><input type="checkbox" name="newedit" id="newedit" /></td>
        <td><input type="checkbox" name="newcreate" id="newcreate" /></td>
        <td><input type="checkbox" name="newdelscheme" id="newdelscheme" /></td>
        <td><input type="checkbox" name="newexport" id="newexport"/></td>
        <td><input type="checkbox" name="newmoderator" id="newmoderator"/></td>
        <td><a class="link" onclick="addGroup()">'.gettext('Add').'</a></tr>
        </table>';
    }
    else
       echo gettext('No project selected');
}

/**
 * Deletes a group from a project, specifed by $gid.  Sets any users that were still in that group to the defaultgid for the project. 
 *
 * @param int $gid
 */
function deleteGroup($gid){
    requireProjectAdmin();
    global $db;
    $result = $db->query("SELECT defaultgid FROM project where pid=$_SESSION[currentProject] LIMIT 1");
    $array = $result->fetch_assoc();
    $db->query("UPDATE member SET gid=$array[defaultgid] WHERE gid=$gid");
    $db->query("DELETE FROM permGroup where gid=".escape($gid)." LIMIT 1");
    showGroups();
}
 
/**
 * Takes in the name and permissions for a group, then creates a new group for the current project.
 *
 * @param string $name
 * @param string $admin
 * @param string $ingestobj
 * @param string $delobj
 * @param string $edit
 * @param string $create
 * @param string $delscheme
 * @param string $export
 * @param string $moderator
 */
function addGroup($name,$admin,$ingestobj,$delobj,$edit,$create,$delscheme,$export,$moderator) {
    requireProjectAdmin(); 
    global $db;
    $perms = 0;
    if(!empty($name)) {
        if($admin == "true")
           $perms += PROJECT_ADMIN;
        if($ingestobj == "true")
           $perms += INGEST_RECORD;
        if($delobj == "true")
           $perms += DELETE_RECORD;
        if($edit == "true")
           $perms += EDIT_LAYOUT;
        if($create == "true")
           $perms += CREATE_SCHEME;
        if($delscheme == "true")
           $perms += DELETE_SCHEME;
        if($export == "true")
           $perms += EXPORT_SCHEME;
        if($moderator == "true")
           $perms += MODERATOR;
        $db->query("INSERT INTO permGroup (pid,name,permissions) VALUES ($_SESSION[currentProject],".escape($name).",$perms)");
    }
    showGroups();
}

/**
 * Shows Project users for a specific project, used via AJAX call.
 */
function showProjectUsers() {
    requireProjectAdmin();
    global $db;
    $result = $db->query("select member.uid,permGroup.gid, permGroup.name,user.username FROM member JOIN user ON (member.uid = user.uid)
JOIN permGroup ON (member.gid = permGroup.gid) WHERE member.gid != 0 AND user.searchAccount=0 AND member.pid = $_SESSION[currentProject]");
    if($result) {
        echo '<table class="table"><th>'.gettext('Username').'</th><th>'.gettext('Group').'</th><th>'.gettext('Action').'</th>';       
        while($array = $result->fetch_assoc()) {
                 echo '<tr><td>'.htmlEscape($array['username']).'</td><td>'.htmlEscape($array['name']).'</td><td><a class="delete" onclick="deleteProjectUser('.$array['uid'].')">X</a></td></tr>';       
        }
        $result = $db->query("SELECT user.username,user.uid FROM user 
        WHERE uid NOT IN (SELECT member.uid from member,permGroup WHERE member.pid = $_SESSION[currentProject] 
        AND member.gid != permGroup.gid) AND user.searchAccount=0 ORDER BY user.username ASC");
        echo $db->error;
        echo '<tr><td><select name="useradd" id="useradd">';
        while($array = $result->fetch_assoc()) {
            //print_r($array);
            echo '<option value="'.$array['uid'].'">'.htmlEscape($array['username']).'</option>';
        }
        echo '</select></td><td><select name="groupadd" id="groupadd">';
        $result = $db->query("SELECT name,gid FROM permGroup WHERE pid=$_SESSION[currentProject]");
        while($array = $result->fetch_assoc()) {
            echo '<option value="'.$array['gid'].'">'.htmlEscape($array['name']).'</option>';
        }
        echo '</select></td><td><a class="link" onclick="addProjectUser()">'.gettext('Add').'</a></td></tr>';
        echo '</table>';
    }
}

/**
 * Adds a user to a project, using the specified group passed in to the function.
 *
 * @param int $user
 * @param int $group
 */
function addProjectUser($user,$group) {
    requireProjectAdmin();
    global $db;
    $db->query("INSERT INTO member (uid,pid,gid) VALUES ($user,$_SESSION[currentProject],$group)");
    showProjectUsers();
}

/**
 * Deletes the user specified in $user from the project
 *
 * @param int $user
 */
function deleteProjectUser($user) {
    requireProjectAdmin();
    global $db;
    $db->query("DELETE FROM member WHERE uid=$user AND pid=$_SESSION[currentProject] LIMIT 1");
    
    showProjectUsers();
}


function resetPassword($user, $password)
{
	requireSystemAdmin();
	global $db;

	// Hash the password
    $salt = time();
    $sha256 = hash_init('sha256');
    hash_update($sha256, $password);
    hash_update($sha256, $salt);
    $pwhash = hash_final($sha256);	
	$db->query('UPDATE user SET password='.escape($pwhash).', salt='.escape($salt).' WHERE uid='.escape($user).' LIMIT 1');

	loadNames();
}

if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'UserFunctions')
{
    $action = $_POST['action'];
    if ($action == 'updateAdmin') {
        updateAdmin($_POST['uid'],$_POST['admin']);
        loadNames();
    }
    elseif ($action == 'updateActivated') {
    	updateActivated($_POST['uid'], $_POST['activated']);
    	loadNames();
    }
    elseif ($action == 'loadNames') {
        loadNames();
    }
    elseif ($action == 'deleteUser') {
        deleteUser($_POST['uid']);
    }
    elseif($action == 'checkUsername') {
        checkUsername($_POST['uname']);
    }
    elseif ($action == 'updateGroupPerms') {
        updateGroupPerms($_POST['permission'], $_POST['checked'], $_POST['gid']);
    }
    elseif ($action == 'showGroups') {
        showGroups();
    }
    elseif ($action == 'deleteGroup') {
        deleteGroup($_POST['gid']);
    }
    elseif ($action == 'addGroup') {
        addGroup($_POST['name'],$_POST['admin'],$_POST['ingestobj'],$_POST['delobj'],$_POST['edit'],$_POST['create'],$_POST['delscheme'],$_POST['exports'],$_POST['moderator']);
    }
    elseif ($action == 'showProjectUsers') {
        showProjectUsers();
    }
    elseif ($action == 'addProjectUser') {
        addProjectUser($_POST['user'],$_POST['group']);
    }
    elseif ($action == 'deleteProjectUser') {
        deleteProjectUser($_POST['user']);
    }
    elseif ($action == 'resetPassword') {
    	resetPassword($_POST['user'], $_POST['password']);
    }
}
?>
