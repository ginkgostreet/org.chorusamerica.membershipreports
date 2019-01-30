<?php

/**
 * Lifecycle events in this extension will cause these registry records to be
 * automatically inserted, updated, or deleted from the database as appropriate.
 * For more details, see "hook_civicrm_managed" (at
 * https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_managed/) as well
 * as "API and the Art of Installation" (at
 * https://civicrm.org/blogs/totten/api-and-art-installation).
 */
use CRM_Membershipmerge_ExtensionUtil as E;

return array(
  array(
    'module' => E::LONG_NAME,
    'name' => 'CRM_Membershipreports_Form_Member_Event',
    'entity' => 'ReportTemplate',
    'params' => array(
      'class_name' => 'CRM_Membershipreports_Form_Member_Event',
      'component' => 'CiviMember',
      'description' => 'Provides reports for membership lifecycle events such as Conferment, Renewal, Rejoin, and Lapse.',
      'label' => 'Membership Lifecycle Event (Detail)',
      'report_url' => 'member/event',
      'version' => 3,
    ),
  ),
);
