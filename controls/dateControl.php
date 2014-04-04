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

require_once("control.php");


class DateControl extends Control
{
	protected $name = "Date Control";
	protected $ExistingData;
	protected $value;
	
	public static $months = array(
		0  => array('name' => '',		  'days' =>  0),
		1  => array('name' => 'January',   'days' => 31),
		2  => array('name' => 'February',  'days' => 28),
		3  => array('name' => 'March',	 'days' => 31),
		4  => array('name' => 'April',	 'days' => 30),
		5  => array('name' => 'May',	   'days' => 31),
		6  => array('name' => 'June',	  'days' => 30),
		7  => array('name' => 'July',	  'days' => 31),
		8  => array('name' => 'August',	'days' => 31),
		9  => array('name' => 'September', 'days' => 30),
		10 => array('name' => 'October',   'days' => 31),
		11 => array('name' => 'November',  'days' => 30),
		12 => array('name' => 'December',  'days' => 31)
	);
	
	public function DateControl($projectid='', $controlid='', $recordid='', $presetid='', $inPublicTable = false)
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
			if (isset($lastIngestion['editRecord']) && $lastIngestion['editRecord'] == $recordid &&
				(isset($lastIngestion[$this->cName.'month']) || isset($lastIngestion[$this->cName.'day']) ||
				isset($lastIngestion[$this->cName.'year']) || isset($lastIngestion[$this->cName.'era']) ||
				isset($lastIngestion[$this->cName.'prefix']) || isset($lastIngestion[$this->cName.'suffix']) ))
			{
				$this->value = simplexml_load_string('<date><month /><day /><year /><era /><prefix /><suffix /></date>');
				$this->value->month = (isset($lastIngestion[$this->cName.'month']) ? $lastIngestion[$this->cName.'month'] : '');
				$this->value->day   = (isset($lastIngestion[$this->cName.'day'])   ? $lastIngestion[$this->cName.'day']   : '');
				$this->value->year  = (isset($lastIngestion[$this->cName.'year'])  ? $lastIngestion[$this->cName.'year']  : '');
				$this->value->era  = (isset($lastIngestion[$this->cName.'era'])	? $lastIngestion[$this->cName.'era']	: '');
				$this->value->prefix  = (isset($lastIngestion[$this->cName.'prefix'])	? $lastIngestion[$this->cName.'prefix']	: '');
				$this->value->suffix  = (isset($lastIngestion[$this->cName.'suffix'])	? $lastIngestion[$this->cName.'suffix']	: '');

				// See if fields were left blank
				if ((string)$this->value->month === '') $this->value->month = '';
				if ((string)$this->value->day === '') $this->value->day = '';
				if ((string)$this->value->year === '') $this->value->year = '';
		
				
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
					
				
					// for reverse compatibility
					if(!isset($this->value->prefix)) $this->value->addChild('prefix','');
					if(!isset($this->value->suffix)) $this->value->addChild('suffix','');
				}
			}
		}
		else if ( (isset($lastIngestion[$this->cName.'month']) || isset($lastIngestion[$this->cName.'day']) ||
				   isset($lastIngestion[$this->cName.'year']) || isset($lastIngestion[$this->cName.'era']) ||
				   isset($lastIngestion[$this->cName.'prefix']) || isset($lastIngestion[$this->cName.'suffix'])
				  ) && !isset($lastIngestion['editRecord']))
		{
			$this->value = simplexml_load_string('<date><month /><day /><year /><era /><prefix /><suffix /></date>');
			$this->value->month = (isset($lastIngestion[$this->cName.'month']) ? $lastIngestion[$this->cName.'month'] : '');
			$this->value->day   = (isset($lastIngestion[$this->cName.'day'])   ? $lastIngestion[$this->cName.'day']   : '');
			$this->value->year  = (isset($lastIngestion[$this->cName.'year'])  ? $lastIngestion[$this->cName.'year']  : '');
			$this->value->era  = (isset($lastIngestion[$this->cName.'era'])	? $lastIngestion[$this->cName.'era']	: '');
			$this->value->era  = (isset($lastIngestion[$this->cName.'prefix'])	? $lastIngestion[$this->cName.'prefix']	: '');
			$this->value->era  = (isset($lastIngestion[$this->cName.'suffix'])	? $lastIngestion[$this->cName.'suffix']	: '');
			
			
			// See if fields were left blank
			if ((string)$this->value->month === '') $this->value->month = '';
			if ((string)$this->value->day === '') $this->value->day = '';
			if ((string)$this->value->year === '') $this->value->year = '';
		
			$this->ExistingData = true;
		}
		else if (!empty($presetid))
		{
			$valueCheck = $db->query('SELECT value FROM p'.$projectid.'Data WHERE id='.escape($presetid).' AND cid='.escape($controlid).' LIMIT 1');
			if ($valueCheck->num_rows > 0)
			{
				$this->ExistingData = true;
				$valueCheck = $valueCheck->fetch_assoc();
				$this->value = simplexml_load_string($valueCheck['value']);
			}
		}
		else if (isset($this->options->defaultValue) && ( !empty($this->options->defaultValue->month) ||
		 		 !empty($this->options->defaultValue->day) || !empty($this->options->defaultValue->year) ))
		{
			$this->value = $this->options->defaultValue;
		}
	}
	
	// Delete any existing data for the control
	public function delete() {
		global $db;
		
		if (!$this->isOK()) return;
		
		if (!empty($this->rid)) $deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1');
		else {
			$deleteCall = $db->query('DELETE FROM p'.$this->pid.'Data WHERE cid='.escape($this->cid));
			$publicDeleteCall = $db->query('DELETE FROM p'.$this->pid.'PublicData WHERE cid='.escape($this->cid));
		}
	}

	public function display()
	{
		//echo '<table><tr><td>';
		echo '<div class="kora_control">';
		
		$month = $day = $year = $prefix = $suffix = '';
		if (isset($this->value->month)) $month = (string) $this->value->month;
		if (isset($this->value->day))   $day   = (string) $this->value->day;
		if (isset($this->value->year))  $year  = (string) $this->value->year;
		if (isset($this->value->prefix))  $prefix  = (string) $this->value->prefix;
		if (isset($this->value->suffix))  $suffix  = (string) $this->value->suffix;
		
		// Use output buffering to get the three fields and then display
		// them in the proper order
		
		// Month
		ob_start();
		echo '<select name="'.$this->cName.'month" id="'.$this->cName.'month">';
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
		echo '<select name="'.$this->cName.'day" id="'.$this->cName.'day">';
		echo '<option value=""';
		if (empty($day) || $day == 0) echo ' selected="selected"';
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
		if((string)$this->options->yearInputStyle == 'text'){
			echo '<input type="text" name="'.$this->cName.'year" value="'.$year.'" id="'.$this->cName.'year" size="4" />';
		}
		else{
			echo '<select name="'.$this->cName.'year" id="'.$this->cName.'year">';
			echo '<option value=""';
			if (empty($year) || $year == 0) echo ' selected="selected"';
			echo '>&nbsp;</option>';
			
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
				echo "<option value=\"$i\"";
				if ($i == $year) echo ' selected="selected"';
				echo ">$i</option>";
			}
			echo '</select>';
		}
		$yearDisplay = ob_get_clean();

		// CE/BCE?
		ob_start();
		if ((string) $this->options->era == 'Yes')
		{
			$era = '';
			if (isset($this->value->era)) $era = (string) $this->value->era;
			
			echo '<select name="'.$this->cName.'era" id="'.$this->cName.'era">';
			
			$eras = $this->getEraData();
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
			echo '<input type="hidden" name="'.$this->cName.'era" id="'.$this->cName.'era" value="CE" />';
		}
		$eraDisplay = ob_get_clean();
		
		
		// prefix
		$prefixDisplay = '';
		if(isset($this->options->prefixes)){
			$prefixDisplay = '<select name="'.$this->cName.'prefix" id="'.$this->cName.'prefix">';
			$prefixDisplay .= '<option></option>';
			foreach($this->options->prefixes as $value){
				$value = (string)$value;
				$selected = '';
				if($value == $prefix) $selected = 'selected="selected"';
				
				$prefixDisplay .= "<option $selected >$value</option>";
			}
			
			$prefixDisplay .= '</select>';
		}

		
		
		// suffix
		$suffixDisplay = '';
		if(isset($this->options->suffixes)){
			$suffixDisplay = '<select name="'.$this->cName.'suffix" id="'.$this->cName.'suffix">';
			$suffixDisplay .= '<option></option>';
			foreach($this->options->suffixes as $value){
				$value = (string)$value;
				$selected = '';
				if($value == $suffix) $selected = 'selected="selected"';
				
				$suffixDisplay .= "<option $selected >$value</option>";
			}
			
			$suffixDisplay .= '</select>';
		}
		
				
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
		
		if($prefixDisplay != ''){
			echo '<br/>Prefix: '.$prefixDisplay;
		}
		if($suffixDisplay != ''){
			echo '<br/>Suffix: '.$suffixDisplay;
		}
		echo '</div>';
		//echo '</td></tr></table>';
	}
	
	public function displayAutoFill($category) {
		$dateOptions = array('from','to');
	
		for($j=0 ; $j<sizeof($dateOptions) ; ++$j) {
			ob_start();
			echo '<select class="af_'.$category.$j.'" name="af_'.$category.$j.'_month" id="af_'.$category.$j.'_month">';
			for ($i=1; $i <= 12; $i++)
			{
				echo "<option value=\"$i\">".gettext(DateControl::$months[$i]['name']).'</option>';
			}
			echo '</select>';
			$monthDisplay = ob_get_clean();
	
			// Day
			ob_start();
			echo '<select class="af_'.$category.$j.'" name="af_'.$category.$j.'_day" id="af_'.$category.$j.'_day">';
			for ($i=1; $i <= 31; $i++)
			{
				echo "<option value=\"$i\">$i</option>";
			}
			echo '</select>';
			$dayDisplay = ob_get_clean();
			
			// Year
			ob_start();
			echo '<select class="af_'.$category.$j.'" name="af_'.$category.$j.'_year" id="af_'.$category.$j.'_year">';
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
				echo "<option value=\"$i\">$i</option>";
			}
			echo '</select>';
			$yearDisplay = ob_get_clean();
	
			// CE/BCE?
			ob_start();
			if ((string) $this->options->era == 'Yes')
			{
				$era = '';
				if (isset($this->value->era)) $era = (string) $this->value->era;
				
				echo '<select class="af_'.$category.$j.'" name="af_'.$category.$j.'_era" id="af_'.$category.$j.'_era">';
				
				$eras = $this->getEraData();
				foreach($eras as $e)
				{
					if($e) {
						echo "<option value=\"$e\">$e</option>";
					}
				}
				echo '</select>';
			}
			else
			{
				echo '<input type="hidden" class="af_'.$category.$j.'" name="af_'.$category.$j.'_era" id="af_'.$category.$j.'_era" value="CE" />';
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
			echo '</div>';

			if ($j == 0) {
				echo '<div style="text-align: center;"> to </div>';
			}
		}
		echo '<input type="hidden" id="af_'.$category.'_op" value="between"/>';
	}
	
	public function displayXML()
	{
		if(!$this->isOK()) return '';
		
		$xmlString = '<date>';
		$xmlString .= '<month>'.(int)$this->value->month.'</month>';
		$xmlString .= '<day>'.(int)$this->value->day.'</day>';
		$xmlString .= '<year>'.(int)$this->value->year.'</year>';
		$xmlString .= '<era>'.(int)$this->value->era.'</era>';
		$xmlString .= '<prefix>'.(int)$this->value->prefix.'</prefix>';
		$xmlString .= '<suffix>'.(int)$this->value->suffix.'</suffix>';
		$xmlString .= '</date>';

		return $xmlString;
	}
	
	public function displayOptionsDialog()
	{
		$controlPageURL = baseURI . 'controls/dateControl.php';
?>

<script type="text/javascript">
//<![CDATA[
function updateDateRange()
{
   	var startDate = $('#startDate').val();
	var endDate = $('#endDate').val();

	$.post('<?php echo $controlPageURL?>', {action:'updateDateRange',source:'DateControl',cid:<?php echo $this->cid?>,endYear:endDate,startYear:startDate }, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateEra()
{
	var e;
	$('input[name="showEra"]').each(function(){
		if(this.checked){
			e = this.value;
		}
	});
	$.post('<?php echo $controlPageURL?>', {action:'updateEra',source:'DateControl',cid:<?php echo $this->cid?>,era:e}, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateYearInput(){
	var e = '';
	$('input[name="yearInputStyle"]').each(function(){
		if(this.checked){
			e = this.value;
		}
	});

	if(e == '') return;
	$.post('<?php echo $controlPageURL?>', {action:'updateYearInput',source:'DateControl',cid:<?php echo $this->cid?>,style:e}, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateFormat()
{
	var f;
	$('input[type="radio"]').each(function(){
		if(this.name == 'format' && this.checked){
			f = this.value;
		}
	});

	$.post('<?php echo $controlPageURL?>', {action:'updateFormat',source:'DateControl',cid:<?php echo $this->cid?>,format:f }, function(resp){$("#ajax").html(resp);}, 'html');
}

function updateDefaultValue()
{
	var vmonth = $('#month').val();
	var vday   = $('#day').val();
	var vyear  = $('#year').val();
	var vera   = $('#era').val();
	$.post('<?php echo $controlPageURL?>', {action:'updateDefaultValue',source:'DateControl',cid:<?php echo $this->cid?>, month:vmonth, day:vday, year:vyear, era:vera }, function(resp){$("#ajax").html(resp);}, 'html');
}

function addOption(option,value){
	if(option == '' || value == '' || value == null) return;
	$.post("<?php echo $controlPageURL?>", {action:'addOption',source:'DateControl',cid:<?php echo $this->cid?>,option:option,value:value}, function(resp){$("#ajax").html(resp);}, 'html');
}

function removeOption(option,value){
	if(option == '' || value == '' || value == null) return;
	$.post("<?php echo $controlPageURL?>", {action:'removeOption',source:'DateControl',cid:<?php echo $this->cid?>,option:option,value:value}, function(resp){$("#ajax").html(resp);}, 'html');
}
$.post('<?php echo $controlPageURL?>', {action:'showDialog',source:'DateControl',cid:<?php echo $this->cid?> }, function(resp){$("#ajax").html(resp);}, 'html');
//]]>
</script>

<div id="ajax"></div>
<?php
	}

	// Format the date for an internal display format given its
	// format display setting and its values.  Takes integers for the
	// first 3 arguments, strings for the fourth and sixth, and a
	// boolean for the fifth
	public static function formatDateForDisplay($month, $day, $year, $era, $showEra, $format, $prefix='', $suffix='')
	{
		$month = (int)$month;
		$day = (int)$day;
		$year = (string)$year;
		
		
		$returnVal = ($prefix == '') ? '':$prefix.' ';
		
		if ($format == 'MDY')
		{
			if ($month > 0)
			{
				$returnVal .= gettext(DateControl::$months[$month]['name']) . ' ';
			}
			if ($day > 0)
			{
				$returnVal .= (string) $day;
				if ($year > 0)
				{
					$returnVal .= ', ';
				}
			}
			if ($year !== '')
			{
				$returnVal .= (string) $year;
			}
		}
		else if ($format == 'DMY')
		{
			if ($day > 0)
			{
				$returnVal .= (string) $day.' ';
			}
			if ($month > 0)
			{
				$returnVal .= gettext(DateControl::$months[$month]['name']) . ' ';
			}
			if ($year !== '')
			{
				$returnVal .= (string) $year;
			}
		}
		else if ($format == 'YMD')
		{
			if ($year !== '')
			{
				$returnVal .= (string) $year.' ';
			}
			if ($month > 0)
			{
				$returnVal .= gettext(DateControl::$months[$month]['name']) . ' ';
			}
			if ($day > 0)
			{
				$returnVal .= (string) $day;
			}
		}
		else
		{
			$returnVal = gettext('This control has no format option set; please check its options.');
		}
		
		$returnVal .= $suffix;
		
		if ($showEra)
		{
			$returnVal .= ' '.(string) $era;
		}
		
		return $returnVal;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function getSearchString($submitData) {
		if(	   isset($submitData[$this->cName."month"]) && !empty($submitData[$this->cName."month"])
			|| isset($submitData[$this->cName."day"])   && !empty($submitData[$this->cName."day"])
			|| isset($submitData[$this->cName."year"])  && !empty($submitData[$this->cName."year"])
			|| isset($submitData[$this->cName."era"])   && !empty($submitData[$this->cName."era"])) {
			
			$str = "'%<month>";
			$str .= !empty($submitData[$this->cName."month"]) ? $submitData[$this->cName."month"] : "%";
			$str .= "</month><day>";
			$str .= !empty($submitData[$this->cName."day"]) ? $submitData[$this->cName."day"] : "%";
			$str .= "</day><year>";
			$str .= !empty($submitData[$this->cName."year"]) ? $submitData[$this->cName."year"] : "%";
			$str .= "</year><era>";
			$str .= !empty($submitData[$this->cName."era"]) ? $submitData[$this->cName."era"] : "%";
			$str .= "</era>%'";
			
			return array(array("LIKE",$str));
		}
		else return false;
	}
	
	public function getType()
	{
		return "Date";
	}
   
	public function setXMLInputValue($value) {
		$dateData = explode(" ",$value[0]);
		list($month,$day,$year) = explode("/",$dateData[0]);
		
		$this->XMLInputValue = array();
		$this->XMLInputValue[$this->cName.'month'] = $month;
		$this->XMLInputValue[$this->cName.'day'] = $day;
		$this->XMLInputValue[$this->cName.'year'] = $year;
		$this->XMLInputValue[$this->cName.'era'] = isset($dateData[1]) ? $dateData[1] : '';

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
		
		$this->value = simplexml_load_string('<date><month /><day /><year /><era /><prefix /><suffix /></date>');
		if (empty($this->rid))
		{
			echo '<div class="error">'.gettext('No Record ID Specified').'.</div>';
			return;
		} else if (isset($this->XMLInputValue)) {
			$this->loadValue($this->XMLInputValue);
		} else if (!empty($_REQUEST)) {
			$this->loadValue($_REQUEST);
		} else {
			$this->value = '';
		}
			  
		// ingest the data
		$query = '';	// default blank query
		if ($this->ExistingData)
		{
			if ($this->isEmpty()) $query = 'DELETE FROM p'.$this->pid.$tableName.' WHERE id='.escape($this->rid).
										   ' AND cid='.escape($this->cid).' LIMIT 1';
			else $query =   'UPDATE p'.$this->pid.$tableName.' SET value='.escape($this->value->asXML()).
							' WHERE id='.escape($this->rid).' AND cid='.escape($this->cid).' LIMIT 1';
		}
		else
		{
			if (!$this->isEmpty()) $query = 'INSERT INTO p'.$this->pid.$tableName.' (id, cid, schemeid, value) VALUES ('.escape($this->rid).', '.escape($this->cid).', '.escape($this->sid).', '.escape($this->value->asXML()).')';
		}
		
		if (!empty($query)) $db->query($query);
	}
	
	public static function initialOptions()
	{
		return '<options><startYear>1970</startYear><endYear>2070</endYear><era>No</era><displayFormat>MDY</displayFormat><defaultValue><day /><month /><year /><era /></defaultValue></options>';
	}
	
	public function isEmpty()
	{
		# RE-WRITTEN THIS WAY TO AVOID PHP WARNINGS
		if (isset($this->XMLInputValue)) { return false; }
		else
		{ return !( !(empty($_REQUEST[$this->cName.'month']) && empty($_REQUEST[$this->cName.'day']) && empty($_REQUEST[$this->cName.'year']) )); }
	}
	
	public function isXMLPacked() { return true; }
	
	public function showData()
	{
		if (!empty($this->rid) && is_object($this->value))
		{
			return DateControl::formatDateForDisplay((int)$this->value->month, (int)$this->value->day, (int)$this->value->year, (string)$this->value->era, ($this->options->era == 'Yes'), (string) $this->options->displayFormat,$this->value->prefix,$this->value->suffix);
		}
	}
	
	public function storedValueToDisplay($xml,$pid,$cid)
	{
		$xml = simplexml_load_string($xml);
		$returnVal = '';

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
		
	
		$prefix = isset($xml->prefix) ? (string)$xml->prefix:'';
		$suffix = isset($xml->suffix) ? (string)$xml->suffix:'';
		
		return DateControl::formatDateForDisplay((int)$xml->month, (int)$xml->day, (int)$xml->year, (string)$xml->era, ($options->era == 'Yes'), (string) $options->displayFormat, $prefix, $suffix);
	}
	
	public function storedValueToSearchResult($xml)
	{
		$xml = simplexml_load_string($xml);
		
		$returnVal = array();
		if (isset($xml->month)) $returnVal['month'] = (string) $xml->month;
		if (isset($xml->day)) $returnVal['day'] = (string) $xml->day;
		if (isset($xml->year)) $returnVal['year'] = (string) $xml->year;
		if (isset($xml->era)) $returnVal['era'] = (string) $xml->era;
		if (isset($xml->prefix)) $returnVal['prefix'] = (string) $xml->prefix;
		if (isset($xml->suffix)) $returnVal['suffix'] = (string) $xml->suffix;
		
		return $returnVal;
	}

	public function validateIngestion($publicIngest = false)
	{
		if ($this->required && $this->isEmpty())
		{
			return gettext('No value supplied for required field').': '.htmlEscape($this->name);
		}
		else if ($this->isEmpty())
		{
			return '';
		}
		
		if (isset($this->XMLInputValue)) {
			$dateArray = $this->XMLInputValue;
		} else {
			$dateArray = $_REQUEST;
		}
		
		$day = (int) $dateArray[$this->cName.'day'];
		$month = (int) $dateArray[$this->cName.'month'];
		$year = (int) $dateArray[$this->cName.'year'];
		$era = $dateArray[$this->cName.'era'];
		
		$startYear = (int) $this->options->startYear;
		$endYear = (int) $this->options->endYear;
		
		// make sure that all of the ranges fit within their specified values
		if (($month < 0) || ($month > 12)) {
			return gettext('Invalid Month specified for field').': '.htmlEscape($this->name);
		} else if ( ( $year < $startYear  ||  $year > $endYear ) && (string)$this->options->yearInputStyle != 'text') {
			return gettext('Invalid Year specified for field').': '.htmlEscape($this->name);
		} else if ( (string) $this->options->era == 'Yes' && !empty($era) && !in_array( (string) $era, $this->getEraData()) ) {
			return '"'.gettext(htmlEscape($era).'" is not a valid option for an era.');
		} else return $this->validDate($day, $month, $year);
	}
	
	// This encapsulates all of the leap-year checks and what not so that derived controls
	// such as multi-date can re-use the logic.
	//
	// Just like validateIngestion, returns '' on success and an error string upon failure
	public function validDate($day, $month, $year, $staticCall = false)
	{
		if ( $day > DateControl::$months[$month]['days'] )
		{
			if (($month == 2) && ($day == 29))
			{
				// Ooh boy, a leap year!
				if (($year % 4) || (!($year % 100) && ($year % 400)))
				{
					if ($staticCall)
					{
						return gettext('Field').': '.htmlEscape($this->name)." - $year ".gettext('is not a leap year').".<br />";
					}
					else
					{
						return "$year ".gettext('is not a leap year').".<br />";
					}
				}
				else return '';   // Wow, it really IS a leap year
			}
			else
			{
				if ($staticCall)
				{
					return gettext('Field').': '.htmlEscape($this->name).' - '.gettext('There are only ').DateControl::$months[$month]['days'].gettext(' days in ').gettext(DateControl::$months[$month]['name']).".<br />";
				}
				else
				{
					return gettext('There are only ').DateControl::$months[$month]['days'].gettext(' days in ').gettext(DateControl::$months[$month]['name']).".<br />";
				}
			}
		}
		else return '';
	}
	
	private function loadValue($dateArray) {
		$this->value->month = (isset($dateArray[$this->cName.'month']) ? $dateArray[$this->cName.'month'] : '');
		$this->value->day   = (isset($dateArray[$this->cName.'day'])   ? $dateArray[$this->cName.'day']   : '');
		$this->value->year  = (isset($dateArray[$this->cName.'year'])  ? $dateArray[$this->cName.'year']  : '');
		$this->value->era   = (isset($dateArray[$this->cName.'era'])   ? $dateArray[$this->cName.'era']   : '');
		$this->value->prefix= (isset($dateArray[$this->cName.'prefix'])? $dateArray[$this->cName.'prefix']   : '');
		$this->value->suffix= (isset($dateArray[$this->cName.'suffix'])? $dateArray[$this->cName.'suffix']   : '');
		
		// See if fields were left blank
		if ((string)$this->value->month === '') $this->value->month = '';
		if ((string)$this->value->day === '') $this->value->day = '';
		if ((string)$this->value->year === '') $this->value->year = '';
		else $this->value->year = (int)(preg_replace('/[^\d]/','',$this->value->year));
	}
	
	private function getEraData() {
		return ($this->ExistingData ? array('CE', 'BCE') : array('','CE','BCE'));
	}
}

class DateControlOptions
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
		<form id="dateSettings">
		<table class="table">
			<tr>
				<td width="60%"><b><?php echo gettext('Date Range')?></b><br /><?php echo gettext('Please provide a minimum and maximum value for the year in the range 0-9999')?>.</td>
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
				<td>
					<b><?php echo gettext('Use Text Input?');?></b><br/>
					<?php echo gettext('Use a text field for entering the year. Date ranges will not be enforced, but only numbers will be accepted.');?>
				</td>
				<td>
					<input type="radio" name="yearInputStyle" value="select" <?php if( (string)$xml->yearInputStyle != 'text') echo 'checked';?> />No&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="radio" name="yearInputStyle" value="text" <?php if( (string)$xml->yearInputStyle == 'text') echo 'checked';?> />Yes&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="button" onclick="updateYearInput()" value="Update">
				</td>
			</tr>
			<tr>
				<td><b><?php echo gettext('Date Format')?></b><br /><?php echo gettext('Please select the format you would prefer to enter your dates in.  This format will also be used for displaying the dates inside of KORA (this setting has no effect on front-end sites).')?></td>
				<td><input type="radio" name="format" value="MDY" <?php  if ( (string)$xml->displayFormat == 'MDY' ) echo 'checked'; ?> /><?php echo gettext('MM DD, YYYY')?><br />
					<input type="radio" name="format" value="DMY" <?php  if ( (string)$xml->displayFormat == 'DMY' ) echo 'checked'; ?> /><?php echo gettext('DD MM YYYY')?><br />
					<input type="radio" name="format" value="YMD" <?php  if ( (string)$xml->displayFormat == 'YMD' ) echo 'checked'; ?> /><?php echo gettext('YYYY MM DD')?><br />
					<input type="button" value="<?php echo gettext('Update')?>" onclick="updateFormat()" /></td>
			</tr>
			<tr>
				<td><b><?php echo gettext('Default Value')?></b><br /><?php echo gettext('Please select an optional value which will be initially filled in during ingestion')?>.</td>
				<td>
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
			echo $monthDisplay.$dayDisplay.$yearDisplay.$eraDisplay;
			echo ' <input type="button" value="'.gettext('Update').'" onclick="updateDefaultValue();" />';
		}
		else if ($xml->displayFormat == 'DMY')
		{
			echo $dayDisplay.$monthDisplay.$yearDisplay.$eraDisplay;
			echo ' <input type="button" value="'.gettext('Update').'" onclick="updateDefaultValue();" />';
		}
		else if ($xml->displayFormat == 'YMD')
		{
			echo $yearDisplay.$monthDisplay.$dayDisplay.$eraDisplay;
			echo ' <input type="button" value="'.gettext('Update').'" onclick="updateDefaultValue();" />';
		}
		else
		{
			echo gettext("This control's display format is an unrecognized value; please check its options.");
		}
		 ?>
				</td>
			</tr>
			<tr>
				<td><b><?php echo gettext('Show Era Field?')?></b><br /><?php echo gettext('Choose whether or not to show the CE/BCE selector; if you choose not to show it, the control will default to assuming CE.')?></td>
				<td><input type="radio" name="showEra" id="showEra" value="No" <?php  if ( (string)$xml->era == 'No' ) echo 'checked'; ?> /><?php echo gettext('No')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="radio" name="showEra" id="showEra" value="Yes" <?php  if ( (string)$xml->era == 'Yes' ) echo 'checked'; ?> /><?php echo gettext('Yes')?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="button" value="<?php echo gettext('Update')?>" onclick="updateEra()" /></td>
			</tr>
			<tr>
				<td>
					<b><?php echo gettext('Prefixes');?></b><br/>
					<?php echo gettext('Choose prefixes that will be available during ingestion').' (i.e. \'circa\'). '.gettext('This option will not be displayed during ingestion if no values are set here.');?>
				</td>
				<td>
					<table><tr><td>
						<select size="5" id="prefixes" name="prefixes"><?php foreach($xml->prefixes as $prefix) echo '<option>'.(string)$prefix.'</option>';?></select><br/>
						<input type="button" value="Remove" onclick="removeOption('prefixes',$('#prefixes').val())"/>
					</td><td>
						<input type="text" id="prefix" /><br/>
						<input type="button" value="Add" onclick="addOption('prefixes',$('#prefix').val())"/>
					</td></tr></table>
				</td>
			</tr>
			<tr>
				<td>
					<b><?php echo gettext('Suffixes');?></b><br/>
					<?php echo gettext('Choose suffixes that will be available during ingestion').' (i.e. \'s\'). '.gettext('This option will not be displayed during ingestion if no values are set here.');?>
				</td>
				<td>
					<table><tr><td>
						<select size="5" id="suffixes" name="suffixes"><?php foreach($xml->suffixes as $suffix) echo '<option>'.(string)$suffix.'</option>';?></select><br/>
						<input type="button" value="Remove" onclick="removeOption('suffixes',$('#suffixes').val())"/>
					</td><td>
						<input type="text" id="suffix" /><br/>
						<input type="button" value="Add" onclick="addOption('suffixes',$('#suffix').val())"/>
					</td></tr></table>
				</td>
			</tr>
		</table>
		</form>
		<?php
	}

	public static function updateDateRange($cid, $startDate, $endDate)
	{
		// Casting to integer should remove injection attacks
		// and make sure our range checks work
		$startDate = (int)$startDate;
		$endDate = (int)$endDate;
		
		if ($startDate < 0 || $startDate > 9999)
		{
			echo gettext('Start Date must be in the range [1,9999]').'.';
		}
		else if ($endDate < 0 || $endDate > 9999)
		{
			echo gettext('End Date must be in the range [1,9999]').'.';
		}
		else if ($startDate > $endDate)
		{
			echo gettext('End Date must be greater than or equal to Start Date');
		}
		else
		{
			$xml = getControlOptions($cid);
			if(!$xml) return;
		
			// Set the new options
			$xml->startYear = $startDate;
			$xml->endYear = $endDate;
			
			setControlOptions($cid, $xml);
			echo gettext('Date Range Settings Updated').'.';
		}
	}
	
	public static function updateEra($cid, $era)
	{
		if (!in_array($era, array('Yes', 'No'))) return;
	
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		// Set the new selection
		$xml->era = $era;
		
		setControlOptions($cid, $xml);
		echo gettext('Era Settings Updated').'.';
	}
	
	public static function updateFormat($cid, $format)
	{
		if (!in_array($format, array('MDY', 'DMY', 'YMD'))) return;
		
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		// Set the new selection
		$xml->displayFormat = $format;
		
		setControlOptions($cid, $xml);
		echo gettext('Date Format Settings Updated').'.';
	}
	
	public static function updateDefaultValue($cid, $month, $day, $year, $era)
	{
		$xml = getControlOptions($cid);
		if(!$xml) return;
				
		// Validate Input
		
		// Era: Can only be CE, BCE, or blank
		if (!in_array($era, array('', 'CE', 'BCE')))
		{
			return;
		}
		
		// Year: Can only be blank or a number in the date range
		if (!empty($year))
		{
			$year = (int)$year;
			if ($year < (int)$xml->startYear)
			{
				$year = (int)$xml->startYear;
			}
			else if ($year > (int)$xml->endYear)
			{
				$year = (int)$xml->endYear;
			}
		} else $year = ''; // this shouldn't be necessary but it ensures consistency
		
		// Month: Can only be blank or a number in the range 1-12
		if (!empty($month))
		{
			$month = (int)$month;
			if ($month < 1)
			{
				$month = 1;
			}
			else if ($month > 12)
			{
				$month = 12;
			}
		} else $month = ''; // this shouldn't be necessary but it ensures consistency
		
		// Day: Can only be in the range valid for that month
		if (!empty($day))
		{
			$day = (int)$day;
			$message = DateControl::validDate($day, $month, $year);
			if (!empty($message))
			{
				echo $message;
				$day = '';
			}
		} else $day = ''; // this shouldn't be necessary but it ensures consistency
		
		// update the information
		if (!isset($xml->defaultValue)) $xml->addChild('defaultValue');
		if (!isset($xml->defaultValue->month)) $xml->defaultValue->addChild('month');
		if (!isset($xml->defaultValue->day)) $xml->defaultValue->addChild('day');
		if (!isset($xml->defaultValue->year)) $xml->defaultValue->addChild('year');
		if (!isset($xml->defaultValue->era)) $xml->defaultValue->addChild('era');
		
		$xml->defaultValue->month = $month;
		$xml->defaultValue->day   = $day;
		$xml->defaultValue->year  = $year;
		$xml->defaultValue->era   = $era;
		
		setControlOptions($cid, $xml);
		echo gettext('Default Value Settings Updated').'.';
	}
	
	public static function addOption($cid,$option,$value){
		if($option == '') return;
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		
		// check for duplicates
		if(isset($xml->$option)){
			foreach($xml->$option as $xmlValue){
				if((string)$xmlValue == $value) return;
			}
		}
		
		$xml->addChild($option,trim($value));
		
		setControlOptions($cid,$xml);
	}
	
	public static function removeOption($cid,$option,$value){
		if($option == '' || $value == '') return;
		$xml = getControlOptions($cid);
		if(!$xml) return;
		
		if(isset($xml->$option)){
			$n=0;
			foreach($xml->$option as $xmlValue){
				if(trim((string)$xmlValue) == $value) {
					unset($xml->$option->$n);
					break;
				}
				$n++;
			}
		}
		setControlOptions($cid,$xml);
	}
	
	public static function updateYearInput($cid,$style){
		if($style == '') return;
		$options = getControlOptions($cid);
		if($options === false) return;
		
		if(isset($options->yearInputStyle)) unset($options->yearInputStyle);
		
		$options->addChild('yearInputStyle',$style);
		
		setControlOptions($cid,$options);
		echo "Year input set to ".$style;
	}
	
}

// Handle the AJAX Calls
if(isset($_POST['action']) && isset($_POST['source']) && $_POST['source'] == 'DateControl'){
	
	requirePermissions(EDIT_LAYOUT, 'schemeLayout.php');
	
	$action = $_POST['action'];
	if($action == 'updateDateRange') {
		DateControlOptions::updateDateRange($_POST['cid'], $_POST['startYear'], $_POST['endYear']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'updateEra') {
		DateControlOptions::updateEra($_POST['cid'], $_POST['era']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'updateFormat') {
		DateControlOptions::updateFormat($_POST['cid'], $_POST['format']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'updateDefaultValue') {
		DateControlOptions::updateDefaultValue($_POST['cid'], $_POST['month'], $_POST['day'], $_POST['year'], $_POST['era']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'addOption') {
		DateControlOptions::addOption($_POST['cid'],$_POST['option'],$_POST['value']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'removeOption') {
		DateControlOptions::removeOption($_POST['cid'],$_POST['option'],$_POST['value']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'updateYearInput') {
		DateControlOptions::updateYearInput($_POST['cid'],$_POST['style']);
		DateControlOptions::showDialog($_POST['cid']);
	} else if ($action == 'showDialog') {
		DateControlOptions::showDialog($_POST['cid']);
	}
	
	
}


?>
