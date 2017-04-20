<?php
class ChRest_Contracts extends Extension_RestController implements IExtensionRestController {
    function getAction($stack) {
        @$action = array_shift($stack);

        // Looking up a single ID?
        if(is_numeric($action)) {
            $this->getId(intval($action));

        } else { // actions
            switch($action) {
            }
        }

        $this->error(self::ERRNO_NOT_IMPLEMENTED);
    }

    function putAction($stack) {
        @$action = array_shift($stack);

        // Looking up a single ID?
        if(is_numeric($action)) {
            $this->putId(intval($action));

        } else { // actions
            switch($action) {
            }
        }

        $this->error(self::ERRNO_NOT_IMPLEMENTED);
    }

    function postAction($stack) {
        @$action = array_shift($stack);

        if(is_numeric($action) && !empty($stack)) {
            $id = intval($action);
            $action = array_shift($stack);

            switch($action) {
                case 'note':
                    $this->postNote($id);
                    break;
            }

        } else {
            switch($action) {
                case 'create':
                    $this->postCreate();
                    break;
                case 'search':
                    $this->postSearch();
                    break;
            }
        }

        $this->error(self::ERRNO_NOT_IMPLEMENTED);
    }

    function deleteAction($stack) {
        $id = array_shift($stack);

        if(null == ($contract = DAO_Contract::get($id)))
            $this->error(self::ERRNO_CUSTOM, sprintf("Invalid contract ID %d", $id));

        DAO_Contract::delete($id);

        $result = array('id' => $id);
        $this->success($result);
    }

    function translateToken($token, $type='dao') {
        if('custom_' == substr($token, 0, 7) && in_array($type, array('search', 'subtotal'))) {
            return 'cf_' . intval(substr($token, 7));
        }

        $tokens = array();

        if('dao'==$type) {
            $tokens = array(
                'id' => DAO_Contract::ID,
                'name'=> DAO_Contract::NAME,
	            'org' => DAO_Contract::ORG,
	            'updated_at' => DAO_Contract::UPDATED_AT
            );

        } elseif ('subtotal'==$type) {
            $tokens = array(
                'fieldsets' => SearchFields_Contract::VIRTUAL_HAS_FIELDSET,
                'links' => SearchFields_Contract::VIRTUAL_CONTEXT_LINK,
                'watchers' => SearchFields_Contract::VIRTUAL_WATCHERS,


            );

            $tokens_cfields = $this->_handleSearchTokensCustomFields(CerberusContexts::CONTEXT_CONTRACT);

            if(is_array($tokens_cfields))
                $tokens = array_merge($tokens, $tokens_cfields);

        } else {
            $tokens = array(
                'id' => SearchFields_Contract::ID,
                'name'=>SearchFields_Contract::NAME,
                'org' => SearchFields_Contract::ORG,
                'updated_at' => SearchFields_Contract::UPDATED_AT,
            );
        }

        if(isset($tokens[$token]))
            return $tokens[$token];

        return NULL;
    }

    function getContext($model) {
        $labels = array();
        $values = array();
        $context = CerberusContexts::getContext(CerberusContexts::CONTEXT_CONTRACT, $model, $labels, $values, null, true);

        return $values;
    }

    function getId($id) {
        $worker = CerberusApplication::getActiveWorker();

        // ACL
//		if(!$worker->hasPriv('...'))
//			$this->error("Access denied.");

        $container = $this->search(array(
            array('id', '=', $id),
        ));

        if(is_array($container) && isset($container['results']) && isset($container['results'][$id]))
            $this->success($container['results'][$id]);

        // Error
        $this->error(self::ERRNO_CUSTOM, sprintf("Invalid contract id '%d'", $id));
    }

    function search($filters=array(), $sortToken='id', $sortAsc=1, $page=1, $limit=10, $options=array()) {
        @$query = DevblocksPlatform::importVar($options['query'], 'string', null);
        @$show_results = DevblocksPlatform::importVar($options['show_results'], 'boolean', true);
        @$subtotals = DevblocksPlatform::importVar($options['subtotals'], 'array', array());

        $params = array();

        // Sort
        $sortBy = $this->translateToken($sortToken, 'search');
        $sortAsc = !empty($sortAsc) ? true : false;

        // Search

        $view = $this->_getSearchView(
            CerberusContexts::CONTEXT_CONTRACT,
            $params,
            $limit,
            $page,
            $sortBy,
            $sortAsc
        );

        if(!empty($query) && $view instanceof IAbstractView_QuickSearch)
            $view->addParamsWithQuickSearch($query, true);

        // If we're given explicit filters, merge them in to our quick search
        if(!empty($filters)) {
            if(!empty($query))
                $params = $view->getParams(false);

            $custom_field_params = $this->_handleSearchBuildParamsCustomFields($filters, CerberusContexts::CONTEXT_CONTRACT);
            $new_params = $this->_handleSearchBuildParams($filters);
            $params = array_merge($params, $new_params, $custom_field_params);

            $view->addParams($params, true);
        }

        if($show_results)
            list($results, $total) = $view->getData();

        // Get subtotal data, if provided
        if(!empty($subtotals))
            $subtotal_data = $this->_handleSearchSubtotals($view, $subtotals);

        if($show_results) {
            $objects = array();

            $models = CerberusContexts::getModels(CerberusContexts::CONTEXT_CONTRACT, array_keys($results));

            unset($results);

            foreach($models as $id => $model) {
                $values = $this->getContext($model);
                $objects[$id] = $values;
            }
        }

        $container = array();

        if($show_results) {
            $container['results'] = $objects;
            $container['total'] = $total;
            $container['count'] = count($objects);
            $container['page'] = $page;
        }

        if(!empty($subtotals)) {
            $container['subtotals'] = $subtotal_data;
        }

        return $container;
    }

    function postSearch() {
        $worker = CerberusApplication::getActiveWorker();

        // ACL
//		if(!$worker->hasPriv('core.addybook'))
//			$this->error(self::ERRNO_ACL);

        $container = $this->_handlePostSearch();

        $this->success($container);
    }

    function putId($id) {
        $worker = CerberusApplication::getActiveWorker();

        // Validate the ID
        if(null == ($contract = DAO_Contract::get($id)))
            $this->error(self::ERRNO_CUSTOM, sprintf("Invalid contract ID '%d'", $id));

        // ACL
        if(!($worker->hasPriv('core.contracts.actions.update_all') || $contract->worker_id == $worker->id))
            $this->error(self::ERRNO_ACL);

        $putfields = array(
            'name' => 'string',
            'org' => 'string',
            'updated_at'=> 'timestamp'
        );

        $fields = array();

        foreach($putfields as $putfield => $type) {
            if(!isset($_POST[$putfield]))
                continue;

            @$value = DevblocksPlatform::importGPC($_POST[$putfield], 'string', '');

            if(null == ($field = self::translateToken($putfield, 'dao'))) {
                $this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $putfield));
            }

            // Sanitize
            $value = DevblocksPlatform::importVar($value, $type);

            $fields[$field] = $value;
        }



        // Handle custom fields
        $customfields = $this->_handleCustomFields($_POST);
        if(is_array($customfields))
            DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_CONTRACT, $id, $customfields, true, true, true);

        // Update
        DAO_Contract::update($id, $fields);
        $this->getId($id);
    }

    function postCreate() {
        $worker = CerberusApplication::getActiveWorker();

        // ACL
        // if(!$worker->hasPriv('core.contracts.actions.create'))
        //   $this->error(self::ERRNO_ACL);

        $postfields = array(
            'name' => 'string',
            'org' => 'string',
            'updated_at'=> 'timestamp',
        );

        $fields = array();

        foreach($postfields as $postfield => $type) {
            if(!isset($_POST[$postfield]))
                continue;
            @$value = DevblocksPlatform::importGPC($_POST[$postfield], 'string', '');

            if(null == ($field = self::translateToken($postfield, 'dao'))) {
                $this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $postfield));
            }

            // Sanitize
            $value = DevblocksPlatform::importVar($value, $type);

            $fields[$field] = $value;
        }

        // Defaults
        if(!isset($fields[DAO_Contract::UPDATED_AT]))
            $fields[DAO_Contract::UPDATED_AT] = time();

        // Check required fields
        $reqfields = array(
            DAO_Contract::NAME,
        );
        $this->_handleRequiredFields($reqfields, $fields);

        // Custom fields
        $custom_fields = $this->_handleCustomFields($_POST);

        // Create
        if(false != ($id = DAO_Contract::create($fields, $custom_fields))) {
            $this->getId($id);
        }
    }

    private function postNote($id) {
        $worker = CerberusApplication::getActiveWorker();

        @$note = DevblocksPlatform::importGPC($_POST['note'],'string','');

        if(null == ($contract = DAO_Contract::get($id)))
            $this->error(self::ERRNO_CUSTOM, sprintf("Invalid contract ID %d", $id));

        // ACL
        if(!($worker->hasPriv('core.contracts.actions.update_all') || $contract->worker_id==$worker->id))
            $this->error(self::ERRNO_ACL);

        // Required fields
        if(empty($note))
            $this->error(self::ERRNO_CUSTOM, "The 'note' field is required.");

        // Post
        $fields = array(
            DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_CONTRACT,
            DAO_Comment::CONTEXT_ID => $contract->id,
            DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
            DAO_Comment::OWNER_CONTEXT_ID => $worker->id,
            DAO_Comment::CREATED => time(),
            DAO_Comment::COMMENT => $note,
        );
        $note_id = DAO_Comment::create($fields);

        $this->success(array(
            'contract_id' => $contract->id,
            'note_id' => $note_id,
        ));
    }
};