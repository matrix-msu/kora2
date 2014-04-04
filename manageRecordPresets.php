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

// Make sure the user has permissions to edit the layout/presets for the current scheme
requireScheme();
requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');

require_once('includes/header.php');

echo '<h2>'.gettext('Manage Record Presets').'</h2>';
echo '<p>'.gettext('Note: demoting an record will cause it to no longer be a preset, but the record will still remain and will now appear in search results, etc.  To truly delete the record, please select').' "'.gettext('Delete').'".</p><br />';
?>
<script type="text/javascript">
function renamePreset()
{
    var varkid = $('oldName').value;
    var varnewname = $('newName').value

    //new Ajax.Updater('ajax',
                     '<?//php echo baseURI.'includes/presetFunctions.php'?>//',
    //                 {   method:'post',
    //                     parameters:{action:'renameRecordPreset',source:'PresetFunctions',kid:varkid,name:varnewname },
    <!--                     onComplete:function(){new Ajax.Updater('oldName', '<?//php echo baseURI.'includes/presetFunctions.php'?>//', { method:'post', parameters:{action:'loadRecordPresetList',source:'PresetFunctions' }} )}
    //                 } );
    $.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'renameRecordPreset',source:'PresetFunctions',kid:varkid,name:varnewname},function(resp){$("#ajax").html(resp);}, 'html');
 	$.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'loadRecordPresetList',source:'PresetFunctions' },function(resp){$("#oldName").html(resp);}, 'html');
    }

function demotePreset(varkid)
{
    //new Ajax.Updater('ajax',
                     '<?//php echo baseURI.'includes/presetFunctions.php'?>//',
   //                  { method:'post',
   //                    parameters:{action:'demoteRecordPreset',source:'PresetFunctions',kid:varkid },
   <!--                    onComplete:function(){new Ajax.Updater('oldName', '<?//php echo baseURI.'includes/presetFunctions.php'?>//', { method:'post', parameters:{action:'loadRecordPresetList',source:'PresetFunctions' }} )}
   //                  } );
    $.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'demoteRecordPreset',source:'PresetFunctions',kid:varkid },function(resp){$("#ajax").html(resp);}, 'html');
    $.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'loadRecordPresetList',source:'PresetFunctions' },function(resp){$("#oldName").html(resp);}, 'html');
}
 

<!-- new Ajax.Updater('ajax', '<?//php echo baseURI.'includes/presetFunctions.php'?>//', { method:'post', parameters:{action:'showRecordPresetDialog',source:'PresetFunctions' }} );
$.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'showRecordPresetDialog',source:'PresetFunctions' },function(resp){$("#ajax").html(resp);}, 'html');
<!-- new Ajax.Updater('oldName', '<?//php echo baseURI.'includes/presetFunctions.php'?>//', { method:'post', parameters:{action:'loadRecordPresetList',source:'PresetFunctions' }} );
$.post(<?php echo "'".baseURI.'includes/presetFunctions.php'."'"?>,{action:'loadRecordPresetList',source:'PresetFunctions' },function(resp){$("#oldName").html(resp);}, 'html');

</script>

<div id="ajax"></div>
<br />
<strong><?php echo gettext('Rename a Preset');?></strong><br />
<table class="table">
<tr>
    <td><strong><?php echo gettext('Old Name');?></strong></td>
    <td><select name="oldName" id="oldName"></select></td>
</tr>
<tr>
    <td><strong><?php echo gettext('New Name');?></strong></td>
    <td><input type="text" name="newName" id="newName" /></td>
</tr>
<tr>
    <td>&nbsp;</td>
    <td><input type="button" value="<?php echo gettext('Rename');?>" onclick="renamePreset();" /></td>
</tr>
</table>
<?php 

require_once('includes/footer.php');
?>