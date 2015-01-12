<?php

defined('MOODLE_INTERNAL') or die();

class enrol_payeer_plugin extends enrol_plugin 
{
    function __construct() 
	{
        $this->load_config();
        $this->recognised_currencies = array(
			'RUB',
            'EUR',
            'USD'
        );
        $this->payeer_url = 'https://www.payeer.com/';
    }

    public function get_info_icons(array $instances) 
	{
        return array(new pix_icon('icon', get_string('pluginname', 'enrol_payeer'), 'enrol_payeer'));
    }

    public function roles_protected() 
	{
        return false;
    }

    public function allow_unenrol(stdClass $instance) 
	{
        return true;
    }

    public function allow_manage(stdClass $instance)
	{
        return true;
    }

    public function show_enrolme_link(stdClass $instance) 
	{
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    public function add_course_navigation($instancesnode, stdClass $instance) 
	{
        if ($instance->enrol !== 'payeer') 
		{
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/payeer:config', $context)) 
		{
            $managelink = new moodle_url('/enrol/payeer/edit.php', array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    public function get_action_icons(stdClass $instance) 
	{
        global $OUTPUT;

        if ($instance->enrol !== 'payeer') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);

        $icons = array();

        if (has_capability('enrol/payeer:config', $context)) 
		{
            $editlink = new moodle_url("/enrol/payeer/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }

    public function get_newinstance_link($courseid)
	{
        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/payeer:config', $context)) 
		{
            return NULL;
        }

        return new moodle_url('/enrol/payeer/edit.php', array('courseid'=>$courseid));
    }


    function enrol_page_hook(stdClass $instance) 
	{
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) 
		{
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time())
		{
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time())
		{
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) 
		{
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        }
		else 
		{
            $teacher = false;
        }

        if ( (float) $instance->cost <= 0 ) 
		{
            $cost = (float) $this->get_config('cost');
        } 
		else 
		{
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) 
		{
            echo '<p>'.get_string('nocost', 'enrol_payeer').'</p>';
        } 
		else 
		{
            if (isguestuser()) 
			{
                if (empty($CFG->loginhttps)) 
				{
                    $wwwroot = $CFG->wwwroot;
                } 
				else 
				{
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $cost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } 
			else 
			{
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
                $instancename    = $this->get_instance_name($instance);
                include($CFG->dirroot.'/enrol/payeer/enrol.html');
            }
        }

        return $OUTPUT->box(ob_get_clean());
    }

    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) 
	{
        global $DB;
		
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) 
		{
            $merge = false;
        } 
		else 
		{
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
		
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } 
		else 
		{
            $instanceid = $this->add_instance($course, (array)$data);
        }
		
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }
	
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability("enrol/manual:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        if ($this->allow_manage($instance) && has_capability("enrol/manual:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/edit', ''), get_string('edit'), $url, array('class'=>'editenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    function begin_transaction($instance, $user) 
	{
        global $CFG, $DB;
		
        if (!$course = $DB->get_record('course', array('id' => $instance->courseid))) 
		{
            print_error('coursenotfound', 'enrol_payeer');
        }
		
        if (empty($course) or empty($user)) 
		{
            print_error('error_usercourseempty', 'enrol_payeer');
        }

        if (!in_array($instance->currency, $this->recognised_currencies)) 
		{
            print_error('error_payeercurrency', 'enrol_payeer');
        }

        $fullname = fullname($user);
        $payeertx->courseid = $course->id;
        $payeertx->userid = $user->id;
        $payeertx->instanceid = $instance->id;
        $payeertx->cost = clean_param(format_float((float)$instance->cost, 2), PARAM_CLEAN);
        $payeertx->currency = clean_param($instance->currency, PARAM_CLEAN);
        $payeertx->date_created = time();

        if (!$payeertx->id = $DB->insert_record('enrol_payeer_transactions', $payeertx)) 
		{
            print_error('error_txdatabase', 'enrol_payeer');
        }

        return $payeertx->id;
    }

    function cron()
	{
        $trace = new text_progress_trace();
        $this->process_expirations($trace);
    }

    public function sync(progress_trace $trace) 
	{
        $this->process_expirations($trace);
        return 0;
    }
}