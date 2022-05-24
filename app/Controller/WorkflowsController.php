<?php
App::uses('AppController', 'Controller');

class WorkflowsController extends AppController
{
    public $components = array(
        'RequestHandler'
    );

    public function beforeFilter()
    {
        parent::beforeFilter();
        $this->Security->unlockedActions[] = 'hasAcyclicGraph';
        try {
            $this->Workflow->setupRedisWithException();
        } catch (Exception $e) {
            $this->set('error', $e->getMessage());
            $this->render('error');
        }
    }

    public function index()
    {
        $params = [
            'filters' => ['name', 'uuid'],
            'quickFilters' => ['name', 'uuid'],
            'contain' => ['Organisation']
        ];
        $this->CRUD->index($params);
        if ($this->IndexFilter->isRest()) {
            return $this->restResponsePayload;
        }
        $this->set('menuData', array('menuList' => 'workflows', 'menuItem' => 'index'));
    }

    public function add()
    {
        $currentUser = $this->Auth->user();
        $params = [
            'beforeSave' => function ($data) use ($currentUser) {
                $data['Workflow']['uuid'] = CakeText::uuid();
                $data['Workflow']['user_id'] = $currentUser['id'];
                $data['Workflow']['org_id'] = $currentUser['org_id'];
                if (!isset($data['Workflow']['description'])) {
                    $data['Workflow']['description'] = '';
                }
                if (empty($data['Workflow']['data'])) {
                    $data['Workflow']['data'] = [];
                }
                return $data;
            },
            'redirect' => [
                'action' => 'index',
            ]
        ];
        $this->CRUD->add($params);
        if ($this->restResponsePayload) {
            return $this->restResponsePayload;
        }
        $this->set('menuData', array('menuList' => 'workflows', 'menuItem' => 'add'));
    }

    public function edit($id)
    {
        $this->set('id', $id);
        $savedWorkflow = $this->Workflow->fetchWorkflow($this->Auth->user(), $id);
        if ($this->request->is('post') || $this->request->is('put')) {
            $newWorkflow = $this->request->data;
            $newWorkflow['Workflow']['data'] = JsonTool::decode($newWorkflow['Workflow']['data']);
            $newWorkflow = $this->__applyDataFromSavedWorkflow($newWorkflow, $savedWorkflow);
            $errors = $this->Workflow->editWorkflow($this->Auth->user(), $newWorkflow);
            $redirectTarget = ['action' => 'view', $id];
            if (!empty($errors)) {
                return $this->__getFailResponseBasedOnContext($errors, null, 'edit', $this->Workflow->id, $redirectTarget);
            } else {
                $successMessage = __('Workflow saved.');
                $savedWorkflow =$this->Workflow->fetchWorkflow($this->Auth->user(), $id);
                return $this->__getSuccessResponseBasedOnContext($successMessage, $savedWorkflow, 'edit', false, $redirectTarget);
            }
        } else {
            $savedWorkflow['Workflow']['data'] = JsonTool::encode($savedWorkflow['Workflow']['data']);
            $this->request->data = $savedWorkflow;
        }

        $this->set('menuData', array('menuList' => 'workflows', 'menuItem' => 'edit'));
        $this->render('add');
    }

    public function delete($id)
    {
        $params = [
            'conditions' => $this->Workflow->buildACLConditions($this->Auth->user()),
            'redirect' => ['action' => 'index']
        ];
        $this->CRUD->delete($id, $params);
        if ($this->IndexFilter->isRest()) {
            return $this->restResponsePayload;
        }
    }

    public function view($id)
    {
        $this->CRUD->view($id, [
            'conditions' => $this->Workflow->buildACLConditions($this->Auth->user()),
            'contain' => ['Organisation', 'User']
        ]);
        if ($this->IndexFilter->isRest()) {
            return $this->restResponsePayload;
        }
        $this->set('id', $id);
        $this->set('menuData', array('menuList' => 'workflows', 'menuItem' => 'view'));
        // $execution_path = $this->Workflow->getExecutionPath($this->Auth->user(), $id);
        // $this->set('execution_path', $execution_path);
    }

    public function enable($id)
    {
        $errors = $this->Workflow->toggleWorkflow($this->Auth->user(), $id, true);
        $redirectTarget = ['action' => 'index'];
        if (!empty($errors)) {
            return $this->__getFailResponseBasedOnContext($errors, null, 'edit', $this->Workflow->id, $redirectTarget);
        } else {
            $successMessage = __('Workflow enabled.');
            $savedWorkflow = $this->Workflow->fetchWorkflow($this->Auth->user(), $id);
            return $this->__getSuccessResponseBasedOnContext($successMessage, $savedWorkflow, 'edit', false, $redirectTarget);
        }
    }

    public function disable($id)
    {
        $errors = $this->Workflow->toggleWorkflow($this->Auth->user(), $id, false);
        $redirectTarget = ['action' => 'index'];
        if (!empty($errors)) {
            return $this->__getFailResponseBasedOnContext($errors, null, 'edit', $this->Workflow->id, $redirectTarget);
        } else {
            $successMessage = __('Workflow disabled.');
            $savedWorkflow = $this->Workflow->fetchWorkflow($this->Auth->user(), $id);
            return $this->__getSuccessResponseBasedOnContext($successMessage, $savedWorkflow, 'edit', false, $redirectTarget);
        }
    }

    public function editor($id = false)
    {
        $modules = $this->Workflow->getModulesByType();
        $workflow = $this->Workflow->fetchWorkflow($this->Auth->user(), $id);
        $modules = $this->Workflow->attachNotificationToModules($this->Auth->user(), $modules, $workflow);
        $workflows = $this->Workflow->fetchWorkflows($this->Auth->user());
        $this->set('selectedWorkflow', $workflow);
        $this->set('workflows', $workflows);
        $this->set('modules', $modules);
    }

    public function triggerIndex()
    {
        $modules = $this->Workflow->getModulesByType();
        $triggers = $modules['blocks_trigger'];
        $triggers = $this->Workflow->attachWorkflowsToTriggers($this->Auth->user(), $triggers, true);
        $this->set('data', $triggers);
        $this->set('menuData', ['menuList' => 'workflows', 'menuItem' => 'index_trigger']);
    }

    public function triggerView($trigger_id)
    {
        $trigger = $this->Workflow->getModuleByID($trigger_id);
        if (empty($trigger)) {
            throw new NotFoundException(__('Invalid trigger ID'));
        }
        $trigger = $this->Workflow->attachWorkflowsToTriggers($this->Auth->user(), [$trigger], true)[0];
        $this->set('data', $trigger);
        $this->set('menuData', ['menuList' => 'workflows', 'menuItem' => 'view_trigger']);
    }

    // public function getOrderedWorkflowsPerTrigger($trigger_id)
    // {
    //     $trigger = $this->Workflow->getModuleByID($trigger_id);
    //     if (empty($trigger)) {
    //         throw new NotFoundException(__('Invalid trigger ID'));
    //     }
    //     $trigger = $this->Workflow->attachWorkflowsToTriggers($this->Auth->user(), [$trigger], true)[0];
    //     return $this->RestResponse->viewData($trigger['Workflows'], 'json');
    // }

    public function rearrangeExecutionOrder($trigger_id)
    {
        $trigger = $this->Workflow->getModuleByID($trigger_id);
        if (empty($trigger)) {
            throw new NotFoundException(__('Invalid trigger ID'));
        }
        $trigger = $this->Workflow->attachWorkflowsToTriggers($this->Auth->user(), [$trigger], true)[0];
        $workflow_order = [];
        if (!empty($trigger['Workflows']['blocking'])) {
            $workflow_order = Hash::extract($trigger['Workflows']['blocking'], '{n}.Workflow.id');
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            $workflow_order = array_unique(JsonTool::decode($this->request->data['Workflow']['workflow_order']));
            $saved = $this->Workflow->saveBlockingWorkflowExecutionOrder($trigger['id'], $workflow_order);
            $redirectTarget = ['action' => 'triggerView', $trigger_id];
            if (empty($saved)) {
                return $this->__getFailResponseBasedOnContext([__('Could not save workflow execution order.')], null, 'rearrangeExecutionOrder', $trigger_id, $redirectTarget);
            } else {
                $successMessage = __('Workflow execution order saved.');
                return $this->__getSuccessResponseBasedOnContext($successMessage, $workflow_order, 'rearrangeExecutionOrder', false, $redirectTarget);
            }
        } else {
            $this->request->data = [
                'Workflow' => [
                    'workflow_order' => JsonTool::encode($workflow_order),
                ]
            ];
        }
        $this->set('trigger', $trigger);
    }

    private function __getSuccessResponseBasedOnContext($message, $data = null, $action = '', $id = false, $redirect = array())
    {
        if ($this->_isRest()) {
            if (!is_null($data)) {
                return $this->RestResponse->viewData($data, $this->response->type());
            } else {
                return $this->RestResponse->saveSuccessResponse('Workflow', $action, $id, false, $message);
            }
        } elseif ($this->request->is('ajax')) {
            return $this->RestResponse->saveSuccessResponse('Workflow', $action, $id, false, $message, $data);
        } else {
            $this->Flash->success($message);
            $this->redirect($redirect);
        }
        return;
    }

    private function __getFailResponseBasedOnContext($message, $data = null, $action = '', $id = false, $redirect = array())
    {
        if (is_array($message)) {
            $message = implode(', ', $message);
        }
        if ($this->_isRest()) {
            if ($data !== null) {
                return $this->RestResponse->viewData($data, $this->response->type());
            } else {
                return $this->RestResponse->saveFailResponse('Workflow', $action, $id, $message);
            }
        } elseif ($this->request->is('ajax')) {
            return $this->RestResponse->saveFailResponse('Workflow', $action, $id, $message, false, $data);
        } else {
            $this->Flash->error($message);
            $this->redirect($this->referer());
        }
    }

    private function __applyDataFromSavedWorkflow($newWorkflow, $savedWorkflow)
    {
        if (!isset($newReport['Workflow'])) {
            $newReport = ['Workflow' => $newWorkflow];
        }
        $ignoreFieldList = ['id', 'uuid', 'org_id', 'user_id'];
        foreach (Workflow::CAPTURE_FIELDS as $field) {
            if (!in_array($field, $ignoreFieldList) && isset($newWorkflow['Workflow'][$field])) {
                $savedWorkflow['Workflow'][$field] = $newWorkflow['Workflow'][$field];
            }
        }
        return $savedWorkflow;
    }

    public function hasAcyclicGraph()
    {
        $this->request->allowMethod(['post']);
        $graphData = $this->request->data;
        $cycles = [];
        $isAcyclic = $this->Workflow->workflowGraphTool->isAcyclic($graphData, $cycles);
        $data = [
            'is_acyclic' => $isAcyclic,
            'cycles' => $cycles,
        ];
        return $this->RestResponse->viewData($data, 'json');
    }
}
