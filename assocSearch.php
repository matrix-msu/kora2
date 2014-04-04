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
require_once('includes/conf.php');
require_once('includes/utilities.php');

requireScheme();

require_once('includes/header.php');

if (!isset($_REQUEST['cid']))
{
    echo gettext('No cid present').'.'; require_once('includes/footer.php'); die();
}
if (!isset($_REQUEST['keywords']))
{
    echo gettext('No search results').'.'; require_once('includes/footer.php'); die();
}

echo '<h2>'.gettext('Search Results').'</h2>';

// get the list of schemes and projects to search
$targetProjects = array();
$targetSchemes = array();

$query = 'SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($_REQUEST['cid']).' LIMIT 1';
$results = $db->query($query);
if ($results->num_rows != 0)
{
	// Get the list of acceptable schemes to search from the current scheme
	$schemeQuery = $db->query('SELECT crossProjectAllowed FROM scheme WHERE schemeid='.$_SESSION['currentScheme'].' LIMIT 1');
	$schemeQuery = $schemeQuery->fetch_assoc();
	$schemeQueryXML = simplexml_load_string($schemeQuery['crossProjectAllowed']);
	$schemeAllowedSchemes = array();
	if (isset($schemeQueryXML->from->entry))
	{
		foreach($schemeQueryXML->from->entry as $entry)
		{
			$schemeAllowedSchemes[] = (int)$entry->scheme;
		}
	}

	// Get the list of acceptable schemes to search from the current control
	$array = $results->fetch_assoc();
	if ($array['options'] != 'none')
	{
	   $xml = simplexml_load_string($array['options']);
	}
	else
	{
		$xml = simplexml_load_string('<options />');
	}
	
	if (!empty($xml->scheme))
	{
		$schemes = array();
		foreach($xml->scheme as $scheme)
		{
			// Make sure the scheme is in the list pulled from the scheme,
			// note just from the current control.  This is necessary due to
			// old control options being persisted when schemes are created
			// from templates
			if (in_array( (int)$scheme, $schemeAllowedSchemes ))
			{
				$schemes[] = (string)$scheme;
			}
		}
		
		if (!empty($schemes))
		{
		    $query = 'SELECT pid, schemeid FROM scheme WHERE schemeid IN ('.implode(',',$schemes).')';
		    $results = $db->query($query);
		    while($record = $results->fetch_assoc())
		    {
		    	$targetProjects[] = $record['pid'];
		    	$targetSchemes[] = $record['schemeid'];
		    }
		}
	}
}

$targetProjects = array_unique($targetProjects);
$targetSchemes = array_unique($targetSchemes);

if (!empty($targetProjects) && !empty($targetSchemes))
{
	?>
	<script type="text/javascript">
	// <![CDATA[
	    function selectKID(kid) {
	        window.opener.document.ingestionForm.Input<?php echo $_GET['cid']; ?>.value = kid;
			window.opener.addAssoc<?php echo $_GET['cid']; ?>();
	        window.close();
	    }
	 // ]]>
	</script>
	<?php 

	// cid, keywords
	$searchLink = 'href="assocSearch.php?cid='.urlencode($_REQUEST['cid']).'&keywords='.urlencode($_REQUEST['keywords']).'&page=%d"';
    $page = (isset($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;

    echo internalSearchResults($targetProjects, $targetSchemes, $_REQUEST['keywords'], 'AND', $page, RESULTS_IN_PAGE, $searchLink, true, true, 'onclick="selectKID(\'%s\')"', true);
}
else
{
	echo gettext('You do not have access to associate to any schemes');
}

echo '<br />';

require_once('includes/footer.php');

?>
