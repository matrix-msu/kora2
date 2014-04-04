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

require_once('listControl.php');

class MultiListControl extends ListControl
{
	protected $name = "Multi-List Control";

    public function MultiListControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
    {
        if (empty($projectid) || empty($controlid)) return;
        global $db;
        
        $this->pid = $projectid;
        $this->cid = $controlid;
        $this->rid = $recordid;
        $this->cName = 'p'.$projectid.'c'.$controlid;
        $this->value = '';
        $this->ExistingData = false;  // needed just in case data is existing AND blank
        
        $controlCheck = $db->query('SELECT schemeid, name, description, required, options FROM p'.$projectid.'Control WHERE cid='.escape($controlid).' LIMIT 1');
        if ($controlCheck->num_rows > 0) {
            $controlCheck = $controlCheck->fetch_assoc();
            $this->sid = $controlCheck['schemeid'];
            foreach(array('name', 'description', 'required', 'options') as $field) {
                $this->$field = $controlCheck[$field];
            }
            
            $this->options = simplexml_load_string($this->options);
        } else $this->pid = $this->cid = $this->rid = $this->cName = '';
        
	// If data exists for this control, get it
	
        if (isset($_SESSION['lastIngestion']))
        {
            $lastIngestion = unserialize($_SESSION['lastIngestion']);
        }
        else
        {
            $lastIngestion = array();
        }
	
        if (!empty($recordid))
        {
            // See if there was a previous ingestion for this object
            if (isset($lastIngestion['editRecord']) && $lastIngestion['editRecord'] == $recordid && isset($lastIngestion[$this->cName]))
            {
                $this->value = simplexml_load_string('<multilist></multilist>');
                foreach($lastIngestion[$this->cName] as $selectedOption)
                {
                    $this->value->addChild('value', xmlEscape($selectedOption));
                }
                $this->ExistingData = true;
            }
            else
            {
                $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($recordid).' AND cid='.escape($controlid).' LIMIT 1');
                if ($valueCheck->num_rows > 0)
                {
                    $this->ExistingData = true;
                    $valueCheck = $valueCheck->fetch_assoc();
                    $this->value = simplexml_load_string($valueCheck['value']);
                }
            }
        }
        else if (isset($lastIngestion[$this->cName]) && !isset($lastIngestion['editRecord']))
        {
            // load value from session
            $this->value = simplexml_load_string('<multilist></multilist>');
            foreach($lastIngestion[$this->cName] as $selectedOption)
            {
                $this->value->addChild('value', xmlEscape($selectedOption));
            }
            $this->ExistingData = true;
        }
        else if (!empty($presetid)) {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0)
            {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                $this->value = simplexml_load_string($valueCheck['value']);
            }
        }
        else if (isset($this->options->defaultValue->option))
        {
            // Otherwise, this is an initial ingestion, so fill in the default
            $this->value = simplexml_load_string('<multilist />');
            foreach($this->options->defaultValue->option as $option)
            {
                $this->value->addChild('value', xmlEscape((string)$option));
            }
        }
    }
	
    public function display()
    {
        if (!$this->isOK()) return;

        print '<div class="kora_control"><select multiple="multiple" name="'.$this->cName.'[]">';
        
        $values = array();
        if (isset($this->value->value))
        {
            foreach($this->value->value as $v)
            {
                $values[] = (string)$v;
            }
        }
        
        // display the options, with the current value selected.
        foreach($this->options->option as $option) {
            echo "<option value='".htmlEscape($option)."'";
            if(in_array($option, $values)) echo ' selected="selected"';
            echo ">$option</option>\n";
        }
        
        echo '</select></div>';
    }

    public function displayOptionsDialog()
    {
        $controlPageURL = baseURI . 'controls/multiListControl.php';
?><!-- Javascript Code below for list add/remove/up/down buttons -->
<script type="text/javascript">
//<![CDATA[
    function removeOption() {
        var answer = confirm("<?php echo gettext('Really delete option?  This will delete all data about this option from any records which currently have selected it.')?>");
        var value = $('#listOptions').val();
        if (answer == true) {
        	$.post('<?php echo $controlPageURL?>', {action:'removeMLOption',source:'MultiListControl',cid:<?php echo $this->cid?>,option:value }, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    function addOption() {
        var value = $('#newOption').val();
    	$.post('<?php echo $controlPageURL?>', {action:'addMLOption',source:'MultiListControl',cid:<?php echo $this->cid?>,label:value }, function(resp){$("#ajax").html(resp);}, 'html');
    }
    function moveOption(vardirection) {
        var value = $('#listOptions').val();
    	$.post('<?php echo $controlPageURL?>', {action:'moveMLOption',source:'MultiListControl',cid:<?php echo $this->cid?>,option:value,direction:vardirection}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function usePreset(varpreset) {
        var answer = confirm("<?php echo gettext('Really select preset?  This will delete all existing options and cannot be undone!')?>");
        var value = $('#listPreset').val();
        if (answer == true) {
        	$.post('<?php echo $controlPageURL?>', {action:'useMLPreset',source:'MultiListControl',cid:<?php echo $this->cid?>,preset:value}, function(resp){$("#ajax").html(resp);}, 'html');
        }
    }
    
    function savePreset()
    {
        var newName = $('#presetName').val();
    	$.post('<?php echo $controlPageURL?>', {action:'saveMLPreset',source:'MultiListControl',cid:<?php echo $this->cid?>,pid:<?php echo $this->pid?>,name:newName}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function addDefault()
    {
        var varOption = $('#unselectedDefault').val();
    	$.post('<?php echo $controlPageURL?>', {action:'updateMLDefaultValue',source:'MultiListControl',cid:<?php echo $this->cid?>,todo:'add',option:varOption}, function(resp){$("#ajax").html(resp);}, 'html');
    }
    
    function removeDefault()
    {
        var varOption = $('#selectedDefault').val();
    	$.post('<?php echo $controlPageURL?>', {action:'updateMLDefaultValue',source:'MultiListControl',cid:<?php echo $this->cid?>,todo:'remove',option:varOption}, function(resp){$("#ajax").html(resp);}, 'html');
    }

	$.post('<?php echo $controlPageURL?>', {action:'showMLDialog',source:'MultiListControl',cid:<?php echo $this->cid?>}, function(resp){$("#ajax").html(resp);}, 'html');
// ]]>
</script>

<div id="ajax"></div>

<?php
    }
    
    public function displayXML()
    {
       if( !$this->isOK()) return;
       
       $values = array();
       if (isset($this->value->value))
       {
	       foreach($this->value->value as $v)
	       {
	           $values[] = (string)$v;
	       }
       }
       
       $xmlstring = "<multilist>";
       foreach($this->options->option as $option)
       {
          if(in_array($option, $values))
          {
             $xmlstring .= '<value>'.xmlEscape($this->value).'</value>';
          }
       }
       $xmlstring .= '</multilist>';
       return $xmlstring;
    }
    
    public function getSearchString($submitData) {
		if(isset($submitData[$this->cName]) && !empty($submitData[$this->cName])) {
			
			$options = array();
			foreach($submitData[$this->cName] as $value) {
				$options[] = array('LIKE',"'%<value>$value</value>%'");
			}
			
			return $options;
		}
		else return false;
	}
    
    public function getType() { return "List (Multi-Select)"; }
    
    public function setXMLInputValue($value) {
    	$this->XMLInputValue = xmlEscape($value);
    }
    
    private function loadValues($valueArray) {
    	$this->value = simplexml_load_string('<multilist></multilist>');
    	
    	foreach($valueArray as $selectedOption)
        {
        	$this->value->addChild('value', xmlEscape($selectedOption));
        }
    }
    
    public function ingest($publicIngest = false)
    {
        global $db;
        
        if (!$this->isOK()) return;
        
        //determine whether to insert into public ingestion table or not
        if($publicIngest)
       	{
       		$tableName = 'PublicData';
       	}
       	else $tableName = 'Data';
        
        if (empty($this->rid)) {
            echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
            return;
        } else if (isset($this->XMLInputValue)) {
        	$this->loadValues($this->XMLInputValue);
        } else if (isset($_REQUEST[$this->cName]) && !empty($_REQUEST[$this->cName])){
            $this->loadValues($_REQUEST[$this->cName]);
        } else {
        	$this->loadValues(array());
        }
        
        // ingest the data
        $query = '';    // default blank query
        if ($this->ExistingData) {
            if ($this->isEmpty()) $query = 'DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).
                                           ' AND cid='.escape($this->cid).' LIMIT 1';
            else $query = 'UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value->asXML()).
                 ' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
        } else {
            if (!$this->isEmpty()) $query = 'INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value->asXML()).')';
        }
        
        if (!empty($query)) $db->query($query);
    }

    public function isXMLPacked() { return true; }
    
    // When a list item is removed, all controls that currently have that item set
    // need to have that item removed.
    public function removeDeletedDataItem($pid, $cid, $dataItem)
    {
        global $db;
        
        $itemQuery = 'SELECT id, value FROM p'.$pid.'Data WHERE cid='.escape($cid);
        $itemQuery = $db->query($itemQuery);
        
        // iterate through all records and remove references to value
        while ($item = $itemQuery->fetch_assoc())
        {
        	$xml = $item['value'];
        	$xml = simplexml_load_string($xml);
        	
        	$newXML = simplexml_load_string('<multilist></multilist>');
        	$shouldUpdate = false;
        	foreach($xml->value as $value)
        	{
        		if ($value == $dataItem)
        		{
        			$shouldUpdate = true;
        		}
        		else
        		{
        			$newXML->addChild('value', xmlEscape((string) $value));
        		}
        	}
        	
        	if ($shouldUpdate)
        	{
        		if (isset($newXML->value))
        		{
        		    $db->query('UPDATE p'.$pid.'Data SET value='.escape($newXML->asXML()).' WHERE cid='.escape($cid)." AND id='$item[id]' LIMIT 1");
        		}
        		else
        		{
        			$db->query('DELETE FROM p'.$pid.'Data WHERE cid='.escape($cid)." AND id='$item[id]' LIMIT 1");
        		}
        	}
        }
        $query  = 'DELETE FROM p'.$pid.'Data WHERE cid='.escape($cid);
        $query .= ' AND value='.escape($dataItem);
        $db->query($query);
    }
    
    public function showData()
    {
    	if (!empty($this->rid))
    	{
    		if (isset($this->value->value))
    		{
    			$returnString = '';
    			foreach($this->value->value as $val)
    			{
    				$val = (string) $val;
    				$returnString .= htmlEscape($val).'<br />';
    			}
    			return $returnString;
    		}
    	}
    }
    
    public function storedValueToDisplay($xml,$pid,$cid)
    {
    	$xml = simplexml_load_string($xml);

        $returnVal = '';
        if (isset($xml->value))
        {
            foreach($xml->value as $v)
            {
            	$v = (string) $v;
                $returnVal .= htmlEscape($v).'<br />';
            }
        }
    	
        return $returnVal;
    }
    
    public function storedValueToSearchResult($xml)
    {
        $xml = simplexml_load_string($xml);

        $returnVal = array();
        if (isset($xml->value))
        {
        	foreach($xml->value as $v) $returnVal[] = (string) $v;
        }
        
        return $returnVal;
    }
	public function validateIngestion($publicIngest = false)
	{
        if ($this->required && $this->isEmpty()){
            return gettext('No value supplied for required field').': '.htmlEscape($this->name);
        }

        if(!empty($_REQUEST[$this->cName])){
        	$value = $_REQUEST[$this->cName];
        }else if (!empty($this->XMLInputValue)){
        	$value = $this->XMLInputValue;
        }else return '';
        
		foreach ($this->options->option as $option) {
			$optionArray[] = (string) $option;
		}
		foreach($value as $v){
			if(!in_array((string)$v,$optionArray)){
				return '"'.htmlEscape($value).gettext('" is not an valid value for '.$this->getName());
			}
		}
	}
}

class MultiListControlOptions
{
	public static function showDialog($cid)
	{
        global $db;
        
        // get the XML list of modifiers
        $query = 'SELECT options FROM p'.$_SESSION['currentProject'].'Control WHERE cid='.escape($cid).' LIMIT 1';
        $query = $db->query($query);
        if (!is_object($query) || $query->num_rows != 1) {
            echo gettext('Improper Control ID or Project ID Specified').'.';
            return;
        }
        $query = $query->fetch_assoc();
        
        $xml = simplexml_load_string($query['options']);
		?>
		<form id="listSettings">
		        
		<table class="table">
		<tr>
		    <td width="40%"><b><?php echo gettext('List Options')?></b><br /><?php echo gettext('These are the choices users will be presented with when ingesting')?>.</td>
		    <td><select name="listOptions" id="listOptions" size="7">
				<?php
		        // display all the modifiers
		        foreach($xml->option as $option) {
		            echo '<option>'.htmlEscape($option).'</option>'."\n";
		        }
	        	?>
		        </select><br />
		        <input type="button" onclick="moveOption('up');" value="<?php echo gettext('Up')?>" />
		        <input type="button" onclick="moveOption('down');" value="<?php echo gettext('Down')?>" />
		        <input type="button" onclick="removeOption();" value="<?php echo gettext('Remove')?>" />
		        <br /><br /><input type="text" name="newOption" id="newOption" />
		        <input type="button" onclick="addOption();" value="<?php echo gettext('Add Option')?>" />
		    </td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Default Value')?></b><br /><?php echo gettext('Optionally, select which value(s) which will be initially selected upon first ingestion')?>.</td>
		    <td>
	        	<?php
		        // get the list of default values
		        $currentDefaults = array();
		        if (isset($xml->defaultValue->option)) { foreach($xml->defaultValue->option as $option) {
		        	$currentDefaults[] = (string)$option;
		        }}
		
		        $selected = '<select name="selectedDefault" id="selectedDefault" size="7">';
		        $unselected = '<select name="unselectedDefault" id="unselectedDefault" size="7">';
		        
		        // display all the modifiers
		        foreach($xml->option as $option) {
		        	if (in_array((string)$option, $currentDefaults))
		        	{
		        		$selected .= '<option>'.htmlEscape($option)."</option>\n";
		        	}
		        	else
		        	{
		        		$unselected .= '<option>'.htmlEscape($option)."</option>\n";
		        	}
		        }
		        
		        $selected .= '</select>';
		        $unselected .= '</select>';
		        
		        echo '<table border="0"><tr><td>'.gettext('Options').'</td><td></td><td>'.gettext('Default Value').'</td></tr>';
		        echo "<tr><td>$unselected</td>";
		        echo '<td><input type="button" value="-->" onclick="addDefault();" /><br /><br />';
		        echo '<input type="button" value="<--" onclick="removeDefault();" /></td>';
		        echo "<td>$selected</td></tr></table>";
		        ?>
		    </td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Presets')?></b><br /><?php echo gettext('Sets of pre-defined list values which are commonly used')?></td>
		    <td><select name="listPreset" id="listPreset">
		        <?php
		        // Get the list of List Control Presets
		        $presetQuery = $db->query('SELECT presetid, name FROM controlPreset WHERE class=\'ListControl\' AND (global=1 OR project='.$_SESSION['currentProject'].') ORDER BY name');
		        while($preset = $presetQuery->fetch_assoc())
		        {
		            echo "<option value=\"$preset[presetid]\">".htmlEscape($preset['name']).'</option>';
		        }
		        ?>
		    </select> <input type="button" onclick="usePreset()" value="<?php echo gettext('Use Preset')?>" /></td>
		</tr>
		<tr>
		    <td><b><?php echo gettext('Create New Preset')?></b><br /><?php echo gettext("If you would like to save this set of allowed file types as a preset, enter a name and click 'Save as Preset'")?>.</td>
		    <td><input type="text" name="presetName" id="presetName" /> <input type="button" onclick="savePreset()" value="<?php echo gettext('Save as Preset')?>" /></td>
		</tr>
		</table>
		</form>
		<?php
	}
	
	public static function updateDefaultValue($cid, $action, $option)
	{
        global $db;
        
	    // Ensure that this is a valid action
        if (!in_array($action, array('add', 'remove')))
        {
            return;
		}
		
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<options></options>');
        
        // copy over all the options
        // At the same time, make sure the option we're asked to copy over is a valid one
        $found = false;
        foreach($xml->option as $op)
        {
            $newXML->addChild('option', xmlEscape((string)$op));
            if ((string)$op == $option)
            {
                $found = true;
            }
        }
        
        // Make sure we actually found a match
        if (!$found)
        {
            return;
        }
        
        $defVal = $newXML->addChild('defaultValue');
        
        // Copy over the Default Values
        if ($action == 'add')
        {
            $duplicate = false;
            // Check for Duplicates
            if (isset($xml->defaultValue->option))
            {
	            foreach($xml->defaultValue->option as $op)
	            {
	                if ((string)$op == $option)
	                {
	                    $duplicate = true;
	                }
	                $defVal->addChild('option', xmlEscape((string)$op));
	            }
            }
            
            if (!$duplicate)
            {
                $defVal->addChild('option', xmlEscape($option));
            }
        }
        else // if $action == 'remove'
        {
            foreach($xml->defaultValue->option as $op)
            {
                if ((string)$op != $option)
                {
                    $defVal->addChild('option', xmlEscape((string)$op));
                }
            }
        }
        
        setControlOptions($cid, $newXML);
	}
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'MultiListControl')
{
    requirePermissions(EDIT_LAYOUT, isset($_SESSION['currentScheme']) ? 'schemeLayout.php?schemeid='.$_SESSION['currentScheme'] : 'selectScheme.php');
    
    $action = $_POST['action'];
    if($action == 'addMLOption') {
        ListControlOptions::add($_POST['cid'], $_POST['label']);
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'removeMLOption') {
    	if (isset($_POST['option']))
        {
            ListControlOptions::remove($_POST['cid'], $_POST['option']);
        }
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'moveMLOption') {
        if (isset($_POST['option']))
        {
    	   ListControlOptions::move($_POST['cid'], $_POST['option'], $_POST['direction']);
        }
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'useMLPreset') {
        ListControlOptions::usePreset($_POST['cid'], $_POST['preset']);
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'saveMLPreset') {
        ListControlOptions::savePreset($_POST['cid'], $_POST['pid'], $_POST['name']);
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateMLDefaultValue') {
        if (isset($_POST['cid']) && isset($_POST['todo']) && isset($_POST['option']))
        {
            MultiListControlOptions::updateDefaultValue($_POST['cid'], $_POST['todo'], $_POST['option']);
        }
        MultiListControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'showMLDialog') {
        MultiListControlOptions::showDialog($_POST['cid']);
    }
}
?>
