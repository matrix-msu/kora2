<?php
use KORA\Manager;
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

// Initial Version: Matt Geimer, 2008
// Refactor: Joe Deming, Anthony D'Onofrio 2013

require_once('includes/includes.php');

Manager::Init();

Manager::RequireProjectAdmin();
Manager::RequireProject();
Manager::RequireScheme();

Manager::PrintHeader();

echo '<h2>'.gettext('Updating Dublin Core Data for').' '.Manager::GetScheme()->GetName().' '.gettext('in').' '.htmlEscape(Manager::GetScheme()->GetName()).'</h2>';

if(Manager::CheckRequestsAreSet(['refresh'])) {
   $dcfields = getDublinCoreFields(Manager::GetProject()->GetPID(), Manager::GetScheme()->GetSID());
   echo gettext('updating data').'...';
   //delete the current data...
   $query = "DELETE FROM dublinCore WHERE pid=".Manager::GetProject()->GetPID()." AND sid=".Manager::GetScheme()->GetSID()."";
   $db->query($query);
   $controlids = array('0');
   
   //Manage adding timestamp to dublin core records
   if(!empty($dcfields)){
   		//Get the cid of the timestamp control for this scheme
   		$timequery = "SELECT cid FROM p".Manager::GetProject()->GetPID()."Control WHERE name='systimestamp' AND schemeid='".Manager::GetScheme()->GetSID()."' LIMIT 1";
   		$result = $db->query($timequery);
   		if(is_object($result) && $result->num_rows != 0){
   			$result = $result->fetch_assoc();
   			$dcfields['timestamp'] = array(simplexml_load_string("<id>".$result['cid']."</id>"));
   		}
   }
   //get the control ids....
   foreach($dcfields as $dctype => $ids) {
      foreach($ids as $id) {
         $controlids[] = $id;
      }
   }
   
   
   //grab the id's of the items we are going to do...
   $controls = implode(",",$controlids);
   $query = "SELECT DISTINCT id FROM p".Manager::GetProject()->GetPID()."Data WHERE cid IN (";
   $query .= $controls;
   $query .= ") AND schemeid=".Manager::GetScheme()->GetSID()." AND value != ''";
   $result = $db->query($query);
   print $db->error;
   $records = array();
   if($result->num_rows != 0) {
      while($array = $result->fetch_assoc()) {
         $records[] = $array['id'];  
      }
      foreach($records as $record) {
         //get the controls for the dcfields for this record
         $query = "SELECT * from p".Manager::GetProject()->GetPID()."Data WHERE cid IN ($controls) AND schemeid=".Manager::GetScheme()->GetSID()." and value != ''
          AND id ='$record' ORDER BY cid";
          $result = $db->query($query);
          echo $db->error;
          //create an array for each of the dc types available
          $xmlstrings = array();
          foreach($dcfields as $dctype => $ids) {          
             $xmlstrings[$dctype] = simplexml_load_string("<$dctype></$dctype>");
          }
          //for each control returned, add it to the correct dctype in $xmlstrings
          while($array = $result->fetch_assoc()) {
             foreach($dcfields as $dctype => $ids) {
                foreach($ids as $ids) {
                   if($array['cid']==$ids) {
                      $xmlstrings[$dctype]->addChild($ids,escape($array['value']));
                   }
                }
             }
          }
          //remove empty dctype fields (may be a non-required control, etc)
          foreach($xmlstrings as $xmldctype => $xmlobj) {
             if(!$xmlobj->children()) {
                unset($xmlstrings[$xmldctype]);  
             }
          }
          //make the query and insert into the table
          $query = "INSERT INTO dublinCore (kid,pid,sid";
          foreach($xmlstrings as $xmlstring => $xmlobj)
          {
          	 $query .= ','.$xmlstring;
          }
          $query .= ") values ('$record','".Manager::GetProject()->GetPID()."','".Manager::GetScheme()->GetSID()."'";
          $xmlarray = array();  
          foreach($xmlstrings as $column => $xml) {
             $query .= ','.escape($xml->asXML());
          }
          $query .= ")";
          //insert the record
          if (!empty($xmlstrings))
          {
              $db->query($query);
              echo $db->error.'.';
          }
      }
   }
   // mark the scheme up-to-date
   $db->query('UPDATE scheme SET dublinCoreOutOfDate=0 WHERE schemeid='.Manager::GetScheme()->GetSID().' LIMIT 1');
   echo '<br /><br />'.gettext('done').'!';
}
else {
   $query = "SELECT * FROM dublinCore WHERE pid=".Manager::GetProject()->GetPID()." AND sid=".Manager::GetScheme()->GetSID()." LIMIT 1";
   $result = $db->query($query);
   $query2 = "SELECT dublinCoreFields FROM scheme WHERE pid=".Manager::GetProject()->GetPID()." AND schemeid=".Manager::GetScheme()->GetSID()." AND dublinCoreFields IS NOT NULL";
   $result2 = $db->query($query2);
   if(!$result->num_rows && !$result2->num_rows) {
      echo gettext('No dublin core data for this scheme.  Please add dublin core fields first.');
   }
   else {
      echo '<h3>'.gettext('Current fields associated with dublin core').'</h3>';
$dcfields = getDublinCoreFields(Manager::GetScheme()->GetSID(), Manager::GetProject()->GetPID());
if($dcfields) {
   echo '<table class="table">';
   foreach($dcfields as $dctype => $ids) {
      if(count($ids) > 0) {
        echo '<tr class="scheme_index"><td colspan="2">'.$dctype.'</td></tr><tr><td>';
        $query = "SELECT cid,name FROM p".Manager::GetProject()->GetPID()."Control WHERE cid IN (".implode(',',$ids).")";
        $result = $db->query($query);
        echo $db->error;
        while($array = $result->fetch_assoc()) {   
            echo ''.htmlEscape($array['name']).', '; 
        }
      }
   }
   echo '</td></tr></table>';
}
else
{
	echo gettext('There are currently no Dublin Core-associated fields in this scheme').'.<br /><br />';
}
echo '<a href="manageDublinCore.php">'.gettext('Manage Dublin Core Data').'</a>';
      
      echo '<br /><br /><br /><br /><strong>'.gettext('Refresh Dublin Core data?  This may take a long time.').'..</strong><br /><br />'
      ?>
      <form name="refreshdcd" action="updateDublinCore.php" method="post">
      <input type="submit" value="<?php echo gettext('Refresh DC Data');?>" name="refresh" />
      </form>   
   <?php 
   }
} //end else
Manager::PrintFooter();
?>

