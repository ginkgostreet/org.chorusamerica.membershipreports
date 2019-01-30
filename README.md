# Membership Reports

Membership Reports (org.chorusamerica.membershipreports) is an extension for
[CiviCRM](https://civicrm.org) which provides reports for membership lifecycle
events, as defined below.

![Screenshot](/images/screenshot.png)

## Installation

This extension has not yet been published for in-app installation. [General
extension installation instructions](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension)
are available in the CiviCRM System Administrator Guide.

## Requirements

* PHP v5.5+
* CiviCRM v4.7+

## Technical Details

| Event       | Definition | Implementation                                  |
| ----------- | ---------- | ----------------------------------------------- |
| Join        | The act of becoming a direct member for the first time. | Report instance "Membership Join Events last month," based off of core template `CRM_Membershipreports_Form_Report`. The history in `civicrm_membership_log` isn't necessary to find Join events; the `join_date` ("Member Since" in the user interface) suffices. |
| Conferment  | The act of receiving a conferred membership. | Report template "Membership Lifecycle Event (Detail)" (provided by this extension), with the "Type of Membership Lifecycle Event" filter set to "Conferment." Returns the `civicrm_membership_log` record with the earliest `modified_date` for each membership with a NULL `membership_owner_id`.

## Known Issues/Limitations

None

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)