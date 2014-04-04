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

require_once('includes/conf.php');
require_once('includes/utilities.php');
requireProject('selectProject.php');
requireScheme('selectScheme.php');
requirePermissions( PROJECT_ADMIN | CREATE_SCHEME | EDIT_LAYOUT, 'selectScheme.php');
//requireProjectAdmin();
require_once('includes/header.php');

echo '<h2>'.gettext('Grant Associatior Permissions').'</h2>';
echo '<p>'.gettext('This form allows you to grant association access to other schemes.  Select a project from the left drop-down menu, followed by a scheme from the right drop-down menu, then click "Add" to grant permission to that scheme to search this scheme.').'</p><br/>';

// get all associator permissions from the project table
// TODO: this should probably be only projects a user is a member of.
$query = 'SELECT name AS projectName,schemeName,pid,schemeid,crossProjectAllowed FROM scheme LEFT JOIN project USING(pid)';
$results = $db->query($query);

$projects = array();
while($result = $results->fetch_assoc()){
	$projects[$result['pid']][]=$result;
}

$projectSelect ='<select id="projectid" name="projectid"><option></option>';
$schemeSelect = '<select id="schemeid" name="schemeid"><option></option><option value="all">Add All Schemes</option>';
foreach($projects as $pid => $schemes){
	$project = $schemes[0]['projectName'];
	$selected = '';
	if($pid == $_SESSION['currentProject']) $selected = 'selected="selected"';
	
	$projectSelect .="<option value=\"$pid\" $selected>$project</option>";
	foreach($schemes as $scheme){
		$schemeSelect.= '<option class="schemeoption project'.$scheme['pid'].'" value="'.$scheme['schemeid'].'">'.$scheme['schemeName']."</option>\n";
	}
}
$projectSelect .='</select>';
$schemeSelect .= '</select>';


?>
<style type="text/css">.schemeoption{display:none;}</style>
<script type="text/javascript">
//<![CDATA[
           
$(document).ready(function(){
	// when the project is changed hide all scheme options then display only
	// scheme options from that project.
	$('#projectid').change(function(){
		$('#schemeid').val('');
		$('.schemeoption').hide();
		$('.project'+$('#projectid').val()).show();
	});

	// show schemes if a project is selected
	if($('#projectid').val() != '') $('.project'+$('#projectid').val()).show();

	$('#perms_table').load('includes/projectFunctions.php', {source:'ProjectFunctions', action:'loadSchemes'});
});

function addScheme() {
	$('#perms_table').load('includes/projectFunctions.php', {action:'addScheme',source:'ProjectFunctions', pid:$('#projectid').val(), sid:$('#schemeid').val()});
}

function removeScheme(varpid,varsid) {
    var answer = confirm("<?php echo gettext("Are you sure you want to remove this permission?");?>");

    if (answer) {
    	$('#perms_table').load('includes/projectFunctions.php', {action:'removeScheme',source:'ProjectFunctions', pid:varpid, sid:varsid});
    }
    return;
}
//]]>
</script>

<?php echo $projectSelect,$schemeSelect;?>
&nbsp;<a href="" onclick="addScheme();return false;"><?php echo gettext('Add');?></a>
<br/><br/>
<div id="perms_table"></div>
<?php

require_once('includes/footer.php');
?>

