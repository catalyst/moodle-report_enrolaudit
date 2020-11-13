<a href="https://travis-ci.com/catalyst/moodle-report_enrolaudit">
<img src="https://travis-ci.com/catalyst/moodle-report_enrolaudit.svg?branch=master">
</a>

Moodle Enrol Audit Report
=====================================

* [What is this?](#what-is-this)
* [How does it work?](#how-does-it-work)
* [Features](#features)
* [Branches](#branches)
* [Installation](#installation)
* [Support](#support)

What is this?
-------------

This plugin enables tracking the history of learner enrolments. 

How does it work?
-----------------

Following installation, the plugin will go over all of the historic learner enrolment
data available in the database to populate the initial report data. 

After that, the plugin has an observer setup to watch for the events 

`user_enrolment_created` `user_enrolment_updated` `user_enrolment_deleted`

All data that is relevant to the report gets stored in a new DB table `report_enrolaudit`.
This lets us take a snapshot of the user enrolment, as Moodle does not provide information
on what changes in an enrolment update, neither in the log tables or in the enrolment
events.

Features
--------

* Site and course level use enrolment history 
* Filtering against users and courses
* Exporting of data

Branches
--------

| Moodle version    | Branch      | PHP  |
| ----------------- | ----------- | ---- |
| Moodle 3.5 to 3.8 | master      | 7.0+ |
| Moodle 3.9        | master      | 7.2+ |

Installation
------------

1. Use git to clone it into your source:

   ```sh
   git clone git@github.com:catalyst/moodle-report_enrolaudit.git report/enrolaudit
   ```

2. Then run the Moodle upgrade.

3. Wait for the cron to run, or manually run the cron if necessary.

When the plugin has installed the reports will be accessible from Site Administration -> 
Reports for the site level report, or within Course Administration -> Reports for the
course level reports.

Support
-------

If you have issues please log them in github here

https://github.com/catalyst/moodle-report_enrolaudit/issues

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/master/pix/catalyst-logo.svg" width="400">
