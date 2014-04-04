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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Initial Version: Matt Geimer, 2008

require_once('includes/utilities.php');

requireProject();

if(isset($_REQUEST['schemeid']))
{
	// verify that the passed id is in fact a valid scheme
	$squery = 'SELECT schemeid, schemeName FROM scheme WHERE schemeid='.escape($_REQUEST['schemeid']).' AND pid='.escape($_SESSION['currentProject']).' LIMIT 1';
	$squery = $db->query($squery);
	if ($squery->num_rows > 0) {
		$squery = $squery->fetch_assoc();
		$_SESSION['currentScheme'] = $squery['schemeid'];
		$_SESSION['currentSchemeName'] = htmlEscape($squery['schemeName']);
		// clear out any unfinished ingestion
		unset($_SESSION['lastIngestion']);
	}
}

requireScheme();

// check once for ability to edit scheme layout to prevent repeated database calls
$ePerm = hasPermissions(EDIT_LAYOUT);

include_once('includes/header.php');
echo '<h2>'.gettext('Scheme Layout for').' '.$_SESSION['currentSchemeName'].'</h2>';

if (isSystemAdmin())
{
	// See if the dublin core information is up-to-date
	$dcQuery = $db->query('SELECT dublinCoreOutOfDate FROM scheme WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1');
	$dcQuery = $dcQuery->fetch_assoc();
	if ($dcQuery['dublinCoreOutOfDate'] > 0)
	{
		echo '<div class="error">'.gettext('Warning').': '.gettext('Dublin Core data for this scheme is out of date.  You should consider running the update script.').'</div><br />';
	}
}

if($ePerm)
{
	$controlQuery = $db->query('SELECT cid FROM p'.$_SESSION['currentProject'].'Control WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1');
	if ($controlQuery->num_rows > 0)
	{
        echo '<a href="manageDublinCore.php">'.gettext('Edit Dublin Core scheme field associations').'</a><br />';
	} 
}

?>
 
<script type="text/javascript">
//<![CDATA[
<?php  if ($ePerm) { ?>
function moveCollection(varcid, vardirection) {
	$.post('includes/schemeFunctions.php', {action:'moveCollection',source:'SchemeFunctions',cid:varcid,direction:vardirection }, function(resp){$("#ajax").html(resp);}, 'html');
    return;
}

function moveControl(varcid, vardirection) {
	$.post('includes/schemeFunctions.php', {action:'moveControl',source:'SchemeFunctions',cid:varcid,direction:vardirection}, function(resp){$("#ajax").html(resp);}, 'html');
    return;
}

function deleteCollection(varcid) {
    var answer = confirm("<?php echo gettext("Really delete collection?  Any data and Dublin Core associations to controls in this collection will be lost, including any data pending approval.");?>");
    if(answer) {
    	$.post('includes/schemeFunctions.php', {action:'deleteCollection',source:'SchemeFunctions',cid:varcid}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    return; 
}

function deleteControl(varcid) {
    var answer = confirm("<?php echo gettext("Really delete control?  Any data and Dublin Core associations will be lost, including any data pending approval.");?>");
    if(answer) {
    	$.post('includes/schemeFunctions.php', {action:'deleteControl',source:'SchemeFunctions',cid:varcid }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    return; 
}
function disableField(varfield)
{
  $("#"+varfield).disabled=true;
}
function enableField(varfield)
{
  $("#"+varfield).disabled=false;
}
<?php  } // endif $ePerm ?>


<?php  if (isProjectAdmin()) { ?>
function updateSchemePreset() {
	$.post('includes/schemeFunctions.php', {action:'updateSchemePreset',source:'SchemeFunctions',sid:<?php echo $_SESSION['currentScheme']?>,preset:$('#schemePreset').is(":checked") }, function(resp){$("#ajax").html(resp);}, 'html');
}

// check or uncheck an individual box
function updateCurrScheme(varfield,name,varcid,collid) {
	var varvalue = Number($("#" + name)[0].checked);
	
	$.post('includes/schemeFunctions.php', 
		{
			action:'updateCurrScheme',
			source:'SchemeFunctions',
			sid:<?php echo $_SESSION['currentScheme']?>,
			field:varfield,
			value:varvalue,
			cid:varcid,
			varcoll:collid
		},
		function(resp){
//			$("#ajax").html(resp);
			if( resp != 'success' ){
				$("#ajax").html(resp);
				$(".controldescription<?php echo $_SESSION['currentScheme']; ?>").toggle();
				// undo the check - this could be annoying lol.
//				$('#' + name)[0].checked = !varvalue;
			}
			else{
				if( name.substr(0,8) == 'required'){
					var publicEntry = $('#publicEntry'+name.substr(8))[0];
					publicEntry.disabled = varvalue;
					varvalue && (publicEntry.checked = 1);
				}
				checkSelectAlls();
			}
		}
	);
}

// check or uncheck all checkboxes in a column
function updateAllScheme(varvalue,varfield,collid) {
	
	$('body').css('cursor','wait');
	$.post('includes/schemeFunctions.php', 
		{
			action:'updateAllScheme',
			source:'SchemeFunctions',
			sid:<?php echo $_SESSION['currentScheme']?>,
			field:varfield,
			value:varvalue,
			varcoll:collid
		},
		function(resp){

			$('body').css('cursor','');
			if( resp == 'success' ){
				// loop through each check box in a column and check/uncheck it.
				$('.' + varfield + collid ).each(function(){
					// do not uncheck disabled check boxes.
					this.disabled || (this.checked = varvalue);
				});


				if(varfield == 'required'){
					// disable all public ingest check boxes
					$('.publicEntry'  + collid ).attr('disabled',varvalue);
					$('#publicEntry'  + collid ).attr('disabled',varvalue);

					// check all public ingest boxes if we checked the required box
					varvalue && $('.publicEntry'  + collid ).attr('checked','checked');
					varvalue && $('#publicEntry'  + collid ).attr('checked','checked');					
				}
			}
			else{
				$("#ajax").html(resp);
			}
		}
	);
}

function checkSelectAlls(){
	// loop through each column
	$('.selectall').each(function(){
		var allChecked = true;
		var allDisabled = true;

		// loop though each check box in a column
		$('.' +  this.id ).each(function(){
			this.checked || (allChecked = false);
			this.disabled || (allDisabled = false);
		});

		this.checked = allChecked;
		this.disabled = allDisabled;
	});
}




<?php  } // endif isProjectAdmin ?>
$.post('includes/schemeFunctions.php', {action:'loadSchemeLayout',source:'SchemeFunctions'}, function(resp){$("#ajax").html(resp);checkSelectAlls();}, 'html');
//]]>
</script>
<div id="ajax"></div>
<?php 
include_once('includes/footer.php');

?>
