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

// Initial Version: Brian Beck, 2009
// Refactor: Joe Deming, Anthony D'Onofrio 2013

// Note: This explicitly doesn't do any requireLogin or requireSystemAdmin checks
// in case a database change/codebase change breaks stuff so heavily that users can't
// log in.

require_once(__DIR__.'/includes/includes.php');

Manager::Init();

Manager::PrintHeader();
?>

<h2><?php echo gettext('KORA Upgrade Utility');?></h2>
<?php echo gettext('This script will ensure that your KORA database is up-to-date.  If an upgrade fails, please attempt it a second time before reporting the issue.')?><br /><br />
<?php

//////////////////////////////////////
// We must check for the existence of the systemInfo table
// and a row in it corresponding to the system version.  It
// creates them if they don't exist.  If they don't exist and
// can't be created, the updater dies since it cannot proceed.
// Other checks don't have to die, but these two are absolutely
// critical.
//////////////////////////////////////

// Check for the existence of the system info table
echo gettext('Checking for existence of System Information database table').'.....';
flush();
$sysTableQuery = $db->query("SHOW TABLES LIKE 'systemInfo'");
if ($sysTableQuery->num_rows < 1)
{
	// The table doesn't exist; create it.
	$result = $db->query('CREATE TABLE systemInfo (
		version VARCHAR(64) NOT NULL,
		baseURL VARCHAR(255) NOT NULL UNIQUE) CHARACTER SET utf8 COLLATE utf8_general_ci');
	if ($result)
	{
		echo '<strong>'.gettext('Created').'</strong><br />';
	}
	else
	{
		echo '<strong><font color="#ff0000">'.gettext('Failed').'</font></strong><br /><br />';
		die(gettext('Unable to create systemInfo table').'.  '.gettext('Please check your database configuration.  Unable to proceed with update process.'));
	}
}
else
{
	echo '<strong>'.gettext('Found').'</strong><br />';
}
echo gettext('Checking for existence of System Information for this install').'.....';
flush();
$sysInfoQuery = $db->query('SELECT version FROM systemInfo WHERE baseURL='.escape(baseURI).' LIMIT 1');
if ($sysInfoQuery->num_rows < 1)
{
	// The row doesn't exist; create it
	$result = $db->query('INSERT INTO systemInfo (baseURL, version) VALUES ('.escape(baseURI).', \'0\')');
	if ($result)
	{
		echo '<strong>'.gettext('Created').'</strong><br />';
		// This should be an associative array containing all the values pulled in the
		// SELECT query above, corresponding to whatever they're created to here.
		$systemInfo = array('version' => '0');
	}
	else
	{
		echo '<strong><font color="#ff0000">'.gettext('Failed').'</font></strong><br /><br />';
		die(gettext('Unable to create systemInfo data').'.  '.gettext('Please check your database configuration.  Unable to proceed with update process.'));
	}
}
else
{
	echo '<strong>'.gettext('Found').'</strong><br />';
	$systemInfo = $sysInfoQuery->fetch_assoc();
}
echo '<br />';

//////////////////////////////////////
// Now we have the System (and thus version) information.
// We can compare the version number against a number of
// benchmarks to see what checks need to be run.  Always
// use PHP's version_compare command.  As a side rule, all
// version number increases must correspond to syntax
// acceptable to version_compare.
//////////////////////////////////////

// General idea: If a new version requires a database shift, add a section to the
// bottom.  Do NOT delete previously existing tests.  NEVER reset the value of
// allTestsPassed to true.  ONLY update the version number in the database if
// allTestsPassed is true.  If ANY test fails, set allTestsPassed to false.  If
// you don't, the version number could be updated and the test might never be run
// again, leading to a permanent error.

$allTestsPassed = true;     // Set this to false if a test fails; NEVER set back to true
$anyUpdatesDone = false;
$messages = array();      // Used to store any messages to be displayed at the end

if (version_compare($systemInfo['version'], '1.0.0', '<'))
{
	$anyUpdatesDone = true;
	
	echo gettext('Found version number less than 1.0.0.  Running beta->production upgrades.').'<br />';
	flush();
	
	// Get the list of tables.  This will let us see if several tables exist/have the correct
	// name.
	$tableQuery = $db->query('SHOW TABLES');
	$fixityExists = false;
	$recordPresetExists = false;
	$controlsExists = false;
	while($t = $tableQuery->fetch_row())
	{
		if ($t[0] == 'fixity')
		{
			$fixityExists = true;
		}
		else if ($t[0] == 'recordPreset')
		{
			$recordPresetExists = true;
		}
		else if ($t[0] == 'controls')
		{
			$controlsExists = true;
		}
	}
        
	// Check for existence of fixity table
	if (!$fixityExists)
	{
		echo gettext('Attempting to create fixity table').'.....<strong>';
		flush();
		$result = $db->query('CREATE TABLE fixity(
			kid VARCHAR(20) NOT NULL,
			cid INTEGER UNSIGNED NOT NULL,
			path VARCHAR(512),
			initialHash VARCHAR('.HASH_HEX_SIZE.') NOT NULL,
			initialTime DATETIME NOT NULL,
			computedHash VARCHAR('.HASH_HEX_SIZE.'),
			computedTime DATETIME NOT NULL,
			PRIMARY KEY(kid,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo '</strong><br />';
	}
        
	// Check for existence of recordPreset
	if (!$recordPresetExists)
	{
		echo gettext('Attempting to create recordPreset table').'.....<strong>';
		flush();
		$result = $db->query('CREATE TABLE recordPreset(
			recpresetid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
			schemeid INTEGER UNSIGNED NOT NULL,
			name VARCHAR(255),
			kid VARCHAR(30),
			PRIMARY KEY(recpresetid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo '</strong><br />';
	}
        
	// Check for correct name of control table
	if ($controlsExists)
	{
		echo gettext('Attempting to rename controls table to control').'.....<strong>';
		flush();
		$result = $db->query('RENAME TABLE controls TO control');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
        
	// Check for allowPasswordReset, searchAccount columns in user table
	$allowPasswordResetExists = false;
	$searchAccountExists = false;
	$columnQuery = $db->query('SHOW COLUMNS FROM user');
	while ($c = $columnQuery->fetch_assoc())
	{
		if ($c['Field'] == 'allowPasswordReset')
		{
			$allowPasswordResetExists = true;
		}
		else if ($c['Field'] == 'searchAccount')
		{
			$searchAccountExists = true;
		}
	}
	if (!$allowPasswordResetExists)
	{
		echo gettext('Expanding user table (Part 1/2)').'.....<strong>';
		flush();
		$result = $db->query('ALTER TABLE user ADD COLUMN allowPasswordReset TINYINT(1) UNSIGNED NOT NULL AFTER searchAccount');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
	if (!$searchAccountExists)
	{
		echo gettext('Expanding user table (Part 2/2)').'.....<strong>';
		flush();
		$result = $db->query('ALTER TABLE user ADD COLUMN resetToken VARCHAR(16) AFTER allowPasswordReset');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
        
	// Check for dublinCoreOutOfDate column in scheme table
	$columnQuery = $db->query('SHOW COLUMNS FROM scheme WHERE Field=\'dublinCoreOutOfDate\'');
	if ($columnQuery->num_rows == 0)
	{
		echo gettext('Expanding scheme table').'.....<strong>';
		flush();
		$result = $db->query('ALTER TABLE scheme ADD COLUMN dublinCoreOutOfDate TINYINT(1) UNSIGNED NOT NULL AFTER dublinCoreFields');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
        
	// Check for xmlPacked column in control table
	$columnQuery = $db->query('SHOW COLUMNS FROM control WHERE Field=\'xmlPacked\'');
	if ($columnQuery->num_rows == 0)
	{
		echo gettext('Expanding control table').'.....<strong>';
		flush();
		$result = $db->query('ALTER TABLE control ADD COLUMN xmlPacked TINYINT(1) NOT NULL AFTER class');
		if ($result)
		{
			$messages[] = gettext('The controls table has been updated; please run "Update Control List" in the System Management console.');
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
	
	// Check for presetid column in controlPreset table
	$columnQuery = $db->query('SHOW COLUMNS FROM controlPreset WHERE Field=\'presetid\'');
	if ($columnQuery->num_rows == 0)
	{
		echo gettext('Expanding controlPreset table').'.....<strong>';
		flush();
		$result = $db->query('ALTER TABLE controlPreset ADD COLUMN presetid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT BEFORE name, DROP PRIMARY KEY, ADD PRIMARY KEY (presetid)');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
	
	// Update Stock Presets
	$presetQuery = $db->query('SELECT name FROM controlPreset WHERE name="Floating Point" and class="Text Control" LIMIT 1');
	if ($presetQuery->num_rows > 0)
	{
		// Update the stock presets
		echo gettext('Renaming stock presets').'.....<strong>';
		flush();
		$result = $db->query('UPDATE controlPreset SET name="Remove Preset" WHERE name IN ("Empty (No RegEx)", "Empty (No Types)") AND class IN ("FileControl", "ListControl", "TextControl")');
		$result = $db->query('UPDATE controlPreset SET name="Decimal Number" WHERE name="Floating Point" AND class="TextControl" LIMIT 1');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		
		echo '</strong><br />';
	}
	
	// Update the database to reflect that it's at 1.0.0.
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='1.0.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '1.0.0';
		}
	}
	
	echo '<br /><br />';
}

if (version_compare($systemInfo['version'], '1.0.2', '<'))
{
	// Run the 1.0.2 List Control Checks
	echo gettext('Trimming List Control Options').'......';
	flush();
	$projectQuery = $db->query('SELECT DISTINCT pid FROM project');
	$allListOptionsGood = true;
	while ($p = $projectQuery->fetch_assoc())
	{
		$optionsQuery = $db->query('SELECT cid, type, options FROM p'.$p['pid']."Control WHERE type IN ('ListControl', 'MultiListControl')");
		while ($o = $optionsQuery->fetch_assoc())
		{
			$xml = simplexml_load_string($o['options']);
			
			$trimmedOptions = array();
			// Build a list of the options that need to be trimmed
			foreach($xml->option as $option)
			{
				if (trim((string)$option) != (string)$option)
				{
					$trimmedOptions[] = (string)$option;
				}
			}
			
			if (!empty($trimmedOptions))
			{
				// Update the options list
				$newXML = simplexml_load_string('<options />');
				foreach($xml->option as $option)
				{
					$newXML->addChild('option', trim((string)$option));
				}
				$updateOptions = $db->query('UPDATE p'.$p['pid'].'Control SET options='.escape($newXML->asXML()).' WHERE cid='.$o['cid'].' LIMIT 1');
				if ($updateOptions)
				{
					$allListOptionsGood = false;
				}
				
				// Update any data records containing the records that need to be trimmed
				if ($o['type'] == 'ListControl')
				{
					// Escape the Strings
					$escapedStrings = array();
					foreach($trimmedOptions as $t)
					{
						$escapedStrings[] = escape($t);
					}
					
					$query = 'SELECT id, cid, value FROM p'.$p['pid'].'Data WHERE cid='.$o['cid'].' AND VALUE IN (';
					$query .= implode(',', $escapedStrings);
					$query .= ')';
					$query = $db->query($query);
					if (!$query)
					{
						$allListOptionsGood = false;
					}
					else
					{
						while ($row = $query->fetch_assoc())
						{
							$updateQuery = $db->query('UPDATE p'.$p['pid'].'Data SET value='.escape(trim($row['value'])).' WHERE id='.escape($row['id']).' AND cid='.$row['cid'].' LIMIT 1');
							if ($updateQuery)
							{
								$allListOptionsGood = false;
							}
						}
					}
				}
				else // if $o['type'] == 'MultiListControl'
				{
					// Escape the Strings
					$escapedStrings = array();
					foreach($trimmedOptions as $t)
					{
						$escapedStrings[] = escape('%>'.$t.'<%');
					}
					
					// Build the Query
					$addOr = false;
					$query = 'SELECT id, cid, value FROM p'.$p['pid'].'Data WHERE cid='.$o['cid'].' AND ';
					foreach($escapedStrings as $s)
					{
						if ($addOr)
						{
							$query .= ' OR ';
						}
						$query .= " value LIKE $s ";
						
						$addOr = true;
					}
					$query = $db->query($query);
					if (!$query)
					{
						$allListOptionsGood = false;
					}
					else
					{
						while ($row = $query->fetch_assoc())
						{
							$xml = simplexml_load_string($row['value']);
							$newXML = simplexml_load_string('<multilist />');
							foreach($xml->value as $value)
							{
								$newXML->addChild('value', trim((string)$value));
							}
							$updateQ = 'UPDATE p'.$p['pid'].'Data SET value='.escape($newXML->asXML()).' WHERE id='.escape($row['id']).' AND cid='.$row['cid'].' LIMIT 1';
							$updateQuery = $db->query($updateQ);
							if (!$updateQuery)
							{
								$allListOptionsGood = false;
								echo gettext('Failure');
							}
						}
					}
				}
			}
		}
	}
	echo "<strong>";
	if ($allListOptionsGood)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong>";
	
	// Update the database to reflect that it's at 1.0.2.
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='1.0.2' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '1.0.2';
		}
	}
	
	echo '<br /><br />';
}

if (version_compare($systemInfo['version'], '1.1.0', '<'))
{
	// Run the 1.1.0 checks
	
	// Check the date controls for the <display> option.
	if (true)
	{
		// This is block exists solely so that the indentation matches all
		// the other tests; at this point I can't think of a good way to check
		// if this has been done or not (checking a single control or even a sample
		// could fail if the user creates new controls before running this script)
		// so we'll just have to do it each time until all the tests succeed and
		// the DB version is bumped to 1.1.0
		
		echo gettext('Adding display option to Date and Multi-Date Controls').'......';
		flush();
		$projectQuery = $db->query('SELECT pid FROM project');
		$allDateOptionsGood = true;
		while ($p = $projectQuery->fetch_assoc())
		{
			$optionsQuery = $db->query('SELECT cid, options FROM p'.$p['pid']."Control WHERE type IN ('DateControl', 'MultiDateControl')");
			while ($o = $optionsQuery->fetch_assoc())
			{
				$dateOptionChanged = false;
				$xml = simplexml_load_string($o['options']);
				if (!isset($xml->displayFormat))
				{
					$xml->addChild('displayFormat', 'MDY');
					$dateOptionChanged = true;
				}
				else if (!in_array( (string) $xml->displayFormat, array('MDY', 'DMY', 'YMD')))
				{
					$xml->displayFormat = 'MDY';
					$dateOptionChanged = true;
				}
				
				// Update the options if they changed
				if ($dateOptionChanged)
				{
					$result = $db->query('UPDATE p'.$p['pid'].'Control SET options='.escape($xml->asXML()).' WHERE cid='.$o['cid'].' LIMIT 1');
					if (!$result)
					{
						$allDateOptionsGood = false;
					}
				}
			}
		}
		echo "<strong>";
		if ($allDateOptionsGood)
		{
			
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	// Once again, it's impossible to easily test if all projects have the
	// necessary column so we have to run this test every time until the DB
	// successfully gets to 1.1.0 or higher.
	if (true)
	{
		echo gettext('Adding Advanced Search column to Project tables').'........';
		$allAdvSearchGood = true;
		$pidQuery = $db->query('SELECT pid FROM project');
		while($row = $pidQuery->fetch_assoc())
		{
			// See if the column exists
			$test = $db->query('SHOW COLUMNS FROM p'.$row['pid'].'Control WHERE Field=\'advSearchable\'');
			if ($test->num_rows == 0)
			{
				$result = $db->query('ALTER TABLE p'.$row['pid'].'Control ADD COLUMN advSearchable TINYINT(1) UNSIGNED NOT NULL AFTER searchable');
				if (!$result)
				{
					$allAdvSearchGood = false;
				}
			}
		}
		echo "<strong>";
		if ($allAdvSearchGood)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	$test = $db->query('SHOW COLUMNS FROM scheme WHERE Field=\'allowPreset\'');
	if ($test->num_rows == 0)
	{
		echo gettext('Adding Scheme Preset column to Scheme table').'........<strong>';
		$result = $db->query('ALTER TABLE scheme ADD COLUMN allowPreset TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER description');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	// Update the "US States" Preset
	if (true)
	{
		echo gettext('Correcting Washington DC in US States Preset').'.........<strong>';
		$result = $db->query('UPDATE controlPreset SET value=\'<options><option>Alabama</option><option>Alaska</option><option>Arizona</option><option>Arkansas</option><option>California</option><option>Colorado</option><option>Connecticut</option><option>Delaware</option><option>District of Columbia</option><option>Florida</option><option>Georgia</option><option>Hawaii</option><option>Idaho</option><option>Illinois</option><option>Indiana</option><option>Iowa</option><option>Kansas</option><option>Kentucky</option><option>Louisiana</option><option>Maine</option><option>Maryland</option><option>Massachusetts</option><option>Michigan</option><option>Minnesota</option><option>Mississippi</option><option>Missouri</option><option>Montana</option><option>Nebraska</option><option>Nevada</option><option>New Hampshire</option><option>New Jersey</option><option>New Mexico</option><option>New York</option><option>North Carolina</option><option>North Dakota</option><option>Ohio</option><option>Oklahoma</option><option>Oregon</option><option>Pennsylvania</option><option>Rhode Island</option><option>South Carolina</option><option>South Dakota</option><option>Tennessee</option><option>Texas</option><option>Utah</option><option>Vermont</option><option>Virginia</option><option>Washington</option><option>West Virginia</option><option>Wisconsin</option><option>Wyoming</option></options>\' WHERE name=\'US States\' AND class=\'ListControl\' AND project=0 LIMIT 1');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	// Update the 'URL or URI' Preset
	$test = $db->query("SELECT presetid FROM controlPreset WHERE name='URL or URI' AND class='TextControl' AND project=0 AND value='/^(http|ftp|https):///' LIMIT 1");
	if ($test->num_rows == 1)
	{
		$test = $test->fetch_assoc();
		echo gettext('Correcting URL/URI Preset').'..........<strong>';
		$result = $db->query("UPDATE controlPreset SET value='/^(http|ftp|https):".'\\\\'.'/'.'\\\\'."//' WHERE presetid=".$test['presetid'].' LIMIT 1');
		if ($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	// Update the database to reflect that it's at 1.1.0.
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='1.1.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '1.1.0';
		}
	}
	echo '<br />';
}

if (version_compare($systemInfo['version'], '2.0.0-beta', '<'))
{
	// Update Image Controls
	if (true)
	{
		echo gettext('Updating Allowed Image Formats').'..........';
		flush();
		// get all projects
		$allUpdated = true;
		$projectQuery = $db->query('SELECT pid FROM project');
		$result = true;
		while ($p = $projectQuery->fetch_assoc())
		{
			$controlQuery = $db->query('SELECT cid,options FROM p'.$p['pid'].'Control WHERE type=\'ImageControl\'');
			while ($c = $controlQuery->fetch_assoc())
			{
				$xml = simplexml_load_string($c['options']);
				$hasxpng = false;
				$haspjpeg = false;
				foreach($xml->allowedMIME->mime as $m)
				{
					if ( (string)$m == 'image/pjpeg' ) $haspjpeg = true;
					if ( (string)$m == 'image/x-png' ) $hasxpng = true;
				}
				if (!$hasxpng)
				{
					$xml->allowedMIME->addChild('mime', 'image/x-png');
				}
				if (!$haspjpeg)
				{
					$xml->allowedMIME->addChild('mime', 'image/pjpeg');
				}
				if ( (!$hasxpng) || (!$haspjpeg) )
				{
					// fix it in the database
					$result = $db->query('UPDATE p'.$p['pid'].'Control SET options='.escape($xml->asXML()).' WHERE cid='.$c['cid'].' LIMIT 1');
					if (!$result)
					{
						$allUpdated = false;
					}
				}
			}
		}
		echo "<strong>";
		if ($allUpdated)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	// Update Reverse Associations
	$badReverseAssocPresent = false;
	// Look for old-style Reverse Associator Fields
	$projectQuery = $db->query('SELECT pid FROM project');
	while ($p = $projectQuery->fetch_assoc())
	{
		// check for a reverse associator without a <assoc> block
		$assocQuery = $db->query('SELECT id FROM p'.$p['pid'].'Data WHERE cid=0 AND VALUE NOT LIKE \'%<assoc>%\' LIMIT 1');
		if ($assocQuery->num_rows > 0)
		{
			// if found, delete all reverse associators in this table and tell the system to rebuild the associators
			$db->query('DELETE FROM p'.$p['pid'].'Data WHERE cid=0');
			$badReverseAssocPresent = true;
		}
	}
	if ($badReverseAssocPresent)
	{
		echo gettext('Updating Reverse Associations').'..........';
		flush();
		// get all projects
		$allUpdated = true;
		$projectQuery = $db->query('SELECT pid FROM project');
		$result = true;
		while ($p = $projectQuery->fetch_assoc())
		{
			$dataQuery = $db->query('SELECT id, cid, value FROM p'.$p['pid'].'Data WHERE cid IN (SELECT cid FROM p'.$p['pid'].'Control WHERE type=\'AssociatorControl\')');
			while ($d = $dataQuery->fetch_assoc())
			{
				// Rebuild the Reverse Associations
				$xml = simplexml_load_string($d['value']);
				if (isset($xml->kid))
				{
					foreach($xml->kid as $kid)
					{
						AssociatorControl::AddReverseAssociation( (string)$kid, $d['id'], $d['cid'] );
					}
				}
			}
		}
		echo "<strong>";
		if ($allUpdated)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	//Add timestamp control to all schemes
	//First get a list of all schemes in all projects
	$allHaveTimestamps = true;
	$anyTimestampsAdded = false;
	$sQuery = "SELECT schemeid, pid FROM scheme";
	$schemes = $db->query($sQuery);
	if(is_object($schemes) && $schemes->num_rows > 0)
		$pids = array();
	while($scheme = $schemes->fetch_assoc()){
		if(array_search($scheme['pid'], $pids) !==FALSE){
			$pids[$scheme['pid']] = array();
			$pids[$scheme['pid']][$scheme['schemeid']] = false;
		}
		else{
			$pids[$scheme['pid']][$scheme['schemeid']] = false;
		}
	}
	//Get the date to add to all records for newly created timestamp controls
	$now = date("r");
	
	//On a fresh install, or before any projects are added, $pids will be empty
	if(!empty($pids)){
		
		//Go through projects/schemes discovered, add timestamp control if not present
		foreach($pids as $pid=>$schms){
			
			foreach($schms as $sid=>$hasTimestamp){
				
				$cQuery = "SELECT name,type FROM p".$pid."Control WHERE schemeid='$sid'";
				$result = $db->query($cQuery);
				//If a project doesn't have a control table, this kora install is damage
				if(!is_object($result)){
					die(gettext('Control table is missing from project').' '.$pid.' '.gettext('or').' '.$db->error);
				}
				//If there is at least 1 control for this scheme, loop through controls checking if timestamp control already exists
				if($result->num_rows > 0){
					while ($control = $result->fetch_assoc()){
						if($control['name'] == 'systimestamp'){
							if($control['type'] == 'TextControl')
								$hasTimestamp = true;
							else{ //If a control named 'systimestamp' is found that is not a text control, timestamp control can't be added
								$hasTimestamp = true;
								$messages[] = 'Schemeid'.' '.$sid.' '.'has a control named systimestamp of type'.' '.$control['type'].' - '."timestamp can't be added".'.<br />';
								$allHaveTimestamps = false;
								$allTestsPassed = false;
							}
						}
					}
				}
				//If timestamp control was not found, add it
				if(!$hasTimestamp){
					$anyTimestampsAdded = true;
					$tempReq = $_REQUEST;
					$_REQUEST['pid'] = $pid;
					$_REQUEST['sid'] = $sid;
					$_REQUEST['name'] = 'systimestamp';
					$_REQUEST['type'] = 'TextControl';
					$_REQUEST['description'] = ''; //Could add a real description here
					$_REQUEST['submit'] = true;
					$_REQUEST['collectionid'] = 0;
					$_REQUEST['publicentry'] = "on"; //Not used
					require('addControl.php');
					$_REQUEST = $tempReq;
					
					//Now that timestamp control is added, add the current time as the timestamp for all existing records inthis scheme
					//First get the cid for the newly created control
					$cidQuery = "SELECT cid FROM p".$pid."Control WHERE name='systimestamp' AND schemeid='$sid' LIMIT 1";
					$cid = $db->query($cidQuery);
					if(!is_object($cid) || $cid->num_rows != 1){
						//Since we just created this control, if it doesn't exist in the control table, that's bad
						$allHaveTimestamps = false;
						$messages[] = gettext('Timestamp control creation failed for scheme').": $sid, $db->error.";
					}
					else{
						$cid = $cid->fetch_assoc();
						$cid = $cid['cid'];
						//Get all of the kids for scheme so that timestamp data can be added
						$dQuery = "SELECT DISTINCT id FROM p".$pid."Data WHERE schemeid='$sid' ORDER BY id ASC";
						$kids = $db->query($dQuery);
						if(!is_object($kids) || $kids->num_rows < 1){
							if($db->error){
								$messages[] = $db->error;
								$allHaveTimestamps = false;
							}
							//else no records for this scheme, so no timestamps to add
						}
						else{
							//Add the timestamp value to the data table
							while($record = $kids->fetch_assoc()){
								$db->query("INSERT INTO p".$pid."Data (id, cid, schemeid, value) VALUES ('$record[id]','$cid','$sid','$now')");
								if($db->error){
									$messages[] = $db->error;
									$allHaveTimestamps = false;
								}
							}
						}
					}
				}
			}
		}
		if($anyTimestampsAdded){
			$anyUpdatesDone = true;
			echo gettext('Adding timestamps').' .........<strong>';
			flush();
			if ($allHaveTimestamps){
				echo gettext('Successful');
			}
			else{
				$allTestsPassed = false;
				echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			}
			echo "</strong><br/>";
	}}
	
	//Add a timestamp column to the dublin core table
	//Note that this is not a regular dublin core field, and will not be added to
	//a user-selectable list of dublin core fields
	$query="SHOW COLUMNS FROM dublinCore LIKE 'timestamp'";
	$result = $db->query($query);
	if($result->num_rows < 1){
		$anyUpdatesDone = true;
		echo "Adding timestamp column to Dublin Core table.......<strong>";
		$addquery = "ALTER TABLE dublinCore ADD COLUMN timestamp VARCHAR(1000)";
		$db->query($addquery);
		if($db->error != ''){
			$allTestsPassed = false;
			echo '<font color="#ff0000">'.gettext('Failed').'</font><br />'.$db->error.'<br />';
		}
		else{
			echo gettext("Successful");
		}
		echo "</strong><br/>";
	}
	
	
	// Update user table
	if(true)
	{
		echo gettext('Updating user Table').'..........<strong>';
		flush();
		// check for language column in user
		$allUpdated = true;
		$userQuery = $db->query('SELECT * FROM user');
		if(!array_key_exists('language', $userQuery->fetch_assoc()))
		{
			// add the language column in user
			$alterUser = $db->query("ALTER TABLE user ADD language varchar(6) default 'en_US' AFTER organization");
			if(!$alterUser) $allUpdated = false;
		}
		if ($allUpdated)
		{
			echo gettext('Successful');
		}
		else
   	 	{
   	 		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
   	 		$allTestsPassed = false;
   	 	}
   	 	echo "</strong><br/>";
   	}
   	
   	
   	
   	// DO NOT UNCOMMENT THIS BLOCK UNTIL ALL CHANGES FOR VERSION 1.2.0 ARE FINISHED!
   	
   	// Update the database to reflect that it's at 2.0.0-beta
   	if ($allTestsPassed)
   	{
   		$result = $db->query("UPDATE systemInfo SET version='2.0.0-beta' WHERE baseURL=".escape(baseURI).' LIMIT 1');
   		if (isset($_SESSION['dbVersion']))
   		{
   			$_SESSION['dbVersion'] = '2.0.0-beta';
   		}
   	}
   	echo '<br />';
}
if(version_compare($systemInfo['version'], '2.0.0', '<'))
{
	//No database changes for 2.0.0 over the beta - just change version
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.0.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.0.0';
		}
	}
	//Mark anyUpdatesDone as true - a change was made, though minor
	$anyUpdatesDone = true;
	echo '<br />';
}

if(version_compare($systemInfo['version'], '2.1.0', '<'))
{
	//**
	//Add Columns to the scheme table
	//**
	if(true)
	{
		echo gettext('Updating scheme Table').'..........';
		flush();
		//get all column names from scheme table and add to an array
		$allUpdated = true;
		$colNames = array();
		$schemeColumns = $db->query("SHOW COLUMNS FROM scheme");
		while($schemeColumn = $schemeColumns->fetch_assoc())
		{
			$colNames[] = $schemeColumn['Field'];
		}
		//check if the new columns exist yet, if not add them to the table
		if(!in_array('publicIngestion', $colNames))
		{
			//add to db
			$r = $db->query("ALTER TABLE scheme ADD publicIngestion TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
			if (!$r)
			{
				$allUpdated = false;
			}
		}
		if(!in_array('legal', $colNames))
		{
			//add to db
			$r = $db->query("ALTER TABLE scheme ADD legal VARCHAR(255)");
			if (!$r)
			{
				$allUpdated = false;
			}
		}
		echo "<strong>";
		if($allUpdated)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	
	//Create public ingestion tables for Data and Controls if they don't already exist
	if(true)
	{
		echo gettext('Adding PublicData Tables').'..........';
		flush();
		$dataTables = $db->query("SHOW TABLES LIKE 'p%Data'");
		$controlTables = $db->query("SHOW TABLES LIKE 'p%Control'");
		
		$projID = array();
		while($table = $dataTables->fetch_assoc())
		{
			$tableNames[] = $table;
			
			//parse out project IDs
			$getProjID = (array_pop($table));
			$getProjID = preg_split("/[A-Za-z]+/", $getProjID);
			$projID[] = $getProjID[1];
		}
		
		foreach($projID as $pid)
		{
			//create new tables in db
			$result = $db->query('CREATE TABLE IF NOT EXISTS p'.$pid.'PublicData(
	                        id INTEGER UNSIGNED NOT NULL,
	                        cid INTEGER UNSIGNED NOT NULL,
	                        schemeid INTEGER UNSIGNED NOT NULL,
	                        value LONGTEXT,
	                        PRIMARY KEY(id,cid)) CHARACTER SET utf8 COLLATE utf8_general_ci');
		}
		echo "<strong>";
		if($result)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.1.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.1.0';
		}
	}
	//Mark anyUpdatesDone as true - a change was made, though minor
	$anyUpdatesDone = true;
	echo '<br />';
}

if(version_compare($systemInfo['version'], '2.1.1', '<'))
{
	// add recordowner control
	if(true)
	{
		echo gettext('Adding recordowner control to all projects').'......';
		flush();
		//check every scheme in each project to see if they have a "recordowner" control
		//if they don't have that cid, then add it to that scheme
		$schemes = $db->query("SELECT schemeid, pid FROM scheme");
		$allUpdated = true;
		while ($scheme = $schemes->fetch_assoc())
		{
			$hasOwner = false;
			$pControl = "p".$scheme['pid']."Control";
			$sid = $scheme['schemeid'];
			$cids = $db->query("SELECT name FROM $pControl WHERE schemeid='$sid'");
			while ($cid = $cids->fetch_assoc())
			{
				if($cid['name'] == "recordowner")
				{
					$hasOwner = true;
				}
			}
			if(!$hasOwner)
			{
				//get sequence value
				$sequence = $db->query("SELECT sequence FROM $pControl WHERE collid=0
					ORDER BY sequence DESC LIMIT 1");
				$sequence = $sequence->fetch_assoc();
				$sequence = $sequence['sequence'] + 1;
				$options = "<options><regex></regex><rows>1</rows><columns>25</columns><defaultValue /></options>";
				$result = $db->query("INSERT INTO $pControl
					(schemeid, collid, type, name, required, searchable, advSearchable, showInResults, showInPublicResults, publicEntry, options, sequence)
					VALUES ('$sid', 0, 'TextControl', 'recordowner', 0, 1, 1, 0, 0, 1, '$options', '$sequence')");
				if(!$result)
				{
					$allUpdated = false;
				}
			}
		}
		echo "<strong>";
		if($allUpdated)
		{
			echo gettext('Successful');
		}
		else
		{
			echo '<font color="#ff0000">'.gettext('Failed').'</font>';
			$allTestsPassed = false;
		}
		echo "</strong><br/>";
	}
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.1.1' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.1.1';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
}
if(version_compare($systemInfo['version'], '2.2.0', '<'))
{
	// convert timestamps to ISO 8601 date.
	echo gettext('Converting time stamp format').'.....';
	flush();
	
	$allUpdated = true;
	
	$query = "SELECT pid FROM project";
	$projects = $db->query($query);
	
	while($project = $projects->fetch_assoc()){
		$pid = $project['pid'];
		
		// get all the timestamp controls
		$query = "SELECT * FROM p{$pid}Data WHERE cid IN (SELECT cid FROM p{$pid}Control WHERE name='systimestamp')";
		$systimestamps = $db->query($query);
		
		if($db->error){
			echo "<br/>Error: $db->error<br/>";
			echo $query.'<br/>';
			$allUpdated = false;
		}
		
		if ($systimestamps->num_rows > 0){
			while($systimestamp = $systimestamps->fetch_assoc()){
				$newTimeStamp = date('c',strtotime($systimestamp['value']));
				$query = "UPDATE p{$pid}Data SET value='$newTimeStamp' WHERE schemeid='$systimestamp[schemeid]' AND cid='$systimestamp[cid]' AND id='$systimestamp[id]'";
				$db->query($query);
				if($db->error){
					echo "<br/>Error: $db->error<br/>";
					echo $query.'<br/>';
					$allUpdated = false;
				}
			}
		}
	}
	
	echo "<br />";
	echo gettext("Creating project stats...");
	//add the quota and currentsize fields
	$projquery = "ALTER TABLE  `project` ADD  `quota` FLOAT NOT NULL ,ADD  `currentsize` FLOAT NOT NULL";
	$db->query($projquery);
	//get all the projectids
	$projects = $db->query("SELECT pid FROM project");
	if($projects->num_rows) {
		while($project = $projects->fetch_assoc()) {
			//get all the file and image controls from the pXControl table
			$pid = $project['pid'];
			$projfilesquery = "SELECT cid,schemeid from p".$pid."Control WHERE type='FileControl' OR type='ImageControl'";
			$filecontrols = $db->query($projfilesquery);
			if($filecontrols->num_rows) {
				$controls = array();
				while($filecontrol = $filecontrols->fetch_assoc()) {
					$controls[] = $filecontrol['cid'];
				}
				$filesquery = "SELECT value FROM p".$pid."Data WHERE cid in (".implode(',',$controls).")";
				$files = $db->query($filesquery);
				if($files->num_rows) {
					$sizecount = 0;
					while($file = $files->fetch_assoc()) {
						$fxml = simplexml_load_string($file['value']);
						$sizecount += $fxml->size;
					}
					//sizecount is in bytes.  We store quote in MB, so MB =  kb/1024 = (bytes/1024)/1024
					$sizecount = ($sizecount/1024)/1024;
					$updatequery = "UPDATE project SET currentsize='".$sizecount."' WHERE pid=".$pid;
					$db->query($updatequery);
				}
			}
		}
	}
	echo "<strong>";
	if($allUpdated)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong><br/>";
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.2.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.2.0';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
	flush();
}
if(version_compare($systemInfo['version'], '2.3.0', '<'))
{
	// THERE WERE NO DATABASE UPDATES BETWEEN 2.2.0 AND 2.3.0
	$allUpdated = true;
	
	if($allUpdated)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong><br/>";
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.3.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.3.0';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
	flush();
}
if(version_compare($systemInfo['version'], '2.5.0', '<'))
{
	// THERE WERE NO DATABASE UPDATES BETWEEN 2.3.0 AND 2.5.0
	$allUpdated = true;
	
	if($allUpdated)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong><br/>";
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.5.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.5.0';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
	flush();
}
if(version_compare($systemInfo['version'], '2.6.0', '<='))
{
	//New Table, koraPlugins
	$allUpdated = true;
	
	//Create koraPlugins Table,
	if(mysqli_num_rows($db->query("SHOW TABLES LIKE 'koraPlugins'"))==0) {
		echo gettext('Creating koraPlugins table...');
		$addKoraPluginQuery = "CREATE TABLE koraPlugins (
							pluginName varchar(64) NOT NULL,
							minKORAVer varchar(16) NOT NULL,
							minDBVer varchar(16) NOT NULL,
							javascriptFiles varchar(255) DEFAULT NULL,
							cssFiles varchar(255) DEFAULT NULL,
							menus MEDIUMTEXT NOT NULL,
							enabled tinyint(1) NOT NULL,
							fileName varchar(64) NOT NULL,
							description varchar(250) DEFAULT NULL,
							PRIMARY KEY (pluginName)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
		$addKoraPluginTable = $db->query($addKoraPluginQuery);
	} else {
		//Alter the menus column
		$alterMenuQuery = "ALTER TABLE koraPlugins CHANGE COLUMN menus menus MEDIUMTEXT NOT NULL";
		$alterMenu = $db->query($alterMenuQuery);
	}
	
	//Error check
	if($db->error){
					echo "<br/>Error: $db->error<br/>";
					if (isset($addKoraPluginQuery)) {echo $addKoraPluginQuery."<br/>";}
					else {echo $alterMenu."<br/>";}
					$allUpdated = false;
				}
	
	if($allUpdated)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong><br/>";
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.6.0' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.6.0';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
	flush();
}
if(version_compare($systemInfo['version'], '2.6.1', '<'))
{
	// THERE WERE NO DATABASE UPDATES BETWEEN 2.6.0 AND 2.6.1
	$allUpdated = true;
	
	if($allUpdated)
	{
		echo gettext('Successful');
	}
	else
	{
		echo '<font color="#ff0000">'.gettext('Failed').'</font>';
		$allTestsPassed = false;
	}
	echo "</strong><br/>";
	
	//Change version number
	if ($allTestsPassed)
	{
		$result = $db->query("UPDATE systemInfo SET version='2.6.1' WHERE baseURL=".escape(baseURI).' LIMIT 1');
		if (isset($_SESSION['dbVersion']))
		{
			$_SESSION['dbVersion'] = '2.6.1';
		}
	}
	//Mark anyUpdatesDone as true
	$anyUpdatesDone = true;
	echo '<br />';
	flush();
}


if (!$anyUpdatesDone)
{
	echo '<h3>'.gettext('No updates found; your database is up-to-date.').'</h3>';
}

if (!empty($messages))
{
	echo '<ul>';
	foreach($messages as $m)
	{
		echo "<li><strong>".gettext($m)."</strong></li>";
	}
	echo '</ul>';
}

Manager::PrintFooter();

?>
