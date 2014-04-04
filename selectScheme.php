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

// Initial Version: Matt Geimer, 2008

include_once('includes/conf.php');
include_once('includes/utilities.php');

requireProject();
unset($_SESSION['currentScheme']);    // clear previously selected scheme
unset($_SESSION['currentSchemeName']);

include_once('includes/header.php');
echo '<h2>'.gettext('Scheme Selection for').' '.htmlEscape($_SESSION['currentProjectName']).'</h2>';
?> 
<script>   
function moveSchemeUp(varsid) {
    $.post("includes/schemeFunctions.php",{action:"moveScheme",source:"SchemeFunctions",sid:varsid,direction:"up"},function(resp){$("#ajax").html(resp);}, 'html');
	return;
}

function moveSchemeDown(varsid) {
    $.post("includes/schemeFunctions.php",{action:"moveScheme",source:"SchemeFunctions",sid:varsid,direction:"down"},function(resp){$("#ajax").html(resp);}, 'html');
	return;
}

function deleteScheme(varsid) {
    var answer = confirm(<?php echo '"'.gettext("Really delete scheme?").'"';?>);
    if(answer) {
		$.post("includes/schemeFunctions.php",{action:"deleteScheme",source:"SchemeFunctions",sid:varsid},function(resp){$("#ajax").html(resp);}, 'html');
    }
    return; 
}
$.post("includes/schemeFunctions.php",{action:"loadSchemes",source:"SchemeFunctions"},function(resp){$("#ajax").html(resp);}, 'html');
</script>
<div id="ajax"> 
</div>
<?php
include_once('includes/footer.php');
?>
