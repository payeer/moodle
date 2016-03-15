<?php
require("../../config.php");
require_once("$CFG->dirroot/enrol/payeer/lib.php");

$id = required_param('m_orderid', PARAM_INT);

if (!$plugin_instance = $DB->get_record("enrol_payeer_transactions", array("id"=>$id))) 
{
    print_error('invalidinstance');
}

$plugin = enrol_get_plugin('payeer');

if (!$course = $DB->get_record("course", array("id"=>$plugin_instance->courseid))) 
{
    redirect($CFG->wwwroot);
}

$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login();

if ($SESSION->wantsurl) 
{
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} 
else 
{
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}

$fullname = format_string($course->fullname, true, array('context' => $context));
	
$PAGE->set_url($destination);
echo $OUTPUT->header();
$a = new stdClass();
$a->teacher = get_string('defaultcourseteacher');
$a->fullname = $fullname;
notice(get_string('paymentsorry', '', $a), $destination);
?>