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

/**
 * NOTE: ALL FUNCTIONS IN THIS FILE ASSUME THAT THEY ARE BEING CALLED WITH PROPER INPUT.
 * They perform no additional input validation, and such validation is the result of the
 * script using them!
 */

// Initial Version: Brian Beck, 2008

require_once('conf.php');
include_once('clientUtilities.php');
require('gettextSupport.php');
date_default_timezone_set('America/New_York');

/**
 * addReverseAssociation, removeReverseAssociation
 *
 * These manage the reverse associations (the implied controlid=0 "who
 *   associates to me" records).  These are here instead of in
 *   associatorControl.php since the reverse associations are managed in
 *   regular KORA code, not just associator code.
 *
 * The associator is, in fact, the cancer of KORA.  It has mutated beyond the
 * purpose and abilities of a mere control and has spread beyond the reaches of
 * its own code file.  It continutes to grow faster and faster, and less and less
 * people are able to understand it.  Soon, even those who had grown to know and
 * rely on the associator will become like those who question how to use it and
 * suggest that it be used in wrong and dark, evil ways.  It will eventually spell
 * the doom of us all.
 *
 * @param unknown_type $targetRecord
 * @param unknown_type $associationFrom
 */
function addReverseAssociation($targetRecord, $kidFrom, $cidFrom)
{
    global $db;
    
    // See if the target and association KIDs are valid
    $tInfo = parseRecordID($targetRecord);
    $aInfo = parseRecordID($kidFrom);
    
    if ($tInfo && $aInfo)
    {
        $table = 'p'.$tInfo['project'].'Data';
        
        $existenceQuery = $db->query("SELECT value FROM $table WHERE id=".escape($targetRecord).' AND cid=0 LIMIT 1');
        if ($existenceQuery->num_rows < 1)
        {
            // the record currently has no reverse association XML
            $xml = '<reverseAssociator><assoc><kid>'.xmlEscape($kidFrom).'</kid><cid>'.xmlEscape($cidFrom).'</cid></assoc></reverseAssociator>';
            $db->query('INSERT INTO '.$table.' (id, cid, schemeid, value) VALUES ('.escape($targetRecord).', 0, '.$tInfo['scheme'].', '.escape($xml).')');
        }
        else
        {
            $existenceQuery = $existenceQuery->fetch_assoc();
            $xml = simplexml_load_string($existenceQuery['value']);
            
            $duplicate = false;
            // Make sure this isn't a duplicate
            if (isset($xml->assoc))
            {
                foreach($xml->assoc as $assoc)
                {
                    if (((string)$assoc->kid == $kidFrom) && ((string)$assoc->cid == $cidFrom))
                    {
                        $duplicate = true;
                    }
                }
            }
            if (!$duplicate)
            {
                $assoc = $xml->addChild('assoc');
                $assoc->addChild('kid', xmlEscape($kidFrom));
                $assoc->addChild('cid', xmlEscape($cidFrom));
                $db->query('UPDATE '.$table.' SET value='.escape($xml->asXML()).' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
            }
        }
    }
}

function removeReverseAssociation($targetRecord, $kidFrom, $cidFrom)
{
    global $db;

    // See if the target and association KIDs are valid
    $tInfo = parseRecordID($targetRecord);
    $aInfo = parseRecordID($kidFrom);

    if ($tInfo && $aInfo)
    {
        $table = 'p'.$tInfo['project'].'Data';
        
        $existenceQuery = $db->query("SELECT value FROM $table WHERE id=".escape($targetRecord).' AND cid=0 LIMIT 1');
        if ($existenceQuery->num_rows > 0)
        {
            $existenceQuery = $existenceQuery->fetch_assoc();
            $xml = simplexml_load_string($existenceQuery['value']);
            
            $newXML = simplexml_load_string('<reverseAssociator />');
            
            // Build the new XML set
            if (isset($xml->assoc))
            {
                foreach($xml->assoc as $assoc)
                {
                    if (((string)$assoc->kid != $kidFrom) || ((string)$assoc->cid != $cidFrom))
            {
                $newAssoc = $newXML->addChild('assoc');
                $newAssoc->addChild('kid', (string)$assoc->kid);
                $newAssoc->addChild('cid', (string)$assoc->cid);
            }
        }
            }
            
            // Update the database.  If there's still at least one reverse assocation,
            // update the row.  Otherwise, delete it
            if (isset($newXML->assoc))
            {
                $db->query('UPDATE '.$table.' SET value='.escape($newXML->asXML()).' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
            }
            else
            {
                $db->query('DELETE FROM '.$table.' WHERE cid=0 AND id='.escape($targetRecord).' LIMIT 1');
            }
            
        }
        // else { we don't care because there's no record to clean }
    }
}

/**
 * Displays a set of breadcrumb navigation for a page system
 *
 * Parameters:
 *
 * @int maxPage - The final page (assumes first page is 1)
 * @int currentPage - Somewhere in the range of [1, maxPage]
 * @int adjacents - The number of adjacent records to show to the current Page
 * @string pageLink - The href or onclick portion of a link to add the page number
 *                    to using printf syntax inside of an <a> tag, eg:
 *                    href="viewObject.php?rid=%d"
 */
function breadCrumbs($maxPage, $currentPage, $adjacents, $pageLink)
{
    $crumbs = '';
    if ($maxPage > 1)
    {
        // Display "Prev" link
        if ($currentPage == 1)
        {
            $crumbs .= gettext('Prev').' | ';
        }
        else
        {
            $crumbs .= '<a '.sprintf($pageLink, ($currentPage - 1)).'>'.gettext('Prev').'</a> | ';
        }
        
        if ($maxPage < (7 + $adjacents * 2))
        {
            // There's not enough pages to bother breaking it up, so
            // display them all
            
            for($i=1; $i <= $maxPage; $i++)
            {
                if ($i != $currentPage)
                {
                   $crumbs .= '<a '.sprintf($pageLink, $i).">$i</a> | ";
                }
                else
                {
                    $crumbs .= "$i | ";
                }
            }
        }
        else   // if lastpage > (6 + ADJACENTS * 2)
        {
            if ($currentPage < (1 + $adjacents * 2))
            {
                // we're near the beginning
                
                // show the early pages
                for($i=1; $i <= (4 + $adjacents * 2); $i++)
                {
                    if ($i != $currentPage)
                    {
                       $crumbs .= '<a '.sprintf($pageLink, $i).">$i</a> | ";
                    }
                    else
                    {
                        $crumbs .= "$i | ";
                    }
                }

                // show the ... and the last two pages
                $crumbs .= '... | <a '.sprintf($pageLink, ($maxPage - 1)).'>'.($maxPage - 1).'</a> | <a '.sprintf($pageLink, $maxPage).'>'.$maxPage.'</a> | ';
            }
            else if ((($maxPage - $adjacents * 2) > $currentPage) && ($currentPage > ($adjacents * 2)))
            {
                // we're in the middle

                // display the first two pages and ...
                $crumbs .= '<a '.sprintf($pageLink, 1).'>1</a> | <a '.sprintf($pageLink, 2).'>2</a> | ... | ';

                // display the middle pages
                for($i=$currentPage-$adjacents; $i <= ($currentPage + $adjacents); $i++)
                {
                    if ($i != $currentPage)
                    {
                       $crumbs .= '<a '.sprintf($pageLink, $i).">$i</a> | ";
                    }
                    else
                    {
                        $crumbs .= "$i | ";
                    }
                }
                
                // show the ... and the last two pages
                $crumbs .= '... | <a '.sprintf($pageLink,($maxPage - 1)).'>'.($maxPage - 1).'</a> | <a '.sprintf($pageLink,$maxPage).'>'.$maxPage.'</a> | ';
            }
            else
            {
                // we're at the end
                
                // display the first two pages and ...
                $crumbs .= '<a '.sprintf($pageLink,1).'>1</a> | <a '.sprintf($pageLink,2).'>2</a> | ... | ';
                
                // display the final pages
                for($i=($maxPage - (2 + $adjacents * 2)); $i <= $maxPage; $i++)
                {
                    if ($i != $currentPage)
                    {
                       $crumbs .= '<a '.sprintf($pageLink,$i).">$i</a> | ";
                    }
                    else
                    {
                        $crumbs .= "$i | ";
                    }
                }
            }
        }
        
        // Display "Next" link
        if ($currentPage == $maxPage)
        {
            $crumbs .= gettext('Next');
        }
        else
        {
            $crumbs .= '<a '.sprintf($pageLink,($currentPage + 1)).'>'.gettext('Next').'</a>';
        }
    }
    
    return $crumbs;
}

/**
 * Cleans up the associations to an object when it's deleted
 */
function cleanUpAssociatorOnDelete($rid)
{
    global $db;
    
    $kidInfo = parseRecordID($rid);
    $pid = $kidInfo['project'];
    
    $assocQuery = $db->query('SELECT value FROM p'.$pid.'Data WHERE id='.escape($rid).' AND cid=0 LIMIT 1');
    if ($assocQuery->num_rows > 0)
    {
        $assocQuery = $assocQuery->fetch_assoc();
        $xml = simplexml_load_string($assocQuery['value']);
        
        if (isset($xml->kid))
        {
            foreach($xml->kid as $kid)
            {
                removeAllAssociations((string)$kid, $rid);
            }
        }
    }
    
    // Clean up the list of things that associate to this record
    $db->query('DELETE FROM p'.$pid.'Data WHERE cid=0 AND id='.escape($rid).' LIMIT 1');
}

/**
 * Creates a thumbnail of an image
 *
 * Parameters:
 *
 * @string $origFile - Local Path to Original File
 * @string $destFile - Local Path to Thumbnail File
 * @int $maxWidth - Maximum Thumbnail Width
 * @int $maxHeight - Maximum Thumbnail Height
 */
function createThumbnail($origFile, $destFile, $maxWidth, $maxHeight)
{
    if ($maxWidth < 1 || $maxHeight < 1 || !file_exists($origFile) || getimagesize($origFile) === false)
    {
//    	echo "<strong>Unable to create thumbnail for:</strong> $origFile<br>";
//    	if(!file_exists($origFile)) echo "<strong>File does not exist.</strong><br/>";
//    	else echo getimagesize($origFile);
        return FALSE;
    }
    
    // Figure out the new dimensions
    $originalSize = getimagesize($origFile);
    $originalWidth = $originalSize[0];
    $originalHeight = $originalSize[1];
            
    // First attempt: Fit to width
    $scale = ((float)$maxWidth) / $originalWidth;
    $thumbWidth = $maxWidth;
    $thumbHeight = (int) ($originalHeight * $scale);
            
    // If the height is too large, fit to it instead
    if ($thumbHeight > $maxHeight)
    {
        $scale = ((float)$maxHeight) / $originalHeight;
        $thumbWidth = (int) ($originalWidth * $scale);
        $thumbHeight = $maxHeight;
    }
    
    if ($originalSize['mime'] == 'image/jpeg' || $originalSize['mime'] == 'image/pjpeg')
    {
        $imageCreateFunction = 'imagecreatefromjpeg';
        $imageFunction = 'imagejpeg';
    }
    else if ($originalSize['mime'] == 'image/gif')
    {
        $imageCreateFunction = 'imagecreatefromgif';
        $imageFunction = 'imagegif';
    }
    else if ($originalSize['mime'] == 'image/png' || $originalSize['mime'] == 'image/x-png')
    {
        $imageCreateFunction = 'imagecreatefrompng';
        $imageFunction = 'imagepng';
    }
    else if ($originalSize['mime'] = 'image/bmp')
    {
        $imageCreateFunction = 'imagecreatefromwbmp';
        $imageFunction = 'imagewbmp';
    }
            
    // Create the Image
    $originalImage = $imageCreateFunction($origFile);
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
    imagecopyresampled($thumbnail, $originalImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);
    imagedestroy($originalImage);
            
    $imageFunction($thumbnail, $destFile);
    imagedestroy($thumbnail);
}

/**
 * Deletes a Collection
 *
 * @param unsigned_integer $collid
 */
function deleteCollection($collid)
{
    global $db;
    
    $collInfo = $db->query("SELECT collection.schemeid AS schemeid, scheme.pid AS pid, collection.sequence AS sequence FROM collection LEFT JOIN scheme USING (schemeid) WHERE collection.collid=$collid");
    $collInfo = $collInfo->fetch_assoc();
    
    $cTable = 'p'.$collInfo['pid'].'Control';
    
    // get list of controls in collection
    $controlList = $db->query("SELECT cid FROM $cTable WHERE collid=$collid");
    
    // call deleteControl on each
    while($c = $controlList->fetch_assoc()) {
        deleteControl($c['cid'], $collInfo['schemeid'], $collInfo['pid']);
    }
    
    // update sequence of other collections and delete record from collection table
    $db->query("UPDATE collection SET sequence=(sequence-1) WHERE schemeid=$collInfo[schemeid] AND sequence > $collInfo[sequence]");
    $db->query("DELETE FROM collection WHERE collid=$collid");
}

/**
 * Deletes a Control
 *
 * @param unsigned_integer $collid
 */
function deleteControl($cid, $sid='', $pid='')
{
    global $db;
    
    if (empty($sid)) $sid=$_SESSION['currentScheme'];
    if (empty($pid)) $pid=$_SESSION['currentProject'];
    
    $dTable = 'p'.$pid.'Data';
    $cTable = 'p'.$pid.'Control';
    
    $query  = "SELECT $cTable.collid AS collid, $cTable.type AS type, control.file AS file, ";
    $query .= "$cTable.sequence AS sequence FROM $cTable ";
    $query .= "LEFT JOIN control ON control.class = $cTable.type WHERE $cTable.cid=$cid";
    $controlInfo = $db->query($query);
    $controlInfo = $controlInfo->fetch_assoc();

    // Include the Control Class so we can instantiate it to delete the Data
    require_once(basePath.CONTROL_DIR.$controlInfo['file']);
    
    $theControl = new $controlInfo['type']($pid, $cid);
    $theControl->delete();
    
    // instantiate a version for the public table, so any data associated with
    // this control gets deleted in the PublicData table aswell.
    $theControl = new $controlInfo['type']($pid, $cid, '', '', true);
    $theControl->delete();
    
    // rebuild Dublin Core data if necessary
    $dcQuery = $db->query('SELECT dublinCoreFields FROM scheme WHERE schemeid='.escape($sid).' LIMIT 1');
    $dcQuery = $dcQuery->fetch_assoc();
    if (!empty($dcQuery['dublinCoreFields']))
    {
        $oldXML = simplexml_load_string($dcQuery['dublinCoreFields']);
        $newXML = simplexml_load_string('<dublinCore />');
        // copy all fields that don't match the cid
        $somethingChanged = false;
        foreach($oldXML->children() as $dcType)
        {
            $idsToAdd = array();
            if (isset($dcType->id))
            {
                foreach($dcType->id as $id)
                {
                    if ((string)$id != $cid)
                    {
                        $idsToAdd[] = (string) $id;
                    }
                    else
                    {
                        $somethingChanged= true;
                    }
                }
            }
            // If there are any control left in this field, add it
            if (!empty($idsToAdd))
            {
                $field = $newXML->addChild($dcType->getName());
                foreach($idsToAdd as $id)
                {
                    $field->addChild('id', $id);
                }
            }
        }
        
        if ($somethingChanged)
        {
            $db->query('UPDATE scheme SET dublinCoreFields='.escape($newXML->asXML()).',dublinCoreOutOfDate=1 WHERE schemeid='.escape($sid).' LIMIT 1');
        }
    }
    
    // update sequence of other controls and delete record from collection table
    $db->query("UPDATE $cTable SET sequence=(sequence-1) WHERE collid=$controlInfo[collid] AND sequence > $controlInfo[sequence]");
    $db->query("DELETE FROM $cTable WHERE cid=$cid");
}

/**
 * Deletes a Project
 *
 * @param unsigned_integer pid
 */
function deleteProject($pid)
{
    global $db;
    
    // get schemes in project
    $schemeList = $db->query("SELECT schemeid FROM scheme WHERE pid=$pid");
    
    // call deleteScheme on each
    while ($s = $schemeList->fetch_assoc()) {
        deleteScheme($s['schemeid']);
    }
    
    // delete project tables
    $db->query('DROP TABLE IF EXISTS p'.$_POST['pid'].'Control');
    $db->query('DROP TABLE IF EXISTS p'.$_POST['pid'].'Data');
    $db->query('DROP TABLE IF EXISTS p'.$_POST['pid'].'PublicData');
    
    // final cleanup
    $deleteResults = $db->query('DELETE FROM project WHERE pid='.escape($_POST['pid']));
    $deleteResults = $db->query('DELETE FROM permGroup WHERE pid='.escape($_POST['pid']));
    $deleteResults = $db->query('DELETE FROM member WHERE pid='.escape($_POST['pid']));
    $deleteResults = $db->query('DELETE FROM dublinCore WHERE pid='.escape($_POST['pid']));
}

/**
 * Deletes a Scheme
 *
 * @param unsigned_integer $schemeid
 */
function deleteScheme($schemeid)
{
    global $db;
    
    $schemeInfo = $db->query('SELECT pid, sequence FROM scheme WHERE schemeid='.escape($schemeid));
    if ($schemeInfo->num_rows == 0) return;
    $schemeInfo = $schemeInfo->fetch_assoc();
    
    // Clean up any reverse associations to objects in this scheme
    $assocQuery = $db->query('SELECT id, value FROM p'.$schemeInfo['pid'].'Data WHERE schemeid='.escape($schemeid).' AND cid=0');
    while($a = $assocQuery->fetch_assoc())
    {
        $xml = simplexml_load_string($a['value']);
        if (isset($xml->kid))
        {
            foreach($xml->kid as $kid)
            {
                // remove the association from $kid to $a['id']
                removeAllAssociations($kid, $a['id']);
            }
        }
    }
    
    // get list of collections in scheme
    $collectionList = $db->query("SELECT collid FROM collection WHERE schemeid=$schemeid");
    
    // call deleteCollection on each
    while ($c = $collectionList->fetch_assoc()) {
        deleteCollection($c['collid']);
    }
    
    
    // special case to delete systimestamp and recordowner from project control and project data tables
    // and public data table
    $projID = $db->query("SELECT pid FROM scheme WHERE schemeid=$schemeid");
    $projID = $projID->fetch_assoc();//why doesn't this work??
    $projControl = "p".$projID['pid']."Control";
    $projData = "p".$projID['pid']."Data";
    $publicData = "p".$projID['pid']."PublicData";
    $delControl = $db->query("DELETE FROM $projControl WHERE schemeid=$schemeid");
    $delData = $db->query("DELETE FROM $projData WHERE schemeid=$schemeid");
    $delPublicData = $db->query("DELETE FROM $publicData WHERE schemeid=$schemeid");
    
    //delete remainder of scheme info
    $query = $db->query("UPDATE scheme SET sequence = (sequence-1) WHERE sequence > $schemeInfo[sequence] AND pid=".$schemeInfo['pid']);
    $query = $db->query("DELETE FROM scheme WHERE schemeid=$schemeid");
    $query = $db->query("DELETE FROM recordPreset WHERE schemeid=$schemeid");
    
    // TODO: delete PREMIS TABLES
}

/**
 * Simple wrapper for the database escaping function
 *
 * @param string $rawString
 * @param bool $addQuotes
 * @return string $escapedString
 */
function escape($rawString, $addQuotes=true) {
    global $db;
    if ($addQuotes==true){
        return "'".$db->real_escape_string($rawString)."'";
    } else {
        return $db->real_escape_string($rawString);
    }
}

/**
 * Extracts a zip folder into the extractFileDir.
 *
 * This function expects a zip file with a folder with the same name
 * as the zip file.  That folder will contain any data. Any file
 * outside of that folder will not appear in the return array, but
 * will be extracted into extractFileDir also.
 *
 * @param $tmpZipFolder : uploaded zip folder
 * @param $originalName : original name of zip folder
 * @return $extractedFiles : array of filenames of the extracted files
 */
function extractZipFolder($tmpZipFolder,$originalName) {
	$zip = new ZipArchive();
	$extractedFiles = array();
	if ($zip->open($tmpZipFolder)) {
		//get folder name
		$pathInfo = pathinfo($originalName);
		$folderName = $pathInfo['filename'];
		if ($zip->extractTo(basePath.extractFileDir) && $files = scandir(basePath.extractFileDir.$folderName)) {
			//get files in extracted folder
			
			
			foreach ($files as $file){
				if ($file == "." || $file == "..") continue;
				rename(basePath.extractFileDir.$folderName."/".$file, basePath.extractFileDir."/".$file);
				$extractedFiles[]=$file;
			}
			
			//remove extracted folder
			rmdir(basePath.extractFileDir.$folderName);
			
			//return $extensionNames;
		} else {
			print '<div class="error">'.gettext('Could not extract the zip folder. Please try again.').'</div>';
			$extractedFiles=false;
		}
		$zip->close();
	} else {
		print '<div class="error">'.gettext('There was trouble opening the zip folder.  Please try again.').'</div>';
		$extractedFiles=false;
	}
	return $extractedFiles;
}


/**
 * Gets a list of all currently installed KORA controls
 *
 * @return array
 */
function getControlList()
{
    global $db;

    $controlList = array();
    
    $result = $db->query("SELECT name, file, class FROM control ORDER BY name");
    while($r = $result->fetch_assoc()) { $controlList[] = $r; }
    
    return $controlList;
}

/**
 * Returns the Dublin Core associated control IDs and the field they are associated with.  Returns 0 if no fields / error.
 *
 * @param integer $schemeid
 * @param integer $projectid
 */
function getDublinCoreFields($schemeid,$projectid) {
   global $db;
   if($schemeid <= 0 || $projectid <= 0)   return 0;
   $query = "SELECT dublinCoreFields FROM scheme WHERE schemeid=$schemeid AND pid=$projectid AND dublinCoreFields IS NOT NULL LIMIT 1";
   $results = $db->query($query);
   if($results->num_rows<=0)  return 0;
   $array = $results->fetch_assoc();
   $dcxml = simplexml_load_string($array['dublinCoreFields']);
   $dcfields = array();
   foreach($dcxml->children() as $dctypes) {
      if(count($dctypes->children() > 0 )) {
         $ids = array();
         foreach($dctypes->children() as $cids) {
            $ids[] = $cids;
         }
         if(count($ids) > 0)  //if tags are left by the remove calls, this will make sure only tags w/ ids are included
            $dcfields[$dctypes->getName()] = $ids;
      }
   }
   return $dcfields;
}

/**
 * Given a Record ID and Control ID, gets the local filename
 * If bad inputs given, returns null
 *
 * @param string $rid
 * @param string $cid
 *
 * @return string
 */
function getFilenameFromRecordID($rid, $cid)
{
    global $db;
    
    $recordInfo = parseRecordID($rid);
    
    $query = 'SELECT value FROM p'.$recordInfo['project'].'Data WHERE id='.escape($rid).' AND cid='.escape($cid).' LIMIT 1';
    $query = $db->query($query);
    
    // make sure data was returned
    if($query->num_rows != 1) return '';
    
    $fileInfo = $query->fetch_assoc();
    $fileInfo = simplexml_load_string($fileInfo['value']);
    
    return basePath.fileDir.$recordInfo['project'].'/'.$recordInfo['scheme'].'/'.$fileInfo->localName;
}

/**
 * Given a Project ID, SchemeID, Control ID and Record ID, gets the local filename
 * for the file that's awaiting approval from a moderator
 * If bad inputs given, returns null
 *
 * @param string $pid
 * @param string $sid
 * @param string $cid
 * @param string $rid
 *
 * @return string
 */
function publicGetFilenameFromRecordID($pid, $sid, $cid, $rid)
{
    global $db;
    
	$rid = (int)$rid;
    
    $query = 'SELECT value FROM p'.$pid.'PublicData WHERE id='.escape($rid).' AND cid='.escape($cid).' LIMIT 1';
    $query = $db->query($query);
    
    // make sure data was returned
    if($query->num_rows != 1) return '';
    
    $fileInfo = $query->fetch_assoc();
    $fileInfo = simplexml_load_string($fileInfo['value']);
    
    return basePath.awaitingApprovalFileDir.$pid.'/'.$sid.'/'.$fileInfo->localName;
}



/**
 * Gets the next Record ID for a scheme and updates the counter
 *
 * @return string
 */
function getNewRecordID($schemeid)
{
    global $db;
    
    $schemeInfo = $db->query("SELECT schemeid, pid, nextid FROM scheme WHERE schemeid=".escape($schemeid)." LIMIT 1");
    if ($schemeInfo = $schemeInfo->fetch_assoc())
    {
        $updateQuery = $db->query("UPDATE scheme SET nextid=nextid+1 WHERE schemeid=".escape($schemeid)." LIMIT 1");
        return  strtoupper(dechex($schemeInfo['pid'])).'-'
               .strtoupper(dechex($schemeInfo['schemeid'])).'-'
               .strtoupper(dechex($schemeInfo['nextid']));
    }
    else return '';
}



/**
 * Gets the next Public Record ID for a scheme and updates the counter
 *
 * @return string
 */
function getNewPublicRecordID($projID)
{
    global $db;
    
    $schemeInfo = $db->query("SELECT id FROM p".$projID."PublicData ORDER BY id DESC LIMIT 1");
    if ($schemeInfo = $schemeInfo->fetch_assoc())
    {
    	return $schemeInfo['id']+1;
    }
	else return '1';
}



/**
 * Gets the permissions values the user has for the current project.  If no
 * project is currently selected or the user is not a member of the current project,
 * 0 is returned
 *
 * @return int permissions
 */
function getUserPermissions($pid='') {
    global $db;
    
    if (empty($pid)) {
        // Eventually this should be fixed.  For some reason, returning 0
        // if it's not set does weird things.
        @$pid=$_SESSION['currentProject'];
    }
    
    // get the user's permissions
    $results = $db->query('SELECT permGroup.permissions AS permissions FROM member LEFT JOIN permGroup ON member.gid = permGroup.gid WHERE member.uid='.escape($_SESSION['uid']).' AND member.pid='.escape($pid).' LIMIT 1');
    if (!$results || ($results->num_rows == 0)) return 0;
    else {
        $results = $results->fetch_assoc();
        return $results['permissions'];
    }
}

/**
 * Get the full path to a file from its filename.  Expects a valid internal localName
 * From KORA
 *
 * @param string $filename
 */
function getThumbPathFromFileName($filename)
{
    $fileParts = explode('-', $filename);
    $pid = hexdec($fileParts[0]);
    $sid = hexdec($fileParts[1]);
    
    return basePath.fileDir.$pid.'/'.$sid.'/thumbs/'.$filename;
}

/**
 * Gets the list of projects a token has authentication to search
 *
 * @return array validProjects
 */
function getTokenPermissions($token)
{
    global $db;
    
    $validProjects = array();
    
    $projectQuery = $db->query('SELECT member.pid AS pid FROM member LEFT JOIN user USING (uid) WHERE user.password='.escape($token));
    while($result = $projectQuery->fetch_assoc())
    {
        $validProjects[] = $result['pid'];
    }
    
    return $validProjects;
}

/**
 * Checks to see if the user has certain permissions for a project
 *
 * @param unsigned_integer $permissions
 * @param unsigned_integer $pid
 * @return boolean
 */
function hasPermissions($permissions=0, $pid='')
{
    if (empty($pid)) $pid=$_SESSION['currentProject'];
    if (!isLoggedIn()) return false;
    $u = getUserPermissions($pid);
    return (($u & $permissions) || ($u & PROJECT_ADMIN) || isSystemAdmin());
}

/**
 * Simple wrapper for HTML escaping function
 *
 * @param string $rawString
 * @return string $escapedString
 */
function htmlEscape($rawString) {
    return str_replace("\n", '<br />', htmlspecialchars($rawString, ENT_QUOTES, "UTF-8"));
}

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
function internalSearchResults($pid,
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
    if (isSystemAdmin())
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
    

    
    /*** STEP 4: DISPLAYING THE RESULTS ***/

    $returnString = '';   // The string showing the results, which is returned
    $returnString .= gettext('Number of search results').": $totalRecords";
    $returnString .= '<br /><br />';
    
    $navigation  = '<strong>'.gettext('Jump to Page').':</strong> ';
    $bc = breadCrumbs($maxPage, $pageNum, ADJACENT_PAGES_SHOWN, $searchLink);
    $navigation .= $bc;
    // See if a "View All" link needs to be shown
    if (!empty($bc) && isProjectAdmin() && $resultsPerPage < RESULTS_IN_VIEWALL_PAGE)
    {
        $navigation .= '&nbsp;&nbsp;&nbsp;<a '.sprintf(substr($searchLink, 0, strlen($searchLink)-1).'&amp;viewall=1"', 1).'>'.gettext('View All').'</a>';
    }
    $navigation .= '<br /><br />';
    
    if ($maxPage > 1 && $showTopBreadCrumbs)
    {
       $returnString .= $navigation;
    }
    
    foreach($displayQueries as $dispQ)
    {
        $objectQuery = $db->query($dispQ['query']);
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
        $controlQuery = 'SELECT cid, schemeid FROM p'.$dispQ['pid'].'Control WHERE showInResults=1';
        if (!empty($sid))
        {
            $controlQuery .= ' AND schemeid IN ('.implode(',', $sid).') ';
        }
        $controlQuery .= ' ORDER BY schemeid, cid';
        $controlQuery = $db->query($controlQuery);
        $controls = array(-1);
        while ($cid = $controlQuery->fetch_assoc())
        {
            $controls[] = $cid['cid'];
        }
        
        $controlType = getControlTypes($dispQ['pid'], $controls);
        
        // Get the Data
        $dataTable = 'p'.$dispQ['pid'].'Data';
        $controlTable = 'p'.$dispQ['pid'].'Control';
        $dataQuery  = "SELECT $dataTable.id AS id, $dataTable.cid AS cid, ".$dispQ['pid']." AS pid, $dataTable.schemeid AS schemeid, $dataTable.value AS value FROM $dataTable ";
        $dataQuery .= "LEFT JOIN $controlTable USING (cid) ";
        $dataQuery .= "LEFT JOIN collection ON ($controlTable.collid = collection.collid) ";
        $dataQuery .= 'WHERE id IN ('.implode(',', $records).') ';
        $dataQuery .= 'AND cid IN ('.implode(',', $controls).') ';
        $dataQuery .= "ORDER BY SUBSTRING_INDEX($dataTable.id, '-', 2), CAST(CONV( SUBSTRING_INDEX($dataTable.id, '-', -1), 16, 10) AS UNSIGNED), collection.sequence, $controlTable.sequence";
        //$dataQuery .= "ORDER BY $dataTable.id, collection.sequence, $controlTable.sequence";
        $dataQuery = $db->query($dataQuery);
        
        
        // we need to display something for results that have no displayable
        // controls, so we create an array with all results as keys and then
        // add displayable controls to the corresponding key
        $datas = array();
        foreach ($records as $record){
        	// record numbers are enclosed in quotes for the query, so
        	// we need to remove them
        	$record = str_replace("'","", $record);
        	$datas[$record]=array();
        }
        while($data = $dataQuery->fetch_assoc()){
        		$datas[$data['id']][]=$data;
        }

        foreach($datas as $rid=>$data){
        	if ($rid == -1) continue;
            $returnString .= '<table class="table"><tr><td colspan="2">';
            $returnString .= "<a ".sprintf($kidLink,$rid).">$rid</a>";
            $returnString .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            $returnString .= '<a href="editObject.php?rid='.$rid.'">'.gettext('edit').'</a> | ';
			$returnString .= '<a href="deleteObject.php?rid='.$rid.'">'.gettext('delete').'</a>';
            $returnString .= '</td></tr>';
            foreach($data as $control){
	            // Instantiate an empty control of the necessary class and use it to convert the
	            // value (potentially in XML) to a pretty display format
	            $theControl = new $controlType[$control['cid']]['class'];
		        $returnString .= '<tr><td class="kora_ccLeftCol">';
		        $returnString .= htmlEscape($controlType[$control['cid']]['name']);
		        $returnString .= '</td><td>';
		        $returnString .= $theControl->storedValueToDisplay($control['value'], $control['pid'], $control['cid']);
		        $returnString .= '</td></tr>';
            }
            if($isAssociatorSearch){
                $returnString .= '<tr><td colspan="2">';
				$returnString .= '<a class="link" '.sprintf($kidLink, $rid).'>'.gettext('Associate this Record').'</a> | <a href="viewObject.php?rid='.$rid.'">'.gettext('Show Detailed Record View').'</a>';
                $returnString .= '</td></tr>';
            }
            $returnString .= '</table>';
        }
    }
    
    if ($maxPage > 1 && $showBottomBreadCrumbs)
    {
       $returnString .= $navigation;
    }

    return $returnString;
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
function sortedInternalSearchResults($pid,
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
    if (isSystemAdmin())
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
    
    /*** STEP 3: Create an array of sorted KID's ***/
    
    //make a list of all the cid's that are DateControl's
    $dateControlArray = array();
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
    $idArray = array();
    while($id = $ids->fetch_assoc())
    {
    	$idArray[] = $id['id'];
    }
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
    else//takes care of everything that is not a date
    {
    	$sortQuery = "SELECT id, value FROM $projData WHERE cid=$sortBy AND id IN $idString ORDER BY value $order";
    	$sortQuery = $db->query($sortQuery);
    	while($val = $sortQuery->fetch_assoc())
    	{
    		$sortArray[$val['id']] = $val['value'];
    	}
    }
    
    //extract the values out leaving only the keys
    $sortArray = array_keys($sortArray);
    
    //append to the array with any id's that didn't have the sortBy control ingested (might be none)
    foreach($idArray as $v)
    {
    	if(!in_array($v, $sortArray))
    	{
    		$sortArray[] = $v;
    	}
    }
    
    return $sortArray;
}


/**
 * Given an array of control ids, return a 2d array with information about each control id
 * Also includes the files for each control in the cid array
 *
 * @return array
 */
function getControlTypes($pid, $cids) {
	
	global $db;
	// get an array of the type of each control id so that we don't have to look it up each time
	$cTable = 'p'.$pid.'Control';
	$typeQuery  = "SELECT $cTable.cid AS cid, $cTable.name AS name, $cTable.type AS class, control.file AS file ";
	$typeQuery .= "FROM $cTable LEFT JOIN control ON ($cTable.type = control.class) ";
	$typeQuery .= "WHERE $cTable.cid IN (".implode(',', $cids).')';
	        
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
	return $controlType;
}

/**
 * Simple function to check if a user is logged in.  Based on checking session variable for a userid greater than 0.
 *
 * @return true / false
 */
function isLoggedIn() {
    return (isset($_SESSION['uid']) && $_SESSION['uid'] > 0 );
}

/**
 * Function to check if the user has rights to the currently selected project
 *
 * @return true / false
 */
function isProjectAdmin() {
    global $db;
    
    if (!isset($_SESSION['currentProject'])
      || empty($_SESSION['currentProject'])) return false;
    if (isSystemAdmin()) return true;
    
    $query = "SELECT member.uid FROM member JOIN project ";
    $query .= "WHERE project.pid='".$_SESSION['currentProject'];
    $query .= "' AND member.gid = project.admingid AND member.uid='".$_SESSION['uid']."' LIMIT 1";
        
    $results = $db->query($query);
    return ($results->num_rows > 0);
}

/**
 * Simple function to check for kora admin rights.
 *
 * @return true / false
 */
function isSystemAdmin() {
    return (isset($_SESSION['admin']) && $_SESSION['admin'] == true );
}

/**
 * Removes all associations from controls in $fromKID to $toKID
 *
 * @param kid $fromKID
 * @param kid $toKID
 */
function removeAllAssociations($fromKID, $toKID)
{
    global $db;
    
    $fromInfo = parseRecordID($fromKID);
    $toInfo = parseRecordID($toKID);
    
    if ($fromInfo && $toInfo)
    {
        $controlQuery = 'SELECT cid FROM p'.$fromInfo['project'].'Control WHERE schemeid='.$fromInfo['scheme'].' AND type=\'AssociatorControl\'';
        $dataQuery = 'SELECT id, cid, value FROM p'.$fromInfo['project'].'Data WHERE cid IN ('.$controlQuery.') AND id='.escape($fromKID);
        $dataQuery = $db->query($dataQuery);
                    
        while($associator = $dataQuery->fetch_assoc())
        {
            $oldXML = simplexml_load_string($associator['value']);
            $newXML = simplexml_load_string('<associator />');
                        
            foreach($oldXML->children() as $childType => $childValue)
            {
                if ($childType != 'kid')    // anything other than a <kid> tag should be
                {                           // maintained, although it shouldn't exist
                    $newXML->addChild($childType, xmlEscape($childValue));
                }
                else if ($childValue != $toKID)   // otherwise, add in all <kid> tags except the
                {                               // one that needs to be deleted.
                    $newXML->addChild($childType, xmlEscape($childValue));
                }
            }
                        
            // Update the database
            if (isset($newXML->kid))
            {
                $db->query('UPDATE p'.$fromInfo['project'].'Data SET value='.escape($newXML->asXML()).'WHERE id='.escape($associator['id']).' AND cid='.escape($associator['cid']).' LIMIT 1');
            }
            else
            {
                $db->query('DELETE FROM p'.$fromInfo['project'].'Data WHERE id='.escape($associator['id']).' AND cid='.escape($associator['cid']).' LIMIT 1');
            }
        }
    }
}

/**
 * Redirects to specified page if not logged in
 *
 * @param string $location
 */
function requireLogin($location = 'login.php')
{
    if (!isLoggedIn())
    {
        header("Location: $location");
        die();
    }
}

/**
 * Redirects to specified page if the currently logged-in user lacks the specified permissions
 * for the currently-selected project
 *
 * @param unsigned_integer $permissions
 * @param string $location
 */
function requirePermissions($permissions, $location)
{
    requireProject();
    if (!hasPermissions($permissions))
    {
        header("Location: $location");
        die();
    }
}

/**
 * Redirects to specified page is no project is currently selected
 *
 * @param string $location
 */
function requireProject($location = 'selectProject.php')
{
    requireLogin();
    if (!isset($_SESSION['currentProject']))
    {
        header("Location: $location");
        die();
    }
}

/**
 * Redirects to specified page if current user is not an Admin of the current Project
 *
 * @param string $location
 */
function requireProjectAdmin($location = 'projectIndex.php')
{
    if (!isProjectAdmin())
    {
        header("Location: $location");
        die();
    }
}

/**
 * Redirects to specified page if no scheme is currently selected
 *
 * @param string $location
 */
function requireScheme($location = 'selectScheme.php')
{
    requireLogin();
    if (!isset($_SESSION['currentScheme']))
    {
        header("Location: $location");
        die();
    }
}

/**
 * Redirects to specified page if current user is not a System Admin
 *
 * @param string $location
 */
function requireSystemAdmin($location = 'index.php')
{
    if (!isSystemAdmin())
    {
        header("Location: $location");
        die();
    }
}

function resetNextRecordId($schemeId) {
	global $db;
	
    $updateQuery = $db->query("UPDATE scheme SET nextid=nextid-1 WHERE schemeid=".escape($schemeId)." LIMIT 1");
	if ($updateQuery) {
		return true;
	} else {
		return false;
	}
}

/**
 * Escapes XML Special Characters
 *
 * @param string $xml
 */
function xmlEscape($rawString)
{
    return str_replace(array('&', '<', '>', '"', "'"), array('&amp;','&lt;', '&gt;', '&quot;', '&apos;'), $rawString);
}

function showAddControlForm($error='')
{
	//start output of page
	require_once('includes/header.php');
	?>
    <h2><?php echo gettext('Add a Control');?></h2>
	<form action="" method="post">
	    <table class="table_noborder">
	    <?php  if (!empty($error)) echo '<div class="error">'.gettext($error).'</div>'; ?>
	        <tr><td align="right"><?php echo gettext('Control to Add');?>:</td><td><select name="type">
	        <?php
	        $controlList = getControlList();
	        foreach($controlList as $c) {
		       echo '<option value="'.$c['class'].'">'.gettext($c['name']).'</option>';
	        }
	        ?>
	        </select></td></tr>
	        <tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" name="name" /></td></tr>
	        <tr><td align="right"><?php echo gettext('Description');?>:</td><td align="right"><textarea name="description" cols="20" rows="3"></textarea></td></tr>
	        <tr><td align="right"><?php echo gettext('Required');?>?</td><td><input type="checkbox" name="required" /></td></tr>
	        <tr><td align="right"><?php echo gettext('Searchable');?>?</td><td><input type="checkbox" name="searchable" /></td></tr>
	        <tr><td align="right"><?php echo gettext('Advanced Search');?>?</td><td><input type="checkbox" name="advanced" /></td></tr>
	        <tr><td align="right"><?php echo gettext('Show in results');?>?</td><td><input type="checkbox" name="showinresults" /></td></tr>
<?php  /*	        <tr><td align="right">Show in public results?</td><td><input type="checkbox" name="showinpublicresults" /></td></tr>
	        <tr><td align="right">Public entry?</td><td><input type="checkbox" name="publicentry" /></td></tr>
*/ ?>	        <tr><td colspan="2"><input type="submit" name="submit" value="<?php echo gettext('Add Control');?>" /></td></tr>
	    </table>
	    <input type="hidden" name="collectionid" value=<?php echo $_REQUEST['collid'];?> />
	</form>
	<?php
	require_once('includes/footer.php');
}

//Turn control creation into a function so the xml importer can share functionality
function createControl($fromRequest,
$name='',
$type='',
$schemeid='',
$collid='',
$description='',
$required = 0,
$searchable = 0,
$advanced = 0,
$showinresults = 0,
$showinpublic = 0,
$publicentry = 0,
$options = '',
$sequence = 0){
	global $invalidControlNames;
	global $db;
	//For regular control creation, get arguments from Request array
	if($fromRequest){
		$name = $_REQUEST['name'];
		$type = $_REQUEST['type'];
		$collid = $_REQUEST['collectionid'];
		$schemeid = $_SESSION['currentScheme'];
		$description = $_REQUEST['description'];
		$required = 0;
		$searchable = 0;
		$advanced = 0;
		$showinresults = 0;
		$showinpublic = 0;
		$publicentry = 0;
		if(isset($_REQUEST['required']) && $_REQUEST['required'] == "on")
		$required = 1;
		if(isset($_REQUEST['searchable']) && $_REQUEST['searchable'] == "on")
		$searchable = 1;
		if(isset($_REQUEST['advanced']) && $_REQUEST['advanced'] == "on")
		$advanced = 1;
		if(isset($_REQUEST['showinresults']) && $_REQUEST['showinresults'] == "on")
		$showinresults = 1;
		if(isset($_REQUEST['showinpublicresults']) && $_REQUEST['showinpublicresults'] == "on")
		$showinpublic = 1;
		if(isset($_REQUEST['publicentry']) && $_REQUEST['publicentry'] == "on")
		$publicentry = 1;
	}
	
	// first make sure the control name isn't blank
	if ((empty($name) || empty($type) ||	empty($schemeid) || $collid === '') && $fromRequest) {
		showAddControlForm(gettext('You must provide a name for this control.'));
		die();
	}
	else if(empty($name) ||	empty($type) ||	empty($schemeid) || $collid === ''){
		return gettext("Missing required field.");
	}
	//Make sure the control name is valid
	if (in_array(strtoupper($name), $invalidControlNames) && $fromRequest)
	{
		showAddControlForm('"'.$_REQUEST['name'].'" '.gettext('is not a valid control name'));
		die();
	}
	else if (in_array(strtoupper($name), $invalidControlNames))
	{
		return $name." ".gettext('is not a valid control name');
	}
	// make sure the control type is valid; if it's not, return; if it is, get the filename
	// to include.
	$typeQuery = $db->query('SELECT file, class FROM control WHERE class='.escape($type).' LIMIT 1');
	if ($typeQuery->num_rows == 0 && $fromRequest)
	{
		showAddControlForm(gettext('Please select a valid control type.'));
		die();
	}
	else if($typeQuery->num_rows == 0){
		return gettext('Please select a valid control type.');
	}
	else
	{
		$controlData = $typeQuery->fetch_assoc();
		require_once(basePath.CONTROL_DIR.$controlData['file']);
		$emptyControl = new $controlData['class'];
	}
	
	// Build the Query to create the control
	$sqlquery = "INSERT INTO p$_SESSION[currentProject]Control (schemeid,collid,type,name,description,required,searchable,advSearchable,showInResults,showInPublicResults,publicEntry,options,sequence)";
	if ($fromRequest)$sqlquery .= " SELECT ";
	else $sqlquery .= " VALUES (";
	$sqlquery .= "$schemeid,$collid,".escape($type).",".escape(trim($name)).",".escape($description).",";
	$sqlquery .= "$required,$searchable,$advanced,$showinresults,$showinpublic,$publicentry,";
	if($fromRequest)$sqlquery .= escape($emptyControl->initialOptions()).", COUNT(sequence)+1 FROM p$_SESSION[currentProject]Control where collid=".escape($collid);
	else $sqlquery .= escape($options).", $sequence".")";
	$db->query($sqlquery);
	if($fromRequest){
		if(((int)$_REQUEST['collectionid']) != 0){
			if($db->error) {
				showAddControlForm($db->error);
				die();
			}
			else{
				header("Location: schemeLayout.php?schemeid=$_SESSION[currentScheme]");
			}
		}
	}
	else{
		if($db->errno != 0)return $db->error;
		else return true;
	}
}


if(!function_exists('print_rr')){
	/**
	 * A wrapper function for print_r(). Adds <pre> tags and html escaping.
	 *
	 * @param Array $array
	 */
	function print_rr($array){

		echo "<pre>";
		ob_start();
		print_r($array);
		$text = ob_get_contents();
		ob_end_clean();
		echo htmlspecialchars($text);
		echo "</pre>";

	}
}

/**
 * Search for records that match a boolean keyword search string.  Will return
 * an array of matching ids only. See KORA_BooleanSearch() for additional
 * details and suggested usage.
 *
 * @param String $string - A boolean keyword search string.
 * 		See KORA_BooleanSearch() for formatting details.
 * @param Integer $projectID
 * @param Integer $schemeID
 * @param Array $searchFields - An array of control ids to search over.
 * @param Array $keywords - Processed keywords will be added to this array.
 * @return Array $idList - An array of matching ids.
 */
function booleanKeywordSearch($string,$projectID,$schemeID,$searchFields,&$keywords = array()){
	global $db;
	$booleanClause = '';

	// unescape the string because it may cause problems with double quotes.
	$string = stripslashes($string);
	$string = strtolower($string);
	
	// get quoted strings
	$matches = array();
	$pattern = '/"([^"]+)"/';
	$num = preg_match_all($pattern,$string,$matches);
	
	// replace quoted strings with empty qoutes as markers for later so
	// we can do functions that would otherwise affect these strings.
	$string = preg_replace($pattern,'"" ',$string);

	// remove AND because we will be treating spaces the same way.
	$string = str_ireplace(' and ',' ',$string);
	
	// condense all white space
	$string = trim(preg_replace('/[\s]+/',' ',$string));
	
	if($string != ''){
		// $n is a counter for quoted strings that we replaced
		$n=0;
		$or = explode(' or ',$string);
		foreach($or as &$and){
			$and = explode(' ',trim($and));
			
			foreach($and as &$keyword){
				$query = 'value ';
				if($keyword[0] == '-'){
					$query.= 'NOT ';
					$keyword = substr($keyword,1);
					// add quoted strings back in
					if($keyword == '""') $keyword = $matches[1][$n++];
				}
				else{
					// add quoted strings back in
					if($keyword == '""') $keyword = $matches[1][$n++];
					$keywords[]=$keyword;
				}

				
				
				$keyword = $db->real_escape_string($keyword);
				$query .="LIKE '%$keyword%'";
				$keyword = $query;
			}
			
			$and = '('.implode(' AND ',$and).')';
		}
		$booleanClause = implode(' OR ',$or);
	}


	// do the query and get a list of record ids.
	$recordQuery = "SELECT * FROM p{$projectID}Data WHERE schemeid='$schemeID' ";
	if (!empty($searchFields)){
		$recordQuery .= 'AND cid IN ('.implode(',',$searchFields).') ';
	}
	if($booleanClause != ''){
		$recordQuery .= 'AND ('.$booleanClause.') ';
	}
	//echo $recordQuery .'<br>';
	$recordQuery = $db->query($recordQuery);
	
	//echo $db->error;
	
	// add ids as array keys so we don't have to do an array_unique() later.
	$idList = array();
	while($r = $recordQuery->fetch_assoc()){
		$idList[$r['id']]='';
	}
	
	return array_keys($idList);
}

/**
 * Gets control's options as xml.  Expects a project to be set in session.
 *
 * @param $cid - the control id - escape() will be called on this.
 */
function getControlOptions($cid){
	global $db;
	
	// Get control options
	$results = $db->query('SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' LIMIT 1');
	if (!is_object($results) || $results->num_rows != 1){
		echo gettext('Improper Control ID or Project ID Specified').'.';
		return false;
	}
	$result = $results->fetch_assoc();
	$xml = simplexml_load_string($result['options']);
	return $xml;
}
	
/**
 * Stores a control's xml options. Expects a project to be set in session.
 *
 * @param $cid - the control id
 * @param $xml - the xml options for the control
 */
function setControlOptions($cid,$xml){
	global $db;
	$query = 'UPDATE p'.$_SESSION['currentProject'].'Control SET options='.escape($xml->asXML());
	$query .= ' WHERE cid='.escape($cid).' LIMIT 1';
	$db->query($query);
}

// These translations are needed to display some strings in the correct language
// from the database without saving them in the database in non-English.
gettext('Record Associator');
gettext('Date');
gettext('Image');
gettext('List');
gettext('Date (Multi-Input)');
gettext('List (Multi-Select)');
gettext('Text (Multi-Input)');
gettext('Text');
gettext('Title');
gettext('Creator');
gettext('Subject');
gettext('Publisher');
gettext('Contributor');
gettext('Date Original');
gettext('Date Digital');
gettext('Format');
gettext('Source');
gettext('Coverage');
gettext('Rights');
gettext('Contributing Institution');
gettext('Geolocator');
gettext('Geolocator Control');


?>
