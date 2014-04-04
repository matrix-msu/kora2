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

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');
require_once('includes/header.php');


echo '<h2>'.gettext('Manage Control Presets').'</h2>';
?>
<script type="text/javascript">
//<![CDATA[
function renameControlPreset(varPresetID) {
    var varNewName = $('#newName' + varPresetID).val();
	$.post(<?php echo "'".baseURI."includes/presetFunctions.php'"?>,{action:'updateControlPresetName',source:'PresetFunctions',preset:varPresetID,name:varNewName },function(resp){$("#ajax").html(resp);}, 'html');
}

function setGlobal(varPresetID) {
    var varGlobal = $('#global' + varPresetID)[0].checked ? 1 : 0;
	$.post(<?php echo "'".baseURI."includes/presetFunctions.php'" ;?>,{action:'updateControlPresetGlobal',source:'PresetFunctions',preset:varPresetID,global:varGlobal },function(resp){$("#ajax").html(resp);}, 'html');
}

function deletePreset(varPresetID) {
	$.post(<?php echo "'".baseURI."includes/presetFunctions.php'";?>,{action:'deleteControlPreset',source:'PresetFunctions',preset:varPresetID },function(resp){$("#ajax").html(resp);}, 'html');
}
     
$.post(<?php echo "'".baseURI."includes/presetFunctions.php'";?>,{action:'showControlPresetDialog',source:'PresetFunctions' },function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>

<div id="ajax"></div>
<?php 
require_once('includes/footer.php');
?>