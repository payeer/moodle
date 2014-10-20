<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) 
{
    $settings->add(new admin_setting_heading('enrol_payeer_settings', '', get_string('pluginname_desc', 'enrol_payeer')));

    $options = array('www.payeer.com'  => 'www.payeer.com');
	
	$settings->add(new admin_setting_configtext('enrol_payeer/payeer_merchant_url', get_string('payeer_merchant_url', 'enrol_payeer'), '', '//payeer.com/merchant/'));
	
    $settings->add(new admin_setting_configtext('enrol_payeer/payeer_shop', get_string('payeer_shop', 'enrol_payeer'), '', '', PARAM_INT, 30));

    $settings->add(new admin_setting_configtext('enrol_payeer/payeer_key', get_string('payeer_key', 'enrol_payeer'), '', ''));

    $settings->add(new admin_setting_configcheckbox('enrol_payeer/payeer_log', get_string('payeer_log', 'enrol_payeer'), '', 0));

    $settings->add(new admin_setting_configtext('enrol_payeer/payeer_emailerr', get_string('payeer_emailerr', 'enrol_payeer'), '', '', PARAM_EMAIL));

    $settings->add(new admin_setting_configtext('enrol_payeer/payeer_ipfilter', get_string('payeer_ipfilter', 'enrol_payeer'), '', ''));

    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
	
    $settings->add(new admin_setting_configselect('enrol_payeer/expiredaction', get_string('expiredaction', 'enrol_payeer'), get_string('expiredaction_help', 'enrol_payeer'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));
	
    $settings->add(new admin_setting_heading('enrol_payeer_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $settings->add(new admin_setting_configcheckbox('enrol_payeer/defaultenrol',
        get_string('defaultenrol', 'enrol'), get_string('defaultenrol_desc', 'enrol'), 1));
	
    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_payeer/status',
        get_string('status', 'enrol_payeer'), get_string('status_desc', 'enrol_payeer'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_payeer/cost', get_string('cost', 'enrol_payeer'), '', 0, PARAM_FLOAT, 4));

    $payeercurrencies = array(
							  'RUB' => 'Russian Ruble',
                              'CAD' => 'Canadian Dollars',
                              'EUR' => 'Euros',
                              'GBP' => 'British Pounds',
							  'USD' => 'US Dollars',
                             );
    $settings->add(new admin_setting_configselect('enrol_payeer/currency', get_string('currency', 'enrol_payeer'), '', 'RUB', $payeercurrencies));

    if (!during_initial_install()) 
	{
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_payeer/roleid',
            get_string('defaultrole', 'enrol_payeer'), get_string('defaultrole_desc', 'enrol_payeer'), $student->id, $options));
    }

    $settings->add(new admin_setting_configtext('enrol_payeer/enrolperiod',
        get_string('enrolperiod', 'enrol_payeer'), get_string('enrolperiod_desc', 'enrol_payeer'), 0, PARAM_INT));
}