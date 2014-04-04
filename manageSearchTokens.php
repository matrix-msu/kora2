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

requireSystemAdmin();

include_once('includes/header.php');

echo '<h2>'.gettext('Manage Search Tokens').'</h2>';
?>
<script type="text/javascript">
//<![CDATA[
function createToken() {
    //new Ajax.Updater('ajax', 'includes/searchTokenFunctions.php',{ parameters:{action:'createToken',source:'SearchTokenFunctions'}});
	$.post('includes/searchTokenFunctions.php',{action:'createToken',source:'SearchTokenFunctions'},function(resp){$("#ajax").html(resp);}, 'html');
}
function deleteToken(varid) {
    var answer = confirm("<?php echo gettext("Really delete token?");?>");
    if(answer) {
       // new Ajax.Updater('ajax', 'includes/searchTokenFunctions.php', { method:'post', parameters:{action:'deleteToken',source:'SearchTokenFunctions',tokenid:varid }} );
		$.post('includes/searchTokenFunctions.php',{action:'deleteToken',source:'SearchTokenFunctions',tokenid:varid },function(resp){$("#ajax").html(resp);}, 'html');
    }
}

function addProjectAccess(vartokenid) {
    //new Ajax.Updater('ajax', 'includes/searchTokenFunctions.php', {parameters:{action:'addAccess',source:'SearchTokenFunctions',tokenid:vartokenid,pid:$('addProject'+vartokenid).value}} );
	$.post('includes/searchTokenFunctions.php',{action:'addAccess',source:'SearchTokenFunctions',tokenid:vartokenid,pid:$('#addProject'+vartokenid)[0].value},function(resp){$("#ajax").html(resp);}, 'html');
}

function removeProjectAccess(vartokenid, varprojectid) {
    //new Ajax.Updater('ajax', 'includes/searchTokenFunctions.php', {parameters:{action:'removeAccess',source:'SearchTokenFunctions',tokenid:vartokenid,pid:varprojectid}} );
	$.post('includes/searchTokenFunctions.php',{action:'removeAccess',source:'SearchTokenFunctions',tokenid:vartokenid,pid:varprojectid},function(resp){$("#ajax").html(resp);}, 'html');
}

//new Ajax.Updater('ajax','includes/searchTokenFunctions.php',{method:'post',parameters:{action:'showTokens',source:'SearchTokenFunctions'}});
$.post('includes/searchTokenFunctions.php',{action:'showTokens',source:'SearchTokenFunctions'},function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>

<div id="ajax"> 
</div>

<?php 
include_once('includes/footer.php');
?>