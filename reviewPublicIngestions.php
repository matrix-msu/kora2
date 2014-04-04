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
// Initial Version: Cassi Miller, 2010

require_once('includes/utilities.php');
requireLogin();
// verify MODERATOR permission
requirePermissions(MODERATOR, 'schemeLayout.php');
requireScheme();


/*** STEP 1: INPUT VALIDATION, AUTHORIZATION VERIFICATION ***/

include_once('includes/header.php');


//javascript includes?>
<script src="javascripts/jquery.blockUI.js" type="text/javascript"></script>
<?php

echo '<h2>'.gettext('Review Public Ingestions').'</h2>';

// sanitize pid and sid
$pid = (int)$_SESSION['currentProject'];
$sid = (int)$_SESSION['currentScheme'];
$pageNum = (isset($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;
if(isset($_REQUEST['viewall']) && $_REQUEST['viewall'] == 1) {
	$resultsPerPage = RESULTS_IN_VIEWALL_PAGE;
	$viewall = true;
}
else {
	$resultsPerPage = RESULTS_IN_PAGE;
	$viewall = false;
}

/*** STEP 2: RECORD COUNTING, INITIAL QUERY BUILDING ***/

// $objectQuery gets the list of IDs of records to pull data for.
$objectQuery = ' FROM p'.$pid.'PublicData WHERE schemeid='.$sid;
// the two lines below came from internalSearchResults() and I don't know if I need them for this page.
//$objectQuery .= ' AND id NOT IN (SELECT DISTINCT kid FROM recordPreset WHERE schemeid=$sid';
//$objectQuery .= ' AND (id NOT IN (SELECT DISTINCT kid FROM recordPreset)) ';

// This is necesary to sort by the base-10 version of Record ID to keep pages in the right order, etc.
$objectQuery .= " ORDER BY SUBSTRING_INDEX(id, '-', 2), CAST(CONV( SUBSTRING_INDEX(id, '-', -1), 16, 10) AS UNSIGNED)";

$pageNumQuery = $db->query('SELECT COUNT(DISTINCT id) AS numRecords '.$objectQuery);
$pageNumQuery = $pageNumQuery->fetch_assoc();

$totalRecords = $pageNumQuery['numRecords'];
$objectQuery = 'SELECT DISTINCT id '.$objectQuery;


/*** STEP 3: PAGE SELECTION, LIMIT QUERY BUILDING ***/    

// Verify the page number.  To do this, we must initially get a count of the number
// of distinct IDs and ensure the page number isn't too high or too low.
$maxPage = ceil($totalRecords / $resultsPerPage);
if ($maxPage < 1) $maxPage = 1;
    
$pageNum = (int) $pageNum;
$resultsPerPage = (int) $resultsPerPage;
    
if ($pageNum < 1) {
    $pageNum = 1;
}
else if ($pageNum > $maxPage) {
    $pageNum = $maxPage;
}
// if the results per page is less than 1, reset it to 10.  We don't fall back to
// RESULTS_IN_PAGE just in case that value itself is corrupted.
if ($resultsPerPage < 1) {
    $resultsPerPage = 10;    
}
    
$startRecord = ($pageNum - 1) * $resultsPerPage;
$resultsLeft = $resultsPerPage;

// Pull either the number of results left in the project or the number
// of results left to display, whichever is less 
$numToPull = ($resultsLeft < $totalRecords - $startRecord) ? $resultsLeft : ($totalRecords - $startRecord);

// The display query gets the records that are actually shown on current page of results
$displayQuery = $objectQuery.' LIMIT '.$startRecord.','.$numToPull;
    

/*** STEP 4: DISPLAYING THE RESULTS ***/

$searchLink = 'href="reviewPublicIngestions.php?';


$displayString = '';   // The string showing the results, which is printed at the end 
echo gettext('Number of records to be approved/denied').": <span id='numberOfRecords'>$totalRecords</span>";
//$displayString .= gettext('Number of records to be approved/denied').": $totalRecords";
$displayString .= '<br />';
  
$navigation  = '<strong>'.gettext('Jump to Page').':</strong> ';
$bc = breadCrumbs($maxPage, $pageNum, ADJACENT_PAGES_SHOWN, $searchLink.'page=%d"');
$navigation .= $bc;
// See if a "View All" link needs to be shown
if (!empty($bc) && $resultsPerPage < RESULTS_IN_VIEWALL_PAGE)
{
    $navigation .= '&nbsp;&nbsp;&nbsp;<a '.$searchLink.'viewall=1"'.'>'.gettext('View All').'</a>';
}
$navigation .= '<br /><br />'; 
    
if ($maxPage > 1)
{
   $displayString .= $navigation;
}    

// set the value for the page num to be sent to ingestApprovedData
if($viewall) $page = 'viewall=1';
else $page = "page=$pageNum";

$objectQuery = $db->query($displayQuery);
// Justification why $records is array("'-1'") instead of array(0):
// if the first record is merely -1, MySQL tries to cast all KIDs that follow it
// into DOUBLEs, throwing a pile of warning 1292s.  While non-blocking, there's no
// need to fill the error logs when we can just do it the right way in the first place.  
$records = array("'-1'");
while($rid = $objectQuery->fetch_assoc())
{
    $records[] = escape($rid['id']);
}    

// get the list of controls that should be shown in the results list
$controlQuery = 'SELECT cid, schemeid FROM p'.$pid.'Control WHERE schemeid='.$sid;
$controlQuery .= ' ORDER BY schemeid, cid';

$controlQuery = $db->query($controlQuery);
$controls = array(-1);
while ($cid = $controlQuery->fetch_assoc())
{
    $controls[] = $cid['cid'];
}
        
// get an array of the type of each control id so that we don't have to look it up each time
$cTable = 'p'.$pid.'Control';
$typeQuery  = "SELECT $cTable.cid AS cid, $cTable.name AS name, $cTable.type AS class, control.file AS file ";
$typeQuery .= "FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) ";
$typeQuery .= "WHERE $cTable.cid IN (".implode(',', $controls).')';
        
$typeQuery = $db->query($typeQuery);
$controlType = array();
while ($ct = $typeQuery->fetch_assoc())
{
    // populate the array and ensure that the control class's file is included.
	$controlType[$ct['cid']] = $ct;
	if (!empty($ct['file']))
	{
		require_once(basePath.CONTROL_DIR.$ct['file']);
	}
}
      
// Get the Data
$dataTable = 'p'.$pid.'PublicData';
$controlTable = 'p'.$pid.'Control';
$dataQuery  = "SELECT $dataTable.id AS id, $dataTable.cid AS cid, ".$pid." AS pid, $dataTable.schemeid AS schemeid, $dataTable.value AS value FROM $dataTable ";
$dataQuery .= "LEFT JOIN $controlTable USING (cid) ";
$dataQuery .= "LEFT JOIN collection ON ($controlTable.collid = collection.collid) ";
$dataQuery .= 'WHERE id IN ('.implode(',', $records).') ';
$dataQuery .= 'AND cid IN ('.implode(',', $controls).') ';
$dataQuery .= "ORDER BY SUBSTRING_INDEX($dataTable.id, '-', 2), CAST(CONV( SUBSTRING_INDEX($dataTable.id, '-', -1), 16, 10) AS UNSIGNED), collection.sequence, $controlTable.sequence";
$dataQuery = $db->query($dataQuery);
        
$currentrid   = '';
reset($records);      // reset the iterator to the beginning of the loop
                      // the initial '-1' will be caught at the same time as
                      // $currentrid = '', so that's not a problem.

echo '<div class="recordAll">';
if($dataQuery->num_rows > 0)
{
	$displayString .= '<a href="#" id="denyall">'.gettext('Deny All').'</a><br /><br />';
}

$idArray = array();//this array is to hold the id of all the records on this page
$dataID = '';
while($data = $dataQuery->fetch_assoc())
{
    $dataID = $data['id'];
    if(!in_array($dataID, $idArray))
    {
    	$idArray[] = $dataID;
    }
	if ($data['id'] != $currentrid)
    {
    	//$displayString .= "<tr><td class='kora_ccLeftCol'>BUTTS!!!!</td>";
    	//$displayString .= '<td>$data[id]: '.$data['id'].'           '.'$currentrid: '.$currentrid.'</td><tr/>';
    	if(!empty($currentrid)) {
    		// $currentrid is the one we are currently displaying.
    		// we want to use this rid for the approve/deny button.
    		$displayString .= '<tr><td colspan="2"><a href="#" id="approve'.$currentrid.'"';
    		$displayString .= ">".gettext('Approve')."</a> | ";
    		$displayString .= '<a href="#" id="deny'.$currentrid.'"';
    		$displayString .= ">".gettext('Deny')."</a></tr></table></div>";
    		
    	} 

        $currentrid = $data['id'];
        next($records);
                
        // strip the escaping slashes since the $records array contains escaped ids.
        $currRecords = str_replace("'", '', current($records));
                
        $displayString .= '<div class="record'.$data['id'].'"><table class="table"><tr><td colspan="2">';
        $displayString .= $data['id'].'</td></tr>';
    }
            
    $displayString .= '<tr><td class="kora_ccLeftCol">'.htmlEscape($controlType[$data['cid']]['name']).'</td><td>';
            
    // Instantiate an empty control of the necessary class and use it to convert the
    // value (potentially in XML) to a pretty display format$recordInfo['project'], $cInfo['cid'], $_REQUEST['rid']
    $theControl = new $controlType[$data['cid']]['class']($data['pid'], $data['cid'], $currentrid,'',true);

    // for file and image controls, we need to call showData() so it shows the link to the file.
    if( $controlType[$data['cid']]['class'] == "FileControl" || $controlType[$data['cid']]['class'] == "ImageControl") {
    	$displayString .= $theControl->showData();
    }
    else {
        $displayString .= $theControl->storedValueToDisplay($data['value'], $data['pid'], $data['cid']);
    }
    $displayString .= "</td></tr>\n";
    
}
if(!empty($currentrid)) {
    $displayString .= '<tr><td colspan="2"><a href="#" id="approve'.$dataID.'"';
    $displayString .= ">".gettext('Approve')."</a> | ";
    $displayString .= '<a href="#" id="deny'.$dataID.'"';
    $displayString .= ">".gettext('Deny')."</a></tr></table></div>";
}
//$displayString .= '</table>';

// Catch any trailing empty record ids
while(next($records))
{
    $currRecords = str_replace("'", '', current($records));
    $displayString .= '<table class="table"><tr><td colspan="2">';
   // $displayString .= '<a '.sprintf($kidLink,$currRecords).'>'.$currRecords.'</a></td></tr></table>'; 
   $displayString .= $currRecords.'</td></tr></table>';        
}
    
if ($maxPage > 1)
{
   $displayString .= $navigation;
}

echo $displayString;

echo "</div>";

include_once('includes/footer.php');
?>

<script type="text/javascript">
//<![CDATA[

//JQUERY to approve data
<?php 
foreach($idArray as $j)
{?>
	$(document).ready(function() {
		$('#approve<?php echo $j; ?>').click( function() {
			var data = 'rid=<?php echo $j; ?>&approved=1';
			$('div.record<?php echo $j; ?>').block({ message: '<h1><?php echo gettext('Approving Data');?></h1>'});
			$.ajax({
				url: "ingestApprovedData.php",
				type: "GET",
				data: data,
				cache: false,
				success: function (html) {
					decrementRemainingRecords();
					$('div.record<?php echo $j; ?>').unblock();
					$("div.record<?php echo $j; ?>").showHtml(html, 'slow');
				}
			});
			
			return false;
		});
	});
<?php }
?>

//JQUERY to deny data
<?php 
foreach($idArray as $j)
{?>
	$(document).ready(function() {
		$('#deny<?php echo $j; ?>').click( function() {
			if (confirm("<?php echo gettext('Are you sure you want to deny this record? The data will be deleted immediately and cannot be undone.');?>")){
				var data = 'rid=<?php echo $j; ?>&approved=0';
				$('div.record<?php echo $j; ?>').block({ message: '<h1><?php echo gettext('Deleting Data'); ?></h1>'});
				$.ajax({
					url: "ingestApprovedData.php",
					type: "GET",
					data: data,
					cache: false,
					success: function (html) {
						decrementRemainingRecords();
						$('div.record<?php echo $j; ?>').unblock();
						$("div.record<?php echo $j; ?>").showHtml(html, 'slow');
					}
				});
			}
			
			return false;
		});
	});
<?php }
?>


//JQUERY to deny ALL data
$(document).ready(function() {
	$('#denyall').click( function() {
		if (confirm("<?php echo gettext('Are you sure you want to deny all records? All data will be deleted immediately and cannot be undone.'); ?>")){
			var data = 'rid=all&approved=0';
			$.blockUI({ message: '<h1><?php echo gettext('Deleting All Records. Please Wait.'); ?></h1>' });
			$.ajax({
				url: "ingestApprovedData.php",
				type: "GET",
				data: data,
				cache: false,
				success: function (html) {
					setZeroRemainingRecords();
					$.unblockUI();
					$("div.recordAll").showHtml(html, 'slow');
				}
			});
		}
		
		return false;
	});
});



//function to decrement records count to zero after a user clicks Deny All
var num = new Number(<?php echo $totalRecords; ?>);
function setZeroRemainingRecords()
{
	num = 0;
	$('#numberOfRecords').html(num);
}

	

//function to decrement records count
function decrementRemainingRecords()
{
	num -= 1;
	$('#numberOfRecords').html(num);
}

//plugin used to scale the div elements when approving or denying
(function($)
{
   $.fn.showHtml = function(html, speed, callback)
   {
      return this.each(function()
      {
         // The element to be modified
         var el = $(this);

         // Preserve the original values of width and height - they'll need 
         // to be modified during the animation, but can be restored once
         // the animation has completed.
         var finish = {width: this.style.width, height: this.style.height};

         // The original width and height represented as pixel values.
         // These will only be the same as `finish` if this element had its
         // dimensions specified explicitly and in pixels. Of course, if that 
         // was done then this entire routine is pointless, as the dimensions 
         // won't change when the content is changed.
         var cur = {width: el.width()+'px', height: el.height()+'px'};

         // Modify the element's contents. Element will resize.
         el.html(html);

         // Capture the final dimensions of the element 
         // (with initial style settings still in effect)
         var next = {width: el.width()+'px', height: el.height()+'px'};

         el .css(cur) // restore initial dimensions
            .animate(next, speed, function()  // animate to final dimensions
            {
               el.css(finish); // restore initial style settings
               if ( $.isFunction(callback) ) callback();
            });
      });
   };


})(jQuery);


//]]>
</script>


