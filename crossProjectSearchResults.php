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
requireLogin();

if (!isset($_REQUEST['keywords']) || !isset($_REQUEST['projects']))
{
	header('Location: crossProjectSearch.php');
}

include_once('includes/header.php');


echo '<h2>'.gettext('Search Results').'</h2>';

if (empty($_REQUEST['keywords']))
{
	echo gettext('Please enter at least one keyword').'.';
}
else if (empty($_REQUEST['projects']))
{
	echo gettext('Please select at least one project to search').'.';
}
else
{
	if (!is_array($_REQUEST['projects'])) $_REQUEST['projects'] = array($_REQUEST['projects']);
	
	// Verify that the user has permission to search through all the requested projects
	$hasPermission = true;
	if (!isSystemAdmin())
	{
		foreach($_REQUEST['projects'] AS $project)
		{
			$permQuery = $db->query('SELECT pid FROM member WHERE uid='.$_SESSION['uid'].' AND pid='.escape($project));
			if ($permQuery->num_rows == 0)
			{
				$hasPermission = false;
			}
		}
	}
	
	if ($hasPermission)
	{
		if (isset($_REQUEST['boolean']) && in_array($_REQUEST['boolean'], array('AND', 'OR')))
		{
			$boolean = $_REQUEST['boolean'];
		}
		else
		{
			$boolean = 'AND';
		}

		// The search link involves ALL projects, so we need to build the search link
		// outside the foreach loop
		
        $searchLink = 'href="'.$_SERVER['PHP_SELF'].'?';
        foreach($_REQUEST['projects'] AS $project)
        {
        	$searchLink .= 'projects[]='.urlencode($project).'&amp;';		
        }
        $searchLink .= 'keywords='.urlencode($_REQUEST['keywords']);
        $searchLink .= '&amp;boolean='.urlencode($boolean);
        $searchLink .= '&amp;page=%d"';
		
    	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
    	
    	// See if "View All" has been clicked; if so up the number of results per page
		$resultsPerPage = (isProjectAdmin() && isset($_REQUEST['viewall']) && ((int)$_REQUEST['viewall'] == 1)) ? RESULTS_IN_VIEWALL_PAGE : RESULTS_IN_PAGE;
		
		echo internalSearchResults($_REQUEST['projects'], '', $_REQUEST['keywords'], $boolean, $page,  $resultsPerPage, $searchLink, true, true);
	}
	else
	{
		echo gettext('Sorry, but you do not have permission to search some of the requested projects').'.';
	}
}

include_once('includes/header.php');

include_once('includes/footer.php');
?>