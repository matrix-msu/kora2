<?php

namespace KoraORM\Controls;

/**
 * A default control for files.
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */
class FileControl extends \KoraORM\KoraControl implements \JsonSerializable
{
	private $originalName = null;
	private $localName = null;
	private $size = null;
	private $type = null;
	private $fullPath = null;
	private $fullUrl = null;
	
	public static function loadMetadata()
	{
		return array( 'koraType' => 'FileControl' );
	}
	
	public function __get($name)
	{
		switch ($name)
		{
			case 'originalName':
				return $this->getOriginalName();
			case 'localName':
				return $this->getLocalName();
			case 'size':
				return $this->getSize();
			case 'type':
				return $this->getType();
			case 'fullPath':
				return $this->getFullPath();
			case 'fullUrl':
				return $this->getFullUrl();
			default:
				return parent::__get($name);
		}
	}
	
	public function __isset($name)
	{
		switch ($name)
		{
			case 'originalName':
				return !is_null($this->getOriginalName());
			case 'localName':
				return !is_null($this->getLocalName());
			case 'size':
				return !is_null($this->getSize());
			case 'type':
				return !is_null($this->getType());
			case 'fullPath':
				return !is_null($this->getFullPath());
			case 'fullUrl':
				return !is_null($this->getFullUrl());
			default:
				return parent::__isset($name);
		}
	}
	
	public function jsonSerialize()
	{
		return array(
				'originalName' => $this->getOriginalName(),
				'localName' => $this->getLocalName(),
				'size' => $this->getSize(),
				'type' => $this->getType(),
				'fullUrl' => $this->getFullUrl()
				);
	}
	
	private function getOriginalName()
	{
		if (!isset($this->orginalName) && isset($this->koraData['originalName']))
			$this->originalName = $this->koraData['originalName'];
		return $this->originalName;
	}
	
	private function getLocalName()
	{
		if (!isset($this->localName) && isset($this->koraData['localName']))
			$this->localName = $this->koraData['localName'];
		return $this->localName;
	}
	
	private function getSize()
	{
		if (!isset($this->size) && isset($this->koraData['size']))
			$this->size = intval($this->koraData['size']);
		return $this->size;
	}
	
	private function getType()
	{
		if (!isset($this->type) && isset($this->koraData['type']))
			$this->type = $this->koraData['type'];
		return $this->type;
	}
	
	private function getFullPath()
	{
		if (!isset($this->fullPath) && !is_null($this->getLocalName()))
			$this->fullPath = getFullPathFromFileName($this->getLocalName());
		return $this->fullPath;
	}
	
	private function getFullUrl()
	{
		if (!isset($this->fullUrl) && !is_null($this->getLocalName()))
			$this->fullUrl = getFullURLFromFileName($this->getLocalName());
		return $this->fullUrl;
	}
	
	public function printHtml()
	{
		echo '<a href="' . htmlspecialchars($this->getFullUrl()) . '">' . htmlspecialchars($this->getOriginalName()) . '</a>';
	}
}