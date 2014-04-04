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

require_once('conf.php');
require_once('utilities.php');

/**
 * loadSchemes() is a function used with AJAX calls to populate a table
 * for the scheme selection page
 *
 */
function loadSchemes() {
	requireProject();
	
    global $db;
    
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
        // get permissions now to avoid DB calls inside the loop
       $eperm = hasPermissions(EDIT_LAYOUT);
       $dperm = hasPermissions(DELETE_SCHEME);
       // Editing Scheme Layout is a DELETE_SCHEME permission because if you can delete
       // it you might as well be able to change it
		?>
	<table class="table">
		<tr>
		<?php  if ($eperm) { ?> <th></th> <?php  } ?>
		<th align="left" id="schemeNames"><?php echo gettext('Name');?></th>
		<th align="left" id="schemeDesc"><?php echo gettext('Description');?></th>
		<?php  if ($dperm) { ?> <th align="left"><?php echo gettext('Edit');?></th> <?php  } ?>
		<?php  if ($dperm) { ?><th align="left" id="schemeDelete"><?php echo gettext('Delete').'</th> '; } ?>
		</tr>
	<?php
	   while($scheme = $schemeList->fetch_assoc())
	    {
	        echo '<tr>';
	        if ($eperm) echo '<td><a class="up"  onclick="moveSchemeUp('.$scheme['schemeid'].')"> /\ </a><a class="down"  onclick="moveSchemeDown('.$scheme['schemeid'].')"> \/ </a></td>';
	        echo '<td><div style="text-align: right; overflow: hidden;">';
	        echo '<a href="schemeLayout.php?schemeid='.$scheme['schemeid'].'" class="button">'.htmlEscape($scheme['schemeName']).'</a></div></td>';
	        echo '<td><div style="word-wrap: break-word; overflow: auto;">'.htmlEscape($scheme['description']).'</div></td>';
	        if ($dperm) echo '<td><div style="text-align:center;"><a class="link" href="editScheme.php?sid='.$scheme['schemeid'].'">'.gettext('Edit');'</a></div></td>';
	        if ($dperm) echo '<td><div class="delete"><a class="link" onclick="deleteScheme('.$scheme['schemeid'].')">X</a></div></td>';
	        echo '</tr>'."\n";
	    }
	    echo '</table>';
	}
}

/**
 * Delete a Scheme
 *
 * @param integer $sid
 */
function deleteSchemeJS($sid)
{
    global $db;
    
    if(hasPermissions(DELETE_SCHEME))
    {
        deleteScheme($sid);
    }
}

/**
 * Move a Scheme Up or Down in the Layout
 *
 * @param integer $sid
 * @param string ('up' || 'down') $direction
 */
function moveScheme($sid, $direction)
{
    global $db;
    
    if(hasPermissions(EDIT_LAYOUT))
    {
        $result = $db->query('SELECT sequence FROM scheme WHERE schemeid='.escape($sid));
        if ($result->num_rows == 0) return;
        
        $result = $result->fetch_assoc();
        $r = $result['sequence'];
        
        if (($direction == 'up') && ($r > 1)) {
            $query = $db->query("UPDATE scheme SET sequence = '$r' WHERE sequence = '".($r-1)."' AND pid=".escape($_SESSION['currentProject']));
            $query = $db->query("UPDATE scheme SET sequence = '".($r-1)."' WHERE schemeid=".escape($sid));
        } else if ($direction == 'down') {
            $result = $db->query('SELECT MAX(sequence) as sequence FROM scheme WHERE pid='.escape($_SESSION['currentProject']));
	        $result = $result->fetch_assoc();
	        $m = $result['sequence'];
	        
	        if ($r < $m) {
	            $query = $db->query("UPDATE scheme SET sequence = '$r' WHERE sequence = '".($r+1)."' AND pid=".escape($_SESSION['currentProject']));
	            $query = $db->query("UPDATE scheme SET sequence = '".($r+1)."' WHERE schemeid=".escape($sid));
	        }
        }
    }
    
    loadSchemes();
}

/**
 * This is a helped function to show an empty collection.  It is used in
 * loadSchemeLayout()
 *
 * @
 */
function emptyCollection($collid, $collName, $collDesc)
{
	if ($collid < 1) return;
	$ePerms = hasPermissions(EDIT_LAYOUT);
?>
<br />
<table class="table_small">
    <tr>
<?php  if ($ePerms) { ?>
        <td valign="top">
            <a class="up" onclick="moveCollection(<?php echo $collid?>, 'up')"> /\ </a>
            <a class="down" onclick="moveCollection(<?php echo $collid?>, 'down')"> \/ </a>
        </td>
<?php  } ?>
        <td><table>
            <tr><th><a href="editCollection.php?cid=<?php echo $collid?>"><?php echo htmlEscape($collName)?></a></th></tr>
            <tr><td><?php echo htmlEscape($collDesc)?></td></tr>
            <tr><td></td></tr>
<?php  if ($ePerms) { ?>
            <tr>
                <td><a href="addControl.php?collid=<?php echo $collid?>"><?php echo gettext('Add a Control');?></a> -
                    <a class="link" onclick="deleteCollection(<?php echo $collid?>)"><?php echo gettext('Remove this Collection');?></a>
                </td>
            </tr>
<?php  } ?>
        </table></td>
    </tr>
</table>
<?php
}

/**
 * Displays the layout (set of collections and controls) of a scheme.
 * Includes functions to delete and move controls/collections if the user
 * has sufficient priveledges
 *
 * @
 */
function loadSchemeLayout()
{
	requireScheme();
    $ePerms = hasPermissions(EDIT_LAYOUT);
	global $db;

	// Show the "Available for Preset" checkbox
	if (isProjectAdmin())
	{
		$schemeInfo = $db->query('SELECT allowPreset FROM scheme WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1');
		if ($schemeInfo)
		{
			$schemeInfo = $schemeInfo->fetch_assoc();
	    	echo '<br /><input type="checkbox" id="schemePreset" name="schemePreset" ';
		    if ($schemeInfo['allowPreset'] > 0)
		    {
		    	echo ' checked ';
		    }
		    echo 'onclick="updateSchemePreset();" /> '.gettext('Allow this scheme\'s layout to be used as a preset?').'<br />';
		}
	}
	
	// get a list of the collections in the project

	$collectionQuery = $db->query('SELECT collid, name, description, sequence FROM collection WHERE schemeid='.escape($_SESSION['currentScheme']).' ORDER BY sequence');
	$collectionList = array();
	while($coll = $collectionQuery->fetch_assoc())
	{
		//Do not display comtrols of collection id 0 for editing
		if($coll['collid'] != 0)
	   		$collectionList[$coll['collid']] = array('name' => $coll['name'], 'description' => $coll['description']);
	}
	
	$cTable = 'p'.$_SESSION['currentProject'].'Control';
	
	// get an ordered list of the controls in the project
	$controlQuery =  "SELECT $cTable.name AS name, $cTable.cid AS cid, $cTable.collid AS collid, $cTable.sequence AS sequence, ";
	$controlQuery .= "$cTable.description AS description, control.name AS type, $cTable.publicEntry AS publicEntry, $cTable.required AS required, ";
	$controlQuery .= "$cTable.showInResults AS privateResults, $cTable.showInPublicResults as publicResults, $cTable.searchable AS searchable, $cTable.advSearchable AS advSearchable ";
	//$controlQuery .= ", $cTable.advSearchable AS advSearchable ";
	$controlQuery .= "FROM $cTable LEFT JOIN collection USING (collid) ";
	$controlQuery .= "LEFT JOIN control ON ($cTable.type = control.class) WHERE $cTable.schemeid=";
	$controlQuery .= escape($_SESSION['currentScheme'])." ORDER BY collection.sequence, $cTable.sequence";

	//   die($controlQuery);
	
	$controlQuery = $db->query($controlQuery);
	if ($controlQuery->num_rows > 0)
	{
		$currentCollection = -1;
		$colIterator['key'] = -1;

		while($c = $controlQuery->fetch_assoc())
		{
			//If collection id is 0, skip processing this collection
			if((int)$c['collid'] === 0){
				continue;
			}
			if ($c['collid'] != $currentCollection) {
		    	if ($currentCollection != -1) { // not the first time
		    		// Close the previous table
		    		?></table></td>
            </tr>
<?php  if($ePerms){ ?>
            <tr>
                <td><a href="addControl.php?collid=<?php echo $currentCollection?>"><?php echo gettext('Add a Control');?></a> -
                    <a class="link" onclick="deleteCollection(<?php echo $currentCollection?>)"><?php echo gettext('Remove this Control Collection');?></a>
                </td>
            </tr>
<?php  } ?>
            </table>
        </td>
    </tr>
</<table>
<?php 		}
                // process any empty collections
                $currentCollection = $c['collid'];
                $colIterator = each($collectionList);   // advance the iterator to
                                                      // clear out the old menu
                
                while (($colIterator !== FALSE) && ($colIterator['key'] != $currentCollection)) {
                	emptyCollection($colIterator['key'], $colIterator['value']['name'], $colIterator['value']['description']);
                    $colIterator = each($collectionList);
                }

		      // TODO: Have someone look at this stupid nested-table layout and clean it?
		        ?>
		      <br />
    
<table class="table_small">
    <tr>
<?php  if($ePerms) { ?>
        <td valign="top">
            <a class="up" onclick="moveCollection(<?php echo $currentCollection?>, 'up')"> /\ </a>
            <a class="down" onclick="moveCollection(<?php echo $currentCollection?>, 'down')"> \/ </a>
        </td>
<?php  } ?>
        <td><table class="table_small">
            <tr><th><a href="editCollection.php?cid=<?php echo $c['collid']?>"><?php echo htmlEscape($collectionList[$c['collid']]['name'])?></a></th></tr>
            <tr><td><?php echo htmlEscape($collectionList[$c['collid']]['description'])?></td></tr>
            <tr><td><table class="table_small">
                <tr class="scheme_index">
				<?php if ($ePerms) { ?><td></td> <?php  } ?>
                    <td><?php echo gettext('Name');?></td>
                    <td><?php echo gettext('Type');?></td>
                    <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('Required');?></td>
                    <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('Search');?></td>
           			<td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('Adv.').'<br/>'.gettext('Search');?></td>
                    <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('Show');?></td>
                    <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('Public').'<br/>'.gettext('Ingest')?></td>
                    <td class="controldescription<?php echo $c['collid'];?>"><?php echo gettext('Description');?></td>
				<?php if ($ePerms) { ?>
					<td><?php echo gettext('Options');?></td>
                    <td><?php echo gettext('Delete');?></td>
                <?php  } ?>
                </tr>
                <?php if($currentCollection>0&&$ePerms){?>
			<tr>
				<td></td>
				<td> <!--Select/Deselect All -->
					<a class="link" onclick="$('.searchoption<?php echo $c['collid'];?>').toggle();$('.controldescription<?php echo $c['collid'];?>').toggle();return false;" >Show/Hide Search Permissions</a></td>
				<td></td>
				<td class="searchoption searchoption<?php echo $c['collid'];?>">
<!--				<a class="link"  onclick="updateAllScheme(1,'publicEntry',<?//php echo $c['collid'];?>),updateAllScheme(1,'required',<?//php echo $c['collid'];?>)">All</a><br/>
				<a class="link"  onclick="updateAllScheme(0,'required',<?//php echo $c['collid'];?>)">None</a>-->
				<input type="checkbox"  id="required<?php echo $c['collid'];?>" class="selectall" onclick="updateAllScheme($(this).attr('checked')+'','required',<?php echo $c['collid'];?>)"/>
				</td>
				<td class="searchoption searchoption<?php echo $c['collid'];?>">
<!--				<a class="link" onclick="updateAllScheme(1,'searchable',<?//php echo $c['collid'];?>)">All</a><br/>
				<a class="link" onclick="updateAllScheme(0,'searchable',<?//php echo $c['collid'];?>)">None</a>-->
				<input type="checkbox"  id="searchable<?php echo $c['collid'];?>" class="selectall" onclick="updateAllScheme($(this).attr('checked')+'','searchable',<?php echo $c['collid'];?>)"/>
				</td>
				<td class="searchoption searchoption<?php echo $c['collid'];?>">
<!--				<a class="link" onclick="updateAllScheme(1,'advSearchable',<?//php echo $c['collid'];?>)">All</a><br/>
				<a class="link" onclick="updateAllScheme(0,'advSearchable',<?//php echo $c['collid'];?>)">None</a>-->
				<input type="checkbox"  id="advSearchable<?php echo $c['collid'];?>" class="selectall" onclick="updateAllScheme($(this).attr('checked')+'','advSearchable',<?php echo $c['collid'];?>)"/>
				</td>
				<td class="searchoption searchoption<?php echo $c['collid'];?>">
<!--				<a class="link" onclick="updateAllScheme(1,'showinresults',<?//php echo $c['collid'];?>)">All</a><br/>
				<a class="link" onclick="updateAllScheme(0,'showinresults',<?//php echo $c['collid'];?>)">None</a>-->
				<input type="checkbox"  id="showinresults<?php echo $c['collid'];?>" class="selectall" onclick="updateAllScheme($(this).attr('checked')+'','showinresults',<?php echo $c['collid'];?>)"/>
				</td>
				<td class="searchoption searchoption<?php echo $c['collid'];?>">
<!--				<a class="link" onclick="updateAllScheme(1,'publicEntry',<?//php echo $c['collid'];?>)">All</a><br/>
				<a class="link" onclick="updateAllScheme(0,'publicEntry',<?//php echo $c['collid'];?>)">None</a>-->
				<input type="checkbox"  id="publicEntry<?php echo $c['collid'];?>" class="selectall" onclick="updateAllScheme($(this).attr('checked')+'','publicEntry',<?php echo $c['collid'];?>)"/>
				</td>
				<td class="controldescription<?php echo $c['collid'];?>"></td>
				<td></td>
				<td></td>
			</tr>
	<?php }?>

                
		<?php   }  // then, for each control, show the row ?>
		<?php $unsupportedAdvSearch = array('File','Image','Record Associator','Geolocator');
			  $unsupportedPublicEntry = array('Record Associator','Geolocator');
		?>
		<tr class="scheme_data" id="dsc_1">
 		<?php  if ($ePerms) { ?>
                    <td valign="top">
                        <a class="up" onclick="moveControl(<?php echo $c['cid']?>, 'up')"> /\ </a>
                        <a class="down" onclick="moveControl(<?php echo $c['cid']?>, 'down')"> \/ </a>
                    </td>
 		<?php  } ?>
        
		<?php if ($ePerms) { ?>
	 		   <td><div style="width: 150px; text-align: right; overflow: hidden;">
				<a href="editControl.php?cid=<?php echo $c['cid']?>">
				<?php echo htmlEscape($c['name']);?>
				</a></div>
	        </td>
	        <td><?php echo gettext($c['type'])?></td>
   			<?php if(!in_array($c['type'],$unsupportedPublicEntry)) {?>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><input type="checkbox"  class="<?php echo "required".$c['collid'];?>" id="<?php echo "required".$c['cid'];?>" name="required" onclick="updateCurrScheme('required',<?php echo "'required".$c['cid']."'";?>,<?php echo $c['cid'];?>,<?php echo $c['collid'];?>);"  <?php if($c['required']) echo'checked="checked"';?> /></td>
        	<?php }?>
        	<?php if(in_array($c['type'],$unsupportedPublicEntry)) {?>
       		<td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('N/A');?></td>
        	<?php }?>
        	<td class="searchoption searchoption<?php echo $c['collid'];?>"><input type="checkbox"  class="<?php echo "searchable".$c['collid'];?>" id="<?php echo "searchable".$c['cid'];?>" name="searchable" onclick="updateCurrScheme('searchable', <?php echo "'searchable".$c['cid']."'";?>, <?php echo $c['cid'];?>,<?php echo $c['collid'];?>);" <?php if($c['searchable']) echo'checked="checked"';?> /></td>
        	<?php if(!in_array($c['type'],$unsupportedAdvSearch)) {?>
        		<td class="searchoption searchoption<?php echo $c['collid'];?>"><input type="checkbox" class="<?php echo "advSearchable".$c['collid'];?>" id="<?php echo "advSearchable".$c['cid'];?>" name="advSearchable" onClick="updateCurrScheme('advSearchable',<?php echo "'advSearchable".$c['cid']."'";?>, <?php echo $c['cid']?>,<?php echo $c['collid'];?>);" <?php if($c['advSearchable']) echo'checked="checked"';?> /></td>
        	<?php }?>
        	<?php if(in_array($c['type'],$unsupportedAdvSearch)) {?>
        		<td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('N/A');?></td>
        	<?php }?>
        	
        	<td class="searchoption searchoption<?php echo $c['collid'];?>"><input type="checkbox" class="<?php echo "showinresults".$c['collid'];?>" id="<?php echo "showinresults".$c['cid'];?>" name="showinresults" onClick="updateCurrScheme('showinresults', <?php echo "'showinresults".$c['cid']."'";?>,<?php echo $c['cid']?>,<?php echo $c['collid'];?>);" <?php if($c['privateResults']) echo'checked="checked"';?> /></td>
			<?php if(!in_array($c['type'],$unsupportedPublicEntry)) {?>
			<td class="searchoption searchoption<?php echo $c['collid'];?>"><input type="checkbox" class="<?php echo "publicEntry".$c['collid'];?>" id="<?php echo "publicEntry".$c['cid'];?>" name="publicEntry" onClick="updateCurrScheme('publicEntry',<?php echo "'publicEntry".$c['cid']."'";?>, <?php echo $c['cid']?>,<?php echo $c['collid'];?>)"  <?php if($c['required']){ echo 'checked="checked" disabled';} elseif($c['publicEntry']){ echo'checked="checked"';}?> /></td>
			<?php }?>
			<?php if(in_array($c['type'],$unsupportedPublicEntry)) {?>
       		<td class="searchoption searchoption<?php echo $c['collid'];?>"><?php echo gettext('N/A');?></td>
			<?php }?>
			<td class="controldescription<?php echo $c['collid'];?>"><?php echo htmlEscape($c['description'])?></td>
	        <td><a href="editOptions.php?cid=<?php echo $c['cid']?>"><?php echo gettext('Edit');?></a></td>	            
	        <td><div class="delete"><a class="link" onclick="deleteControl(<?php echo $c['cid']?>)">X</a></div></td> <?//php  } ?>
		<?php }?>
		<?php if (!$ePerms) {?>
	        <td><div style="width: 150px; text-align: right; overflow: hidden;">
				<?php echo htmlEscape($c['name']);?></div>
	        </td>
	        <td><?php echo gettext($c['type'])?></td>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php if ($c['required'] != 0) echo gettext('Yes'); else echo gettext('No'); ?></td>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php if ($c['searchable'] != 0) echo gettext('Yes'); else echo gettext('No'); ?></td>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php if ($c['advSearchable'] != 0) echo gettext('Yes'); else echo gettext('No'); ?></td>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php
	        	if ($c['privateResults'] && $c['publicResults']) echo gettext('Yes');
	            elseif ($c['privateResults']) echo /*'Internal '*/ gettext('Yes');
	            /*else if ($c['publicEntry']) echo 'Public'; */
	            else echo gettext('No'); ?></td>
	        <td class="searchoption searchoption<?php echo $c['collid'];?>"><?php if ($c['publicEntry'] != 0) echo gettext('Public'); else echo gettext('Private');?></td>
			<td class="controldescription<?php echo $c['collid'];?>"><?php echo htmlEscape($c['description'])?></td>
		<?php }?>
        </tr>
			<?php  }// end foreach control
?>
</table></td>
			
            </tr>
            <?php  if($currentCollection > 0){ //Only display add control/edit buttons if the collection is valid (not 0)
            echo '<tr><td>
            	  <a href="addControl.php?collid='.$currentCollection.'">'.gettext('Add a Control').'</a> -
                  <a class="link" onclick="deleteCollection('.$currentCollection.')">'.gettext('Remove this Control Collection').'</a>
            	  </td></tr>';}?>
            </table>
        </td>
    </tr>
</table>
<?php
    }
    
    // clear out any remaining collections
    
    $colIterator = each($collectionList);   // advance the iterator to
                                            // clear out the old menu
    
    while ($colIterator) {
        emptyCollection($colIterator['key'], $colIterator['value']['name'], $colIterator['value']['description']);
        $colIterator = each($collectionList);
    }
    
    echo '<a href="addCollection.php">'.gettext('Add a Control Collection').'</a>';
?>
<script type="text/javascript">
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
</script>
<script type="text/javascript" src='includes/thickbox/thickbox.js'></script>
<link rel="stylesheet" href='includes/thickbox/thickbox.css' type="text/css"></link>
<?php

}


/**
 * Deletes a Control
 *
 * @param integer $cid
 */
function deleteControlJS($cid)
{
	global $db;
	
	if (hasPermissions(EDIT_LAYOUT))
	{
        // MAKE SURE CONTROL IS IN CURRENT SCHEME
        $cTable = 'p'.$_SESSION['currentProject'].'Control';
		$check = $db->query("SELECT $cTable.sequence AS conSeq, collection.sequence AS colSeq, $cTable.collid AS collid FROM $cTable LEFT JOIN collection USING (collid, schemeid) WHERE $cTable.cid=".intval($cid)." AND $cTable.schemeid=".escape($_SESSION['currentScheme']).' LIMIT 1');
        if ($check->num_rows > 0)
        {
        	deleteControl(intval($cid), $_SESSION['currentScheme'], $_SESSION['currentProject']);
        }
	}
	loadSchemeLayout();
}

/**
 * Deletes a Control Collection
 *
 * @param integer $cid
 */
function deleteCollectionJS($cid)
{
	global $db;
	
    if (hasPermissions(EDIT_LAYOUT))
    {
        // MAKE SURE COLLECTION IS IN CURRENT SCHEME
    	$check = $db->query('SELECT sequence FROM collection WHERE collid='.intval($cid).' AND schemeid='.escape($_SESSION['currentScheme']).' LIMIT 1');
        if ($check->num_rows > 0)
        {
            deleteCollection(intval($cid));
        }
    }
    loadSchemeLayout();
}

function getProjectBySchemeId($schemeId) {
	global $db;
	
	$query = "SELECT pid FROM scheme WHERE schemeid=$schemeId";
	$query = $db->query($query);
	$data = $query->fetch_assoc();
	
	return $data['pid'];
}

/**
 * Moves a Control Up or Down (or between collections)
 *
 * @param integer $cid
 * @param string $direction { 'up' or 'down' }
 */
function moveControl($cid, $direction)
{
    global $db;
	
	if (hasPermissions(EDIT_LAYOUT))
	{
		$cTable = 'p'.$_SESSION['currentProject'].'Control';
		
        // MAKE SURE CONTROL IS IN CURRENT SCHEME
		$check = $db->query("SELECT $cTable.sequence AS conSeq, collection.sequence AS colSeq, $cTable.collid AS collid FROM $cTable LEFT JOIN collection USING (collid, schemeid) WHERE $cTable.cid=".escape($cid)." AND $cTable.schemeid=".escape($_SESSION['currentScheme']).' LIMIT 1');
        if ($check->num_rows > 0)
        {
        	$check = $check->fetch_assoc();
            $conSeq = $check['conSeq'];
            $colSeq = $check['colSeq'];
            $origCol = $check['collid'];
            
            if ($direction == 'up')
            {
            	// if the control sequence is > 1 we don't need to move between
            	// collections
            	if ($conSeq > 1) {
            		$db->query("UPDATE $cTable SET sequence='$conSeq' WHERE sequence='".($conSeq-1)."' AND collid=".escape($origCol));
            		$db->query("UPDATE $cTable SET sequence='".($conSeq-1)."' WHERE cid=".escape($cid));
            	}
            	// if the collection sequence is = 1 it's at the top and we do nothing
            	elseif ($colSeq > 1) {
            	   $check = $db->query('SELECT collid FROM collection WHERE schemeid='.escape($_SESSION['currentScheme']).' AND sequence='.escape($colSeq-1));
            	   $check = $check->fetch_assoc();
            	   $newCol = $check['collid'];
            	   $check = $db->query("SELECT MAX(sequence)+1 AS sequence FROM $cTable WHERE collid=".escape($newCol));
          	       $check = $check->fetch_assoc();
            	   $newSeq = $check['sequence'];
                   // this check is needed for moving into an empty group
            	   if ($newSeq < 1) $newSeq = 1;
            	   
            	   $db->query("UPDATE $cTable SET collid='$newCol', sequence='$newSeq' WHERE cid=".escape($cid));
            	   $db->query("UPDATE $cTable SET sequence=(sequence-1) WHERE collid='$origCol'");
            	}
            }
            elseif ($direction == 'down')
            {
            	// get the maximum control and collection sequence values
            	$check = $db->query("SELECT MAX(sequence) AS sequence FROM $cTable WHERE collid='$origCol'");
            	$check = $check->fetch_assoc();
            	$maxConSeq = $check['sequence'];
            	$check = $db->query("SELECT MAX(sequence) AS sequence FROM collection WHERE schemeid=".escape($_SESSION['currentScheme']));
                $check = $check->fetch_assoc();
                $maxColSeq = $check['sequence'];
                
                // if the control sequence is < conMax we don't need to move between
                // collections
                if ($conSeq < $maxConSeq) {
                    $db->query("UPDATE $cTable SET sequence='$conSeq' WHERE sequence='".($conSeq+1)."' AND collid=".escape($origCol));
                    $db->query("UPDATE $cTable SET sequence='".($conSeq+1)."' WHERE cid=".escape($cid));
                }
                // if the collection sequence is = colMax it's at the bottom and we
                // do nothing
                elseif ($colSeq < $maxColSeq) {
                   $check = $db->query('SELECT collid FROM collection WHERE schemeid='.escape($_SESSION['currentScheme']).' AND sequence='.escape($colSeq+1));
                   $check = $check->fetch_assoc();
                   $newCol = $check['collid'];

                   $db->query("UPDATE $cTable SET sequence=(sequence+1) WHERE collid='$newCol'");
                   $db->query("UPDATE $cTable SET collid='$newCol', sequence='1' WHERE cid=".escape($cid));
                }
            }
        }
	}
	loadSchemeLayout();
}

/**
 * Moves a Control Collection Up or Down
 *
 * @param integer $cid
 * @param string $direction { 'up' or 'down' }
 */
function moveCollection($cid, $direction)
{
	global $db;
	
    if (hasPermissions(EDIT_LAYOUT))
    {
        // MAKE SURE COLLECTION IS IN CURRENT SCHEME
        $check = $db->query('SELECT sequence FROM collection WHERE collid='.escape($cid).' AND schemeid='.escape($_SESSION['currentScheme']).' LIMIT 1');
        if ($check->num_rows > 0)
        {
        	$check = $check->fetch_assoc();
        	$seq = $check['sequence'];
        	
        	if (($direction == 'up') && ($seq > 1))
        	{
	            $query = $db->query("UPDATE collection SET sequence = '$seq' WHERE sequence = '".($seq-1)."' AND schemeid=".escape($_SESSION['currentScheme']));
	            $query = $db->query("UPDATE collection SET sequence = '".($seq-1)."' WHERE collid=".escape($cid));
        	} else if ($direction == 'down')
        	{
	            $result = $db->query('SELECT MAX(sequence) as sequence FROM collection WHERE schemeid='.escape($_SESSION['currentScheme']));
	            $result = $result->fetch_assoc();
	            $m = $result['sequence'];
	            
	            if ($seq < $m) {
	                $query = $db->query("UPDATE collection SET sequence = '$seq' WHERE sequence = '".($seq+1)."' AND schemeid=".escape($_SESSION['currentScheme']));
	                $query = $db->query("UPDATE collection SET sequence = '".($seq+1)."' WHERE collid=".escape($cid));
	            }
        	}
        }
    }
    loadSchemeLayout();
}

/**
 * Displays a set of javascript navigation bars 1 2 .... 3 4 5 .... 17 etc.
 * Shows first and last page and 5 pages in either direction around the current Page
 *
 * @param integer $numPages
 * @param integer $currPage
 */
function showNavigationLinks($numPages, $currPage)
{
    echo breadCrumbs($numPages, $currPage, ADJACENT_PAGES_SHOWN, 'onclick="setPage(%d);"');
}


function updateSchemePreset($sid, $preset)
{
	global $db;

	// Make sure the user is a project or system admin to edit this variable
	if (isProjectAdmin() && $sid == $_SESSION['currentScheme'])
	{
        // The Database needs 1 or 0, not "true" or "false")
	    $preset = ($preset == 'true') ? 1 : 0;
	
        $db->query('UPDATE scheme SET allowPreset='.$preset.' WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1');
	}

	loadSchemeLayout();
}

function updateCurrScheme($sid, $field, $value, $cid, $varcoll)
{
	global $db;
	// Make sure the user is a project or system admin to edit this variable
	if (isProjectAdmin() && $sid == $_SESSION['currentScheme'])
	{
		if($field=='required')
		{
			if($value==0)
			{
		         $result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value." WHERE cid=".$cid." AND collid=".$varcoll);
			}
			else
			{
				$result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value.",publicEntry=1 WHERE cid=".$cid." AND collid=".$varcoll);
				
			}
		}
		else
		{
         	$result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value." WHERE cid=".$cid." AND collid=".$varcoll);
         	if(!$result) echo $db->error;
		}
	}

//	echo 'success';
	loadSchemeLayout();
			?>
		    	<script type="text/javascript">
		    	$('.searchoption<?php echo $varcoll;?>').toggle();
		    	checkSelectAlls();
		    	</script>
		    	<?php
}

function updateAllScheme($sid, $field, $value, $varcoll)
{
	global $db;
	// Make sure the user is a project or system admin to edit this variable
	if (isProjectAdmin() && $sid == $_SESSION['currentScheme'])
	{
		if($value=='checked') $value = 1;
		else $value = 0;
		if($field=='publicEntry')
		{
			$result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value." WHERE required=0 AND collid=".$varcoll);
			if(!$result) echo $db->error;
		}
		elseif($field=='required')
		{
		    $result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET publicEntry=1 WHERE required=1 AND collid=".$varcoll);
    	   	if(!$result) echo $db->error;
		    $result1 = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value." WHERE collid=".$varcoll);
	    	if(!$result1) echo $db->error;
	    	
		}
		else
		{
 		 $result = $db->query("UPDATE p".$_SESSION['currentProject']."Control SET ".$field."=".$value." WHERE collid=".$varcoll);
         if(!$result) echo $db->error;
		}
				loadSchemeLayout();
		?>
		    	<script type="text/javascript">
		    	$('.searchoption<?php echo $varcoll;?>').toggle();
		    	checkSelectAlls();
		    	</script>
		    	<?php
	}
	
	//echo 'success';
	
}
/**
 *  The page is actually here, then the correct function is called determined from a post variable.
 */
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'SchemeFunctions'){
	$action = $_POST['action'];
    if ($action == 'loadSchemes') {
        loadSchemes();
    } elseif ($action == 'deleteScheme') {
        deleteSchemeJS($_POST['sid']);
        loadSchemes();
    } elseif ($action == 'moveScheme') {
        moveScheme($_POST['sid'], $_POST['direction']);
    } elseif ($action == 'loadSchemeLayout') {
        loadSchemeLayout();
    } elseif ($action == 'deleteControl') {
        deleteControlJS($_POST['cid']);
    } elseif ($action == 'deleteCollection') {
        deleteCollectionJS($_POST['cid']);
    } elseif ($action == 'moveControl') {
        moveControl($_POST['cid'], $_POST['direction']);
    } elseif ($action == 'moveCollection') {
        moveCollection($_POST['cid'], $_POST['direction']);
    } elseif ($action == 'showNavigationLinks') {
        showNavigationLinks($_POST['nPages'], $_POST['cPage']);
    } elseif($action == 'updateSchemePreset') {
    	updateSchemePreset($_POST['sid'], $_POST['preset']);
    } elseif($action == 'updateCurrScheme') {
    	updateCurrScheme($_POST['sid'], $_POST['field'], $_POST['value'], $_POST['cid'], $_POST['varcoll']);
    }elseif($action == 'updateAllScheme') {
    	updateAllScheme($_POST['sid'], $_POST['field'], $_POST['value'],$_POST['varcoll']);
    }
} //else echo gettext('action').": $_POST[action]    ".gettext('source').": $_POST[source]"
?>
