<?php
/**
 * Kora Basic Search is a default implementation of Kora basic search which
 * only uses basic Kora search clauses on the Kora backend to perform its
 * search.
 * 
 * It may not return the most accurate results, but it is consistent with the 
 * internal Kora basic search results, easy to implement, and quick.
 * 
 * @author Zachary Pepin <zachary.pepin@matrix.msu.edu>
 */

namespace KoraORM\Search;

/**
 * The Kora Basic Search class.
 */
class KoraBasicSearch extends \KoraORM\BasicSearchHandler
{
	/**
	 * A listing of common keywords which should be ignored in the Kora Basic
	 * Search.
	 * 
	 * @var array
	 */
	private static $COMMON_KEYWORDS = array('a', 'an', 'the');
	
	/**
	 * Load the metadata associated with this search handler.
	 * 
	 * @return array
	 */
	public static function loadMetadata()
	{
		return array(
				'name' => 'Kora Basic Search',	// the pretty name of this search handler
				'slug' => 'kora-basic-search'); // the slug name of this search handler
	}
	
	public function __construct(\KoraORM\KoraManager $manager, $projectID, $schemeID)
	{
		parent::__construct($manager, $projectID, $schemeID);
	}
	
	public function basicSearch($query)
	{
		// sanitize query
		if (!is_array($query))
		{
			// split by whitespace
			$query = preg_split('/[\s]+/', strval($query));
			// filter common keywords such as a, an, and the
			$query = array_filter($query, function ($keyword) {
				return !in_array($keyword, self::$COMMON_KEYWORDS);
			});
			$query = array_map(function ($keyword) {
				return str_replace(array('%', '_'), array('\\%', '\\_'), $keyword);
			}, $query);
		}
		
		$ometa = $this->manager->getObjectMetadata($this->projectID, $this->schemeID);
		
		// find all the searchable controls
		$searchable = array_filter($ometa['controls'], function ($c) {
				return $c['searchable'];
			});
		
		// create clauses for each searchable control - keyword pair
		$clauses = array();
		foreach ($searchable as &$c)
		{
			foreach ($query as &$keyword)
			{
				$clauses[] = new \KORA_Clause($c['koraName'], 'LIKE', "%${keyword}%");
			}
			unset($keyword);
		}
		unset($c);
		
		// join all the clauses together to make the query
		$query = array_reduce($clauses, function (&$result, &$item) {
			if (is_null($result))
				return $item;
			else
				return new \KORA_Clause($result, 'OR', $item);
		});
		
		if (is_null($query))
			return array();
	
		return $this->manager->search($this->projectID, $this->schemeID, $query);
	}
}