<?php

namespace KoraORM\Controls;

/**
 * A default control for gelocators.
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */
class GeolocatorControl extends \KoraORM\KoraControl
{
	public static function loadMetadata()
	{
		return array( 'koraType' => 'GeolocatorControl' );
	}
}