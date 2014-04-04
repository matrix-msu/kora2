<?php
session_start();
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
 *   pid: project id                (REQUIRED)
 *   sid: scheme  id                (optional)
 *   keywords: list of search terms (optional)
 *   boolean: 'AND' or 'OR'         (required if keywords present)
 *
 * if keyword not specified, pulls all objects from project/scheme
 * if scheme not specified, searches entire project
 */

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');
requireLogin();

// a project ID must be passed; if it is not, something is wrong so
// fall back to the project index page
if (!isset($_REQUEST['pid'])) header('Location: projectIndex.php');

if(isset($_REQUEST['sid'])){
	// make sure we have the right scheme selected
	$results = $db->query("SELECT schemeid,schemeName FROM scheme WHERE schemeid=".escape($_REQUEST['sid']));
	$result = $results->fetch_assoc();
	$_SESSION['currentScheme'] = $result['schemeid'];
	$_SESSION['currentSchemeName'] = $result['schemeName'];
}
else{
	unset($_SESSION['currentScheme'],$_SESSION['currentSchemeName']);
}

include_once('includes/header.php');


$_SESSION['currentProject'] = $_REQUEST['pid'];
$sid = !empty($_REQUEST['sid']) ? $_REQUEST['sid'] : '';
if(empty($sid))unset($_SESSION['currentScheme']);
else $_SESSION['currentScheme'] = $sid;
$keywords = isset($_REQUEST['keywords']) ? $_REQUEST['keywords'] : '';
$boolean = isset($_REQUEST['boolean']) ? $_REQUEST['boolean'] : 'AND';
$pageNum = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
$searchLink = 'href="'.$_SERVER['PHP_SELF'].'?pid='.urlencode($_REQUEST['pid']);
if (!empty($sid)) $searchLink .= '&amp;sid='.urlencode($sid);
if (!empty($boolean)) $searchLink .= '&amp;boolean='.urlencode($boolean);
$searchLink .= '&amp;keywords='.urlencode($keywords);
if (isProjectAdmin() && isset($_REQUEST['viewall']) && ((int)$_REQUEST['viewall'] == 1))
{
	$searchLink .= '&amp;viewall=1';
}
$searchLink .= '&amp;page=%d"';
$results = array();
if(empty($sid))
{
	$allSIDs = array();
	$sid = '';
	$schemeList = $db->query('SELECT schemeid, schemeName FROM scheme WHERE pid='.$_SESSION['currentProject']);
    while ($scheme = $schemeList->fetch_assoc()) {
		$allSIDs[] = $scheme['schemeid'];
    }
}
else $allSIDs[] = $sid;
foreach($allSIDs as $currSID)
{
    $result =  sortedInternalSearchResults($_REQUEST['pid'],$currSID,$keywords,$boolean,'id','ASC',false);
    $IDs = '';
	foreach($result as $index=>$id)
	{
		$IDs.=("'".$id."',");
	}
    if(!empty($result))  $results[$currSID] = $IDs;
}
$_SESSION['results'] = $results;
// See if "View All" has been clicked; if so up the number of results per page
$resultsPerPage = (isProjectAdmin() && isset($_REQUEST['viewall']) && ((int)$_REQUEST['viewall'] == 1)) ? RESULTS_IN_VIEWALL_PAGE : RESULTS_IN_PAGE;
$srchResults = internalSearchResults($_REQUEST['pid'], $sid, $keywords, $boolean, $pageNum, $resultsPerPage, $searchLink);
echo '<h2>'.gettext('Search Results').'</h2>';
$ePerms = (hasPermissions(EXPORT_SCHEME)||isSystemAdmin());
if(strlen($srchResults)!=39&&$ePerms){
?>
<a href="schemeExport.php?type=data">Export Search Results to XML</a></br></br>
<?php
}
echo internalSearchResults($_REQUEST['pid'], $sid, $keywords, $boolean, $pageNum, $resultsPerPage, $searchLink);
include_once('includes/footer.php');

?>
