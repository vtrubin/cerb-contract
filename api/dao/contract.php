<?php
class DAO_Contract extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
    const ORG = 'org';
	const UPDATED_AT = 'updated_at';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO contract () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		if(isset($fields[DAO_Contract::ORG]))
			DAO_ContextLink::setLink('cerberusweb.contexts.contract',$id,'cerberusweb.contexts.org',$fields[DAO_Contract::ORG]);
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Default fields
		if(!isset($fields[DAO_Contract::UPDATED_AT]))
			$fields[DAO_Contract::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_CONTRACT, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'contract', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.contract.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_CONTRACT, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('contract', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Contract[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, org, updated_at ".
			"FROM contract ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Contract
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Contract[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Contract();
			$object->id = $row['id'];
			$object->name = $row['name'];
            $object->org = intval($row['org']);
			$object->updated_at = $row['updated_at'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
    static function maint() {
        // Fire event
        $eventMgr = DevblocksPlatform::getEventService();
        $eventMgr->trigger(
            new Model_DevblocksEvent(
                'context.maint',
                array(
                    'context' => CerberusContexts::CONTEXT_CONTRACT,
                    'context_table' => 'contract',
                    'context_key' => 'id',
                )
            )
        );
    }

	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM contract WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_CONTRACT,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	public static function random() {
		return self::_getRandom('contract');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Contract::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_Contract', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"c.id as %s, ".
			"c.name as %s, ".
            "c.org as %s, ".
            /*"o.name as %s, ".*/
			"c.updated_at as %s ",
				SearchFields_Contract::ID,
				SearchFields_Contract::NAME,
                SearchFields_Contract::ORG,
                /*SearchFields_Contract::ORG_NAME,*/
				SearchFields_Contract::UPDATED_AT
			);
			
		/*$join_sql = "FROM contract  ".
					"LEFT JOIN contact_org o ON (o.id=contract.org) ".
				(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = contract.id) ", Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_CONTRACT)) : " ").
			'';*/
        $join_sql =
            "FROM contract c ";
		
		/*// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'contract.id',
			$select_sql,
			$join_sql
		);
				*/
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_Contract');
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
	
		array_walk_recursive(
			$params,
			array('DAO_Contract', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'contract',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = CerberusContexts::CONTEXT_CONTRACT;
		$from_index = 'contract.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			/*case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;*/
				
			case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Contract::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function countsByOrgId($org) {
		$db = DevblocksPlatform::getDatabaseService();

		$counts = array(
				'total' => 0,
				'open' => 0,
				'waiting' => 0,
				'closed' => 0,
		);

		$sql = sprintf("SELECT COUNT(id) AS count ".
				"FROM contract ".
				"WHERE org = %d ",
				$org
		);
		$results = $db->GetArraySlave($sql);

		if(is_array($results))
			foreach($results as $result) {
				if($result['is_closed']) {
					$counts['closed'] += $result['count'];
				} else if($result['is_waiting']) {
					$counts['waiting'] += $result['count'];
				} else {
					$counts['open'] += $result['count'];
				}

				$counts['total'] += $result['count'];
			}

		return $counts;
	}
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY contract.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Contract::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT contract.id) " : "SELECT COUNT(contract.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_Contract extends DevblocksSearchFields {
	const ID = 'a_id';
	const NAME = 'a_name';
    const ORG = 'a_org';
    const ORG_NAME = 'o_name';
	const UPDATED_AT = 'a_updated_at';
    const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';

	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';

    //new in cerb 7.3
    static private $_fields = null;

    static function getPrimaryKey() {
        return 'c.id';
    }

    static function getCustomFieldContextKeys() {
        return array(
            CerberusContexts::CONTEXT_CONTRACT => new DevblocksSearchFieldContextKeys('c.id', self::ID),
        );
    }

    static function getWhereSQL(DevblocksSearchCriteria $param) {
        switch($param->field) {
            case self::FULLTEXT_COMMENT_CONTENT:
                return self::_getWhereSQLFromCommentFulltextField($param, Search_CommentContent::ID, CerberusContexts::CONTEXT_CONTRACT, self::getPrimaryKey());
                break;

            case self::VIRTUAL_CONTEXT_LINK:
                return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_CONTRACT, self::getPrimaryKey());
                break;

            case self::VIRTUAL_WATCHERS:
                return self::_getWhereSQLFromWatchersField($param, CerberusContexts::CONTEXT_CONTRACT, self::getPrimaryKey());
                break;

            default:
                if('cf_' == substr($param->field, 0, 3)) {
                    return self::_getWhereSQLFromCustomFields($param);
                } else {
                    return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
                }
                break;
        }
    }
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'contract', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'contract', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
            self::ORG => new DevblocksSearchField(self::ORG, 'contract', 'org', $translate->_('common.org'), Model_CustomField::TYPE_NUMBER, true),
			self::ORG_NAME => new DevblocksSearchField(self::ORG_NAME, 'o', 'name', $translate->_('common.organization'), Model_CustomField::TYPE_SINGLE_LINE, true),
            self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'contract', 'updated_at', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS', false),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null, null, false),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null, null, false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_CONTRACT,
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_Contract {
	public $id;
	public $name;
    public $org;
	public $updated_at;
};

class View_Contract extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'contract';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		// [TODO] Name the worklist view
		$this->name = $translate->_('Contract');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Contract::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Contract::NAME,
            SearchFields_Contract::ORG,
			SearchFields_Contract::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_Contract::VIRTUAL_CONTEXT_LINK,
			SearchFields_Contract::VIRTUAL_HAS_FIELDSET,
			SearchFields_Contract::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Contract::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_Contract', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Contract', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_Contract::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Contract::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
//			case SearchFields_Contract::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_Contract', $column);
//				break;

//			case SearchFields_Contract::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn('DAO_Contract', $column);
//				break;
				
			case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Contract', CerberusContexts::CONTEXT_CONTRACT, $column);
				break;
				
			case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Contract', CerberusContexts::CONTEXT_CONTRACT, $column);
				break;
				
			case SearchFields_Contract::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_Contract', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Contract', $column, 'contract.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_Contract::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Contract::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_Contract::ID),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Contract::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
            'org' =>
                array(
                    'type' => DevblocksSearchCriteria::TYPE_NUMBER,
                    'options' => array('param_key' => SearchFields_Contract::ORG),
                ),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Contract::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_Contract::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_CONTRACT, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				// ...
			}
		}
		
		return $params;
	}
    function getParamFromQuickSearchFieldTokens($field, $tokens) {
        switch($field) {
            default:
                if($field == 'links' || substr($field, 0, 6) == 'links.')
                    return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);

                $search_fields = $this->getQuickSearchFields();
                return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
                break;
        }

        return false;
    }
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CONTRACT);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.contracts::contract/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Contract::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Contract::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;

            case SearchFields_Contract::ORG:
                $tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
                break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Contract::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_CONTRACT);
				break;
				
			case SearchFields_Contract::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
			
			case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
				
			case SearchFields_Contract::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Contract::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Contract::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Contract::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
            case SearchFields_Contract::ORG:
                $criteria = new DevblocksSearchCriteria($field,$oper,$value);
                break;
				
			case SearchFields_Contract::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Contract::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Contract::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Contract::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
	
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_Contract::EXAMPLE] = 'some value';
					break;
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Contract::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Contract::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_Contract::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_CONTRACT, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Contract extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek, IDevblocksContextImport {
    static function isReadableByActor($models, $actor) {
        // Everyone can view
        return CerberusContexts::allowEverything($models);
    }

    static function isWriteableByActor($models, $actor) {
        // Everyone can modify
        return CerberusContexts::allowEverything($models);
    }
	function getRandom() {
		return DAO_Contract::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=contract&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$contract = DAO_Contract::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($contract->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $contract->id,
			'name' => $contract->name,
            'org'=>$contract->org,
			'permalink' => $url,
			'updated' => $contract->updated_at,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($contract, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Contract:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CONTRACT);

		// Polymorph
		if(is_numeric($contract)) {
			$contract = DAO_Contract::get($contract);
		} elseif($contract instanceof Model_Contract) {
			// It's what we want already.
		} elseif(is_array($contract)) {
			$contract = Cerb_ORMHelper::recastArrayToModel($contract, 'Model_Contract');
		} else {
			$contract = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
            'org' => $prefix.$translate->_('common.org'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
            'org' => Model_CustomField::TYPE_NUMBER,
			'updated_at' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CONTRACT;
		$token_values['_types'] = $token_types;
		
		if($contract) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $contract->name;
			$token_values['id'] = $contract->id;
			$token_values['name'] = $contract->name;
            $token_values['org'] = $contract->org;
            $token_values['updated_at'] = $contract->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($contract, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=contract&id=%d-%s",$contract->id, DevblocksPlatform::strToPermalink($contract->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_CONTRACT;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Contract';
		$view->view_columns = array(
			SearchFields_Contract::NAME,
            SearchFields_Contract::ORG,
			SearchFields_Contract::UPDATED_AT,
		);
		/*
		$view->addParams(array(
			SearchFields_Contract::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_Contract::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_Contract::UPDATED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Contract';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			/*$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Contract::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Contract::CONTEXT_LINK_ID,'=',$context_id),
			);*/
            $params_req = array(
                new DevblocksSearchCriteria(SearchFields_Contract::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
            );
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		/*if(!empty($context_id) && null != ($contract = DAO_Contract::get($context_id))) {
			$tpl->assign('model', $contract);
		}
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CONTRACT, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_CONTRACT, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_CONTRACT, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:cerberusweb.contracts::contract/ajax/peek.tpl');*/
        /*end of old version*/
        $context = CerberusContexts::CONTEXT_CONTRACT;

        if(!empty($context_id)) {
            $model = DAO_Contract::get($context_id);
        }

        if(empty($context_id) || $edit) {
            if(isset($model))
                $tpl->assign('model', $model);

            // Custom fields
            $custom_fields = DAO_CustomField::getByContext($context, false);
            $tpl->assign('custom_fields', $custom_fields);

            $custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
            if(isset($custom_field_values[$context_id]))
                $tpl->assign('custom_field_values', $custom_field_values[$context_id]);

            $types = Model_CustomField::getTypes();
            $tpl->assign('types', $types);

            // View
            $tpl->assign('id', $context_id);
            $tpl->assign('view_id', $view_id);
            $tpl->display('devblocks:cerberusweb.contracts::contract/ajax/peek_edit.tpl');

        } else {
            // Counts
            $activity_counts = array(
                'comments' => DAO_Comment::count($context, $context_id),
            );
            $tpl->assign('activity_counts', $activity_counts);

            // Links
            $links = array(
                $context => array(
                    $context_id =>
                        DAO_ContextLink::getContextLinkCounts(
                            $context,
                            $context_id,
                            array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
                        ),
                ),
            );
            $tpl->assign('links', $links);

            // Timeline
            if($context_id) {
                $timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments($context, $context_id));
                $tpl->assign('timeline_json', $timeline_json);
            }

            // Context
            if(false == ($context_ext = Extension_DevblocksContext::get($context)))
                return;

            // Dictionary
            $labels = array();
            $values = array();
            CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
            $dict = DevblocksDictionaryDelegate::instance($values);
            $tpl->assign('dict', $dict);

            $properties = $context_ext->getCardProperties();
            $tpl->assign('properties', $properties);

            $tpl->display('devblocks:cerberusweb.contracts::contract/ajax/peek.tpl');
        }
	}
	
	function importGetKeys() {
		// [TODO] Translate
	
		$keys = array(
			'name' => array(
				'label' => 'Name',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_Contract::NAME,
				'required' => true,
			),
            'org' => array(
                'label' => 'Organization',
                'type' => Model_CustomField::TYPE_NUMBER,
                'param' => SearchFields_Contract::ORG,
                'required' => true,
            ),
			'updated_at' => array(
				'label' => 'Updated Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_Contract::UPDATED_AT,
			),
		);
	
		$fields = SearchFields_Contract::getFields();
		self::_getImportCustomFields($fields, $keys);
		
		DevblocksPlatform::sortObjects($keys, '[label]', true);
	
		return $keys;
	}
	
	function importKeyValue($key, $value) {
		switch($key) {
			
		}
	
		return $value;
	}
	
	function importSaveObject(array $fields, array $custom_fields, array $meta) {
		// If new...
		if(!isset($meta['object_id']) || empty($meta['object_id'])) {
			// Make sure we have a name
			if(!isset($fields[DAO_Contract::NAME])) {
				$fields[DAO_Contract::NAME] = 'New ' . $this->manifest->name;
			}
	
			// Create
			$meta['object_id'] = DAO_Contract::create($fields);
	
		} else {
			// Update
			DAO_Contract::update($meta['object_id'], $fields);
		}
	
		// Custom fields
		if(!empty($custom_fields) && !empty($meta['object_id'])) {
			DAO_CustomFieldValue::formatAndSetFieldValues($this->manifest->id, $meta['object_id'], $custom_fields, false, true, true); //$is_blank_unset (4th)
		}
	}
};
