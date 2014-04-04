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

// Initial Version: Matt Geimer, 2008

include_once('includes/conf.php');
include_once('includes/utilities.php');

requireProjectAdmin();

include_once('includes/header.php');
echo '<h2>'.gettext('Manage Groups for').' ';
$results = $db->query('SELECT defaultgid,admingid FROM project WHERE pid='.escape($_SESSION['currentProject']).' LIMIT 1');
$array = $results->fetch_assoc();
echo htmlEscape($_SESSION['currentProjectName']).'</h2>';
$admingid = $array['admingid'];
$defaultgid = $array['defaultgid'];
?>
<script type="text/javascript">
//<![CDATA[
function modperms(thing) {
    var namear = thing.id.split("_");
    var value = 0;
    if(thing.checked) {
        value = 1;    
    } 
	$.post('includes/userFunctions.php', {action:'updateGroupPerms',source:'UserFunctions',permission:namear[0],checked:value,gid:namear[1]}, function(resp){$("#ajax").html(resp);}, 'html');
}
function deleteGroup(vargid) {
    var answer = confirm("<?php echo gettext("Really delete group?");?>");
    if(answer) {
    	$.post('includes/userFunctions.php', {action:'deleteGroup',source:'UserFunctions',gid:vargid}, function(resp){$("#ajax").html(resp);}, 'html');        
    }
    return; 
}
function addGroup() {
	$.post('includes/userFunctions.php', {action:'addGroup',source:'UserFunctions', name:$('#groupname').val(), admin:$('#newadmin :checked'),
	    ingestobj:$('#newingestobj :checked'), delobj:$('#newdelobj :checked'), edit:$('#newedit :checked'), create:$('#newcreate :checked'), delscheme:$('#newdelscheme :checked'),
	    exports:$('#newexport :checked'), moderator:$('#newmoderator :checked')}, function(resp){$("#ajax").html(resp);}, 'html');
}
$.post('includes/userFunctions.php', {action:'showGroups',source:'UserFunctions'}, function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>
<div id="ajax">
</div>
<?php 
include_once('includes/footer.php');
?>
