<?php

use CRM_Membershipreports_ExtensionUtil as E;

class CRM_Membershipreports_Form_Member_Event extends CRM_Report_Form_Member_Detail {

  /**
   * @var array
   *   Details of the "Abandoned" membership status as returned by
   *   api.MembershipStatus.getsingle.
   */
  protected $abandonedStatus = [];

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

    // Contributions are not part of this report, as they cannot be reliably
    // tied to a specific membership event -- payments link to civicrm_membership,
    // not civicrm_membership_log, and payments and log events need not occur
    // at the same time. Moreover, memberships can have more than one
    // contribution associated with them, and the default SQL for this report
    // joins them such that duplicate rows appear for a single event, which is
    // nonsensical.
    unset($this->_columns['civicrm_contribution']);
    unset($this->_customGroupExtends[array_keys($this->_customGroupExtends, 'Contribution')[0]]);

    try {
      $this->abandonedStatus = civicrm_api3('MembershipStatus', 'getsingle', ['name' => 'Abandoned']);
    }
    catch (Exception $e) {
      $this->abandonedStatus = array();
    }

    // Prepend the Membership Log for visual prominence.
    $this->_columns = $this->getMembershipLogDefinition() + $this->_columns;
  }

  /**
   * Register custom validation callbacks.
   */
  public function addRules() {
    parent::addRules();

    $this->addFormRule([$this, 'validateConfermentSelections']);
    $this->addFormRule([$this, 'validateLifecycleEventSelections']);
  }

  /**
   * Builds the JOIN clause for Abandonment event reports.
   */
  protected function buildAbandonmentJoin() {
    $this->_from .= "
      INNER JOIN (
          SELECT membership_id, MIN(modified_date) AS modified_date
          FROM civicrm_membership_log
          WHERE status_id = {$this->abandonedStatus['id']}
          GROUP BY membership_id
      ) {$this->_aliases['civicrm_membership_log']}
          ON {$this->_aliases['civicrm_membership']}.id =
             {$this->_aliases['civicrm_membership_log']}.membership_id";
  }

  /**
   * Builds the JOIN clause for Conferment event reports.
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
   * Builds the JOIN clause for Lapse event reports.
   */
  protected function buildLapseJoin() {
    $expiredStatusId = civicrm_api3('MembershipStatus', 'getvalue', [
      'name' => 'Expired',
      'return' => 'id',
    ]);

    $this->_from .= "
      INNER JOIN (
          SELECT membership_id, modified_date
          FROM civicrm_membership_log AS currentLog
          WHERE status_id = $expiredStatusId
          AND {$this->selectFromPreviousLog(['status_id'])} <> $expiredStatusId
      ) {$this->_aliases['civicrm_membership_log']}
          ON {$this->_aliases['civicrm_membership']}.id =
             {$this->_aliases['civicrm_membership_log']}.membership_id";
  }

  /**
   * Builds the JOIN clause for Rejoin event reports.
   */
  protected function buildRejoinJoin() {
    $expiredFirst = TRUE;
    $this->buildReupJoin($expiredFirst);
  }

  /**
   * Builds the JOIN clause for Renewal event reports.
   */
  protected function buildRenewalJoin() {
    $expiredFirst = FALSE;
    $this->buildReupJoin($expiredFirst);
  }

  /**
   * Builds the JOIN clause for Reup (Renewal and Rejoin) event reports.
   *
   * @param bool $expiredFirst
   *   To return membership reup events where the membership was allowed to
   *   expire first, set to TRUE. For reups while the membership was still
   *   active (Renewals), set to FALSE.
   */
  protected function buildReupJoin($expiredFirst) {
    $comparisonOp = $expiredFirst ? '=' : '<>';
    $expiredStatusId = civicrm_api3('MembershipStatus', 'getvalue', [
      'name' => 'Expired',
      'return' => 'id',
    ]);

    $this->_from .= "
      INNER JOIN (
          SELECT membership_id, modified_date
          FROM civicrm_membership_log AS currentLog
          WHERE end_date > {$this->selectFromPreviousLog(['end_date'])}
          AND {$this->selectFromPreviousLog(['status_id'])} $comparisonOp $expiredStatusId
      ) {$this->_aliases['civicrm_membership_log']}
          ON {$this->_aliases['civicrm_membership']}.id =
             {$this->_aliases['civicrm_membership_log']}.membership_id";
  }

  /**
   * Builds the FROM clause for the report query.
   *
   * Overridden to include a JOIN to the civicrm_membership_log table and to
   * remove a JOIN to civicrm_contribution.
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

    if (!is_callable($this->logJoinBuilder)) {
      throw new \CRM_Core_Exception('Callback for building civicrm_membership_log JOIN clause not found', 0, ['callback' => $this->logJoinBuilder]);
    }
    call_user_func($this->logJoinBuilder);
    $this->joinAddressFromContact();
    $this->joinPhoneFromContact();
    $this->joinEmailFromContact();
  }

  /**
   * @return array
   *   Machine name => human readable label
   */
  protected function getLifecycleEventOptions() {
    $options = [
      'Conferment' => E::ts('Conferment'),
      'Renewal' => E::ts('Renewal'),
      'Rejoin' => E::ts('Rejoin'),
      'Lapse' => E::ts('Lapse'),
    ];

    if (!empty($this->abandonedStatus)) {
      $options['Abandonment'] = E::ts('Abandonment');
    }

    return $options;
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
          'lifecycle_event_type' => [
            'pseudofield' => TRUE, // prevents automatic processing in WHERE builder
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->getLifecycleEventOptions(),
            'title' => E::ts('Type of Membership Lifecycle Event'),
            'type' => CRM_Utils_Type::T_INT,
          ],
          'modified_date' => [
            'operatorType' => CRM_Report_Form::OP_DATE,
            'title' => $modifiedDateLabel,
            'type' => CRM_Utils_Type::T_DATE,
          ],
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
   * Generates a correlated subquery so that comparisons can be made between any
   * log record in a membership's history and the immediately preceding one.
   *
   * @param array $columns
   *   Columns for the subquery to return.
   * @return string
   *   SQL subquery.
   */
  protected function selectFromPreviousLog(array $columns) {
    $selectFields = implode(', ', $columns);

    return "
      (
          SELECT $selectFields
          FROM civicrm_membership_log
          WHERE modified_date < currentLog.modified_date
          AND membership_id = currentLog.membership_id
          ORDER BY modified_date DESC
          LIMIT 1
      )";
  }

  /**
   * Validates the user's selections to ensure that only conferred memberships
   * are considered for the Conferment event, and that conferred memberships are
   * excluded from all other events.
   *
   * @param array $values
   *   User-submitted values to validate.
   * @return mixed
   *   Boolean TRUE if valid, else array of errors [field_key => err_msg]
   */
  public function validateConfermentSelections($values) {
    $errors = [];

    $ownerIdSupplied = (strlen(trim($values['owner_membership_id_value'])) > 0)
        || (strlen(trim($values['owner_membership_id_min'])) > 0)
        || (strlen(trim($values['owner_membership_id_max'])) > 0);
    $membershipIsConferred = ($values['owner_membership_id_op'] === 'nnll') || $ownerIdSupplied;
    $isConfermentEvent = $values['lifecycle_event_type_value'] === 'Conferment';

    if ($isConfermentEvent && !$membershipIsConferred) {
      $errors['lifecycle_event_type_value'] = E::ts('The selection for the Membership Owner ID filter is incompatible with the Conferment event; direct memberships must be excluded.');
    }
    if (!$isConfermentEvent && $membershipIsConferred) {
      $errors['lifecycle_event_type_value'] = E::ts('The selection for the Membership Owner ID filter is incompatible with this event; conferred memberships must be excluded.');
    }

    return empty($errors) ? TRUE : $errors;
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
