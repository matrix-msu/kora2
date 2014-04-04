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
require_once('includes/ingestionClass.php');

requireScheme();
requirePermissions(INGEST_RECORD, 'schemeLayout.php');

include_once('includes/header.php');

echo '<h2>'.gettext('Ingestion').'</h2>';
?>
<script>

function usePreset()
{
    var answer = confirm(<?php echo "'".gettext("Loading a preset will erase any data you have currently entered into fields.  Continue?")."'";?>);
    if (answer)
    {
        document.recordPresetForm.submit();    
    }
}
//]]>
</script>
<?php 
// Do Presets exist, and is this an initial ingestion page?
// If so, show the preset selection dialog

if (!isset($_POST['ingestionForm']))
{
	
	$presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$_SESSION['currentScheme'].' ORDER BY name ASC');
    if ($presetQuery->num_rows > 0)
    {
    	
		echo '<br /><strong>';
		printf(gettext('Controls marked with a %s are required'),'<font color="#FF0000">*</font>');
		echo '</strong><br /><br />';

		?>
		<form action="<?php echo $_SERVER['PHP_SELF']?>" method="get" id="recordPresetForm" name="recordPresetForm">
		<table class="table">
		<tr>
    	<td style="width:30%"><strong><?php echo gettext('Load Values from Preset');?>:</strong></td>
    	<td><select name="preset" id="preset">
		<?php 
   		 while ($presetRow = $presetQuery->fetch_assoc())
   		 {
     	   echo '<option value="'.$presetRow['kid'].'">'.htmlEscape($presetRow['name']).'&nbsp;'.'</option>';	
   		 }
		?>    
    	</select>
   		<input type="button" value="<?php echo gettext('Load');?>" onclick="usePreset();" /></td>
		</tr>
		</table>
		</form>

		<?php 
	} // endif presetQuery->num_rows > 0
} // endif isset[ingestionForm]

if (isset($_POST['ingestionForm'])) {
	$schemeTest = $db->query('SELECT schemeid FROM scheme WHERE pid='.escape($_POST['projectid']).' AND schemeid='.escape($_POST['schemeid']).' LIMIT 1');
        if ($schemeTest->num_rows > 0)
        {
            if (empty($_POST['recordid']))
            {
                $rid = getNewRecordID($_POST['schemeid']);
                $newRecord = true;
            }
            else
            {
                $rid = $_POST['recordid'];
                $newRecord = false;
            }
	    $form = new IngestionForm($_POST['projectid'], $_POST['schemeid'], $rid, '', $newRecord);
	    $form->ingest(null, false);
	}
	else
        {
            echo gettext('Invalid Project/Scheme ID passed');
        }
}
else
{
    $preset = '';
    if (isset($_REQUEST['preset']))
    {
        $rid = parseRecordID($_REQUEST['preset']);

        // Make sure the Preset ID is valid and matches a record from the current scheme
        if ($rid && $rid['project'] == $_SESSION['currentProject'] && $rid['scheme'] == $_SESSION['currentScheme'])
        {
            $preset = $_REQUEST['preset'];
        }
    }
    $form = new IngestionForm($_SESSION['currentProject'], $_SESSION['currentScheme'], '', $preset, false);
    $form->display();	
}


include_once('includes/footer.php');

?>
