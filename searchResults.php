<?php
use KORA\Manager;
use KORA\Project;
use KORA\KoraSearch;
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
// Refactor: Joe Deming, Anthony D'Onofrio 2013
require_once('includes/includes.php');

Manager::Init();

Manager::RequireLogin();

Manager::PrintHeader();

echo '<h2>'.gettext('Search Results').'</h2>';


// SEVERAL SCENARIOS HERE
// SCENARIO 1) CROSS-PROJECT-SEARCH, SHOULD PASS IN Projects[] ARRAY, KEYWORD REQUIRED, IGNORE SID ARRAY
// SCENARIO 2) SINGLE-PROJECT SEARCH, MAY PASS IN Schemes[] ARRAY, KEYWORD REQUIRED, PID IS ASSUMED TO BE Manager::GetProject()->GetPID()
// SCENARIO 3) LIST ALL OBJECTS FROM SPECIFIC PROJECT, KEYWORD IS EMPTY BUT PASSED IN

$crossproject = false;
$searchok = true;

$pids = null;
if (Manager::CheckRequestsAreSet(['projects'])) 
{ $pids = (is_array($_REQUEST['projects'])) ? $_REQUEST['projects'] : array($_REQUEST['projects']); $crossproject = true; $_REQUEST['display']='false';}
elseif (Manager::GetProject()) 
{ $pids = array(Manager::GetProject()->GetPID()); }

$sids = null;
if (!$crossproject)
{
	if (Manager::CheckRequestsAreSet(['sids'])) 
	{ $sids = (is_array($_REQUEST['sids'])) ? $_REQUEST['sids'] : array($_REQUEST['sids']);}
	elseif (Manager::GetScheme()) 
	{ $sids = array(Manager::GetScheme()->GetSID());}
}
else { 
	$sids = '';
	$pids = explode(',',$pids[0]);
}

$keywords = Manager::CheckRequestsAreSet(['keywords']) ? $_REQUEST['keywords'] : '';
$boolean = (Manager::CheckRequestsAreSet(['boolean']) && in_array($_REQUEST['boolean'], array('AND', 'OR'))) ? $_REQUEST['boolean'] : 'AND';

// IF EITHER pids OR sids > SIZE 1, THEN WE ARE DOING A CROSS-PROJECT, OR CROSS-SCHEME SEARCH AND SHOULD HAVE KEYWORD
if (((sizeof($pids) > 1) || (is_array($sids) && sizeof($sids) > 1) || empty($sids)) && empty($keywords))
{ Manager::PrintErrDiv(gettext('Please enter at least one keyword').'.'); $searchok = false; }

if (sizeof($pids) <= 0)
{ Manager::PrintErrDiv(gettext('Please select at least one project to search').'.'); $searchok = false; }

// Verify that the user has permission to search through all the requested projects
if (!Manager::IsSystemAdmin())
{
	foreach($pids AS $pid)
	{
		$proj = new Project($pid);
		
		if($pid==Manager::GetProject()->GetPID() && Manager::IsProjectAdmin()){
			continue;
		}
		
		if ($proj->GetUserPermissions() < 256) 
		{
			Manager::PrintErrDiv(gettext('Sorry, but you do not have permission to search some of the requested projects').'.');
			$searchok = false;
			break;
		}
	}
}

if ($searchok)
{
	if(Manager::CheckRequestsAreSet(['advSearch'])){
		$keywords=explode('<ADVSEARCHKEY>',$keywords);
		array_pop($keywords);
	}
	$sortBy = 'id';
	$sortOrder = 'ASC';
	if(Manager::CheckRequestsAreSet(['sortBy'])){
		$sortBy = $_REQUEST['sortBy'];
	}
	if(Manager::CheckRequestsAreSet(['sortOrder'])){
		$sortOrder = $_REQUEST['sortOrder'];
	}
	$results = array();
	$resultsmerged = array();
	foreach($pids as $currpid)
	{
		$results[$currpid] = KoraSearch::SortedInternalSearchResults($currpid,$sids,$keywords,$boolean,$sortBy,$sortOrder,false);
		$resultsmerged = array_merge($resultsmerged, $results[$currpid]);
	}
	$_SESSION['results'] = $results;

	//Display "List All Scheme Records Link at bottom of page"
	echo '<br><br>';
	echo '<a class="ks_view_all_pages" onclick="viewAllPages()">View All</a>';
	echo '<br><br>';

	//paginate
	KoraSearch::PrintSearchResultsAJAXLoad($resultsmerged);
	
	//Display "List All Scheme Records Link at bottom of page"
	echo '<br><br>';
	echo '<a class="ks_view_all_pages"  onclick="viewAllPages()">View All</a>';
	echo '<br><br>';
}

Manager::PrintFooter();
?>