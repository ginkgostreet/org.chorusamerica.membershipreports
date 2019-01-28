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

* PHP v5.4+
* CiviCRM v4.7+

## Technical Details

| Event       | Definition | Implementation                                  |
| ----------- | ---------- | ----------------------------------------------- |
| Join        | The act of becoming a direct member for the first time. | Report instance "Membership Join Events last month," based off of core template `CRM_Membershipreports_Form_Report`. The history in `membership_log` isn't necessary to find Join events; the `join_date` ("Member Since" in the user interface) suffices. |

## Known Issues/Limitations

None

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)