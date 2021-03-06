<?php

namespace KoraORM\Controls;

require_once( __DIR__ . '/ListControl.php' );

/**
 * A default control for lists (multi-select).
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */
class MultiListControl extends \KoraORM\KoraControl implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
	private $lists = null;
	
	public static function loadMetadata()
	{
		return array( 'koraType' => 'MultiListControl' );
	}
	
	public function getIterator()
	{
		return new \ArrayIterator($this->getLists());
	}
	
	public function offsetExists($offset)
	{
		return is_int($offset) && 0 <= $offset && $offset < count($this->getLists());
	}
	
	public function offsetGet($offset)
	{
		return $this->getLists()[$offset];
	}
	
	public function offsetSet($offset, $value)
	{
		throw new Exception("unsupported operation");
	}
	
	public function offsetUnset($offset)
	{
		throw new Exception("unsupported operation");
	}
	
	public function count()
	{
		return count($this->getLists());
	}
	
	public function jsonSerialize()
	{
		return $this->getLists();
	}
	
	private function getLists()
	{
		if (!isset($this->lists) && is_array($this->koraData))
		{
			$this->lists = array();
			foreach ($this->koraData as $koraData)
				$this->lists[] = new \KoraORM\Controls\ListControl($this->entity, $koraData);
		}
		return $this->lists;
	}
	
	public function printHtml()
	{
		$first = true;
		foreach ($this->getLists() as $list)
		{
			if ($first)
				$first = false;
			else
				echo ', ';
			$list->printHtml();
		}
	}
}