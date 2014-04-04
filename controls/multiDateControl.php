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

require_once("dateControl.php");

class MultiDateControl extends DateControl
{
	protected $name = 'Multi-Date Control';
	
    public function MultiDateControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
    {
        if (empty($projectid) || empty($controlid)) return;
        global $db;

        $this->pid = $projectid;
        $this->cid = $controlid;
        $this->rid = $recordid;
        $this->cName = 'p'.$projectid.'c'.$controlid;
        $this->value = '';
        $this->ExistingData = false;
        
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
                $xmlString = '<multidate>';
                foreach($lastIngestion[$this->cName] as $selectedOption)
                {
                    $xmlString .= $selectedOption;
                }
                $xmlString .= '</multidate>';
                $this->value = simplexml_load_string($xmlString);
                // As an absolute fallback, if that fails (it shouldn't possibly if ingestion
                // calls validateIngestion before calling this), check for that and set it to
                // empty as a fallback
                if ($this->value === FALSE)
                {
                	$this->value === simplexml_load_string('<multidate></multidate>');
                }
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
            $xmlString = '<multidate>';
            foreach($lastIngestion[$this->cName] as $selectedOption)
            {
                $xmlString .= $selectedOption;
            }
            $xmlString .= '</multidate>';
            $this->value = simplexml_load_string($xmlString);
            // As an absolute fallback, if that fails (it shouldn't possibly if ingestion
            // calls validateIngestion before calling this), check for that and set it to
            // empty as a fallback
            if ($this->value === FALSE)
            {
            	$this->value === simplexml_load_string('<multidate></multidate>');
            }
        }
        else if (!empty($presetid))
        {
            $valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
            if ($valueCheck->num_rows > 0) {
                $this->ExistingData = true;
                $valueCheck = $valueCheck->fetch_assoc();
                $this->value = simplexml_load_string($valueCheck['value']);
            }
        }
        else if (isset($this->options->defaultValue) && !empty($this->options->defaultValue))
        {
            $this->value = $this->options->defaultValue;
        }
    }
	
	public function display()
	{
        global $db;
        
        ?>
<script type="text/javascript">
//<![CDATA[
function formatDateForDisplay<?php echo $this->cid?>(month, day, year, era)
{
    // The empty member is to fill the 0 index of the array
    var monthArray = new Array("", "<?php echo gettext('January')?>", "<?php echo gettext('February')?>", "<?php echo gettext('March')?>",
    	     "<?php echo gettext('April')?>", "<?php echo gettext('May')?>", "<?php echo gettext('June')?>", "<?php echo gettext('July')?>",
    	     "<?php echo gettext('August')?>", "<?php echo gettext('September')?>", "<?php echo gettext('October')?>",
    	     "<?php echo gettext('November')?>", "<?php echo gettext('December')?>");

   myString = "";
<?php
if ((string) $this->options->displayFormat == 'MDY')
{
   // The + ensures that the string is type-cast to a number
?>
    
    if (+month > 0)
    {
        myString = myString + monthArray[+month] + " ";
    }
    if (+day > 0)
    {
        myString = myString + String(day);
        if (+year > 0)
        {
            myString = myString + ", ";
        }
    }
    if (+year > 0)
    {
        myString = myString + String(+year);
    }
<?php
}
else if ((string) $this->options->displayFormat == 'DMY')
{
?>
    if (+day > 0)
    {
        myString = myString + String(day) + " ";
    }
    if (+month > 0)
    {
        myString = myString + monthArray[+month] + " ";
    }
    if (+year > 0)
    {
        myString = myString + String(+year);
    }
<?php
}
else if ((string) $this->options->displayFormat == 'YMD')
{
?>
    if (+year > 0)
    {
        myString = myString + String(+year) + " ";
    }
    if (+month > 0)
    {
        myString = myString + monthArray[+month] + " ";
    }
    if (+day > 0)
    {
        myString = myString + String(day);
    }
<?php
}
else
{
?>    myString = "<?php echo gettext('Bad Date Format Option')?>"; <?php
}

// If CE/BCE is enabled, add Javascript Code to show it.
if ((string) $this->options->era == 'Yes')
{
?>
    myString = myString + " " + String(era);
<?php
}
?>
    return myString;
}

function addListOption<?php echo $this->cid?>()
{
    var month = document.getElementById("<?php echo $this->cName?>" + "month").value;
    var day = document.getElementById("<?php echo $this->cName?>" + "day").value;
    var year = document.getElementById("<?php echo $this->cName?>" + "year").value;
    var era = document.getElementById("<?php echo $this->cName?>" + "era").value;
    
    // Make sure at least ONE thing has a value
    if (month != "" || day != "" || year != "")
    {

    var optn = document.createElement("option");
    var selectbox = document.getElementById("<?php echo $this->cName?>");

    optn.text = formatDateForDisplay<?php echo $this->cid?>(month, day, year, era);
    optn.value = "<date><month>" + String(month) + "</month><day>" + String(day) + "</day><year>" + String(year) + "</year><era>" + String(era) + "</era></date>";
    
    selectbox.options.add(optn);
    
    }
}

function removeListOption<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
                
    for(i = selectbox.length - 1; i >= 0; i--)
    {
        if (selectbox.options[i].selected)
        {
            for(j = i; j < (selectbox.length - 1); j++)
            {
                selectbox.options[j].text = selectbox.options[j+1].text;
                selectbox.options[j].value = selectbox.options[j+1].value;
            }
                        
            selectbox.length--;
        }
    }
}

function moveListOptionUp<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
    
    // Start at 1 rather than 0 so that we don't even bother dealing with the top case
    // If the desired element is at the top it won't be moved anyway
    for(i = 1; i < selectbox.length; i++)
    {
        if (selectbox.options[i].selected)
        {
            var tempText = selectbox.options[i-1].text;
            var tempValue = selectbox.options[i-1].value;
            
            selectbox.options[i-1].text = selectbox.options[i].text;
            selectbox.options[i-1].value = selectbox.options[i].value;
            selectbox.options[i].text = tempText;
            selectbox.options[i].value = tempValue;
        }
    }
}

function moveListOptionDown<?php echo $this->cid?>()
{
    var selectbox = document.getElementById("<?php echo $this->cName?>");
    
    // End at selectbox.length - 2 rather than -1 so that we don't even bother dealing
    // with the bottom case.  If the desired element is at the bottom it won't be moved anyway
    for(i = 0; i <= (selectbox.length - 2); i++)
    {
        if (selectbox.options[i].selected)
        {
            var tempText = selectbox.options[i+1].text;
            var tempValue = selectbox.options[i+1].value;
            
            selectbox.options[i+1].text = selectbox.options[i].text;
            selectbox.options[i+1].value = selectbox.options[i].value;
            selectbox.options[i].text = tempText;
            selectbox.options[i].value = tempValue;
        }
    }
}
//]]>
</script>

<table border="0">
<!--<div>-->

<tr>
    <td colspan="2">
        <select id="<?php echo $this->cName?>" name="<?php echo $this->cName?>[]" multiple="multiple" size="5">
<?php       if (isset($this->value->date))
        {
            foreach($this->value->date as $date) {
            	$value = htmlEscape($date->asXML());
            	
            	if ((int)$date->month > 0)
            	{
            		$month = gettext(DateControl::$months[(int)$date->month]['name']);
            	}
            	else
            	{
            		$month = '';
            	}
            	$day = (int)$date->day;
            	$year = (int)$date->year;
            	
                $display = DateControl::formatDateForDisplay((int)$date->month, (int)$date->day, (int)$date->year, (string)$date->era, ((string)$this->options->era == 'Yes'),(string)$this->options->displayFormat);
                
                echo '            <option value="'.$value.'">'.$display."</option>\n";
            }
        }
?>
        </select>
    </td>
</tr>
<tr>
    <td><input type="button" onclick="moveListOptionUp<?php echo $this->cid?>()" value="<?php echo gettext('Up')?>" /></td>
    <td><input type="button" onclick="moveListOptionDown<?php echo $this->cid?>()" value="<?php echo gettext('Down')?>" /></td>
</tr>
<tr>
    <td colspan="2">
<?php
        // Use output buffering to get the three fields and then display
        // them in the proper order

        // Month
        ob_start();
        echo "\n".'<select id="'.$this->cName.'month" name="'.$this->cName.'month">';
        echo "\n\t".'<option value="">&nbsp;</option>';
        for ($i=1; $i <= 12; $i++)
        {
            echo "\n\t<option value=\"$i\">".gettext(DateControl::$months[$i]['name']).'</option>';
        }
        echo "\n".'</select>';
        $monthDisplay = ob_get_clean();

        // Day
        ob_start();
        echo "\n".'<select id="'.$this->cName.'day" name="'.$this->cName.'day">';
        echo "\n\t".'<option value="">&nbsp;</option>';
        for ($i=1; $i <= 31; $i++)
        {
            echo "\n\t<option value=\"$i\">$i</option>";
        }
        echo "\n</select>";
        $dayDisplay = ob_get_clean();

        // Year
        ob_start();
        echo "\n".'<select id="'.$this->cName.'year" name="'.$this->cName.'year">';
        echo "\n\t".'<option value="">&nbsp;</option>';
        
        $startYear = (int) $this->options->startYear;
        $endYear = (int) $this->options->endYear;
        
        // Make sure we don't hit an infinite loop - if end < start, switch them
        if ($endYear < $startYear)
        {
            $temp = $endYear;
            $endYear = $startYear;
            $startYear = $temp;
            unset($temp);
        }
        
        for ($i=$startYear; $i <= $endYear; $i++)
        {
            echo "\n\t<option value=\"$i\">$i</option>";
        }
        echo "\n</select>";
        $yearDisplay = ob_get_clean();

        // Era
        ob_start();
        if ((string) $this->options->era == 'Yes')
        {
            echo "\n".'<select id="'.$this->cName.'era" name="'.$this->cName.'era">';
            echo "\n\t".'<option value="">&nbsp;</option>';
            echo "\n\t".'<option value="CE">CE</option>';
            echo "\n\t".'<option value="BCE">BCE</option>';
            echo "\n".'</select>';
        }
        else
        {
        	// Make sure there is an element with the id of "cNameera" that defaults to CE
        	echo '<input type="hidden" name="'.$this->cName.'era" id="'.$this->cName.'era" value="CE" />';
        }
        $eraDisplay = ob_get_clean();
        
        // Display stuff in the proper order
        if ($this->options->displayFormat == 'MDY')
        {
            echo $monthDisplay.$dayDisplay.$yearDisplay.$eraDisplay;
        }
        else if ($this->options->displayFormat == 'DMY')
        {
            echo $dayDisplay.$monthDisplay.$yearDisplay.$eraDisplay;
        }
        else if ($this->options->displayFormat == 'YMD')
        {
            echo $yearDisplay.$monthDisplay.$dayDisplay.$eraDisplay;
        }
        else
        {
            echo gettext("This control's display format is an unrecognized value; please check its options.");
        }
?>
    </td>
</tr>
<tr>
    <td><input type="button" onclick="addListOption<?php echo $this->cid?>()" value="<?php echo gettext('Add')?>" /></td>
    <td><input type="button" onclick="removeListOption<?php echo $this->cid?>()" value="<?php echo gettext('Remove')?>" /></td>
</tr>
</table>
<!--</div>-->
<?php
	}

    public function displayOptionsDialog()
    {
        $controlPageURL = baseURI . 'controls/multiDateControl.php';
?>
<script type="text/javascript">
//<![CDATA[
function updateDateRange()
{
   var startDate = $('#startDate').val();
   var endDate = $('#endDate').val();
	$.post('<?php echo $controlPageURL?>', {action:'updateDateRange',source:'MultiDateControl',cid:<?php echo $this->cid?>,endYear:endDate,startYear:startDate}, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateEra()
{
   	var e;
	$('input[type="radio"]').each(function(){
		if(this.name == 'showEra' && this.checked){
			e = this.value;
		}
	});
	$.post('<?php echo $controlPageURL?>', {action:'updateEra',source:'MultiDateControl',cid:<?php echo $this->cid?>,era:e }, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateFormat()
{
	var f;
	$('input[type="radio"]').each(function(){
		if(this.name == 'format' && this.checked){
			f = this.value;
		}
	});
	$.post('<?php echo $controlPageURL?>', {action:'updateFormat',source:'MultiDateControl',cid:<?php echo $this->cid?>,format:f }, function(resp){$("#ajax").html(resp);}, 'html');
}

function moveDefaultValue(varDirection)
{
    var defaultValue = $('#defaultValue').val();
	$.post('<?php echo $controlPageURL?>', {action:'moveDefaultValue',source:'MultiDateControl',cid:<?php echo $this->cid?>,defaultV:defaultValue,direction:varDirection }, function(resp){$("#ajax").html(resp);}, 'html');
}

function removeDefaultValue()
{
    var defaultValue = $('#defaultValue').val();
	$.post('<?php echo $controlPageURL?>', {action:'removeDefaultValue',source:'MultiDateControl',cid:<?php echo $this->cid?>,defaultV:defaultValue}, function(resp){$("#ajax").html(resp);}, 'html');
}

function addDefaultValue()
{
    var vmonth = $('#month').val();
    var vday   = $('#day').val();
    var vyear  = $('#year').val();
    var vera   = $('#era').val();
	$.post('<?php echo $controlPageURL?>', {action:'addDefaultValue',source:'MultiDateControl',cid:<?php echo $this->cid?>,month:vmonth, day:vday, year:vyear, era:vera }, function(resp){$("#ajax").html(resp);}, 'html');
}

$.post('<?php echo $controlPageURL?>', {action:'showDialog',source:'MultiDateControl',cid:<?php echo $this->cid?> }, function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>

<div id="ajax"></div>
<?php
    }
	
	public function displayXML()
	{
        if(!$this->isOK()) return;

        $xmlString = '<multidate>';
        
        foreach($this->value->date as $date)
        {
            $xmlString .= '<date>';
            $xmlString .= '<month>'. (string) $date->month .'</month>';
            $xmlString .= '<day>'. (string) $date->day .'</day>';
            $xmlString .= '<year>'. (string) $date->year .'</year>';
            $xmlString .= '<era>'. (string) $date->era .'</era>';
            $xmlString .= '</date>';
        }
        
        $xmlString .= '</multidate>';
        
        return $xmlString;
	}
	
	public function getType()
	{
		return 'Date (Multi-Input)';
	}
	
	public function getSearchString($submitData) {
		if (isset($submitData[$this->cName]) && !empty($submitData[$this->cName])) {
			$dates = array();
			
			foreach($submitData[$this->cName] as $date) {
				$dateXML = simplexml_load_string($date);
				
				$str = "'%<date><month>";
	    		$str .= !empty($dateXML->month) ? $dateXML->month : "%";
	    		$str .= "</month><day>";
	    		$str .= !empty($dateXML->day) ? $dateXML->day : "%";
	    		$str .= "</day><year>";
	    		$str .= !empty($dateXML->year) ? $dateXML->year : "%";
	    		$str .= "</year><era>";
	    		$str .= !empty($dateXML->era) ? $dateXML->era : "%";
	    		$str .= "</era></date>%'";
	    		
	    		$dates[] = array('LIKE',$str);
			}
			
			return $dates;
		}
		else return false;
	}
	
	public function setXMLInputValue($value) {
		$dateParse = array();
		foreach ($value as $date) {
			$dateData = explode(" ",$date);
			list($month,$day,$year) = explode("/",$dateData[0]);
			
			$tmpDateStr = "<date><month>".$month."</month><day>".$day."</day><year>".$year."</year>";
			if (isset($dateData[1])) { $tmpDateStr .= "<era>".$dateData[1]."</era></date>"; }
			else { $tmpDateStr .= "<era>CE</era></date>"; }
			
			$dateParse[] = $tmpDateStr;
		}
		
		$this->XMLInputValue = $dateParse;
	}
	
	private function loadValues($valueArray) {
		$xmlString = '<multidate>';
        foreach($valueArray as $selectedOption)
        {
            $xmlString .= $selectedOption;
        }
        $xmlString .= '</multidate>';
        $this->value = simplexml_load_string($xmlString);
        
        
        // As an absolute fallback, if that fails (it shouldn't possibly if ingestion
        // calls validateIngestion before calling this), check for that and set it to
        // empty as a fallback
        if ($this->value === FALSE)
        {
        	$this->value === simplexml_load_string('<multidate></multidate>');
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
        } else if (!empty($_REQUEST) && isset($_REQUEST[$this->cName])) {
            $this->loadValues($_REQUEST[$this->cName]);
        } else {
        	$this->value = simplexml_load_string('<multidate></multidate>');
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
	
	public function isEmpty()
	{
		return !(!empty($_REQUEST[$this->cName]) || isset($this->XMLInputValue));
	}
	
	public function showData()
	{
		if (isset($this->value->date))
		{
			$returnString = '';
			foreach($this->value->date as $date)
			{
	            $returnString .= DateControl::formatDateForDisplay((int)$date->month, (int)$date->day, (int)$date->year, (string)$date->era, ($this->options->era == 'Yes'), (string)$this->options->displayFormat);
	            $returnString .= '<br />';
			}
			return $returnString;
		}
	}
	
	public function storedValueToDisplay($xml,$pid,$cid)
	{
		$xml = simplexml_load_string($xml);
		
		$returnVal = '';
		
		if (isset($xml->date))
		{
			foreach($xml->date as $date)
			{
			    // Get the options for the control
		        global $db;
		        $pid = (int) $pid;
		        if ($pid < 1) return gettext('Invalid PID');
		        $cid = (int) $cid;
		        if ($cid < 1) return gettext('Invalid Control ID');
		        $optionQuery = $db->query('SELECT options FROM p'.$pid.'Control WHERE cid='.$cid.' LIMIT 1');
		        if ($optionQuery->num_rows < 1)
		        {
		            return gettext('Invalid PID/CID');
		        }
		        $optionQuery = $optionQuery->fetch_assoc();
		        $options = simplexml_load_string($optionQuery['options']);
		        
		        $returnVal .= DateControl::formatDateForDisplay((int)$date->month, (int)$date->day, (int)$date->year, (string)$date->era, ((string) $options->era == 'Yes'), (string)$options->displayFormat);
                $returnVal .= '<br />';
			}
		}
		
		return $returnVal;
	}
	
	public function storedValueToSearchResult($xml)
	{
        $xml = simplexml_load_string($xml);
        
        $returnVal = array();
        
        if (isset($xml->date))
        {
            foreach($xml->date as $date)
            {
            	$currentDate = array();
		        if (isset($date->month)) $currentDate['month'] = (string) $date->month;
		        if (isset($date->day)) $currentDate['day'] = (string) $date->day;
		        if (isset($date->year)) $currentDate['year'] = (string) $date->year;
		        if (isset($date->era)) $currentDate['era'] = (string) $date->era;
		        
		        $returnVal[] = $currentDate;
            }
        }
        return $returnVal;
	}
	
	public function validateIngestion($publicIngest = false)
	{
	    if ($this->required && $this->isEmpty()){
            return gettext('No value supplied for required field').': '.htmlEscape($this->name);
        }

        $returnVal = '';
        if (!empty($_REQUEST[$this->cName] )){
        	$dates = $_REQUEST[$this->cName];
        }else if (!empty($this->XMLInputValue)){
        	$dates = $this->XMLInputValue;
        }else return '';
        
        	
		$startYear = (int) $this->options->startYear;
        $endYear = (int) $this->options->endYear;

        
        foreach($dates as $date){
            // Suppress the Error but catch it on the line below in case the XML is bad
            @$xml = simplexml_load_string($date);
            if ($xml === FALSE){
            	$returnVal = gettext('Value supplied for field').': '.htmlEscape($this->name).' '.gettext('is not valid XML').'.';
            }else{
                if (isset($xml->month) && isset($xml->day) && isset($xml->year) && isset($xml->era)){
                	$month = (string) $xml->month;
                	$day =   (string) $xml->day;
                	$year =  (string) $xml->year;
                	$era =   (string) $xml->era;
                		
                	if (!empty($era) && !in_array($era, array('CE', 'BCE')))
                	{
                		$returnVal = gettext('Field').' '.htmlEscape($this->name).': '.gettext('Era must be CE or BCE');
                	}
                	else if (!empty($month) && ((int)$month < 1 || (int)$month > 12))
                	{
                		$returnVal = gettext('Field').' '.htmlEscape($this->name).': '.gettext('Invalid Month');
                	}
                	else if (!empty($year) && ((int)$year < $startYear || (int)$year > $endYear))
                	{
                		$returnVal = gettext('Field').' '.htmlEscape($this->name).': '.gettext('Year outside of valid range');
                	}
                	else
                	{
                		$returnVal = $this->validDate((int)$day, (int)$month, (int)$year);
                	}
                }
                else
                {
                	$returnVal = gettext('Value supplied for field').': '.htmlEscape($this->name).' '.gettext('does not have all required XML members').'.';
                }
        	}
        }
            
        return $returnVal;
	
	}
}

class MultiDateControlOptions
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
		<form id="multiDateSettings">
		<table class="table">
		    <tr>
		        <td width="60%"><b><?php echo gettext('Date Range')?></b><br /><?php echo gettext('Please provide a start and end year in the range 1-9999 for the control')?>.</td>
		        <td><table border="0">
		           <tr>
		               <td><?php echo gettext('Start Date')?></td>
		               <td><input type="text" name="startDate" id="startDate" value="<?php echo (string)$xml->startYear?>" /></td>
		           </tr>
		           <tr>
		               <td><?php echo gettext('End Date')?></td>
		               <td><input type="text" name="endDate" id="endDate" value="<?php echo (string)$xml->endYear?>" /></td>
		           </tr>
		           <tr>
		               <td></td>
		               <td><input type="button" value="<?php echo gettext('Update')?>" onclick="updateDateRange()" /></td>
		           </tr>
		        </table></td>
		    </tr>
		    <tr>
		        <td><b><?php echo gettext('Date Format')?></b><br /><?php echo gettext('Please select the format you would prefer to enter your dates in.  This format will also be used for displaying the dates inside of KORA (this setting has no effect on front-end sites).')?></td>
		        <td><input type="radio" name="format" value="MDY" <?php  if ( (string)$xml->displayFormat == 'MDY' ) echo 'checked'; ?> /><?php echo gettext('MM DD, YYYY')?><br />
		            <input type="radio" name="format" value="DMY" <?php  if ( (string)$xml->displayFormat == 'DMY' ) echo 'checked'; ?> /><?php echo gettext('DD MM YYYY')?><br />
		            <input type="radio" name="format" value="YMD" <?php  if ( (string)$xml->displayFormat == 'YMD' ) echo 'checked'; ?> /><?php echo gettext('YYYY MM DD')?><br />
		            <input type="button" value="Update" onclick="updateFormat()" /></td>
		    </tr>
		    <tr>
		        <td><b><?php echo gettext('Default Value')?></b><br /><?php echo gettext('Please select an optional value which will be initially filled in during ingestion')?>.</td>
		        <td>
		        <table>
				<div class="kora_control">
		            <tr><td colspan="2">
		                <select id="defaultValue" name="defaultValue" size="5">
						<?php
					   // Show any existing Default Values
					   if (isset($xml->defaultValue->date))
					   {
					       $i = 1;
					       foreach($xml->defaultValue->date as $date)
					       {
					            $display = DateControl::formatDateForDisplay((int)$date->month, (int)$date->day, (int)$date->year, (string)$date->era, ((string)$xml->era == 'Yes'),(string)$xml->displayFormat);
					            echo "<option value=\"$i\">$display</option>";
					            $i++;
					       }
					   }
					   ?>
                		</select>
					</td></tr>
            		<tr>
		                <td><input type="button" value="<?php echo gettext('Up')?>" onclick="moveDefaultValue('up')" /></td>
		                <td><input type="button" value="<?php echo gettext('Down')?>" onclick="moveDefaultValue('down')" /></td>
            		</tr>
            		<tr><td colspan="2">
		<?php
        $month = $day = $year = '';
        if (isset($xml->defaultValue->month)) $month = (string) $xml->defaultValue->month;
        if (isset($xml->defaultValue->day))   $day   = (string) $xml->defaultValue->day;
        if (isset($xml->defaultValue->year))  $year  = (string) $xml->defaultValue->year;
        
        // Use output buffering to get the three fields and then display
        // them in the proper order
        
        // Month
        ob_start();
        echo '<select name="month" id="month">';
        echo '<option value=""';
        if (empty($month)) echo ' selected="selected"';
        echo '>&nbsp;</option>';
        
        for ($i=1; $i <= 12; $i++)
        {
            echo "<option value=\"$i\"";
            if ($i == $month) echo ' selected="selected"';
            echo '>'.gettext(DateControl::$months[$i]['name']).'</option>';
        }
        echo '</select>';
        $monthDisplay = ob_get_clean();

        // Day
        ob_start();
        echo '<select name="day" id="day">';
        echo '<option value=""';
        if (empty($day)) echo ' selected="selected"';
        echo '>&nbsp;</option>';
        for ($i=1; $i <= 31; $i++)
        {
            echo "<option value=\"$i\"";
            if ($i == $day) echo ' selected="selected"';
            echo ">$i</option>";
        }
        echo '</select>';
        $dayDisplay = ob_get_clean();
        
        // Year
        ob_start();
        echo '<select name="year" id="year">';
        echo '<option value=""';
        if (empty($year)) echo ' selected="selected"';
        echo '>&nbsp;</option>';
        
        $startYear = (int) $xml->startYear;
        $endYear = (int) $xml->endYear;
        
        // Make sure we don't hit an infinite loop - if end < start, switch them
        if ($endYear < $startYear)
        {
            $temp = $endYear;
            $endYear = $startYear;
            $startYear = $temp;
            unset($temp);
        }
        
        for ($i=$startYear; $i <= $endYear; $i++)
        {
            echo "<option value=\"$i\"";
            if ($i == $year) echo ' selected="selected"';
            echo ">$i</option>";
        }
        echo '</select>';
        $yearDisplay = ob_get_clean();

        // CE/BCE?
        ob_start();
        if ((string) $xml->era == 'Yes')
        {
            $era = '';
            if (isset($xml->defaultValue->era)) $era = (string) $xml->defaultValue->era;
            
            echo '<select name="era" id="era">';
            
            $eras = array('','CE','BCE');
            foreach($eras as $e)
            {
                echo "<option value=\"$e\"";
                if ($e == $era) echo ' selected="selected"';
                echo ">$e</option>";
            }
            echo '</select>';
        }
        else
        {
            echo '<input type="hidden" name="era" id="era" value="CE" />';
        }
        $eraDisplay = ob_get_clean();
        
        // Display stuff in the proper order
        if ($xml->displayFormat == 'MDY')
        {
            echo $monthDisplay.' '.$dayDisplay.' '.$yearDisplay.' '.$eraDisplay;
        }
        else if ($xml->displayFormat == 'DMY')
        {
            echo $dayDisplay.' '.$monthDisplay.' '.$yearDisplay.' '.$eraDisplay;
        }
        else if ($xml->displayFormat == 'YMD')
        {
            echo $yearDisplay.' '.$monthDisplay.' '.$dayDisplay.' '.$eraDisplay;
        }
        else
        {
            echo gettext("This control's display format is an unrecognized value; please check its options.");
        }
		?>
		            </td></tr>
		            <tr>
		                <td><input type="button" value="<?php echo gettext('Add')?>" onclick="addDefaultValue();" /></td>
		                <td><input type="button" value="<?php echo gettext('Remove')?>" onclick="removeDefaultValue();" /></td>
		            </tr>
		            
		            </table>
					</div>
		        </td>
		    </tr>
		    <tr>
		        <td><b><?php echo gettext('Show Era Field?')?></b><br /><?php echo gettext('Choose whether or not to show the CE/BCE selector; if you choose not to show it, the control will default to assuming CE.')?></td>
		        <td><input type="radio" name="showEra" id="showEra" value="No" <?php  if ( (string)$xml->era == 'No' ) echo 'checked'; ?> /><?php echo gettext('No')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		            <input type="radio" name="showEra" id="showEra" value="Yes" <?php  if ( (string)$xml->era == 'Yes' ) echo 'checked'; ?> /><?php echo gettext('Yes')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		            <input type="button" value="<?php echo gettext('Update')?>" onclick="updateEra()" /></td>
		    </tr>
		</table>
		</form>
		<?php
    }
    
    public static function moveDefaultValue($cid, $default, $direction)
    {
        global $db;
        
        // Make sure the default to be moved is a number (corresponding to position in list)
        if (is_numeric($default))
        {
            $default = (int)$default;
        }
        else
        {
            return;
        }
        
        // Make sure the direction is valid
        if (!in_array($direction, array('up', 'down')))
        {
            return;
        }
        
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<options />');
        
        // Ensure that the key is valid
        if (!isset($xml->defaultValue->date) || $default < 1 || $default > count($xml->defaultValue->date))
        {
            return;
        }

        // Otherwise, make sure this isn't a redundant move
        if (($direction == 'up' && $default == 1) || ($direction == 'down' && $default == count($xml->defaultValue->date)))
        {
            return;
        }
        
        // copy over fields other than the default Value
        foreach($xml->children() as $key => $value)
        {
            if ($key != 'defaultValue')
            {
                $newXML->addChild($key, (string)$value);
            }
        }
        $defVal = $newXML->addChild('defaultValue');
        // Copy the old defaults, minus the one to be removed
        $i = 1;
        $cache = '';
        foreach($xml->defaultValue->date as $v)
        {
            if ($direction == 'up')
            {
                if ($i == ($default - 1))
                {
                    $cache = $v;    // Cache the record directly before the one
                                    // to be moved up
                }
                else if ($i == $default)
                {
                    // Add the current record, then the cached one (which was the one
                    // above the current record), which has the effect of moving it up
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$v->month);
                    $date->addChild('day', (string)$v->day);
                    $date->addChild('year', (string)$v->year);
                    $date->addChild('era', (string)$v->era);
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$cache->month);
                    $date->addChild('day', (string)$cache->day);
                    $date->addChild('year', (string)$cache->year);
                    $date->addChild('era', (string)$cache->era);
                }
                else
                {
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$v->month);
                    $date->addChild('day', (string)$v->day);
                    $date->addChild('year', (string)$v->year);
                    $date->addChild('era', (string)$v->era);
                }
            }
            else // if direction == 'down'
            {
                if ($i == $default)
                {
                    // Cache the specified record so that we can add the one below it
                    // first, effectively moving it down
                    $cache = $v;
                }
                else if ($i == ($default + 1))
                {
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$v->month);
                    $date->addChild('day', (string)$v->day);
                    $date->addChild('year', (string)$v->year);
                    $date->addChild('era', (string)$v->era);
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$cache->month);
                    $date->addChild('day', (string)$cache->day);
                    $date->addChild('year', (string)$cache->year);
                    $date->addChild('era', (string)$cache->era);
                }
                else
                {
                    $date = $defVal->addChild('date');
                    $date->addChild('month', (string)$v->month);
                    $date->addChild('day', (string)$v->day);
                    $date->addChild('year', (string)$v->year);
                    $date->addChild('era', (string)$v->era);
                }
            }
            $i++;
        }
        
       setControlOptions($cid, $newXML);
    }
    
    public static function removeDefaultValue($cid, $default)
    {
        // Make sure the default to be moved is a number (corresponding to position in list)
        if (is_numeric($default))
        {
            $default = (int)$default;
        }
        else
        {
            return;
        }
        
        $xml = getControlOptions($cid);
        if(!$xml) return;
        
        $newXML = simplexml_load_string('<options />');
        
        // Ensure that the key is valid
        if (!isset($xml->defaultValue->date) || $default < 1 || $default > (count($xml->defaultValue->date) + 1))
        {
            return;
        }
        
        // copy over fields other than the default Value
        foreach($xml->children() as $key => $value)
        {
            if ($key != 'defaultValue')
            {
                $newXML->addChild($key, (string)$value);
            }
        }
        $defVal = $newXML->addChild('defaultValue');
        // Copy the old defaults, minus the one to be removed
        $i = 1;
        foreach($xml->defaultValue->date as $v)
        {
            if ($i != $default)
            {
                $date = $defVal->addChild('date');
                $date->addChild('month', (string)$v->month);
                $date->addChild('day', (string)$v->day);
                $date->addChild('year', (string)$v->year);
                $date->addChild('era', (string)$v->era);
            }
            $i++;
        }
        
        setControlOptions($cid, $newXML);
    }
    
    public static function addDefaultValue($cid, $month, $day, $year, $era)
    {
        $xml = getControlOptions($cid);
        if(!$xml) return;

        // validate the components
        if (!empty($month)) {
            $month = (int) $month;
            if ($month < 1 || $month > 12) {
                $month = '';
            }
        } else $month = '';
        
        if (!empty($day)) {
            $day = (int) $day;
            if ($day < 1 || $day > 31) {
                $day = '';
            }
        } else $day = '';
        
        if (!empty($year)) {
            $year = (int) $year;
            if ($year < (int)$xml->startYear || $year > (int)$xml->endYear) {
                $year = '';
            }
        } else $year = '';
        
        if (!in_array($era, array('CE', 'BCE', ''))) {
            $era = '';
        }
        
        if ($month == '' && $day == '' && $year == '') {
            return;
        }
        
        $date = $xml->defaultValue->addChild('date');
        $date->addChild('month', $month);
        $date->addChild('day', $day);
        $date->addChild('year', $year);
        $date->addChild('era', $era);
        
        setControlOptions($cid, $xml);
    }
}

if (isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'MultiDateControl')
{
    requireScheme();
    $action = $_POST['action'];
    
    if ($action == 'showDialog') {
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'moveDefaultValue') {
        MultiDateControlOptions::moveDefaultValue($_POST['cid'], $_POST['defaultV'], $_POST['direction']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'removeDefaultValue') {
        MultiDateControlOptions::removeDefaultValue($_POST['cid'], $_POST['defaultV']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'addDefaultValue') {
        MultiDateControlOptions::addDefaultValue($_POST['cid'], $_POST['month'], $_POST['day'], $_POST['year'], $_POST['era']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if($action == 'updateDateRange') {
        DateControlOptions::updateDateRange($_POST['cid'], $_POST['startYear'], $_POST['endYear']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateEra') {
        DateControlOptions::updateEra($_POST['cid'], $_POST['era']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    } else if ($action == 'updateFormat') {
        DateControlOptions::updateFormat($_POST['cid'], $_POST['format']);
        MultiDateControlOptions::showDialog($_POST['cid']);
    }
}

?>
