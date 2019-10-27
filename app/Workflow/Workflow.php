<?php
namespace App\Workflow;

use App\Workflow\Eloquent\Definition;
use App\Workflow\Eloquent\Entry;
use App\Workflow\Eloquent\CurrentStep;
use App\Workflow\Eloquent\HistoryStep;

use App\Workflow\StateNotActivatedException;
use App\Workflow\StepNotFoundException;
use App\Workflow\CurrentStepNotFoundException;
use App\Workflow\ActionNotFoundException;
use App\Workflow\ActionNotAvailableException;
use App\Workflow\ResultNotFoundException;
use App\Workflow\ResultNotAvailableException;
use App\Workflow\FunctionNotFoundException;
use App\Workflow\EntryNotFoundException;
use App\Workflow\ConfigNotFoundException;
use App\Workflow\SplitNotFoundException;
use App\Workflow\JoinNotFoundException;

class Workflow {

    /**
     * The workflow five states.
     *
     * @var int 
     */
    const OSWF_CREATED    = 1;
    const OSWF_ACTIVATED  = 2;
    const OSWF_SUSPENDED  = 3;
    const OSWF_COMPLETED  = 4;
    const OSWF_KILLED     = 5;

    /**
     * The workflow instance object.
     *
     * @var App\Workflow\Eloquent\Entry 
     */
    protected $entry;

    /**
     * The workflow config description.
     *
     * @var array
     */
    protected $wf_config;

    /**
     * workflow options 
     *
     * @var array
     */
    protected $options = [];

    /**
     * workflow constructor
     *
     * @param  string $entry_id
     * @return void
    */
    public function __construct($entry_id)
    {
        $entry = Entry::find($entry_id);
        if ($entry)
        {
            $this->entry = $entry;
            $definition = Definition::find($entry->definition_id);
            if (!$definition)
            {
                throw new ConfigNotFoundException();
            }

            if (isset($definition->contents) && $definition->contents)
            {
                $this->wf_config = $definition->contents;
            }
            else
            {
                throw new ConfigNotFoundException();
            }
        }
        else
        {
            throw new EntryNotFoundException();
        }
    }

    /**
     * create workflow.
     *
     * @param string $definition_id
     * @param string $caller
     * @return string
     */
    public static function createInstance($definition_id, $caller)
    {
        $entry = new Entry;
        $entry->definition_id = $definition_id;
        $entry->creator = $caller;
        $entry->state = self::OSWF_CREATED;
        $entry->save();
        return new Workflow($entry->id);
    }

    /**
     * get entry id.
     *
     * @return string
     */
    public function getEntryId()
    {
       return $this->entry->id; 
    }

    /**
     * check action is available
     *
     * @param array $action_descriptor
     * @return boolean
     */
    private function isActionAvailable($action_descriptor)
    {
        if (isset($action_descriptor['restrict_to']) && isset($action_descriptor['restrict_to']['conditions']) && $action_descriptor['restrict_to']['conditions'])
        {
            if (!$this->passesConditions($action_descriptor['restrict_to']['conditions']))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * initialize workflow.
     *
     * @param array options 
     * @return void
     */
    public function start($options=[])
    {
        $this->options = array_merge($this->options, $options);

        if (!isset($this->wf_config['initial_action']) || !$this->wf_config['initial_action'])
        {
            throw new ActionNotFoundException();
        }

        //$available_action_flg = false;
        //foreach ($this->wf_config['initial_actions'] as $action_descriptor)
        //{
        //    if ($this->isActionAvailable($action_descriptor))
        //    {
        //        $available_action_flg = true;
        //        break;
        //    }
        //}
        //if (!$available_action_flg)
        //{
        //    throw new ActionNotAvailableException();
        //}

        $action_descriptor = $this->wf_config['initial_action'];
        if (!$this->isActionAvailable($action_descriptor))
        {
            throw new ActionNotAvailableException();
        }

        // confirm result whose condition is satified.
        if (!isset($action_descriptor['results']) || !$action_descriptor['results'])
        {
            throw new ResultNotFoundException();
        }

        $available_result_descriptor = $this->getAvailableResult($action_descriptor['results']);
        if (!$available_result_descriptor)
        {
            throw new ResultNotAvailableException();
        }
        // create new current step
        $this->createNewCurrentStep($available_result_descriptor, $action_descriptor['id'], '');
        // change workflow state to activited
        $this->changeEntryState(self::OSWF_ACTIVATED);

        return $this;
    }

    /**
     * get workflow state.
     *
     * @return string
     */
    public function getEntryState()
    {
        return $this->entry->state;
    }

    /**
     * change workflow state.
     *
     * @param string $new_state
     * @return void
     */
    public function changeEntryState($new_state)
    {
        $entry = Entry::find($this->entry->id);
        $entry->state = $new_state;
        $entry->save();
    }

    /**
     * complete workflow.
     *
     * @param string $entry_id
     * @return void
     */
    protected function completeEntry($entry_id)
    {
        return $this->changeEntryState($entry_id, self::OSWF_COMPLETED);
    }

    /**
     * get current steps for workflow.
     *
     * @return array
     */
    public function getCurrentSteps()
    {
        return Entry::find($this->entry->id)->currentSteps;
    }

    /**
     *  get step meta.
     *
     * @param string $step_id
     * @return array
     */
    public function getStepMeta($step_id, $name='')
    {
        $step_description = $this->getStepDescriptor($step_id);
        if ($name) 
        {
            return isset($step_description[$name]) ? $step_description[$name] : '';
        }
        return $step_description;
    }

    /**
     *  move workflow step to history
     *
     * @param App\Workflow\Eloquent\CurrentStep $current_step
     * @param int $action_id
     * @return string previous_id 
     */
    private function moveToHistory($current_step, $action_id)
    {
        // add to history records
        $history_step = new HistoryStep;
        $history_step->fill($current_step->toArray());
        $history_step->action_id = $action_id;
        $history_step->caller = isset($this->options['caller']) ? $this->options['caller'] : '';
        $history_step->finish_time = time();
        $history_step->save();

        // delete from current step
        $current_step->delete();

        return $history_step->id;
    }

    /**
     *  create new workflow step.
     *
     * @param array $result_descriptor
     * @param int $action_id
     * @param string $previous_id
     * @return void
     */
    private function createNewCurrentStep($result_descriptor, $action_id, $previous_id='')
    {
        $step_descriptor = [];
        if (isset($result_descriptor['step']) && $result_descriptor['step'])
        {
            $step_descriptor = $this->getStepDescriptor($result_descriptor['step']);
            if (!$step_descriptor)
            {
                throw new StepNotFoundException();
            }
        }
        if (!$step_descriptor)
        {
            return;
        }
        // order to use for workflow post-function
        if (isset($step_descriptor['state']) && $step_descriptor['state'])
        {
            $this->options['state'] = $step_descriptor['state'];
        }

        $new_current_step = new CurrentStep;
        $new_current_step->entry_id = $this->entry->id;
        $new_current_step->action_id = $action_id;
        $new_current_step->step_id = isset($result_descriptor['step']) ? intval($result_descriptor['step']) : 0;
        $new_current_step->previous_id = $previous_id;
        $new_current_step->status = isset($result_descriptor['status']) ? $result_descriptor['status'] : 'Finished';
        $new_current_step->start_time = time();
        $new_current_step->owners =  isset($this->options['owners']) ? $this->options['owners'] : '';
        $new_current_step->comments = isset($this->options['comments']) ? $this->options['comments'] : '';
        $new_current_step->caller = isset($this->options['caller']) ? $this->options['caller'] : '';
        $new_current_step->save();

        // trigger before step
        if (isset($step_descriptor['pre_functions']) && $step_descriptor['pre_functions'])
        {
            $this->executeFunctions($step_descriptor['pre_functions']);
        }
    }

    /**
     * transfer workflow step.
     *
     * @param array $current_steps
     * @param int $action;
     * @return void
     */
    private function transitionWorkflow($current_steps, $action_id)
    {
        foreach ($current_steps as $current_step)
        {
            $step_descriptor = $this->getStepDescriptor($current_step->step_id);
            if (!$step_descriptor)
            {
                throw new StepNotFoundException();
            }

            $action_descriptor = $this->getActionDescriptor(isset($step_descriptor['actions']) ? $step_descriptor['actions'] : [], $action_id);
            if ($action_descriptor)
            {
                break;
            }
        }
        if (!$action_descriptor)
        {
            throw new ActionNotFoundException(); 
        }
        if (!$this->isActionAvailable($action_descriptor))
        {
            throw new ActionNotAvailableException();
        }

        if (!isset($action_descriptor['results']) || !$action_descriptor['results'])
        {
            throw new ResultNotFoundException();
        }
        // confirm result whose condition is satified.
        $available_result_descriptor = $this->getAvailableResult($action_descriptor['results']);
        if (!$available_result_descriptor)
        {
            throw new ResultNotAvailableException();
        }

        // triggers after step
        if (isset($step_descriptor['post_functions']) && $step_descriptor['post_functions'])
        {
            $this->executeFunctions($step_descriptor['post_functions']);
        }
        // triggers before action
        if (isset($action_descriptor['pre_functions']) && $action_descriptor['pre_functions'])
        {
            $this->executeFunctions($action_descriptor['pre_functions']);
        }
        // triggers before result
        if (isset($available_result_descriptor['pre_functions']) && $available_result_descriptor['pre_functions'])
        {
            $this->executeFunctions($available_result_descriptor['pre_functions']);
        }
        // split workflow
        if (isset($available_result_descriptor['split']) && $available_result_descriptor['split'])
        {
            // get split result
            $split_descriptor = $this->getSplitDescriptor($available_result_descriptor['split']);
            if (!$split_descriptor)
            {
                throw new SplitNotFoundException();
            }

            // move current to history step
            $prevoius_id = $this->moveToHistory($current_step, $action_id);
            foreach ($split_descriptor['list'] as $result_descriptor)
            {
                $this->createNewCurrentStep($result_descriptor, $action_id, $prevoius_id);
            }
        }
        else if (isset($available_result_descriptor['join']) && $available_result_descriptor['join'])
        {
            // fix me. join logic will be realized, suggest using the propertyset
            // get join result
            $join_descriptor = $this->getJoinDescriptor($available_result_descriptor['join']);
            if (!$join_descriptor)
            {
                throw new JoinNotFoundException();
            }

            // move current to history step
            $prevoius_id = $this->moveToHistory($current_step, $action_id);
            if ($this->isJoinCompleted())
            {
                // record other previous_ids by propertyset
                $this->createNewCurrentStep($join_descriptor, $action_id, $prevoius_id);
            }
        }
        else
        {
            // move current to history step
            $prevoius_id = $this->moveToHistory($current_step, $action_id);
            // create current step
            $this->createNewCurrentStep($available_result_descriptor, $action_id, $prevoius_id);
        }
        // triggers after result
        if (isset($available_result_descriptor['post_functions']) && $available_result_descriptor['post_functions'])
        {
            $this->executeFunctions($available_result_descriptor['post_functions']);
        }
        // triggers after action
        if (isset($action_descriptor['post_functions']) && $action_descriptor['post_functions'])
        {
            $this->executeFunctions($action_descriptor['post_functions']);
        }
    }

    /**
     * check if the join is completed 
     */
    private function isJoinCompleted()
    {
        return !CurrentStep::where('entry_id', $this->entry->id)->exists();
    }

    /**
     * execute action 
     *
     * @param string $action_id
     * @param array $options;
     * @return string
     */
    public function doAction($action_id, $options=[])
    {
        $state = $this->getEntryState($this->entry->id);
        if ($state != self::OSWF_CREATED && $state != self::OSWF_ACTIVATED)
        {
            throw new StateNotActivatedException();
        }

        $current_steps = $this->getCurrentSteps();
        if (!$current_steps)
        {
            throw new CurrentStepNotFoundException();
        }

        // set options
        $this->options = array_merge($this->options, $options);
        // complete workflow step transition
        $this->transitionWorkflow($current_steps, intval($action_id));
    }

    /**
     * get join descriptor from list.
     *
     * @param string $join_id
     * @return array 
     */
    private function getJoinDescriptor($join_id)
    {
        foreach ($this->wf_config['joins'] as $join)
        {
            if ($join['id'] == $join_id)
            {
                return $join;
            }
        }
        return [];
    }

    /**
     * get split descriptor from list.
     *
     * @param string $split_id
     * @return array 
     */
    private function getSplitDescriptor($split_id)
    {
        foreach ($this->wf_config['splits'] as $split)
        {
            if ($split['id'] == $split_id)
            {
                return $split;
            }
        }
        return [];
    }

    /**
     * get action descriptor from list.
     *
     * @param array $actions
     * @param string $action_id
     * @return array 
     */
    private function getActionDescriptor($actions, $action_id)
    {
        // get global config
        $actions = $actions ?: [];
        foreach ($actions as $action)
        {
            if ($action['id'] == $action_id)
            {
                return $action;
            }
        }
        return [];
    }

    /**
     *  get step configuration.
     *
     * @param array $steps
     * @param string $step_id
     * @return array
     */
    private function getStepDescriptor($step_id)
    {
        foreach ($this->wf_config['steps'] as $step)
        {
            if ($step['id'] == $step_id)
            {
                return $step;
            }
        }
        return [];
    }

    /**
     * save workflow configuration info.
     *
     * @param array $info
     * @return void
     */
    public static function saveWorkflowDefinition($info)
    {
        $definition = $info['_id'] ? Definition::find($info['_id']) : new Definition;
        $definition->fill($info);
        $definition->save();
    }

    /**
     * remove configuration info.
     *
     * @param string $definition_id
     * @return void
     */
    public static function removeWorkflowDefinition($definition_id)
    {
        Definition::find($definition_id)->delete();
    }

    /**
     * get all available actions
     *
     * @param array $info
     * @param bool $dest_state added for kanban dnd, is not common param.
     * @return array
     */
    public function getAvailableActions($options=[], $dest_state = false)
    {
        // set options
        $this->options = array_merge($this->options, $options);

        $available_actions = [];
        // get current steps
        $current_steps = $this->getCurrentSteps();
        foreach ($current_steps as $current_step)
        {
            $actions = $this->getAvailableActionsFromStep($current_step->step_id, $dest_state);
            $actions && $available_actions += $actions;
        }
        return $available_actions;
    }

    /**
     * get available actions for step
     *
     * @param string $step_id
     * @param bool $dest_state added for kanban dnd, is not common param.
     * @return array
     */
    private function getAvailableActionsFromStep($step_id, $dest_state = false)
    {
        $step_descriptor = $this->getStepDescriptor($step_id);
        if (!$step_descriptor)
        {
            throw new StepNotFoundException();
        }
        if (!isset($step_descriptor['actions']) || !$step_descriptor['actions'])
        {
            return [];
        }
        // global conditions for step
        if (!$this->isActionAvailable($step_descriptor))
        {
            return [];
        }

        $available_actions = [];
        foreach ($step_descriptor['actions'] as $action)
        {
            if ($this->isActionAvailable($action))
            {
                if ($dest_state)
                {
                    $state = '';
                    if (isset($action['results']) && is_array($action['results']) && count($action['results']) > 0 && isset($action['results'][0]['step']))
                    {
                        $dest_step_descriptor = $this->getStepDescriptor($action['results'][0]['step']);
                        $state = $dest_step_descriptor['state'];
                    }
                    
                    $available_actions[] = [ 'id' => $action['id'], 'name' => $action['name'], 'screen' => $action['screen'] ?: '', 'state' => $state ];
                }
                else
                {
                    $available_actions[] = [ 'id' => $action['id'], 'name' => $action['name'], 'screen' => $action['screen'] ?: '' ];
                }
            }
        }

        return $available_actions;
    }

    /**
     * get available result from result-list 
     *
     * @param array $results_descriptor
     * @return array
     */
    public function getAvailableResult($results_descriptor)
    {
        $available_result_descriptor = [];

        // confirm result whose condition is satified.
        foreach ($results_descriptor as $result_descriptor)
        {
            if (isset($result_descriptor['conditions']) && $result_descriptor['conditions'])
            {
                if ($this->passesConditions($result_descriptor['conditions']))
                {
                    $available_result_descriptor = $result_descriptor;
                    break;
                }
            }
            else
            {
                $available_result_descriptor = $result_descriptor;
            }
        }
        return $available_result_descriptor;
    }

    /**
     * check conditions is passed
     *
     * @param array $conditions
     * @return boolean
     */
    private function passesConditions($conditions)
    {
        if (!isset($conditions['list']) || !$conditions['list'])
        {
            return true;
        }

        $type = isset($conditions['type']) && isset($conditions['type']) ? $conditions['type'] : 'and';
        $result = $type == 'and' ? true : false;

        foreach ($conditions['list'] as $condition)
        {
            $tmp = $this->passesCondition($condition);
            if ($type == 'and' && !$tmp)
            {
                return false;
            }
            if ($type == 'or' && $tmp)
            {
                return true;
            }
        }
        return $result;
    }

    /**
     * check condition is passed
     *
     * @param array $condition
     * @return boolean
     */
    private function passesCondition($condition)
    {
        return $this->executeFunction($condition);
    }

    /**
     * execute functions
     *
     * @param array function
     * @return void
     */
    private function executeFunctions($functions)
    {
        if (!$functions || !is_array($functions))
        {
            return;
        }

        foreach ($functions as $function) 
        {
            if (is_array($function) && $function)
            {
                $this->executeFunction($function);
            }
        }
    }

    /**
     * execute function
     *
     * @param array $function
     * @return mixed
     */
    private function executeFunction($function)
    {
        $method = explode('@', $function['name']);
        $class = $method[0];
        $action = isset($method[1]) && $method[1] ? $method[1] : 'handle';

        // check handle function exists
        if (!method_exists($class, $action))
        {
            throw new FunctionNotFoundException();
        }
        $args = isset($function['args']) ? $function['args'] : [];
        // generate temporary vars
        $tmp_vars = $this->genTmpVars($args);
        // call handle function
        return $class::$action($tmp_vars);
    }

    /**
     * get all workflows' name.
     *
     * @return array
     */
    public static function getWorkflowNames()
    {
        return Definition::all(['name']);
    }

    /**
     * generate temporary variable.
     *
     * @return array
     */
    private function genTmpVars($args=[])
    {
        $tmp_vars = [];
        foreach ($this->entry as $key => $val)
        {
            $tmp_vars[$key] = $val;
        }
        $tmp_vars = array_merge($tmp_vars, $this->options);

        return array_merge($tmp_vars, $args);
    }

    /**
     * get property set
     *
     * @return mixed 
     */
    public function getPropertySet($key)
    {
        return $key ? $this->entry->propertysets[$key] : $this->entry->propertysets;
    }

    /**
     * add property set 
     *
     * @return void 
     */
    public function setPropertySet($key, $val)
    {
        $this->entry->propertysets = array_merge($this->entry->propertysets ?: [], [ $key => $val ]);
        $this->entry->save();
    }

    /**
     * remove property set
     *
     * @return void 
     */
    public function removePropertySet($key)
    {
        $this->entry->unset($key ? ('propertysets.' . $key) : 'propertysets');
    }

    /**
     * get used states in the workflow
     *
     * @return array
     */
    public static function getStates($contents)
    {
        $state_ids = [];
        $steps = isset($contents['steps']) && $contents['steps'] ? $contents['steps'] : [];
        foreach ($steps as $step)
        {
            $state_ids[] = isset($step['state']) ? $step['state'] : '';
        }
        return $state_ids;
    }

    /**
     * get used screens in the workflow 
     *
     * @return array 
     */
    public static function getScreens($contents)
    {
        $screen_ids = [];
        $steps = isset($contents['steps']) && $contents['steps'] ? $contents['steps'] : [];
        foreach ($steps as $step)
        {
            if (!isset($step['actions']) || !$step['actions'])
            {
                continue;
            }
            foreach ($step['actions'] as $action)
            {
                if (!isset($action['screen']) || !$action['screen'])
                {
                    continue;
                }
                $action['screen'] !=  '-1' && !in_array($action['screen'], $screen_ids) && $screen_ids[] = $action['screen'];
            }
        }
        return $screen_ids;
    }

    /**
     * get step num 
     *
     * @return int 
     */
    public static function getStepNum($contents)
    {
        $steps = isset($contents['steps']) && $contents['steps'] ? $contents['steps'] : [];
        return count($steps);
    }

    /**
     * fake new workflow step.
     *
     * @param array $result_descriptor
     * @param array $caller
     * @return void
     */
    public function fakeNewCurrentStep($result_descriptor, $caller)
    {
        $new_current_step = new CurrentStep;
        $new_current_step->entry_id = $this->entry->id;
        $new_current_step->step_id = intval($result_descriptor['id']);
        $new_current_step->status = isset($result_descriptor['status']) ? $result_descriptor['status'] : '';
        $new_current_step->start_time = time();
        $new_current_step->caller = $caller ?: '';
        $new_current_step->save();
    }
}
