<?php

namespace KoraORM\Controls;

/**
 * A default control for lists.
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */
class ListControl extends \KoraORM\KoraControl implements \JsonSerializable
{
	public static function loadMetadata()
	{
		return array( 'koraType' => 'ListControl' );
	}
	
	public function __get($name)
	{
		switch ($name)
		{
			case 'text':
				return $this->getText();
			default:
				return parent::__get($name);
		}
	}
	
	public function __isset($name)
	{
		switch ($name)
		{
			case 'text':
				return !is_null($this->getText());
			default:
				return parent::__isset($name);
		}
	}
	
	public function __toString()
	{
		return $this->getText();
	}
	
	public function jsonSerialize()
	{
		return $this->getText();
	}
	
	private function getText()
	{
		return strval($this->koraData);
	}
	
	public function printHtml()
	{
		echo htmlspecialchars($this->getText());
	}
}