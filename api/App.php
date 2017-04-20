<?php


if (class_exists('DevblocksEventListenerExtension')):
    class ContractEventListener extends DevblocksEventListenerExtension {
        /**
         * @param Model_DevblocksEvent $event
         */
        function handleEvent(Model_DevblocksEvent $event) {
            switch($event->id) {
                case 'cron.maint':
                    DAO_Contract::maint();
                    break;
            }
        }
    };
endif;

if(class_exists('Extension_DevblocksEventAction')):
    class WgmContracts_EventActionPost extends Extension_DevblocksEventAction {
        function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
            $tpl = DevblocksPlatform::getTemplateService();
            $tpl->assign('params', $params);
            $tpl->assign('workers', DAO_Worker::getAll());

            if(!is_null($seq))
                $tpl->assign('namePrefix', 'action'.$seq);

            $event = $trigger->getEvent();
            $values_to_contexts = $event->getValuesContexts($trigger);
            $tpl->assign('values_to_contexts', $values_to_contexts);

            // Custom fields
            DevblocksEventHelper::renderActionCreateRecordSetCustomFields(CerberusContexts::CONTEXT_CONTRACT, $tpl);

            // Template
            $tpl->display('devblocks:cerberusweb.contracts::contracts/events/action_create_contract.tpl');
        }

        function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
            @$watcher_worker_ids = DevblocksPlatform::importVar($params['worker_id'],'array',array());
            $watcher_worker_ids = DevblocksEventHelper::mergeWorkerVars($watcher_worker_ids, $dict);

            @$notify_worker_ids = DevblocksPlatform::importVar($params['notify_worker_id'],'array',array());
            $notify_worker_ids = DevblocksEventHelper::mergeWorkerVars($notify_worker_ids, $dict);

            $tpl_builder = DevblocksPlatform::getTemplateBuilder();

            $subject = $tpl_builder->build($params['subject'], $dict);
            $phone = $tpl_builder->build($params['phone'], $dict);
            $is_outgoing = $params['is_outgoing'];
            $is_closed = $params['is_closed'];
            $created = intval(@strtotime($tpl_builder->build($params['created'], $dict)));
            $comment = $tpl_builder->build($params['comment'], $dict);

            if(empty($created))
                $created = time();

            $out = sprintf(">>> Creating contract\n".
                "Subject: %s\n".
                "Phone #: %s\n".
                "Type: %s\n".
                "Status: %s\n".
                "Created: %s (%s)\n".
                "",
                $subject,
                $phone,
                ($is_outgoing ? 'Outgoing' : 'Incoming'),
                ($is_closed ? 'Closed' : 'Open'),
                (!empty($created) ? date("Y-m-d h:ia", $created) : 'none'),
                $params['created']
            );

            // Custom fields
            $out .= DevblocksEventHelper::simulateActionCreateRecordSetCustomFields($params, $dict);

            $out .= "\n";

            // Watchers
            if(is_array($watcher_worker_ids) && !empty($watcher_worker_ids)) {
                $out .= ">>> Adding watchers to contract:\n";
                foreach($watcher_worker_ids as $worker_id) {
                    if(null != ($worker = DAO_Worker::get($worker_id))) {
                        $out .= ' * ' . $worker->getName() . "\n";
                    }
                }
                $out .= "\n";
            }

            // Comment content
            if(!empty($comment)) {
                $out .= sprintf(">>> Writing comment on contract\n\n".
                    "%s\n\n",
                    $comment
                );

                if(!empty($notify_worker_ids) && is_array($notify_worker_ids)) {
                    $out .= ">>> Notifying\n";
                    foreach($notify_worker_ids as $worker_id) {
                        if(null != ($worker = DAO_Worker::get($worker_id))) {
                            $out .= ' * ' . $worker->getName() . "\n";
                        }
                    }
                    $out .= "\n";
                }
            }

            // Connection
            $out .= DevblocksEventHelper::simulateActionCreateRecordSetLinks($params, $dict);


            // Run in simulator
            @$run_in_simulator = !empty($params['run_in_simulator']);
            if($run_in_simulator) {
                $this->run($token, $trigger, $params, $dict);
            }

            // Set object variable
            $out .= DevblocksEventHelper::simulateActionCreateRecordSetVariable($params, $dict);

            return $out;
        }

        function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
            @$watcher_worker_ids = DevblocksPlatform::importVar($params['worker_id'],'array',array());
            $watcher_worker_ids = DevblocksEventHelper::mergeWorkerVars($watcher_worker_ids, $dict);

            @$notify_worker_ids = DevblocksPlatform::importVar($params['notify_worker_id'],'array',array());
            $notify_worker_ids = DevblocksEventHelper::mergeWorkerVars($notify_worker_ids, $dict);

            $tpl_builder = DevblocksPlatform::getTemplateBuilder();
            $subject = $tpl_builder->build($params['subject'], $dict);
            $phone = $tpl_builder->build($params['phone'], $dict);
            $is_outgoing = intval($params['is_outgoing']);
            $is_closed = intval($params['is_closed']);
            $created = intval(@strtotime($tpl_builder->build($params['created'], $dict)));
            $comment = $tpl_builder->build($params['comment'], $dict);

            if(empty($created))
                $created = time();

            $trigger = $dict->__trigger;

            $fields = array(
                DAO_Contract::SUBJECT => $subject,
                DAO_Contract::PHONE => $phone,
                DAO_Contract::CREATED_DATE => $created,
                DAO_Contract::UPDATED_DATE => time(),
                DAO_Contract::IS_CLOSED => $is_closed ? 1 : 0,
                DAO_Contract::IS_OUTGOING => $is_outgoing ? 1 : 0,
            );

            if(false == ($contract_id = DAO_Contract::create($fields)))
                return false;

            // Custom fields
            DevblocksEventHelper::runActionCreateRecordSetCustomFields(CerberusContexts::CONTEXT_CONTRACT, $contract_id, $params, $dict);

            // Watchers
            if(is_array($watcher_worker_ids) && !empty($watcher_worker_ids)) {
                CerberusContexts::addWatchers(CerberusContexts::CONTEXT_CONTRACT, $contract_id, $watcher_worker_ids);
            }

            // Comment content
            if(!empty($comment)) {
                $fields = array(
                    DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_BOT,
                    DAO_Comment::OWNER_CONTEXT_ID => $trigger->bot_id,
                    DAO_Comment::COMMENT => $comment,
                    DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_CONTRACT,
                    DAO_Comment::CONTEXT_ID => $contract_id,
                    DAO_Comment::CREATED => time(),
                );
                DAO_Comment::create($fields, $notify_worker_ids);
            }

            // Links
            DevblocksEventHelper::runActionCreateRecordSetLinks(CerberusContexts::CONTEXT_CONTRACT, $contract_id, $params, $dict);

            // Set object variable
            DevblocksEventHelper::runActionCreateRecordSetVariable(CerberusContexts::CONTEXT_CONTRACT, $contract_id, $params, $dict);

            return $contract_id;
        }
    };
endif;