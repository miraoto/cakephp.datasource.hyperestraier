<?php
/**
 * Hyperestraier Datasource
 *
 * PHP 5
 *
 * Copyright (c) 2012 miraoto. All rights reserved.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://log.miraoto.com/2012/10/
 * @package       app.Model.Datasource
 * @since         File available since Release 0.0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Hyperestraier Datasource object
 *
 * Create a datasource in your Config/database.php
 * public $he = array(
 *     'datasource' => 'HeSearch.Hyperestraier',
 *     'persistent' => false,
 *     'host'       => 'localhost',
 *     'port'       => 1978,
 *     'node'       => 'child',
 *     'login'      => 'admin',
 *     'password'   => 'admin',
 * );
 *
 * @package       app.Model.Datasource
 */

define('SERVICES_HYPERESTRAIER_DEBUG', 1);

class Hyperestraier extends DataSource {

/**
 * Datasource description
 *
 * @var string
 */
	public $description = "Hyperestraier Datasource";

/**
 * Base configuration settings for Hyperestraier driver
 *
 * @var array
 */
	protected $_baseConfig = array(
		'persistent' => true,
		'host'       => 'localhost',
		'login'      => 'admin',
		'password'   => 'admin',
		'node'       => 'child',
		'port'       => '1978'
	);

/**
 * Hyperestraier node object connection
 *
 * @var Node $_connection
 */
	protected $_connection = null;


/**
 * Constructor
 *
 * @param array $config Array of configuration information for the Datasource.
 * @param boolean $autoConnect Whether or not the datasource should automatically connect.
 */
	public function __construct($config = null, $autoConnect = true) {
		parent::__construct($config);

		if (!$this->enabled()) {
			throw new MissingConnectionException(array(
				'class' => get_class($this)
			));
		}

		if ($autoConnect) {
			$this->connect();
		}
	}

/**
 * Connects to the hyperestraier using options in the given configuration array.
 *
 * @return boolean True if the database could be connected, else false
 * @throws MissingConnectionException
 */
	public function connect() {
		$config = $this->config;
		$this->connected = false;

		$include_path   = get_include_path();
		$include_path   = explode(PATH_SEPARATOR,$include_path);
		$include_path[] = ROOT . DS . 'app' . DS . 'Lib' . DS;
		set_include_path(implode(PATH_SEPARATOR,$include_path));

		App::import('Lib/Services/HyperEstraier','Node');

		try {
			$dsn = 'http://' . $config['host'] . ':' . $config['port'] . DS . 'node' . DS . $config['node'];
			$this->_connection = new Services_HyperEstraier_Node();
			$this->_connection->setUrl($dsn);
			$this->connected = true;
		} catch (Exception $e) {
			throw new MissingConnectionException(array('class' => $e->getMessage()));
		}

		return $this->connected;
	}

/**
 * Caches/returns cached results for child instances
 *
 * @param mixed $data
 * @return array Array of sources available in this datasource.
 */
	public function listSources($data = null) {
		if ($this->cacheSources === false) {
			return null;
		}
		if ($this->_sources !== null) {
			return $this->_sources;
		}

		$sources = new Services_HyperEstraier_Condition;

		$key = $this->config['node'] . '_list';
		$key = preg_replace('/[^A-Za-z0-9_\-.+]/', '_', $key);
		$sources = Cache::read($key, '_cake_model_he_');

		if (empty($sources)) {
			$sources = $data;
			Cache::write($key, $data, '_cake_model_he_');
		}

		return $this->_sources = $sources;
	}

/**
 * Used to read records from the Datasource. The "R" in CRUD
 *
 * To-be-overridden in subclasses.
 *
 * @param Model $model The model being read.
 * @param array $queryData An array of query data used to find the data you want
 * @return mixed
 */
	public function read(Model $model, $queryData = array()) {
		$queryData = $this->_scrubQueryData($queryData);
		$cond  = new Services_HyperEstraier_Condition;

		// query
		// @see Service::HyperEstraier::Condition::setPhese
		$query = (isset($queryData['query'])) ? $queryData['query'] : '';
		$cond->setPhrase($query);

		// limit
		// @see Service::HyperEstraier::Condition::limit
		if (!is_null($queryData['limit'])) {
			$cond->setMax($queryData['limit']);
		}
		// offset
		// @see Service::HyperEstraier::Condition::Offset
		$offset = (!is_null($queryData['offset'])) ? $queryData['offset'] : 0;
		$cond->setSkip($offset);

		// order
		// @see Service::HyperEstraier::Condition::setOrder
		// @example @title STRA ... ASC by String
		//          @title STRD ... DESC by String
		//			@title NUMA ... ASC by Number or Date
		//			@title NUMD ... NUMD by Number or Date
		if ($queryData['order'][0]) {
		    $cond->setOrder($queryData['order'][0]);
		}

		// options
		// @see Service::HyperEstraier::Condition::setOptions
		$cond->setOptions(Services_HyperEstraier_Condition::SIMPLE);

		$resultSet = array();
		$nodeResult = $this->_connection->search($cond, 0);

		if ($model->findQueryType == 'count') {
    		return array(
    			array(
                    array(
                        'count'=>(int)$nodeResult->docNum()
                    )
                )
            );
		}
		if ($nodeResult) {
			if ($nodeResult->docNum() == 0) {
				return $resultSet;
			}
			else {
				foreach ($nodeResult as $rank => $rdoc) {
					$date = ($rdoc->getAttribute('@mdate')) ? date("Y-m-d H:i:s", strtotime($rdoc->getAttribute('@mdate'))) : null;

					$keywords = array();
					$keywords = $rdoc->getKeywords();
					$keywords = explode("\t", $keywords);
					$keyword = array();
					for ($i = 0; $i < 10; $i = $i + 2) {
						$keyword[] = $keywords[$i];
					}

					$snippet = $rdoc->getSnippet();
					$snippet = htmlspecialchars($snippet, ENT_QUOTES);
            		$snippet = preg_replace("/(\n)?(.+)\t(.+)\n/i", "<b>\\2</b>", $snippet);
            		$snippet = preg_replace("/\n\n/","... ", $snippet);

					$tmp = array(
						'id'      => $rdoc->getAttribute('@id'),
						'score'   => $rdoc->getAttribute('#nodescore'),
					    'uri'     => $rdoc->getAttribute('@uri'),
					    'title'   => $rdoc->getAttribute('@title'),
			    		'digest'  => $rdoc->getAttribute('@digest'),
						'date'    => $date,
					    'keyword' => $keyword,
						'snippet' => $snippet,
					);
					$resultSet[$model->name][] = $tmp;
				}
			}
		}
		else {
			if (Services_HyperEstraier_Error::hasErrors()) {
				throw new InternalErrorException('SystemError：' . $Node->status . ' ' . Services_HyperEstraier_Error::getErrors());
			}
		}
		return $resultSet;
	}

/**
 * Private helper method to remove query metadata in given data array.
 *
 * @param array $data
 * @return array
 */
	protected function _scrubQueryData($data) {
		static $base = null;
		if ($base === null) {
			$base = array_fill_keys(array('conditions', 'fields', 'joins', 'limit', 'offset', 'group'), array());
			$base['callbacks'] = null;
			$base['query']     = null;
			$base['options']   = null;
			$base['order']     = null;

		}
		return (array)$data + $base;
	}

/**
 * Returns an SQL calculation, i.e. COUNT() or MAX()
 *
 * @param Model $model
 * @param string $func Lowercase name of SQL function, i.e. 'count' or 'max'
 * @param array $params Function parameters (any values must be quoted manually)
 * @return string An SQL calculation function
 */
	public function calculate($model, $func, $params = array()) {
		return array(
            'count'=>true
        );
	}
}
