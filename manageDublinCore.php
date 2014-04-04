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

require_once('includes/utilities.php');
requireProjectAdmin();
requireScheme('selectScheme.php');

require_once('includes/header.php');


?>
<script type="text/javascript">
//<![CDATA[
function addDublinCore() {
    //new Ajax.Updater('ajax', 'includes/dublinFunctions.php', { parameters: {action:'addDublinCore',source:'DublinFunctions',dctype:$('dcfield').value,
    //cid:$('controlmap').value } } );
    $.post('includes/dublinFunctions.php',{action:'addDublinCore',source:'DublinFunctions',dctype:$('#dcfield').val(),
        cid:$('#controlmap').val() },function(resp){$("#ajax").html(resp);}, 'html');
    return;
}
function removeDublinCore(varcid,vardctype) {
    var answer = confirm("<?php echo gettext("Remove DC field?  Records currently in KORA will require a recalculation of DC data.");?>");
    if(answer) {
        alert("<?php echo gettext("Run the Dublin Core Data Update function when you are done editing the controls associated with Dublin Core for this scheme");?>");
        //new Ajax.Updater('ajax', 'includes/dublinFunctions.php', { parameters: { action:'removeDublinCore',source:'DublinFunctions',cid:varcid,
        //dctype:vardctype }});
        $.post('includes/dublinFunctions.php',{ action:'removeDublinCore',source:'DublinFunctions',cid:varcid,
            dctype:vardctype },function(resp){$("#ajax").html(resp);}, 'html');
    }
    return;
}
//new Ajax.Updater('ajax', 'includes/dublinFunctions.php', { method:'post', parameters:{action:'loadDublinCore',source:'DublinFunctions' }});
$.post('includes/dublinFunctions.php',{action:'loadDublinCore',source:'DublinFunctions' },function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>
<?php
echo "<h2>".gettext('Dublin Core Settings for')." $_SESSION[currentSchemeName]</h2>";
//get all the controls that aren't in the dc field
?>
<div id="ajax"></div>

<?php
require_once('includes/footer.php');
?>
