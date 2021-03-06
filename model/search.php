<?php
namespace KORA;

use KORA\Manager;
use KORA\Record;
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

// Initial Version: Joseph M. Deming, 2013

require_once(__DIR__.'/../includes/includes.php');

Manager::AddJS('javascripts/search.js', Manager::JS_CLASS);

/**
 * @class KoraSearch object
 *
 * This is a static class just to give these previously global functions
 * some 'namespace' of their own and encapsulate these long segments of
 * code for related procedures into a single file/space
 */
class KoraSearch {
	
	/**
	 * Very simple keyword search across searchable fields for use in internal listings
	 *
	 * @param int $pid
	 * @param int $sid (if left blank, searches all schemes in a project)
	 * @param string $keywords
	 * @param string $boolean ('AND' or 'OR')
	 * @param int $pageNum (>=1)
	 * @param string $searchLink (URL missing only the page # for breadcrumb navigation,
	 *                            in printf format with a %d)
	 * @param bool showTopBreadCrumbs, showBottomBreadCrumbs - just what they suggest
	 * @param string $kidLink (URL to display an object based on its KID, in printf
	 *                         format with a %s)
	 * @param bool isAssociatorSearch - if this is set, the results will be formatted
	 *             differently; there will be an extra, hard-coded "view this object"
	 *             link (on the assumption that the kidLink goes somewhere else), as
	 *             well as an "Associate this Record" link that, like the KID link,
	 *             points to wherever kidLink does.
	 * @return string $htmlOutput
	 */
	public static function internalSearchResults($pid,
								   $sid='',
								   $keywords='',
								   $boolean='AND',
								   $pageNum=1,
								   $resultsPerPage = RESULTS_IN_PAGE,
								   $searchLink='href="searchProjectResults.php?page=%d"',
								   $showTopBreadCrumbs = true,
								   $showBottomBreadCrumbs = true,
								   $kidLink='href="viewObject.php?rid=%s"',
								   $isAssociatorSearch = false)
	{
		global $db;
		
		/*** STEP 1: INPUT VALIDATION, AUTHORIZATION VERIFICATION ***/
		
		// Sanitize the PIDs to integer form and make sure they're in array form
		if (!is_array($pid)) $pid = array($pid);
		foreach($pid as &$p)
		{
			$p = (int) $p;
		}
		unset($p);     // Because this is a variable by reference, it has to be unset
		
		// if a scheme id(s) is/are provided, sanitize them
		if (!empty($sid))
		{
			if (!is_array($sid))
			{
				$sid = array($sid);
			}
			foreach($sid as &$s)
			{
				$s = (int) $s;
			}
			unset($s);     // Because this is a variable by reference, it has to be unset
			
			$sid = array_unique($sid);
		}
		

		
		// Check for access to the requested projects.  This has the added benefit of
		// verifying that $pid is well-formed.
		if (Manager::IsSystemAdmin())
		{
			// If the user is a system admin, we don't care if they are a member, but
			// we do care that the PIDs are valid
			foreach($pid as $p)
			{
				$accessQuery = $db->query('SELECT pid FROM project WHERE pid='.$p.' LIMIT 1');
				if ($accessQuery->num_rows != 1)
				{
					return gettext('Invalid').' pid: '.$p.'<br />';
				}
			}
		}
		else
		{
			foreach($pid as $p)
			{
				$accessQuery = $db->query('SELECT pid FROM member WHERE uid='.$_SESSION['uid'].' AND pid='.$p.' LIMIT 1');
				if ($accessQuery->num_rows != 1 && !$isAssociatorSearch)
				{
					return gettext('You need permission to search this project').'.<br />';
				}
			}
		}
		
		// Since the list of pids is now shown to be valid, sort them numerically.
		// This guarantees consistent ordering of results for pagination purposes.
		sort($pid);
		
		// Make sure the boolean is properly formatted
		if (!in_array($boolean, array('AND', 'OR')))
		{
			return gettext('Invalid Boolean Specified').'.<br />';
		}
		
		/*** STEP 2: RECORD COUNTING, INITIAL QUERY BUILDING ***/
		
		// A count of all the records returned by all the projects
		$totalRecords = 0;
		// A stored list of all the queries to get objects from the various projects
		$objectQueries = array();
		
		// Loop through the projects; build the object queries for each
		foreach($pid as $project)
		{
			// $objectQuery gets the list of IDs of records to pull data for.
			// The 1=1 is needed so that if no schemeid or keywords are sent
			// the query is still valid
			$objectQuery = ' FROM p'.$project.'Data WHERE 1=1';
			// $searchableQuery gets the list of controls that have the searchable flag set
			// i.e. that we should search across
			$searchableQuery = 'SELECT cid FROM p'.$project.'Control WHERE searchable=1';
			
			// if a specific scheme was specified, restrict our searches to that scheme
			if (!empty($sid))
			{
				$objectQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
				$objectQuery .= ' AND id NOT IN (SELECT DISTINCT kid FROM recordPreset WHERE schemeid IN ('.implode(',', $sid).')) ';
				$searchableQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
			}
			else
			{
				$objectQuery .= ' AND (id NOT IN (SELECT DISTINCT kid FROM recordPreset)) ';
			}
			
			// if keywords were provided, restrict the object query to records matching the keywords
			if (!empty($keywords))
			{
				// this provides support for advanced search which are passed in as
				// array("(cid=# BOOL value OP 'someValue')","(cid=# BOOL value OP 'someValue')",...,"(cid=# BOOL value OP 'someValue')")
				// that way the values are only searched within the specified control
				if(is_array($keywords)) {
					$where = "(SELECT DISTINCT id FROM p".$project."Data WHERE ".array_shift($keywords).")";
					
					while($clause = array_shift($keywords)) {
						//$clause = htmlentities($clause);
						$where = "(SELECT DISTINCT id FROM p".$project."Data WHERE $clause $boolean id IN $where)";
					}

					$objectQuery .= " AND id IN $where ";
				}
				else {
					$searchableQuery = $db->query($searchableQuery);
					if ($searchableQuery->num_rows == 0)
					{
						// Question: Is this an error that should be returned?
						$nameQuery = $db->query('SELECT name FROM project WHERE pid='.$project.' LIMIT 1');
						$nameQuery = $nameQuery->fetch_assoc();
						echo '<div class="error">'.gettext('Warning').': '.gettext('No searchable fields in project').': '.htmlEscape($nameQuery['name']).'</div>';
		
						// Go to the next project
						continue;
					}
					$searchable = array();
					while ($s = $searchableQuery->fetch_assoc())
					{
						$searchable[] = $s['cid'];
					}
					
					// ensure that only searchable-marked fields are searched
					$objectQuery .= ' AND cid IN ('.implode(',', $searchable).')';
					
					// handle the keywords
					$objectQuery .= ' AND (';
					$i = 1;  // used to make sure the boolean isn't prepended on the first argument
					//$keywordList = ;
					foreach(explode(' ', $keywords) as $keyword)
					{
						if ($i != 1) $objectQuery .= " $boolean ";
						$objectQuery .= ' (value LIKE '.escape('%'.$keyword.'%').') ';
						$i++;
					}
					$objectQuery .= ')';
			
				}
			}
			// This is necesary to sort by the base-10 version of Record ID to keep pages in the right order, etc.
			$objectQuery .= "ORDER BY SUBSTRING_INDEX(id, '-', 2), CAST(CONV( SUBSTRING_INDEX(id, '-', -1), 16, 10) AS UNSIGNED)";

			$pageNumQuery = $db->query('SELECT COUNT(DISTINCT id) AS numRecords '.$objectQuery);
			$pageNumQuery = $pageNumQuery->fetch_assoc();
			
			$totalRecords += $pageNumQuery['numRecords'];
			
			// store the Object Query in the List
			$objectQueries[] = array('pid' => $project,
									 'query' => 'SELECT DISTINCT id '.$objectQuery,
									 'count' => $pageNumQuery['numRecords']);
		}
		 
		/*** STEP 3: PAGE SELECTION, LIMIT QUERY BUILDING ***/
		
		// Verify the page number.  To do this, we must initially get a count of the number
		// of distinct IDs and ensure the page number isn't too high or too low.
		$maxPage = ceil($totalRecords / $resultsPerPage);
		if ($maxPage < 1) $maxPage = 1;
		
		$pageNum = (int) $pageNum;
		$resultsPerPage = (int) $resultsPerPage;
		
		if ($pageNum < 1)
		{
			$pageNum = 1;
		}
		else if ($pageNum > $maxPage)
		{
			$pageNum = $maxPage;
		}
		// if the results per page is less than 1, reset it to 10.  We don't fall back to
		// RESULTS_IN_PAGE just in case that value itself is corrupted.
		if ($resultsPerPage < 1)
		{
			$resultsPerPage = 10;
		}
		
		// The display queries will be the queries that are actually shown to
		// display a single page of results.
		$displayQueries = array();
		
		$startRecord = ($pageNum - 1) * $resultsPerPage;
		$resultsLeft = $resultsPerPage;
		// Iterate through all the Object Queries in order until we either run out
		// of queries or fulfill the number of results in a page
		foreach($objectQueries as $objQ)
		{
			// First, see if we're done
			if ($resultsLeft == 0)
			{
				break;
			}
			
			// Next, see if we're able to skip past this set entirely
			if ($startRecord > $objQ['count'])
			{
				$startRecord -= $objQ['count'];
			}
			else
			{
				// Pull either the number of results left in the project or the number
				// of results left to display, whichever is less
				$numToPull = ($resultsLeft < ($objQ['count'] - $startRecord)) ?
					$resultsLeft : ($objQ['count'] - $startRecord);
				
				$displayQueries[] = array('pid' => $objQ['pid'],
										  'query' => $objQ['query'].' LIMIT '.$startRecord.','.$numToPull);
				
				// Decrement the remaining counter
				$resultsLeft -= $numToPull;
				// Start from the beginning of any projects after this
				$startRecord = 0;
			}
		}
		

		

	}


	/**
	 * Very simple keyword search across searchable fields for use in internal listings
	 * This version is for sorting the results by a control
	 *
	 * @param int $pid
	 * @param int $sid (if left blank, searches all schemes in a project)
	 * @param string $keywords
	 * @param string $boolean ('AND' or 'OR')
	 * @param string $sortBy - the control name to sort by
	 * @param string $order - order to sort by, either ASC or DESC
	 * @param bool isAssociatorSearch - if this is set, the results will be formatted
	 *             differently; there will be an extra, hard-coded "view this object"
	 *             link (on the assumption that the kidLink goes somewhere else), as
	 *             well as an "Associate this Record" link that, like the KID link,
	 *             points to wherever kidLink does. Does not work with this function
	 * @return array of sorted record id's
	 */
	public static function sortedInternalSearchResults($pid,
										$sid='',
										$keywords='',
										$boolean='AND',
										$sortBy = false,
										$order = false,
										$isAssociatorSearch = false)
	{
		global $db;
		
		/*** STEP 1: INPUT VALIDATION, AUTHORIZATION VERIFICATION ***/
		
		// Sanitize the PIDs to integer form and make sure they're in array form
		if (!is_array($pid)) $pid = array($pid);
		foreach($pid as &$p)
		{
			$p = (int) $p;
		}
		unset($p);     // Because this is a variable by reference, it has to be unset
		
		// if a scheme id(s) is/are provided, sanitize them
		if (!empty($sid))
		{
			if (!is_array($sid))
			{
				$sid = array($sid);
			}
			foreach($sid as &$s)
			{
				$s = (int) $s;
			}
			unset($s);     // Because this is a variable by reference, it has to be unset
			
			$sid = array_unique($sid);
		}
		

		
		// Check for access to the requested projects.  This has the added benefit of
		// verifying that $pid is well-formed.
		if (Manager::IsSystemAdmin())
		{
			// If the user is a system admin, we don't care if they are a member, but
			// we do care that the PIDs are valid
			foreach($pid as $p)
			{
				$accessQuery = $db->query('SELECT pid FROM project WHERE pid='.$p.' LIMIT 1');
				if ($accessQuery->num_rows != 1)
				{
					return gettext('Invalid').' pid: '.$p.'<br />';
				}
			}
		}
		else
		{
			foreach($pid as $p)
			{
				$accessQuery = $db->query('SELECT pid FROM member WHERE uid='.$_SESSION['uid'].' AND pid='.$p.' LIMIT 1');
				if ($accessQuery->num_rows != 1 && !$isAssociatorSearch)
				{
					return gettext('You need permission to search this project').'.<br />';
				}
			}
		}
		
		// Since the list of pids is now shown to be valid, sort them numerically.
		// This guarantees consistent ordering of results for pagination purposes.
		sort($pid);
		
		// Make sure the boolean is properly formatted
		if (!in_array($boolean, array('AND', 'OR')))
		{
			return gettext('Invalid Boolean Specified').'.<br />';
		}
		
		/*** STEP 2: RECORD COUNTING, INITIAL QUERY BUILDING ***/
		
		// A count of all the records returned by all the projects
		$totalRecords = 0;
		// A stored list of all the queries to get objects from the various projects
		$objectQueries = array();
		//Maintaining the keyword structure so manipulation will not effect it 
		$keywords_orig = $keywords;
		//make a list of all the cid's that are DateControl's
		$dateControlArray = array();
		//array of results
		$idArray = array();
		
		// Loop through the projects; build the object queries for each
		foreach($pid as $project)
		{
			$keywords = $keywords_orig;
			// $objectQuery gets the list of IDs of records to pull data for.
			// The 1=1 is needed so that if no schemeid or keywords are sent
			// the query is still valid
			$objectQuery = ' FROM p'.$project.'Data WHERE 1=1';
			// $searchableQuery gets the list of controls that have the searchable flag set
			// i.e. that we should search across
			$searchableQuery = 'SELECT cid FROM p'.$project.'Control WHERE searchable=1';
			
			// if a specific scheme was specified, restrict our searches to that scheme
			if (!empty($sid))
			{
				$objectQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
				$objectQuery .= ' AND id NOT IN (SELECT DISTINCT kid FROM recordPreset WHERE schemeid IN ('.implode(',', $sid).')) ';
				$searchableQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
			}
			else
			{
				$objectQuery .= ' AND (id NOT IN (SELECT DISTINCT kid FROM recordPreset)) ';
			}
			
			// if keywords were provided, restrict the object query to records matching the keywords
			if (!empty($keywords))
			{
				// this provides support for advanced search which are passed in as
				// array("(cid=# BOOL value OP 'someValue')","(cid=# BOOL value OP 'someValue')",...,"(cid=# BOOL value OP 'someValue')")
				// that way the values are only searched within the specified control
				if(is_array($keywords)) {
					$where = "(SELECT DISTINCT id FROM p".$project."Data WHERE ".array_shift($keywords).")";
					
					while($clause = array_shift($keywords)) {
						$where = "(SELECT DISTINCT id FROM p".$project."Data WHERE $clause $boolean id IN $where)";
					}

					$objectQuery .= " AND id IN $where ";
				}
				else {
					$searchableQuery = $db->query($searchableQuery);
					if ($searchableQuery->num_rows == 0)
					{
						// Question: Is this an error that should be returned?
						$nameQuery = $db->query('SELECT name FROM project WHERE pid='.$project.' LIMIT 1');
						$nameQuery = $nameQuery->fetch_assoc();
						echo '<div class="error">'.gettext('Warning').': '.gettext('No searchable fields in project').': '.htmlEscape($nameQuery['name']).'</div>';
		
						// Go to the next project
						continue;
					}
					$searchable = array();
					while ($s = $searchableQuery->fetch_assoc())
					{
						$searchable[] = $s['cid'];
					}
					
					// ensure that only searchable-marked fields are searched
					$objectQuery .= ' AND cid IN ('.implode(',', $searchable).')';
					
					// handle the keywords
					$objectQuery .= ' AND (';
					$i = 1;  // used to make sure the boolean isn't prepended on the first argument
					//$keywordList = ;
					if(!is_array($keywords)){
						$keywords = explode(' ', $keywords);
					}
					if (count($keywords)  == 1) {
						//Single keyword, could be KID so also compare to id
						$keyword = $keywords[0];
						//value stuff (same as else)
						if ($i != 1) $objectQuery .= " $boolean ";
							
						//Convert special chars to match the encoded values in the db.
						$encoded_keyword = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
						$char = current($m);
						$utf = iconv('UTF-8', 'UCS-4', $char);
						return sprintf("&#x%s;", ltrim(strtoupper(bin2hex($utf)), "0"));
						}, $keyword);
						
						$objectQuery .= ' (value LIKE '.escape('%'.$keyword.'%').' OR value LIKE '.escape('%'.$encoded_keyword.'%').') ';
						
						//id stuff
						$objectQuery .= ' OR (id = "'.gettext($keyword).'")';
						$i++;
					} else {
						foreach($keywords as $keyword){
							if ($i != 1) $objectQuery .= " $boolean ";
							
							//Convert special chars to match the encoded values in the db.
							$encoded_keyword = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
							$char = current($m);
							$utf = iconv('UTF-8', 'UCS-4', $char);
							return sprintf("&#x%s;", ltrim(strtoupper(bin2hex($utf)), "0"));
							}, $keyword);
							
							$objectQuery .= ' (value LIKE '.escape('%'.$keyword.'%').' OR value LIKE '.escape('%'.$encoded_keyword.'%').') ';
							$i++;
						}
					}
					$objectQuery .= ')';
			
				}
			}
			// This is necesary to sort by the base-10 version of Record ID to keep pages in the right order, etc.
			$objectQuery .= "ORDER BY SUBSTRING_INDEX(id, '-', 2), CAST(CONV( SUBSTRING_INDEX(id, '-', -1), 16, 10) AS UNSIGNED)";

			$pageNumQuery = $db->query('SELECT COUNT(DISTINCT id) AS numRecords '.$objectQuery);
			$pageNumQuery = $pageNumQuery->fetch_assoc();
			
			$totalRecords += $pageNumQuery['numRecords'];
			
			// store the Object Query in the List
			/* $objectQueries[] = array('pid' => $project,
									 'query' => 'SELECT DISTINCT id '.$objectQuery,
									 'count' => $pageNumQuery['numRecords']); */
									 
			$projControl = "p".$project."Control";
			$datesQuery = "SELECT cid FROM $projControl WHERE type='DateControl'";
			$datesQuery = $db->query($datesQuery);
			while($dateControl = $datesQuery->fetch_assoc())
			{
				$dateControlArray[] = $dateControl['cid'];
			}
			
			//query objectQuery and put results into an array of id's
			$objectQuery = 'SELECT DISTINCT id '.$objectQuery;
			$ids = $db->query($objectQuery);
			
			while($id = $ids->fetch_assoc())
			{
				$idArray[] = $id['id'];
			}
		}
		
		/*** STEP 3: Create an array of sorted KID's ***/
		
		$idString = "('".implode("','",$idArray)."')";
		
		//*****Here we will query and sort everything that has a value in the sortBy control********
		
		//check if sortBy cid is a DateControl and process it as a special case
		$projData = 'p'.$project.'Data';
		$sortArray = array();//sorted array with only id's
		if(in_array($sortBy, $dateControlArray))
		{
			//get all results with the sortBy control that are in the idString
			$sortQuery = "SELECT id, value FROM $projData WHERE cid=$sortBy AND id IN $idString";
			$sortQuery = $db->query($sortQuery);
			while($val = $sortQuery->fetch_assoc())
			{
				//parse values out of xml into a string that can be sorted and add to array
				$xmlString = simplexml_load_string($val['value']);
				$pDate = $xmlString->year."-".str_pad($xmlString->month,2,"0",STR_PAD_LEFT)."-".str_pad($xmlString->day,2,"0",STR_PAD_LEFT);
				$sortArray[$val['id']] = $pDate;
			}
			//sort either ascending or descending
			if($order == 'ASC')
			{
				asort($sortArray);
			}
			else if($order == 'DESC')
			{
				arsort($sortArray);
			}
		}
		elseif ($sortBy !== false)//takes care of everything that is not a date
		{
			$sortQuery = "SELECT id, value FROM $projData WHERE cid=$sortBy AND id IN $idString ORDER BY value " . KoraSearch::GetValidSearchOrder($order);
			$sortQuery = $db->query($sortQuery);
			while($val = $sortQuery->fetch_assoc())
			{
				$sortArray[$val['id']] = $val['value'];
			}
		}
		else
		{
			// SORT BY KID TOP-TO-BOTTOM IF NOT SPECIFIED AS ARG
			asort($idArray);
		}
		
		//extract the values out leaving only the keys
		$sortArray = array_keys($sortArray);
		
		//append to the array with any id's that didn't have the sortBy control ingested (might be none)
		foreach($idArray as $v)
		{
			set_time_limit(30);
			if(!in_array($v, $sortArray))
			{
				$sortArray[] = $v;
			}
		}
		
		return $sortArray;
	}
	
	/**
	 * Takes an array of KIDs and prints out proper div tags to load the records
	 * via AJAX when they are viewed by the user
	 *
	 * @param array $results_ array of KIDs
	 *
	 * @return true if print success, false otherwise
	 */
	public static function PrintSearchResultsAJAXLoad($results_, $isAssociatorSearch=false, $displayRange=null)
	{
		// TODO: MAYBE FIND A BETTER WAY TO PASS THIS ADJACENT_PAGES_SHOWN VAR TO JAVASCRIPT?
		print "<div class='ks_results' navlinkadj='".ADJACENT_PAGES_SHOWN."' >";
		
		//This is the div for pagination
		print "<div class='ks_results_navlinks'></div>";
		
		print"<div class='ks_results_numresults'>Num Results</div><br /><br />";
		$currpage = 1;
		$currpagecount = 0;
		foreach ($results_ as $kid)
		{
			$recinfo = Record::ParseRecordID($kid);
			if (!$recinfo) { continue; }
			
			// PAGE SEPARATION
			if ($currpagecount == 0)
			{ print "<div class='ks_results_page' page='$currpage' >"; }
			
			
			if($isAssociatorSearch){
				print "<div class='ks_results_assoc' kid='".$kid."'><a>Associated this record (".$kid.")</a></div>";
			}
			print "<div class='ks_result_item' pid='${recinfo['pid']}' sid='${recinfo['sid']}' rid='${recinfo['rid']}' loaded='false' >$kid</div>";
			
			// PAGE SEPARATION
			if ($currpagecount == RESULTS_IN_PAGE)
			{ print "</div>"; $currpagecount = 0; $currpage++; }
			else
			{ $currpagecount++; }
		}
		// THIS WILL CLOSE OUT THE FINAL PAGE IF IT IS NOT EXACTLY THE RIGHT COUNT ALREADY
		if ($currpagecount != 0)
		{ print "</div>"; $currpagecount = 0; }
		
		print "<div class='ks_results_navlinks'></div>";
		if(sizeof($results_)>0)
			print "<div class='ks_results_numresults'>Num Results</div>";
		print "</div>";
		
		return true;
	}

	/**
	  * Prints the html for a KORA keyword search form
	  *
	  * @param List[string] $sortControls Controls that able to be sorted
	  * @param List[string] $unsupportedSort Controls that do not support sorting
	  *
	  * @return void
	  */
	public static function PrintKeywordSearchForm($sortControls,$unsupportedSort){
		?>
		<input type="hidden" class="ks_asKs_pid" value="<?php echo Manager::GetProject()->GetPID();?>" />
		<input type="hidden" class="ks_asKs_sid" value="<?php echo Manager::GetScheme()->GetSID();?>" />
		<table class="table" id=advSearch_table_keyword>
		<tr><td>
		Keywords: <input type="text" class="ks_asKs_keywords" id="keywords" /> *separate keywords with a space
		</td></tr>
		<tr><td>
		Include objects that match
		<select class="ks_asKs_boolean">
			<option value="AND">AND</option>
			<option value="OR">OR</option>
		</select>
		 keywords.
		</td></tr>
		<!-- allow the user to sort by a certain control. -->
		<tr><td colspan="2">Sort Results By:
		<select id="advSearch_sortByKeyword" class="advSearch_sortByKeyword">
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
		<select id="sortOrder_keyword" class="sortOrder_keyword" disabled>
		<option value="ASC"> ---------- </option>
		<?php
		echo "<option value='ASC'>Ascending</option>";
		echo "<option value='DESC'>Descending</option>";
		?>
		</select>
		</td></tr>
		<tr><td><input type="button" value="Search" class="ks_asKs_submit" /></td></tr>
		</table>
		<?php
	}
	
	/**
	  * Print out the html for a KORA advanced search form
	  *
	  * @param List[string] $controls Controls that support advanced search
	  * @param List[string] $unsupportedAdvSearch Controls that do not support advanced search
	  * @param List[string] $sortControls Controls that able to be sorted
	  * @param List[string] $unsupportedSort Controls that do not support sorting
	  *
	  * @return void
	  */
	public static function PrintAdvancedSearchForm($controls,$unsupportedAdvSearch,$sortControls,$unsupportedSort){
	?>
		<input type="hidden" class="ks_as_pid" value="<?php echo Manager::GetProject()->GetPID();?>" />
		<input type="hidden" class="ks_as_sid" value="<?php echo Manager::GetScheme()->GetSID();?>" />
		<table class="table" id=advSearch_table_other>
		
		<?php
		foreach($controls as $c) {
			// This check should not be needed but used as an extra
			// security check to ensure that only supported fields
			// can be used in advanced search
			if (!in_array($c['type'],$unsupportedAdvSearch)) {
				include_once(basePath.CONTROL_DIR.$c['file']);
				$controlClass = $c['class'];
				$control = new $controlClass(Manager::GetProject()->GetPID(),$c['cid']);
				
				echo '<tr><td>'.$c['name'].': </td><td>';
				$control->display(false);
				echo '</td></tr>';
			}
		}
		?>
		<!-- allow the user to sort by a certain control. -->
		<tr><td colspan="2">Sort Results By:
		<select id="advSearch_sortBy" class="advSearch_sortBy">
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
		<select id="sortOrder_other" class="sortOrder_other" disabled>
		<option value="ASC"> ---------- </option>
		<?php
		echo "<option value='ASC'>Ascending</option>";
		echo "<option value='DESC'>Descending</option>";
		?>
		</select>
		</td></tr>
		<tr><td colspan="2"><input type="button" value="Search" class="ks_as_submit" /></td></tr>
		</table>
		<?php
	}

	/**
	  * Print out the html for a Kora cross project search form
	  *
	  * @return void
	  */
	public static function PrintCrossProjectSearchForm(){
		global $db;
	
		?><table class="ks_cps_table">
		    <tr>
		        <td>
		            <b><?php echo gettext('Keywords');?></b>
		        </td>
		        <td>
		            <input type="text" class="ks_cps_keywords" size="70" />*separate keywords with a space
		        </td>
		    </tr>
		    <tr>
		        <td>
		            <b><?php echo gettext('Boolean');?></b>
		        </td>
		        <td>
		            <select class="ks_cps_boolean">
		                <option value="AND" selected="selected"><?php echo gettext('AND');?></option>
		                <option value="OR"><?php echo gettext('OR');?></option>
		            </select>
		        </td>
		    </tr>
		    <tr>
		        <td>
		            <b><?php echo gettext('Projects');?></b>
		        </td>
		        <td>
		            <select class="ks_cps_projects" multiple="multiple" size="5">
		<?php 
		// Get the list of projects the user is allowed to search
		if (Manager::IsSystemAdmin())
		{
		    $projectQuery = 'SELECT pid, name FROM project WHERE active=1 ORDER BY name';
		} else {
			$projectQuery  = 'SELECT project.pid AS pid, project.name AS name FROM member LEFT JOIN project USING (pid)';
			$projectQuery .= ' WHERE member.uid='.$_SESSION['uid'].' AND project.active=1 ORDER BY project.name';
		}
		
		$projectQuery = $db->query($projectQuery);
		while($project = $projectQuery->fetch_assoc())
		{
		    echo '                <option value="'.$project['pid'].'" selected="selected">'.htmlEscape($project['name'])."</option>\n";	
		}
		?>
		            </select>
		        </td>
		    <tr>
		        <td colspan="2"><input type="button" value="<?php echo gettext('Search');?>" class="ks_cps_submit"/></td>
		    </tr>
		</table><?php
	}
	
	/**
	  * Print out the html for a Kora project search form
	  *
	  * @param int $pid Project ID for the search
	  *
	  * @return void
	  */
	public static function PrintProjectSearchForm($pid){
		global $db;
	
		?>
		<input type="hidden" class="ks_ps_pid" value="<?php echo $pid;?>" />
		<table class="ks_ps_table">
		    <tr>
		        <td><?php echo gettext('Scheme');?>:</td>
		        <td><select class="ks_ps_sid">
		            <option value=""><?php echo gettext('All');?></option>
		<?php 
		            // show options for all schemes in the project
		            $schemeList = $db->query('SELECT schemeid, schemeName FROM scheme WHERE pid='.$pid);
		            while ($scheme = $schemeList->fetch_assoc()) {
		            	echo '<option value="'.$scheme['schemeid'].'">'.htmlEscape($scheme['schemeName']).'</option>';
		            }
		?>
		        </select></td>
		    </tr>
		    <tr>
		        <td><?php echo gettext('Keywords');?>:</td>
		        <td><input type="text" class="ks_ps_keywords" />*separate keywords with a space</td>
		    </tr>
		    <tr>
		    	<td><?php echo gettext('Boolean');?>:</td>
		        <td><select class="ks_ps_boolean">
		            <option value="OR"/> <?php echo gettext('Or');?>
		            <option value="AND" /> <?php echo gettext('And');?>
		        </select></td>
		    </tr>
		    <tr>
		        <td></td>
		        <td><input type="button" class="ks_ps_submit" value="<?php echo gettext('Search');?>" /></td>
		    </tr>
		</table>
		<?php 
	}
	
	/**
	  * Parses unwanted characters from a search string
	  *
	  * @param string $queryString String to be parsed
	  *
	  * @return array of string parts
	  */
	public static function ParseSearchString($queryString)
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
	
	/**
	  * Turn multiple word interpretations for ascending and descending into the keywords
	  * we use within Kora (i.e. ASC, DESC)
	  *
	  * @param string $order_ The order direction for the sort
	  *
	  * @return the proper keyword for sort direction
	  */
	// CHECKS SEVERAL DIFFERENT WAYS OF WORDING 'ASC' OR 'DESC' RETURNING DEFAULT OF 'ASC' IF OTHERS INVALID
	public static function GetValidSearchOrder($order_)
	{
		if (in_array(strtoupper($order_), array('ASC', 'ASCENDING', 'UP')))                    { return 'ASC'; }
		elseif (in_array(strtoupper($order_), array('DESC', 'DESCENDING', 'DOWN', 'REVERSE'))) { return 'DESC'; }
		else                                                                                   { return 'ASC'; }
	
	}
	
}

?>
