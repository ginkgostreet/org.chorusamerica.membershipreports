<?php

use CRM_Membershipreports_ExtensionUtil as E;

class CRM_Membershipreports_Form_Member_Event extends CRM_Report_Form_Member_Detail {

  /**
   * @var callable
   *   The method which will build the JOIN clause for the membership log table.
   */
  protected $logJoinBuilder;

  /**
   * Class constructor.
   */
  public function __construct() {
    // Call the parent method first; we need to modify the product of its work.
    parent::__construct();

    // Prepend the Membership Log for visual prominence.
    $this->_columns = $this->getMembershipLogDefinition() + $this->_columns;
  }

  /**
   * Register custom validation callbacks.
   */
  public function addRules() {
    parent::addRules();

    $this->addFormRule([$this, 'validateLifecycleEventSelections']);
  }

  /**
   * Builds the JOIN clause for conferment event reports.
   */
  protected function buildConfermentJoin() {
    $this->_from .= "
      INNER JOIN (
          SELECT membership_id, MIN(modified_date) AS modified_date
          FROM civicrm_membership_log
          GROUP BY membership_id
      ) {$this->_aliases['civicrm_membership_log']}
          ON {$this->_aliases['civicrm_membership']}.id =
             {$this->_aliases['civicrm_membership_log']}.membership_id";
  }

  /**
   * Builds the FROM clause for the report query.
   *
   * Overridden to include a JOIN to the civicrm_membership_log table.
   */
  public function from() {
    $this->setFromBase('civicrm_contact');
    $this->_from .= "
         {$this->_aclFrom}
               INNER JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_membership']}.contact_id AND {$this->_aliases['civicrm_membership']}.is_test = 0
               LEFT  JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
                          ON {$this->_aliases['civicrm_membership_status']}.id =
                             {$this->_aliases['civicrm_membership']}.status_id ";

    if (is_callable($this->logJoinBuilder)) {
      call_user_func($this->logJoinBuilder);
    }
    $this->joinAddressFromContact();
    $this->joinPhoneFromContact();
    $this->joinEmailFromContact();

    //used when contribution field is selected.
    if ($this->isTableSelected('civicrm_contribution')) {
      $this->_from .= "
             LEFT JOIN civicrm_membership_payment cmp
                 ON {$this->_aliases['civicrm_membership']}.id = cmp.membership_id
             LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                 ON cmp.contribution_id={$this->_aliases['civicrm_contribution']}.id\n";
    }
  }

  /**
   * @return array
   *   Machine name => human readable label
   */
  protected function getLifecycleEventOptions() {
    return [
      'Conferment' => E::ts('Conferment'),
    ];
  }

  /**
   * Get table defintion/report configuration for member log table.
   *
   * @return array
   */
  protected function getMembershipLogDefinition() {
    $modifiedDateLabel = E::ts('Date of Membership Lifecycle Event');
    return [
      'civicrm_membership_log' => array(
        'dao' => \CRM_Member_DAO_MembershipLog::class,
        'fields' => [
          'modified_date' => [
            'required' => TRUE,
            'title' => $modifiedDateLabel,
          ],
        ],
        'filters' => [
          'lifecycle_event_type' => array(
            'pseudofield' => TRUE, // prevents automatic processing in WHERE builder
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->getLifecycleEventOptions(),
            'title' => E::ts('Type of Membership Lifecycle Event'),
            'type' => CRM_Utils_Type::T_INT,
          )
        ],
        'order_bys' => array(
          'modified_date' => array(
            'title' => $modifiedDateLabel,
            'default' => 1,
            'default_weight' => 0,
            'default_order' => 'DESC',
          ),
        ),
      )
    ];
  }

  /**
   * Pre process function.
   *
   * Called prior to building the form.
   *
   * Note that the form is built even when the form is submitted (i.e., you can
   * be assured that if postProcess fires, preProcess has already fired).
   *
   * The purpose of this override is to ensure the setting of whatever
   * additional filters are required to target conferred memberships when the
   * user selects conferment events as the subject of the report. Doing so here
   * rather than in postProcess() ensures the UI is updated with the forced
   * selections.
   */
  public function preProcess() {
    $event = CRM_Utils_Array::value('lifecycle_event_type_value', $this->_submitValues);
    if ($event === 'Conferment') {
      $this->_submitValues['owner_membership_id_op'] = 'nnll';
      $this->_submitValues['owner_membership_id_value'] = '';
    }
    parent::preProcess();
  }

  /**
   * Validates the user's selections re the type of membership lifecycle event
   * on which to report and sets the callback responsible for building the
   * appropriate JOIN clause.
   *
   * @param array $values
   *   User-submitted values to validate.
   * @return mixed
   *   Boolean TRUE if valid, else array of errors [field_key => err_msg]
   */
  public function validateLifecycleEventSelections($values) {
    $errors = [];
    if ($values['lifecycle_event_type_op'] !== 'eq') {
      $errors['lifecycle_event_type_op'] = E::ts('Operator not supported');
    }
    if (!array_key_exists($values['lifecycle_event_type_value'], $this->getLifecycleEventOptions())) {
      $errors['lifecycle_event_type_value'] = E::ts('Lifecycle event not supported');
    }
    $this->logJoinBuilder = [$this, 'build' . $values['lifecycle_event_type_value'] . 'Join'];

    return empty($errors) ? TRUE : $errors;
  }

}
