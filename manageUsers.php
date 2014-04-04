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

require_once('includes/conf.php');
require_once('includes/utilities.php');
requireSystemAdmin();
require_once('includes/header.php');

echo '<h2>'.gettext('Manage Users').'</h2>';
?>
<script type="text/javascript">
//<![CDATA[
function updateAdmin(varuid) {
	$.post('includes/userFunctions.php', {action:'updateAdmin',source:'UserFunctions',uid:varuid,admin:$('#adminbox_'+varuid)[0].checked}, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateActivated(varuid) {
	$.post('includes/userFunctions.php', {action:'updateActivated',source:'UserFunctions',uid:varuid,activated:$('#activatedbox_'+varuid)[0].checked}, function(resp){$("#ajax").html(resp);}, 'html');
}

function deleteUser(varuid) {
    var answer = confirm("<?php echo gettext("Really delete user?");?>");
    if(answer) {
    	$.post('includes/userFunctions.php', {action:'deleteUser',source:'UserFunctions',uid:varuid}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    return; 
}

function resetPassword() {
    var uid = $('#username option:selected').text();
    var pw1 = $('#password1').val();
    var pw2 = $('#password2').val();
    if (pw1 == pw2) {
    	$.post('includes/userFunctions.php', { user:uid, password:pw1, action:'resetPassword',source:'UserFunctions'}, function(resp){$("#ajax").html(resp);});
		alert("Your password has successfully been changed!");
    } else {
        alert("<?php echo gettext('Passwords do not match.  Please Try Again.');?>");
    }
}
$.post('includes/userFunctions.php', {action:'loadNames',source:'UserFunctions'}, function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>
<div id="ajax"> 
</div>
<?php 
require_once('includes/footer.php');
?>
