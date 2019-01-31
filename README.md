# Membership Reports

Membership Reports (org.chorusamerica.membershipreports) is an extension for
[CiviCRM](https://civicrm.org) which provides reports for membership lifecycle
events, as defined below.

This extension provides most of its functionality through its "Membership
Lifecycle Event (Detail)" Report Template.

![Screenshot](/images/screenshot.png)

## Installation

This extension has not yet been published for in-app installation. [General
extension installation instructions](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension)
are available in the CiviCRM System Administrator Guide.

## Requirements

* PHP v5.5+
* CiviCRM v4.7+

## Technical Details

Unless otherwise noted, the reporting for each type of membership lifecycle
event is implemented in the report template "Membership Lifecycle Event (Detail)"
(`CRM_Membershipreports_Form_Member_Event`). To specify which type of event is
reported, set the "Type of Membership Lifecycle Event" filter accordingly.
(Note: the "Abandonment" option appears only if such a membership status exists.)

| Event       | Definition | Implementation                                  |
| ----------- | ---------- | ----------------------------------------------- |
| Join        | The act of becoming a direct member for the first time. | Report instance "Membership Join Events last month," based off of core template `CRM_Membershipreports_Form_Report`. The history in `civicrm_membership_log` isn't necessary to find Join events; the `join_date` ("Member Since" in the user interface) suffices. |
| Conferment  | The act of receiving a conferred membership. | Returns the `civicrm_membership_log` record with the earliest `modified_date` for each membership with a non-NULL `membership_owner_id`. |
| Renewal     | The act of renewing (i.e, extending the `end_date` of) a direct membership while it is in a non-expired status. | For each membership with a non-NULL `membership_owner_id`, returns each `civicrm_membership_log` record which has a different `end_date` value than the row immediately preceding it in the membership history, provided the status of the previous row is not "Expired." |
| Rejoin      | The act of becoming a direct member (again) after allowing an existing membership to expire. | For each membership with a non-NULL `membership_owner_id`, returns each `civicrm_membership_log` record which has a different `end_date` value than the row immediately preceding it in the membership history, provided the status of the previous row is "Expired." |
| Lapse       | The act of allowing a direct membership to expire beyond its grace period. | For each membership with a non-NULL `membership_owner_id`, returns each `civicrm_membership_log` record which has a status of "Expired" and a row immediately preceding it in the membership history with a different status. |
| Abandonment | The act of _intentionally_ allowing a direct membership to expire beyond its grace period (e.g., because contact has started an organization through which she now receives conferred membership). | Returns the `civicrm_membership_log` record with the earliest `modified_date` and a status of "Abandoned" for each membership with a NULL `membership_owner_id`. |


## Known Issues/Limitations

None

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)