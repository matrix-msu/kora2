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
requireProjectAdmin();
require_once('includes/header.php');
echo '<h2>'.gettext('Manage Users for').' '.htmlEscape($_SESSION['currentProjectName']).'</h2>';
?>
<script type="text/javascript">
function addProjectUser() {
   // new Ajax.Updater('ajax', 'includes/userFunctions.php', { parameters: {action:'addProjectUser',source:'UserFunctions',user:$('useradd').value,
   // group:$('groupadd').value } } )
    $.post('includes/userFunctions.php',{action:'addProjectUser',source:'UserFunctions',user:$('#useradd').val(),
        group:$('#groupadd').val() },function(resp){$("#ajax").html(resp);}, 'html');
}
function deleteProjectUser(varuser) {
    var answer = confirm(<?php echo '"'.gettext("Really delete from project?").'"';?>);
    if(answer) {
        //new Ajax.Updater('ajax', 'includes/userFunctions.php', { parameters: {action:'deleteProjectUser',source:'UserFunctions',user:varuser} } )
		$.post('includes/userFunctions.php',{action:'deleteProjectUser',source:'UserFunctions',user:varuser},function(resp){$("#ajax").html(resp);}, 'html');
    }
    return;
}
//new Ajax.Updater('ajax', 'includes/userFunctions.php', { parameters:{action:'showProjectUsers',source:'UserFunctions' }});
$.post('includes/userFunctions.php',{action:'showProjectUsers',source:'UserFunctions' },function(resp){$("#ajax").html(resp);}, 'html');
</script>
<div id="ajax">
</div>
<?php 
require_once('includes/footer.php');
?>
