================
inesonic-redmine
================
You can use this plugin to tie your WordPress site to an external Redmine issue
tracking system.  Features include:

* Tight integration with NinjaForms -- Create forms for feature requests,
  customer inquiries, and bugs using NinjaForms.  Form submissions will be
  inserted directly into Redmine.

* File uploads -- Users can upload files in NinjaForms forms that will be
  included in bug reports.

* Customer submissions are referenced by customer ID only to facilitate
  GPRD and CPRA compliance.

* Customer submissions are automatically deleted from Redmine when the customer
  account is purged.  You can prevent this on a per-submission basis by either
  changing the customer ID to 0 in the customer ID custom field or by moving
  the bug or issue to a new Redmine project.

* Generate both customer facing and internally facing emails on issue
  submission.  

* The plugin provides shortcodes allowing you to list known issues within any
  given project for a given customer or for all customers.  You can use this to
  present customers with a list of their active issues or to present a list of
  all reported issues.

This plugin will optionally use the
`Inesonic Logger <https://github.com/tuxidriver/inesonic-logger>` plugin to
report errors in your settings.  Errors are also logged to the WordPress/PHP
error log file.

.. note::

   To make a user friendly bug/issue submission form, you should plan on using
   the NinjaForms Conditional Fields plugin to hide fields that are not
   relevant to each issue category.


Using This Plugin
=================
To use, copy this entire directory into your WordPress plugins directory
and then activate the plugin from the WordPress admin panel.

Once activated, you can use the "Configure" on the WordPress plugins page to
tie this plugin to Redmine.  You'll need to set the Redmine API key, Redmine
server URL, email template directory, the Redmine Customer ID field name, and
the NinjaForms -> Redmine settings.  Each are discussed in more detail below.


Redmine API Key
---------------
From your administrative account on your Redmine server, click on the
"Administration" link on the top of the page.  Create a user that represents
your WordPress site.  Submissions will appear in Redmine as this user.
Reasonable names might be "Customer", "Website", etc.  You should give this
user a difficult password.

Temporarily login as your newly created user.  Click on "My Account" on the
top-right hand side of the page and then click "Show" under "API access key".
Record the access key for use later.

Log into your Redmine administrative account.  Select
Administration -> Settings -> API.  Check the "Enable REST web service"
checkbox.

In WordPress, type or paste the access key into the Redmine API Key field and
click the "Submit" button.  Note that the key will *not* be shown once
submitted.


Redmine URL
-----------
Type in the URL of your Redmine site.


Redmine Customer ID Field Name
------------------------------
Submissions into Redmine must be linked to specific customers.  The Inesonic
Redmine/NinjaForms plugin does this using a custom field.

Log into your administrative Redmine account.  Click "Administration" on the
top of the page and then "Custom fields".

Click on "New custom field" towards the top-right side of the page.  Select
"Issues" to tie the custom field to Redmine issues and click "Next >>".

For "Format" select "Integer".  Enter a nice name for the custom field to be
displayed in Redmine along with a description.

Make the custom field visible to any user.

Lastly, under "Projects" add the custom field to all projects that are tied
to WordPress and NinjaForms via this plugin.

When done, enter the name of the field into the "Redmine Customer ID Field
Name" input but on the WordPress Plugins page discussed above.


Email Template Directory
------------------------
To send out emails, you'll need to create email template files, placing those
files on your website.  Email templates can be placed anywhere that you have
access to.  You can specify a directory where these templates are placed in
this field.  Email templates are discussed in more detail below.


NinjaForms -> Redmine Settings
------------------------------
You should use this text area to define the linkages between NinjaForms and
Redmine.  The linkage is defined using a YAML specification.  YAML is a complex
format for describing data that is designed to be, in theory, easy to read and
write.  For details on YAML, see the `YAML Specification <https://yaml.org/>`.

Below is an example YAML specification for the Inesonic NinjaForms/Redmine
bridge.

.. code-block:: yaml
   :linenos:
      
   type-of-inquiry-field: "type_of_inquiry"
   support:
     internal-subject: "General Support Request"
     customer-subject: "Thank you for contacting us"
     internal-email-template: "support_request_internal.html"
     customer-email-template: "support_request_customer.html"
     internal-email-address: "inquiry@mysite.com"
     text-field: "message"
   privacy:
     internal-subject: "GPDR Privacy Request"
     customer-subject: "Thank you for contacting us"
     internal-email-template: "privacy_request_internal.html"
     customer-email-template: "privacy_request_customer.html"
     internal-email-address: "inquery@mysite.com"
     text-field: "message"
   feature:
     customer-subject: "Thank you for contacting us"
     customer-email-template: "feature_request_customer.html"
     brief-description: "brief_description"
     text-field: "detailed_description"
     category-field: "suggestion_category"
     categories:
       application:
         project: "Application"
         tracker: "Feature Request"
       website:
         project: "Website"
         tracker: "Feature Request"
       documentation:
         project: "Documentation Support"
         tracker: "Feature Request"
     file-uploads-field: "file_upload"
   issue:
     customer-subject: "Thank you for contacting us"
     customer-email-template: "issue_report_customer.html"
     brief-description: "brief_description"
     text-field: "detailed_description"
     category-field: "issue_category"
     categories:
       application:
         project: "Application"
         tracker: "Bug"
         subcategory-field: "application_subcategory"
         subcategories:
           crash: "Crash"
           hangs: "Hang"
           unexpected_behavior: "Unexpected Behavior"
           compiler_error: "Compiler Error"
           math_library: "Math Function"
           operator: "Operator"
           other: "Other"
       website:
         project: "Website"
         tracker: "Bug"
         subcategory-field: "website_subcategory"
         subcategories:
           payment: "Payment System"
           login: "User Account"
           licenses: "License Management"
           password: "Passwords"
           register: "Registration"
           compatibility: "Browser Compatibility"
           other: "Other"
       subscription:
         project: "Subscription"
         tracker: "Customer Issue"
         subcategory-field: "subscription_subcategory"
         subcategories:
           duplicate: "Duplicate"
           key: "Key Rejected"
           terminated: "Incorrect Termination"
           updates: "Updates"
           other: "Other"
       billing:
         project: "Billing"
         tracker: "Customer Issue"
         subcategory-field: "billing_subcategory"
         subcategories:
           cant_pay: "Customer Reported Payment Issue"
           duplicate: "Duplicate Charges"
           fails: "Customer Reported Payment Refused"
           other: "Other"
       documentation:
         project: "Documentation Support"
         tracker: "Bug"
         subcategory-field: "documentation_subcategory"
         subcategories:
           errata: "Errata"
           missing: "Missing Information"
           wording: "Poor Wording"
           other: "Other"
     file-uploads-field: "file_upload"

Note that the indentation is important and you must indent using spaces, not
tabs.

On the very left, you must have a "type-of-inquiry-field".  This field
the NinjaForms field name that indicates the type of inquiry the customer
wishes to make.   All the other un-indented fields represent the specific
value fields taken from the NinjaForms Select field or similar.  Indented
content under each "type-of-inquiry" entry is specific to that entry.

Under each "type-of-inquiry" entry you can have the following settings:

+-------------------------+---------------------------------------------------+
| Type Of Inquiry Setting | Description                                       |
+=========================+===================================================+
| internal-subject        | Use this entry to specify the subject line for    |
|                         | internally directed emails.  Note that you must   |
|                         | also include the "internal-email-template" and    |
|                         | "internal-email-address" settings.                |
+-------------------------+---------------------------------------------------+
| internal-email-template | Use this entry to specify the name of the email   |
|                         | template file to be used.  Note that you must     |
|                         | also include the "internal-subject" and           |
|                         | "internal-email-template" settings.               |
+-------------------------+---------------------------------------------------+
| internal-email-address  | Use this entry to specify the email address to    |
|                         | send internal emails to.   Note that you must     |
|                         | also include the "internal-subject" and           |
|                         | "internal-email-template settings.                |
+-------------------------+---------------------------------------------------+
| customer-subject        | Use this entry to specify the subject for         |
|                         | customer facing emails.  Note that you must also  |
|                         | include the "customer-email-template" setting.    |               
+-------------------------+---------------------------------------------------+
| customer-email-template | Use this entry to specify the template file to be |
|                         | used to generate customer facing emails.  Note    |
|                         | that you must also include the "customer-subject" |
|                         | setting.                                          |
+-------------------------+---------------------------------------------------+
| text-field              | Use this entry to specify the field name of the   |
|                         | NinjaForms field containing a detailed            |
|                         | description entered by the customer.              |
+-------------------------+---------------------------------------------------+
| brief-description       | Use this entry to specify the field name of the   |
|                         | NinjaForms field containing a one line brief      |
|                         | description entered by the customer.              |
+-------------------------+---------------------------------------------------+
| category-field          | You can optionally include this entry to specify  |
|                         | a NinjaForms field used to specify an issue       |
|                         | category.  The field should be a NinjaForms       |
|                         | Select field or similar.  Note that you will also |
|                         | need to include the "categories" setting          |
|                         | discussed below.                                  |
+-------------------------+---------------------------------------------------+
| categories              | You can use this entry to specify settings        |
|                         | specific to each issue category.  The entry       |
|                         | should be a YAML dictionary keyed by the          |
|                         | NinjaForms category field values.                 |
+-------------------------+---------------------------------------------------+
| file-uploads-field      | You can optionally include this field to indicate |
|                         | the NinjaForms File Upload field used to capture  |
|                         | uploaded by the user.                             |
+-------------------------+---------------------------------------------------+

The "category-field" and "categories" field tie the specific type of inquiry to
Redmine and thus must be included to connect the issue to Redmine.  The
"file-uploads-field" is also only meaningful when the issue is tied to Redmine.

The "categories" field ties a set of NinjaForms category field values to
specific Redmine projects where the NinjaForms category maps to a specific
Redmine project.  You can have multiple NinjaForms categories map to the same
Redmine project but you *can-not* map a single NinaForms categories to multiple
Redmine projects.

Each category entry can contain the following sub-entries.

+-------------------+---------------------------------------------------+
| Category Setting  | Description                                       |
+===================+===================================================+
| project           | You can use this entry to specify the name of the |
|                   | Redmine project tied to the issue category.       |
+-------------------+---------------------------------------------------+
| tracker           | You can use this entry to specify the name of the |
|                   | Redmine tracker within the Redmine project for    |
|                   | this issue category.                              |
+-------------------+---------------------------------------------------+
| subcategory-field | You can use this entry to specify the NinjaForms  |
|                   | Select field or similar to map to a Redmine issue |
|                   | category.                                         |
+-------------------+---------------------------------------------------+
| subcategories     | You can use this entry to map each NinjaForms     |
|                   | issue category Select field value to a Redmine    |
|                   | issue category for the project.                   |
+-------------------+---------------------------------------------------+


Email Templates
===============
Email text content is generated using the Symfony\Twig library.  Documentation
can be found at https://twig.symfony.com/.

Below is a simple example:

.. code-block:: html

   <!DOCTYPE html>
   <html dir="ltr" lang="en-us">
     <head>
       <title>Support Request</title>
     </head>
     <body>
       <p>A support request was issue by:</p>
       <table style="border-width: 0px; border-collapse: collapse;">
         <tbody>
           <tr><td>Name:&nbsp;</td></td>{{ display_name }}</td></tr>
           <tr><td>Email:&nbsp;</td><td>{{ email }}</span></td></tr>
           <tr><td>Username:&nbsp;</td><td>{{ username }}</td></tr>
         </tbody>
       </table>
       <hr/>
       <p>{{ message }}</p>
     </body>
   </html>

Note Twig fields such as {{ display_name }} and {{ message }}.  Placing these
fields into your email template allows you to provide user and issue specific
details to emails generated by this plugin.

The following fields are supported:

+-------------------------+-------------------------------------------------+
| Field                   | Provides                                        |
+=========================+=================================================+
| {{ display_name }}      | The customer's display name.                    |
+-------------------------+-------------------------------------------------+
| {{ email }}             | The customer's email address.                   |
+-------------------------+-------------------------------------------------+
| {{ username }}          | The customer's username.                        |
+-------------------------+-------------------------------------------------+
| {{ message }}           | The detailed description or text field content  |
|                         | provided by the customer.                       |
+-------------------------+-------------------------------------------------+
| {{ brief_description }} | The brief description provided by the customer. |
+-------------------------+-------------------------------------------------+
| {{ site_url }}          | The site's top level URL.  You can use this to  |
|                         | link back to site content, include logos, etc.  |
+-------------------------+-------------------------------------------------+

We provide several examples we use at `Inesonic <https://https://inesonic.com>`
in the assets/templates directory.
