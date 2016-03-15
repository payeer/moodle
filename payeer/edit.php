<?php
require('../../config.php');
require_once('edit_form.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT); 
$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/payeer:config', $context);

$PAGE->set_url('/enrol/payeer/edit.php', array('courseid'=>$course->id, 'id'=>$instanceid));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));

if (!enrol_is_enabled('payeer')) 
{
    redirect($return);
}

$plugin = enrol_get_plugin('payeer');

if ($instanceid) 
{
    $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'payeer', 'id'=>$instanceid), '*', MUST_EXIST);
} 
else 
{
    require_capability('moodle/course:enrolconfig', $context);
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id = null;
    $instance->courseid = $course->id;
}

$mform = new enrol_payeer_edit_form(NULL, array($instance, $plugin, $context));

if ($mform->is_cancelled()) 
{
    redirect($return);
} 
else if ($data = $mform->get_data())
{
    if ($instance->id)
	{
        $instance->status = $data->status;
        $instance->name = $data->name;
		$instance->customchar1 = $data->customchar1;
        $instance->cost = $data->cost;
        $instance->currency = $data->currency;
        $instance->roleid = $data->roleid;
        $instance->enrolperiod = $data->enrolperiod;
        $instance->enrolstartdate = $data->enrolstartdate;
        $instance->enrolenddate = $data->enrolenddate;
        $instance->timemodified = time();
        $DB->update_record('enrol', $instance);
    } 
	else 
	{
        $fields = array(
			'status'=>$data->status,
			'name'=>$data->name,
			'customchar1'=>$data->customchar1,
			'cost'=>$data->cost,
			'currency'=>$data->currency,
			'roleid'=>$data->roleid,
			'enrolperiod'=>$data->enrolperiod,
			'enrolstartdate'=>$data->enrolstartdate,
			'enrolenddate'=>$data->enrolenddate
		);
		
        $plugin->add_instance($course, $fields);
    }

    redirect($return);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_payeer'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_payeer'));
$mform->display();
echo $OUTPUT->footer();
?>