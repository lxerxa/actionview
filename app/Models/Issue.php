<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Project\Provider;
use Cartalyst\Sentinel\Users\EloquentUser;
use App\Workflow\Workflow;
use Illuminate\Support\Facades\Event;
use App\Events\IssueEvent;
use App\Project\Eloquent\Labels;
use App\Models\Modules;
use DB;


class Issue extends Model
{
    public $project_key, $table, $default_user, $tmp_user;
    public $schema, $workflow, $abb, $abb_id, $parent_id;
    public $modules;
    public $mail_field = 'barcode';
    public $_CACHE;

    public function setAbb($abb)
    {
        $this->abb = $abb;
        $item = DB::collection('config_type')->where('project_key', $this->project_key)->where('abb', $abb)->first();
        if ($item) {
            $this->abb_id = $item['_id']->__toString();
        }
        return $this;
    }

    //设置project_key
    public function setProjectKey($project_key)
    {
        $this->project_key = $project_key;
        $this->modules = new Modules($this->project_key);
        return $this;
    }

    //设置parent_id
    public function setPrentId($parent_id)
    {
        $this->parent_id = $parent_id;
        return $this;
    }

    //设置判断药品是否已存在的字段，默认是barcode
    public function setMainFild($fild)
    {
        $this->mail_field = $fild;
        return $this;
    }



    //获取字段
    public function getField($key)
    {
        $_key = $this->project_key . "_" . $key;

        if (isset($this->_CACHE[$_key]) && $this->_CACHE[$_key]) {
            $item =  $this->_CACHE[$_key];
        } else {
            $item = DB::collection('config_field')->where('project_key', $this->project_key)->where('key', $key)->first();
            if (!$item) return false;
            $item['id'] = $item['_id']->__toString();
            $this->_CACHE[$_key] = $item;
        }
        return $item;
    }

    public function get_this_class_methods($class)
    {
        $array1 = get_class_methods($class);
        if ($parent_class = get_parent_class($class)) {
            $array2 = get_class_methods($parent_class);
            $array3 = array_diff($array1, $array2);
        } else {
            $array3 = $array1;
        }
        return $array3;
    }



    /**
     * 通过条形码获取主任务
     */
    public function findByBarcode($code, $parent_id = null, $ext = [], $is_del = null, $orderby = null, $field = null)
    {
        $methods = ['where', 'orWhere', 'whereIn', 'whereNotIn', 'whereBetween', 'whereNull', 'whereNotNull'];
        if (is_null($field) || !$field) $field = $this->mail_field;
        if ($field == 'barcode') { //条码处理
            $code = intval($code);
        }
        if (!$this->abb_id) {
            $item = DB::collection('issue_' . $this->project_key);
        } else {
            $item = DB::collection('issue_' . $this->project_key)->where('type', $this->abb_id);
        }
        $item = $item->where($field, $code);
        // if(in_array( $field, ['icp','icpno'])){ //icp的并行处理
        //     $item = $item->where($field, $code)->orWhere(($field=='icp'?'icpno':'icp'),$code);
        // }else{
        //     $item = $item->where($field, $code);
        // }

        if ($ext) {
            foreach ($ext as $k => $v) {
                $meth = 'where';
                if (strpos($k, '@') === 0 && strpos($k, '_')) { //针对@whereNotNull_key 等情况
                    $keys = explode("_", $k);
                    $k =  $keys[1];
                    $methx = str_replace('@', '', $keys[0]);
                    if (in_array($methx, $methods)) {
                        $meth = $methx;
                    }
                }
                if (in_array($meth, ['whereNull', 'whereNotNull'])) {
                    $item = $item->$meth($k);
                } else {
                    $item = $item->$meth($k, $v);
                }
            }
        }

        $item = is_null($parent_id) ? $item->whereNull('parent_id') : $item->whereNotNull('parent_id');
        $item = is_null($is_del) ? $item->whereNull('del_flg') : $item->whereNotNull('del_flg');
        if (!$orderby) {
            $item = $item->first();
        } else {
            $orate = 'asc';
            if (is_array($orderby)) {
                $okey = $orderby[0];
                if (isset($orderby[1]) && in_array(strtolower($orderby[1]), ['asc', 'desc'])) {
                    $orate = strtolower($orderby[1]);
                }
            } else {
                $okey = $orderby;
            }
            $item = $item->orderBy($okey, $orate)->first();
        }
        return $item;
    }




    public function inputFlow($data, $default_user = '')
    {
        if (!$data) {
            $this->error = '内容不能为空。';
            return false;
        }

        $new_fields = [];
        $fields = Provider::getFieldList($this->project_key);

        foreach ($fields as $field) {
            if ($field->type !== 'File') {
                $new_fields[$field->key] = $field->name;
            }
        }

        $new_fields['type'] = '类型';
        $new_fields['state'] = '状态';
        $new_fields['parent'] = '父级任务';
        $new_fields['reporter'] = '报告者';
        $new_fields['created_at'] = '创建时间';
        $new_fields['updated_at'] = '更新时间';
        $new_fields['resolver'] = '解决者';
        $new_fields['resolved_at'] = '解决时间';
        $new_fields['closer'] = '关闭者';
        $new_fields['closed_at'] = '关闭时间';
        $fields = $new_fields;


        // get the type schema
        $new_types = [];
        $standard_type_ids = [];
        $types = Provider::getTypeList($this->project_key);
        foreach ($types as $type) {
            $tmp = [];
            $tmp['id'] = $type->id;
            $tmp['name'] = $type->name;
            $tmp['type'] = $type->type ?: 'standard';
            $tmp['workflow'] = $type->workflow;
            $tmp['schema'] = Provider::getSchemaByType($type->id);
            $new_types[$type->id] = $tmp;
            if (!isset($data['type']) || !$data['type']) {
                if ($type->abb == $this->abb) { //置顶类型
                    $data['type'] = $type->id;
                }
            } else {
                if (strlen($data['type']) < 5) {
                    if ($type->abb == strlen($data['type'])) { //置顶类型
                        $data['type'] = $type->id;
                    }
                }
            }
            if ($tmp['type'] == 'standard') {
                $standard_type_ids[] = $tmp['id'];
            }
        }
        $types = $new_types;

        // get the state option
        $new_priorities = [];
        $priorities = Provider::getPriorityOptions($this->project_key);
        foreach ($priorities as $priority) {
            $new_priorities[$priority['name']] = $priority['_id'];
        }
        $priorities = $new_priorities;

        // get the state option
        $new_states = [];
        $states = Provider::getStateOptions($this->project_key);
        foreach ($states as $state) {
            $new_states[$state['name']] = $state['_id'];
        }
        $states = $new_states;

        // get the state option
        $new_resolutions = [];
        $resolutions = Provider::getResolutionOptions($this->project_key);
        foreach ($resolutions as $resolution) {
            $new_resolutions[$resolution['name']] = $resolution['_id'];
        }
        $resolutions = $new_resolutions;

        $value = $data;
        $issue = [];
        if (isset($value['title'])) $cur_title = $issue['title'] = $value['title'];

        if ($types[$value['type']]['type'] === 'subtask' && (!isset($value['parent_id']) || !$value['parent_id']) && !$this->parent_id) {
            $this->error = '父级任务列不能为空。';
            return false;
        } else {
            $issue['parent_id'] = isset($value['parent_id']) ? $value['parent_id'] : $this->parent_id;
        }



        if (isset($value['priority']) && $value['priority']) {
            $issue['priority'] = $priorities[$value['priority']];
        }

        if (isset($value['state']) && $value['state']) {
            if (isset($states[$value['state']]) &&  $states[$value['state']]) {
                $issue['state'] = $states[$value['state']];
                $workflow = $types[$value['type']]['workflow'];
                if (!in_array($issue['state'], $workflow['state_ids'])) {
                    unset($issue['state']);
                }
            }
        }

        if (isset($value['resolution']) && $value['resolution']) {
            if (!isset($resolutions[$value['resolution']]) || !$resolutions[$value['resolution']]) {
                unset($issue['resolution']);
            } else {
                $issue['resolution'] = $resolutions[$value['resolution']];
            }
        }

        $user_relate_fields = ['assignee' => '负责人', 'reporter' => '报告者', 'resolver' => '解决者', 'closer' => '关闭时间'];
        foreach ($user_relate_fields as $uk => $uv) {
            if (isset($value[$uk]) && $value[$uk]) {
                $tmp_user = EloquentUser::where('first_name', $value[$uk])->first();
                if (!$tmp_user) {
                    unset($value[$uk]);
                } else {
                    $issue[$uk] = ['id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email];
                    if ($uk == 'resolver') {
                        $issue['his_resolvers'] = [$tmp_user->id];
                    }
                }
            }
        }

        $time_relate_fields = ['created_at' => '创建时间', 'resolved_at' => '解决时间', 'closed_at' => '关闭时间', 'updated_at' => '更新时间'];
        foreach ($time_relate_fields as $tk => $tv) {
            if (isset($value[$tk]) && $value[$tk]) {
                $stamptime = strtotime($value[$tk]);
                if ($stamptime === false) {
                    unset($issue[$tk]);
                } else {
                    $issue[$tk] = $stamptime;
                }
            }
        }

        $schema = $types[$value['type']]['schema'];
        foreach ($schema as $field) {
            if (isset($field['required']) && $field['required'] && (!isset($value[$field['key']]) || !$value[$field['key']])) {
                $this->error = $fields[$field['key']] . '列值不能为空。';
                return false;
            }
            if (isset($value[$field['key']]) && $value[$field['key']]) {
                $field_key = $field['key'];
                $field_value = $value[$field['key']];
            } else {
                continue;
            }

            if (in_array($field_key, ['priority', 'resolution', 'assignee'])) {
                continue;
            }

            if ($field_key == 'labels') {
                $issue['labels'] = [];
                foreach (explode(',', $field_value) as $val) {
                    if (trim($val)) {
                        $issue['labels'][] = trim($val);
                    }
                }
                $issue['labels'] = array_values(array_unique($issue['labels']));
            } else if ($field['type'] === 'SingleUser' || $field_key === 'assignee') {
                $tmp_user = EloquentUser::where('first_name', $field_value)->first();
                if (!$tmp_user) {
                    $this->error =  $fields[$field_key] . '列用户不存在。';
                    return false;
                } else {
                    $issue[$field_key] = ['id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email];
                }
            } else if ($field['type'] === 'MultiUser') {
                $issue[$field_key] = [];
                $issue[$field_key . '_ids'] = [];
                foreach (explode(',', $field_value) as $val) {
                    if (!trim($val)) {
                        continue;
                    }

                    $tmp_user = EloquentUser::where('first_name', trim($val))->first();
                    if (!$tmp_user) {
                        $this->error = $fields[$field_key] . '列用户不存在。';
                        return false;
                    } else if (!in_array($tmp_user->id, $issue[$field_key . '_ids'])) {
                        $issue[$field_key][] = ['id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email];
                        $issue[$field_key . '_ids'][] = $tmp_user->id;
                    }
                }
            } else if (in_array($field['type'], ['Select', 'RadioGroup', 'SingleVersion'])) {
                foreach ($field['optionValues'] as $val) {
                    if ($val['name'] === $field_value) {
                        $issue[$field_key] = $val['id'];
                        break;
                    }
                }
                if (!isset($issue[$field_key]) && $field['optionValues']) {
                    $this->error = $fields[$field_key] . '列值匹配失败。';
                    return false;
                }
            } else if (in_array($field['type'], ['MultiSelect', 'CheckboxGroup', 'MultiVersion'])) {
                $issue[$field_key] = [];
                foreach (explode(',', $field_value) as $val) {
                    $val = trim($val);
                    if (!$val) {
                        continue;
                    }

                    $isMatched = false;
                    foreach ($field['optionValues'] as $val2) {
                        if ($val2['name'] === $val) {
                            $issue[$field_key][] = $val2['id'];
                            $isMatched = true;
                            break;
                        }
                    }
                    if (!$isMatched && $field['optionValues']) {
                        $this->error = $fields[$field_key] . '列值匹配失败。';
                        return false;
                    }
                }
                $issue[$field_key] = array_values(array_unique($issue[$field_key]));
            } else if (in_array($field['type'], ['DatePicker', 'DatetimePicker'])) {
                $stamptime = strtotime($field_value);
                if ($stamptime === false) {
                    $this->error = $fields[$field_key] . '列值格式错误。';
                    return false;
                } else {
                    $issue[$field_key] = $stamptime;
                }
            } else if ($field['type'] === 'TimeTracking') {
                if (!$this->ttCheck($field_value)) {
                    $this->error = $fields[$field_key] . '列值格式错误。';
                    return false;
                } else {
                    $issue[$field_key] = $this->ttHandle($field_value);
                    $issue[$field_key . '_m'] = $this->ttHandleInM($issue[$field_key]);
                }
            } else if ($field['type'] === 'Number') {
                $issue[$field_key] = floatval($field_value);
            } else {
                $issue[$field_key] = $field_value;
            }
        }

        $new_types = [];
        foreach ($types as $type) {
            $new_types[$type['id']] = $type;
        }
        $types = $new_types;

        //模块定义 
        if (isset($value['modules']) && is_array($value['modules']) && (!isset($value['module']) || !$value['module'])) {
            foreach ($value['modules'] as $v) {
                $issue['module'][] =  $this->modules->name2Id($v);
            }
        }

        if (!isset($issue['type']))  $issue['type'] = $data['type'];
        if (!isset($issue['assignee'])) {
            $user_info = $this->userInfo($default_user);
            $issue['assignee'] = $user_info;
        }
        if (!isset($issue['reporter'])) {
            $issue['reporter'] = $issue['assignee'];
        }
        $this->schema = $types[$issue['type']]['schema'];
        $this->workflow = $types[$issue['type']]['workflow'];
        return $issue;
    }

    public function newOne($issue)
    {
        if (!$issue) return false;
        $issue = $this->inputFlow($issue, $this->default_user);
        if (!isset($issue['resolution']) || !$issue['resolution']) {
            $issue['resolution'] = 'Unresolved';
        }

        $max_row = DB::collection('issue_' . $this->project_key)->orderBy('no', 'desc')->first();
        $issue['no'] = $max_row ? intval($max_row['no']) + 1 : 1;

        if (!isset($issue['created_at']) || !$issue['created_at']) {
            $issue['created_at'] = time();
        }
        if (!isset($issue['updated_at']) || !$issue['updated_at']) {
            $issue['updated_at'] = time();
        }

        if (!isset($issue['state']) || !$issue['state']) {
            if (isset($issue['type'])) {
                $wf = $this->initializeWorkflow($issue['type'], $this->tmp_user->_id);
                $issue += $wf;
            }
        } else if (in_array($issue['state'], $this->workflow->state_ids ?: [])) {
            $wf = $this->initializeWorkflowForImport($this->workflow, $issue['state']);
            $issue += $wf;
        }
        $id = DB::collection('issue_' . $this->project_key)->insertGetId($issue);
        $id = $id->__toString();
        // add to histroy table
        Provider::snap2His($this->project_key, $id, $this->schema);
        // trigger event of issue created
        Event::dispatch(new IssueEvent($this->project_key, $id, $issue['reporter'], ['event_key' => 'create_issue']));

        if (isset($issue['labels']) && $issue['labels']) {
            $this->createLabels($this->project_key, $issue['labels']);
        }
        return $id;
    }

    public function updateOne($id, $issue)
    {
        $issue = $this->inputFlow($issue, $this->default_user);
        if (!isset($issue['updated_at']) || !$issue['updated_at']) {
            $issue['updated_at'] = time();
        }
        DB::collection('issue_' . $this->project_key)->where('_id', $id)->update($issue);
        // add to histroy table
        $snap_id = Provider::snap2His($this->project_key, $id, $this->schema, array_keys($issue));
        // trigger event of issue edited
        if (isset($issue['reporter'])) Event::dispatch(new IssueEvent($this->project_key, $id, $issue['reporter'], ['event_key' => 'edit_issue', 'snap_id' => $snap_id]));
        return $id;
    }


    public function createLabels($project_key, $labels)
    {
        $created_labels = [];
        $project_labels = Labels::where('project_key', $project_key)
            ->whereIn('name', $labels)
            ->get();
        foreach ($project_labels as $label) {
            $created_labels[] = $label->name;
        }
        // get uncreated labels
        $new_labels = array_diff($labels, $created_labels);
        foreach ($new_labels as $label) {
            Labels::create(['project_key' => $project_key, 'name' => $label]);
        }
        return true;
    }

    public function userInfo($val)
    {
        $tmp_user = '';
        if ($val) {
            $tmp_user = EloquentUser::where('first_name', trim($val))->first();
        }
        if (!$tmp_user) {
            $tmp_user = EloquentUser::orderBy('crated_at', 'asc')->first();
        }
        $this->tmp_user = $tmp_user;
        $ret = ['id' => $tmp_user->id, 'name' => $tmp_user->first_name, 'email' => $tmp_user->email];
        return $ret;
    }


    public function initializeWorkflow($type, $userid)
    {
        // get workflow definition
        $wf_definition = Provider::getWorkflowByType($type);
        // create and start workflow instacne
        $wf_entry = Workflow::createInstance($wf_definition->id, $userid)->start(['caller' => $userid]);
        // get the inital step
        $initial_step = $wf_entry->getCurrentSteps()->first();
        $initial_state = $wf_entry->getStepMeta($initial_step->step_id, 'state');

        $ret['state'] = $initial_state;
        //$ret['resolution'] = 'Unresolved';
        $ret['entry_id'] = $wf_entry->getEntryId();
        $ret['definition_id'] = $wf_definition->id;
        return $ret;
    }

    /**
     * initialize the workflow for the issue import.
     *
     * @param  object  $wf_definition
     * @param  string  $state
     * @return array
     */
    public function initializeWorkflowForImport($wf_definition, $state, $user)
    {
        // create and start workflow instacne
        $wf_entry = Workflow::createInstance($wf_definition->id, $user->_id);

        $wf_contents = $wf_definition->contents ?: [];
        $steps = isset($wf_contents['steps']) && $wf_contents['steps'] ? $wf_contents['steps'] : [];

        $fake_step = [];
        foreach ($steps as $step) {
            if (isset($step['state']) && $step['state'] == $state) {
                $fake_step = $step;
                break;
            }
        }
        if (!$fake_step) {
            return [];
        }

        $caller = ['id' => $user->_id, 'name' => $user->first_name, 'email' => $user->email];
        $wf_entry->fakeNewCurrentStep($fake_step, $caller);

        $ret['entry_id'] = $wf_entry->getEntryId();
        $ret['definition_id'] = $wf_definition->id;

        return $ret;
    }
}
