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

require_once('utilities.php');
require_once('ingestionClass.php');
require_once('conf.php');

requireScheme();
requirePermissions(INGEST_RECORD, 'schemeLayout.php');

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
//This page will only work if the associated control name is EXACTLY the same as
//it's associated scheme.  ie) Scheme = Question, controlName = Question.

	requireProject();
    global $db;
    $schemeNameArray = array();  // An array of the scheme id's.  Id's  can be called by scheme name.
	$schemeList = $db->query('SELECT schemeid, schemeName, description, sequence FROM scheme WHERE pid='.escape($_SESSION['currentProject']).' ORDER BY sequence');
	if (!$schemeList)
	{
	    echo $db->error;
	}
	else if ($schemeList->num_rows == 0)
	{
	    echo gettext('This project currently has no schemes').'.';
	}
	else    // schemes exist
	{
		while($scheme = $schemeList->fetch_assoc())
	    {
	        $schemeNameArray[] =  $scheme['schemeName'];
	        $schemeIDArray[] = $scheme['schemeid'];  
	    }
	}

	$cname = $_SESSION['currentScheme'];
	if (isset($_REQUEST['control']))
	{
		$cName = $_REQUEST['control'];
	}
	
if (!isset($_POST['ingestionForm']))
{
	$presetQuery = $db->query('SELECT name, kid FROM recordPreset WHERE schemeid='.$schemeIDArray[array_search($cName, $schemeNameArray)].' ORDER BY name ASC');
	if ($presetQuery->num_rows > 0)
    {
		echo '<br /><strong>';
		printf(gettext('Controls marked with a %s are required'),'<font color="#FF0000">*</font>');
		echo '</strong><br /><br />';

		?>
		<form action="#" method="get" id="recordPresetForm" name="recordPresetForm">
		<div >
		
    	<div style="width:30%"><strong><?php echo gettext('Load Values from Preset');?>:</strong></div>
    	<div><select name="preset" id="preset">
		<?php 
   		 while ($presetRow = $presetQuery->fetch_assoc())
   		 {
     	   echo '<option value="'.$presetRow['kid'].'">'.htmlEscape($presetRow['name']).'&nbsp;'.'</option>';	
   		 }
		?>    
    	</select>
   		<input type="button" value="<?php echo gettext('Load');?>" onclick="usePreset();" /></div>
		</div>
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
	    $_SESSION['assoc'] = true;
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
        if ($rid && $rid['project'] == $_SESSION['currentProject'] && $rid['scheme'] == $schemeIDArray[array_search($cName, $schemeNameArray)])
        {
            $preset = $_REQUEST['preset'];
        }
    }
    $form = new IngestionForm($_SESSION['currentProject'], $schemeIDArray[array_search($cName, $schemeNameArray)], '', $preset, false);
    $form->display();	
}


?>
<div id="tb_closeBox"></div>