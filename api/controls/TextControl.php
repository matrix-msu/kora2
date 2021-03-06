<?php

namespace KoraORM\Controls;

/**
 * A default control for text.
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */
class TextControl extends \KoraORM\KoraControl implements \JsonSerializable
{
	public static function loadMetadata()
	{
		return array( 'koraType' => 'TextControl' );
	}
	
	public function __get($name)
	{
		if ($name == 'text')
			return $this->getText();
		return parent::__get($name);
	}
	
	public function __isset($name)
	{
		if ($name == 'text')
			return isset($this->koraData);
		return parent::__isset($name);
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
		return $this->koraData;
	}
	
	public function printHtml()
	{
		echo htmlspecialchars($this->text);
	}
}