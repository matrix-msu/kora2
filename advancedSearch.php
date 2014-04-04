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

/**
 * searchProject
 *
 * GET/POST arguments:
 *   schemeid: scheme id                (REQUIRED)
 *
 * if scheme not specified, will be redirected to projectIndex.php
 */

// Initial Version: Meghan McNeil, 2009

require_once('includes/utilities.php');
include_once('includes/ingestionClass.php');
requireLogin();

// a project an scheme ID must be passed; if it is not,
// something is wrong so fall back to the project index page
//if (!isset($_REQUEST['schemeid'])) header('Location: projectIndex.php');
include_once("includes/controlDataFunctions.php");
include_once("includes/koraSearch.php");
include_once('includes/header.php');

//javascript includes ?>
<link rel="stylesheet" href="javascripts/jPaginate/css/style.css" />
<script src="javascripts/jPaginate/jquery.paginate.js" type="text/javascript"></script>
<?php

function parseSearchString($queryString)
{
	$parts = array();
	
	while($queryString != "")
	{
		$pos = strpos($queryString, "\"");
		
		if(!is_integer($pos)) // just parse the whole thing
		{
			$moreparts = explode(" ", preg_replace("/%/", " ", trim($queryString)));
			$parts = array_merge($parts, $moreparts);
			$queryString = "";
		}
		else //split the string up into the quoted part, and everything else
		{
			// parse out the left string
			$left = substr($queryString, 0, $pos);
			$moreparts = explode(" ", ereg_replace("%", " ", trim($left)));
			$parts = array_merge($parts, $moreparts);
			
			$right = substr($queryString, $pos+1);
			
			// try to find the right ", and if not, use the whole right string
			$pos2 = strpos($right, "\"");
			if(!is_integer($pos2)) // use the whole thing
			{
				$moreparts = array(ereg_replace("%", " ", trim($right)));
				$parts = array_merge($parts, $moreparts);
				$queryString = "";
			}
			else
			{
				$left2 = substr($right, 0, $pos2);
				$moreparts = array(ereg_replace("%", " ", trim($left2)));
				$parts = array_merge($parts, $moreparts);
				
				$queryString = substr($right, $pos2+1);
			}
			
		}
	}
	
	return $parts;
}

?>

<h2>Advanced Search</h2>


<?php
//control name that do NOT have advanced search support
$unsupportedAdvSearch = array('File','Image','Record Associator','Geolocator');

//control names that CANNOT be sorted by
$unsupportedSort = array('Text (Multi-Input)', 'List (Multi-Select)', 'Multi-Date', 'Date (Multi-Input)');

//get controls that allow advanced search
$cTable = 'p'.$_SESSION['currentProject'].'Control';
$query = "SELECT $cTable.type AS class, $cTable.cid AS cid, $cTable.name AS name, control.file AS file,
		  control.name AS type FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) WHERE
		  $cTable.advSearchable=1 AND $cTable.schemeid=".$_SESSION['currentScheme'].' AND control.name
		  NOT IN ("'.implode('","',$unsupportedAdvSearch).'") ORDER BY collid,sequence';
$query = $db->query($query);
$controls = array();
while ($results = $query->fetch_assoc()) {
	$controls[] = $results;
}

//get controls that allow sorting
$sortControls = array();
$sortQuery = "SELECT $cTable.type AS class, $cTable.cid AS cid, $cTable.name AS name, control.file AS file,
		  	control.name AS type FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) WHERE
		  	$cTable.advSearchable=1 AND $cTable.schemeid=".$_SESSION['currentScheme'].' AND control.name
		  	NOT IN ("'.implode('","',$unsupportedSort).'") ORDER BY collid,sequence';
$sortQuery = $db->query($sortQuery);
while ($sortResult = $sortQuery->fetch_assoc())
{
	$sortControls[] = $sortResult;
}

//initalize variables
$koraClauses = array();
$boolean = 'AND';
$getQuery = array();
$controlIDs = array();
$likeClauses = array();
$keywordArray = array();//temporarily stores keyword=>cid combinations
$pages = false;

//if keyword search
if(isset($_GET['submit_keywords']) && !empty($_GET['submit_keywords']) && !empty($_GET['keywords'])) {

	$sortBy = false;
	if(isset($_GET['sortBy']))
	{
		$sortBy = mysqli_real_escape_string($db, $_GET['sortBy']);
		//if($sortBy == 'kid') $sortBy = false;
	}
	$sortOrder = 'ASC';//change this to false to get rid of the jquery style pagination
	if(isset($_GET['sortOrder']))
	{
		$sortOrder = mysqli_real_escape_string($db, $_GET['sortOrder']);
	}
	$keywords = parseSearchString($_GET['keywords']);
	
	// apply each keyword to each advance searchable control
	foreach($controls as $c) {
		
		$controlClause = array();
		foreach($keywords as $word) {
			$controlClause[] = "value LIKE '%$word%'";
		}
		
		//collect control IDs and LIKE clauses
		$controlIDs[] = $c['cid'];
		$likeClauses[] = implode(' '.$_GET['andOr'].' ',$controlClause);
		
		// add keyword search to kora clauses
		//$koraClauses[] = "cid=".$c['cid']." AND ".implode(' '.$_GET['andOr'].' ',$controlClause);
	
	}
	
	//remove duplicate LIKE clauses
	$likeClauses = array_unique($likeClauses);
	
	//form search string for control IDs and LIKE clauses
	$clauseString = "cid IN (";
	
	$cid = implode(', ',$controlIDs);
	$clauseString .= $cid;

	$clauseString .= ") AND ";

	$like = implode(', ',$likeClauses);
	$clauseString .= $like;
	
	//put string into an array to later get passed to internalSearchResults
	$koraClauses[] = $clauseString;

	$boolean = 'OR';
}

// if advanced search
else if(isset($_GET['submit_advanced']) && !empty($_GET['submit_advanced'])) {

	$sortBy = false;
	if(isset($_GET['sortBy']))
	{
		$sortBy = mysqli_real_escape_string($db, $_GET['sortBy']);
		//if($sortBy == 'kid') $sortBy = false;
	}
	$sortOrder = 'ASC';//change this to false to get rid of the jquery style pagination
	if(isset($_GET['sortOrder']))
	{
		$sortOrder = mysqli_real_escape_string($db, $_GET['sortOrder']);
	}
	
	foreach($controls as $c) {
		include_once(basePath.CONTROL_DIR.$c['file']);
		$controlClass = $c['class'];
		$control = new $controlClass($_SESSION['currentProject'],$c['cid']);
		// get correct op and formatted value to search
		$searchValue = $control->getSearchString($_GET);
		if($searchValue && is_array($searchValue)) {
			$values = array();
			foreach($searchValue as $sv) {
				if (is_array($sv[1])) {
					$sv[1] = "('".implode("','",$sv[1])."')";
				}
				//this is a special case to get rid of searching for ANY date instead
				// of leaving an empty date field out of the search completely
				$tempSV1 = $sv[1];
				$emptyDate = "'%<month>%</month><day>%</day><year>%</year><era>CE</era>%'";
				if($tempSV1!=$emptyDate)
				{
					$values[] = "value ".$sv[0]." ".$sv[1];
				}
			}

			// add search value to keywordArray
			if(!empty($values)) {
				$implodedValue = implode(' AND ',$values);
				//$koraClauses[] = "cid = ".$c['cid']." AND ".implode(' AND ',$values);
				$keywordArray[$implodedValue][] = $c['cid'];
			}
		}
	}

	//build koraclauses array with keywordArray data
	foreach($keywordArray as $key=>$cids)
	{
		$clauseString ="cid IN (";
		foreach($cids as $cidVal)
		{
			$clauseString .= $cidVal.",";
		}
		$clauseString = substr_replace($clauseString, "", -1);//get rid of last comma
		$clauseString .= ") AND ".$key;
		$koraClauses[] = $clauseString;
		

	}

}

//if there is something to search
if(!empty($koraClauses)) {
	
	if(isset($_GET['page'])) {
		$page = $_GET['page'];
	}
	//first time search
	else {
		$page = 1;
	}

	// get query string from the $_GET variable without page
	$query = array();
	foreach($_GET as $key=>$value) {
		if ($key != 'page' && !empty($_GET[$key])){
			// if $value is an array, make the array
			// query string friendly
			if(is_array($value)) {
				$val = array();
				foreach($value as $v) {
					$val[] = $key."[]=$v";
				}
				$query[] = implode('&',$val);
			} else {
				$query[] = "$key=$value";
			}
		}
	}
	//create Search link
	$searchLink = 'href="advancedSearch.php?'.implode('&',$query).'&page=%d"';

	//search and print results
	if($sortBy && $sortOrder)//for sorted results
	{
		//get an array with the kid's in sorted order
		$sortArray = sortedInternalSearchResults($_SESSION['currentProject'],$_SESSION['currentScheme'],
									$koraClauses,$boolean,$sortBy,$sortOrder);
		//find the number of pages we need
		$perPage = RESULTS_IN_PAGE;
		//admins can view all (limited to RESULTS_IN_VIEWALL_PAGE)
		if( isProjectAdmin() && isset($_GET['viewAll']) && $_GET['viewAll'] == true)
		{
			$perPage = RESULTS_IN_VIEWALL_PAGE;
		}
		$numResults = sizeof($sortArray);
		$pages = ceil($numResults/$perPage);
		
		//split the array into an array of imploded arrays
		$implodedSortArrays = array();
		$i=0;
		for($j=0;$j<$pages;$j++)
		{
			$tempArray = array();
			for($k=0;$k<$perPage;$k++)
			{
				if($i < $numResults)
				{
					$tempArray[] = "'".$sortArray[$i]."'";
					$i++;
				}
			}
			$implodedSortArrays[] = implode("," , $tempArray);
		}
		$IDs = '';
		foreach($implodedSortArrays as $index=>$id)
		{
			$IDs.=($id.",");
		}
		$results = array();
		$results[$_SESSION['currentScheme']] = $IDs;
		$_SESSION['results'] = $results;
	?>
<?php
		echo "Number of search results: ".$numResults."<br /><br />";
		
		//create 2 div id's.  one for the pagination javascript and one for the ajax call to display
		// also create a view all button for administrators
		
		if(isProjectAdmin() && $pages>1)
		{
			//build a new url with viewall
			$uri = $_SERVER['REQUEST_URI'];
			$uri .= '&viewAll=true';
			echo '<a href="'.$uri.'">'.gettext("View All").'</a><br /><br />';
		}
		?>
		<div id="pagination"></div>
		<?php
		$ePerms = (hasPermissions(EXPORT_SCHEME)||isSystemAdmin());
		if(!empty($IDs)&&$ePerms){
		?>
			<a href = "schemeExport.php?type=data">Export Search Results to XML</a></br>
		<?
		}
		?>
		<div id="resultDiv"></div>
		<?php
		
	}
	else//unsorted results
	{
		//*note*
		//if the jquery pagination is used, this function never gets called
		print internalSearchResults($_SESSION['currentProject'],$_SESSION['currentScheme'],
									$koraClauses,$boolean,$page,RESULTS_IN_PAGE,$searchLink);
	}
}
else {
	?>
	<form id="keywordSearch" name="keywordSearch" action="" method="get">
	<table class="table"><tr><td>
	Keywords: <input type="text" name="keywords" id="keywords" />
	</td></tr>
	<tr><td>
	Include objects that match
	<select name="andOr">
		<option value="AND">all</option>
		<option value="OR">any</option>
	</select>
	 keywords.
	</td></tr>
	<!-- allow the user to sort by a certain control. -->
	<tr><td colspan="2">Sort Results By:
	<select name="sortBy" id="sortByKeyword">
	<option value="id"> ---------- </option>
	<?php
	foreach($sortControls as $c) {
		if(!in_array($c['type'], $unsupportedSort))
		{
			//store the control ID
			echo "<option value='$c[cid]'>$c[name]</option>";
		}
	}?>
	</select>
	<!-- allow the user to sort in ascending or descending order. -->
	In Order:
	<select name="sortOrder" disabled>
	<option value="ASC"> ---------- </option>
	<?php
	echo "<option value='ASC'>Ascending</option>";
	echo "<option value='DESC'>Descending</option>";
	?>
	</select>
	</td></tr>
	<tr><td><input type="submit" value="Search" name="submit_keywords" /></td></tr>
	</table></form>
	
	
	
	
	<form id="advancedSearch" name="advancedSearch" action="" method="get">
	<table class="table">
	
	<?php
	foreach($controls as $c) {
		// This check should not be needed but used as an extra
		// security check to ensure that only supported fields
		// can be used in advanced search
		if (!in_array($c['type'],$unsupportedAdvSearch)) {
			include_once(basePath.CONTROL_DIR.$c['file']);
			$controlClass = $c['class'];
			$control = new $controlClass($_SESSION['currentProject'],$c['cid']);
			
			echo '<tr><td>'.$c['name'].': </td><td>';
			$control->display(true);
			echo '</td></tr>';
		}
	}
	?>
	<!-- allow the user to sort by a certain control. -->
	<tr><td colspan="2">Sort Results By:
	<select name="sortBy" id="sortBy">
	<option value="id"> ---------- </option>
	<?php
	foreach($sortControls as $c) {
		if(!in_array($c['type'], $unsupportedSort))
		{
			//store the control ID
			echo "<option value='$c[cid]'>$c[name]</option>";
		}
	}?>
	</select>
	<!-- allow the user to sort in ascending or descending order. -->
	In Order:
	<select name="sortOrder" disabled>
	<option value="ASC"> ---------- </option>
	<?php
	echo "<option value='ASC'>Ascending</option>";
	echo "<option value='DESC'>Descending</option>";
	?>
	</select>
	</td></tr>
	<tr><td colspan="2"><input type="submit" value="Search" name="submit_advanced" /></td></tr>
	</table></form>
<?php
}
?>

<script type="text/javascript">

<?php
if($pages){ ?>
//****jQuery pagination****
//  this jquery function creates page numbers at the top of the page
//  and then does an ajax call to display the results depending on
//  what number is clicked on
//convert php array to javascript array
var jsArray = new Array();
var numOfPages = <?php echo $pages; ?>;
<?php
foreach($implodedSortArrays as $key => $value)
{
	echo 'jsArray['.$key.'] = "'.$value.'";';
}
?>
$(document).ready(function(){
	$('#pagination').paginate({count : numOfPages,
								start : 1,
								display : 18,
								border : false,
								text_color : '#76690D',
								background_color : 'none',
								text_hover_color : '#000000',
								background_hover_color : 'none',
								images : false,
								mouse : 'press',
		onChange : function(page){
			var data = 'kids=' + jsArray[page-1];
			$.ajax({
				url: "displayRecords.php",
				type: "POST",
				data: data,
				cache: false,
				success: function (html) {
					$('#resultDiv').html(html);
				}
			});
		}
	});
});

//do an ajax call for the first page of the results seperately
$(document).ready(function(){
	var data = 'kids=' + jsArray[0];
	//$('#resultDiv').hide();
	$.ajax({
		url: "displayRecords.php",
		type: "POST",
		data: data,
		cache: false,
		success: function (html) {
			$('#resultDiv').html(html);
			//$('#resultDiv').toggle('slow');
		}
	});
});
<?php
}
else{
?>
//keyword search - order selection box limiter
var selectmenuKeyword = document.getElementById("sortByKeyword")
selectmenuKeyword.onchange = function()
{
  	var chosenOptionK = this.options[this.selectedIndex]
   	if (chosenOptionK.value != "nothing")
	{
		//only enable drop down menu for ascending/descending order if a control to sort by is selected
		document.keywordSearch.sortOrder.disabled = false;
   	}
};

//advanced search - order selection box limiter
var selectmenu = document.getElementById("sortBy")
selectmenu.onchange = function()
{
  	var chosenOption = this.options[this.selectedIndex]
   	if (chosenOption.value != "nothing")
	{
		//only enable drop down menu for ascending/descending order if a control to sort by is selected
		document.advancedSearch.sortOrder.disabled = false;
   	}
};
<?php }?>
</script>

<?php
include_once('includes/footer.php');
?>