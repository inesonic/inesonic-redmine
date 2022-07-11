<?php
/**
 * Plugin Name:       Inesonic NinjaForms -> Redmine bridge
 * Description:       A small plugin that can tie a NinjaForms form to Redmine for issue logging.
 * Version:           1.0.0
 * Author:            Inesonic,  LLC
 * Author URI:        https://inesonic.com
 * License:           GPLv3
 * License URI:
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Text Domain:       inesonic-redmine
 * Domain Path:       /locale
 ***********************************************************************************************************************
 * Copyright 2021-2022, Inesonic, LLC
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 * \file inesonic-redmine.php
 *
 * Main plug-in file.
 */

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/include/options.php";
require_once __DIR__ . "/include/plugin-page.php";

/**
 * Inesonic WordPress plug-in that can tie a NinjaForms form to Redmine.
 */
class InesonicRedmine {
    const VERSION = '1.0.0';
    const SLUG    = 'inesonic-redmine';
    const NAME    = 'Inesonic Redmine';
    const AUTHOR  = 'Inesonic, LLC';
    const PREFIX  = 'InesonicRedmine';

    /**
     * The plug-in template directory
     */
    const DEFAULT_TEMPLATE_DIRECTORY = __DIR__ . '/assets/templates/';

    /**
     * Options prefix.
     */
    const OPTIONS_PREFIX = 'inesonic_redmine';

    /**
     * The customer ID field name.
     */
    const CUSTOMER_ID_FIELD = 'Customer ID';

    /**
     * The singleton class instance.
     */
    private static $instance;  /* Plug-in instance */

    /**
     * Method that is called to initialize a single instance of the plug-in
     */
    public static function instance() {
        if (!isset(self::$instance) && !(self::$instance instanceof InesonicRedmine)) {
            self::$instance = new InesonicRedmine();
        }
    }

    /**
     * Static method that is triggered when the plug-in is activated.
     */
    public static function plugin_activated() {
        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('activate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query(
                    'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_redmine_issues_and_errata' . ' (' .
                        'project VARCHAR(48) NOT NULL,' .
                        'status VARCHAR(255),' .
                        'payload MEDIUMTEXT NOT NULL,' .
                        'PRIMARY KEY (project, status)' .
                    ')'
                );
            }
        }
    }

    /**
     * Static method that is triggered when the plug-in is deactivated.
     */
    public static function plugin_uninstalled() {
        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('deactivate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'inesonic_redmine_issues_and_errata');
            }
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->loader = null;
        $this->twig_template_environment = null;
        $this->redmine_data = null;

        $this->options = new Inesonic\Redmine\Options(self::OPTIONS_PREFIX, self::DEFAULT_TEMPLATE_DIRECTORY);
        $this->plugin_page = new Inesonic\Redmine\PlugInsPage(plugin_basename(__FILE__), self::NAME, $this->options);

        add_action('init', array($this, 'customize_on_initialization'));
        add_action('inesonic-log-issue-to-redmine', array($this, 'log_issue_to_redmine'), 10, 1);

        add_action('edit_user_profile', array($this, 'list_support_requests'), 20, 1);
    }

    /**
     * Method that performs various initialization tasks during WordPress init phase.
     */
    public function customize_on_initialization() {
        add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));

        add_action('inesonic-redmine-purge-defunct-issues', array($this, 'purge_defunct_issues'));
        if (!wp_next_scheduled('inesonic-redmine-purge-defunct-issues')) {
            $time = time() + 20;
            wp_schedule_event($time, 'inesonic-daily', 'inesonic-redmine-purge-defunct-issues');
        }

        add_action('inesonic-redmine-update-issues-and-errata', array($this, 'update_issues_and_errata'));
        if (!wp_next_scheduled('inesonic-redmine-update-issues-and-errata')) {
            $time = time() + 60 * 60;
            wp_schedule_event($time, 'inesonic-every-hour', 'inesonic-redmine-update-issues-and-errata');
        }

        add_shortcode('inesonic_redmine', array($this, 'redmine_shortcode'));
    }

    /**
     * Method that adds custom CRON intervals for testing.
     *
     * \param[in] $schedules The current list of CRON intervals.
     *
     * \return Returns updated schedules with new CRON entries added.
     */
    public function add_custom_cron_interval($schedules) {
        $schedules['inesonic-every-hour'] = array(
            'interval' => 60 * 60,
            'display' => esc_html__('Every hour')
        );

        $schedules['inesonic-daily'] = array(
            'interval' => 60 * 60 * 24 * 2,
            'display' => esc_html__('Every other day')
        );

        return $schedules;
    }

    /**
     * Function that periodically updates our Redmine issues and errata.
     */
    public function update_issues_and_errata() {
        global $wpdb;
        $query_results = $wpdb->get_results(
            'SELECT project,status FROM ' . $wpdb->prefix . 'inesonic_redmine_issues_and_errata'
        );

        foreach ($query_results as $query_result) {
            $project = $query_result->project;
            $statuses = $query_result->status;
            $payload = $this->get_issue_data($project, $statuses);

            $wpdb->update(
                $wpdb->prefix . 'inesonic_redmine_issues_and_errata',
                array('payload' => $payload),
                array('project' => $project, 'status' => $statuses),
                array('%s'),
                array('%s', '%s')
            );
        }
    }

    /**
     * Method that is triggered periodically to purge defunct Redmine issues.  Defunct issues are issues tied to a
     * customer that no longer exists.
     */
    public function purge_defunct_issues() {
        $customer_id_field = trim($this->options->customer_id_field());
        if ($customer_id_field != '') {
            $client_url = $this->options->client_url();
            $api_key = $this->options->api_key();
            $redmine = new \Redmine\Client\NativeCurlClient($client_url, $api_key);

            $issues = $this->redmine_issues();
            $issues_to_purge = array();
            foreach ($issues as $issue_id => $issue) {
                $custom_fields_by_name = $issue['custom_fields_by_name'];
                if (array_key_exists($customer_id_field, $custom_fields_by_name)) {
                    $customer_id = intval($custom_fields_by_name[$customer_id_field]);
                    if ($customer_id != 0) {
                        $user_data = get_user_by('ID', $customer_id);
                        if ($user_data === false || $user_data === null) {
                            $issues_to_purge[] = $issue_id;
                        }
                    }
                }
            }

            foreach ($issues_to_purge as $issue_id) {
                $redmine->getApi('issue')->remove($issue_id);
            }
        }
    }

    /**
     * Method that is triggered to list customer support request information.
     *
     * \param[in] $user_data The WP_User data for the requested user.
     */
    public function list_support_requests($user_data) {
        $issues = $this->redmine_issues();
        $subject_line_starter = "User " . $user_data->ID . ": ";

        echo '<div>
                <h2>Redmine Issues</h2>
                <table>
                  <thead><tr><td>Issue ID</td><td>Project</td><td>Subject Line</td></tr></thead>
                  <tbody>';

        foreach ($issues as $issue_id => $issue) {
            if (str_starts_with($issue['subject'], $subject_line_starter)) {
                echo '<tr>' .
                       '<td>' . $issue_id . '</td>' .
                       '<td>' . $issue['project'] . '</td>' .
                       '<td>' . $issue['subject'] . '</td>' .
                     '</tr>';
            }
        }

        echo '    </tbody>
                </table>
              </div>';
    }

    /**
     * Method that is triggered when an issue is to be logged to Redmine.
     *
     * \param[in] $form_data The NinjaForms form data.
     */
    public function log_issue_to_redmine($form_data) {
        $user_data = wp_get_current_user();
        if ($user_data !== null && $user_data !== false && $user_data->ID != 0) {
            $redmine_settings = $this->redmine_settings();
            $type_of_inquiry_field = $redmine_settings['type-of-inquiry-field'];

            $fields_by_key = $form_data['fields_by_key'];
            $type_of_inquiry = $fields_by_key[$type_of_inquiry_field]['value'];

            if (array_key_exists($type_of_inquiry, $redmine_settings)) {
                $inquiry_settings = $redmine_settings[$type_of_inquiry];

                if (array_key_exists('text-field', $inquiry_settings)) {
                    $text_field = $inquiry_settings['text-field'];
                    $text_field = $fields_by_key[$text_field]['value'];
                } else {
                    $text_field = 'N.A.';
                }

                if (array_key_exists('brief-description', $inquiry_settings)) {
                    $brief_description = $inquiry_settings['brief-description'];
                    $brief_description = $fields_by_key[$brief_description]['value'];
                } else {
                    $brief_description = 'N.A.';
                }

                if (array_key_exists('category-field', $inquiry_settings) &&
                    array_key_exists('categories', $inquiry_settings)        ) {
                    $category_field = $inquiry_settings['category-field'];
                    $categories = $inquiry_settings['categories'];

                    $category_name = $fields_by_key[$category_field]['value'];
                    if (array_key_exists($category_name, $categories)) {
                        $category_data = $categories[$category_name];

                        if (array_key_exists('project', $category_data)) {
                            if (array_key_exists('tracker', $category_data)) {
                                $redmine_project = $category_data['project'];
                                $redmine_tracker = $category_data['tracker'];

                                $redmine_issue_category = null;
                                $valid_configuration = false;
                                if (array_key_exists('subcategory-field', $category_data) &&
                                    array_key_exists('subcategories', $category_data)        ) {
                                    $subcategory_field = $category_data['subcategory-field'];
                                    if (array_key_exists($subcategory_field, $fields_by_key)) {
                                        $subcategory_name = $fields_by_key[$subcategory_field]['value'];
                                        $subcategories = $category_data['subcategories'];
                                        if (array_key_exists($subcategory_name, $subcategories)) {
                                            $redmine_issue_category = $subcategories[$subcategory_name];
                                            $valid_configuration = true;
                                        } else {
                                            self::log_error(
                                                'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\"" .
                                                ' unknown subcategory ' . $subcategory_name
                                            );
                                        }
                                    } else {
                                        self::log_error(
                                            'InesonicRedmine: settings, missing NinjaForms field ' . $subcategory_field
                                        );
                                    }
                                } else if (!array_key_exists('subcategory-field', $category_data) &&
                                           !array_key_exists('subcategories', $category_data)        ) {
                                    $valid_configuration = true;
                                    $redmine_issue_category = null;
                                } else {
                                    self::log_error(
                                        'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\"" .
                                        "missing one of:\n\n" .
                                        "    'subcategory-field', 'subcategories'\n\n" .
                                        "You must include all fields to enable Redmine issue logging."
                                    );
                                }

                                if ($valid_configuration) {
                                    if (array_key_exists('file-uploads-field', $inquiry_settings)) {
                                        $file_uploads_field = $inquiry_settings['file-uploads-field'];
                                        if (array_key_exists($file_uploads_field, $fields_by_key)) {
                                            $uploaded_files = $fields_by_key[$file_uploads_field]['value'];
                                        } else {
                                            $uploads_files = null;
                                            self::log_error(
                                                'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry .
                                                "\" unknown field " . $file_uploads_field
                                            );
                                        }
                                    } else {
                                        $uploaded_files = null;
                                    }

                                    $this->add_to_redmine(
                                        $user_data,
                                        $text_field,
                                        $brief_description,
                                        $redmine_project,
                                        $redmine_tracker,
                                        $redmine_issue_category,
                                        $uploaded_files
                                    );
                                }
                            } else {
                                self::log_error(
                                    'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" " .
                                    "missing 'tracker' field."
                                );
                            }
                        } else {
                            self::log_error(
                                'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" " .
                                "missing 'project' field."
                            );
                        }
                    } else {
                        self::log_error(
                            'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" " .
                            "unknown issue category:\n\n    " . $category_field_content
                        );
                    }
                } else if (array_key_exists('category-field', $inquiry_settings) ||
                           array_key_exists('categories', $inquiry_settings)        ) {
                    self::log_error(
                        'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" missing one of:\n\n" .
                        "    'category-field', 'categories'\n\n" .
                        "You must include all fields or none of the fields to enable Redmine issue logging."
                    );
                }

                if (array_key_exists('internal-subject', $inquiry_settings)        &&
                    array_key_exists('internal-email-template', $inquiry_settings) &&
                    array_key_exists('internal-email-address', $inquiry_settings)     ) {
                    $internal_subject = $inquiry_settings['internal-subject'];
                    $internal_email_template = $inquiry_settings['internal-email-template'];
                    $internal_email_address = $inquiry_settings['internal-email-address'];

                    $this->send_internal_email(
                        $user_data,
                        $internal_email_address,
                        $internal_subject,
                        $internal_email_template,
                        $text_field,
                        $brief_description
                    );
                } else if (array_key_exists('internal-subject', $inquiry_settings)        ||
                           array_key_exists('internal-email-template', $inquiry_settings) ||
                           array_key_exists('internal-email-address', $inquiry_settings)     ) {
                    self::log_error(
                        'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" missing one of:\n\n" .
                        "    'internal-subject', 'internal-email-template', 'internal-email-address'\n\n" .
                        "You must include all fields or none of the fields to enable internal emails."
                    );
                }

                if (array_key_exists('customer-subject', $inquiry_settings)        &&
                    array_key_exists('customer-email-template', $inquiry_settings)    ) {
                    $customer_subject = $inquiry_settings['customer-subject'];
                    $customer_email_template = $inquiry_settings['customer-email-template'];

                    $this->send_customer_email(
                        $user_data,
                        $customer_subject,
                        $customer_email_template,
                        $text_field,
                        $brief_description
                    );
                } else if (array_key_exists('customer-subject', $inquiry_settings)        ||
                           array_key_exists('customer-email-template', $inquiry_settings)    ) {
                    self::log_error(
                        'Inesonic Redmine: settings, type-of-inquiry "' . $type_of_inquiry . "\" missing one of:\n\n" .
                        "    'customer-subject', 'customer-email-template'\n\n" .
                        "You must include all fields or none of the fields to enable customer emails."
                    );
                }

                do_action(
                    "inesonic_add_history",
                    $user_data->ID,
                    'SUPPORT_REQUEST',
                    $type_of_inquiry . " - " . $brief_description
                );
            }
        }
    }

    /**
     * Method that provides a shortcode to list filtered Redmine issues.
     *
     * \param[in] $attributes The shortcode attributes.
     *
     * \return Returns the user's first name.
     */
    public function redmine_shortcode($attributes) {
        if (array_key_exists('project', $attributes)) {
            $project = $attributes['project'];
        } else {
            $project = '';
        }

        if (array_key_exists('status', $attributes)) {
            $statuses = $attributes['status'];
        } else {
            $statuses = '';
        }

        global $wpdb;
        $query_result = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT payload FROM ' . $wpdb->prefix . 'inesonic_redmine_issues_and_errata' . ' WHERE ' .
                    'project = %s AND status = %s',
                $project,
                $statuses
            )
        );

        if ($wpdb->num_rows > 0) {
            $response = $query_result[0]->payload;
        } else {
            $response = $this->get_issue_data($project, $statuses);
            $wpdb->insert(
                $wpdb->prefix . 'inesonic_redmine_issues_and_errata',
                array(
                    'project' => $project,
                    'status' => $statuses,
                    'payload' => $response
                ),
                array(
                    '%s',
                    '%s',
                    '%s'
                )
            );
        }

        $customer_id = get_current_user_id();
        if ($customer_id !== 0 && $customer_id !== null) {
            $response .= '<style>.inesonic-redmine-' . $customer_id . ' { font-style: italic; }</style>';
        }

        return $response;
    }

    /**
     * Method that queries Redmine for issue data.
     *
     * \param[in] $project     The project to obtain information for.  An empty string indicates all projects.
     *
     * \param[in] $statuses    A comma separated list of statuses to obtain information for.  An empty string indicates
     *                         all status conditions.
     *
     * \param[in] $customer_id If not null or 0, then all rows for issues created by this user will be given the
     *                         inesonic-redmine-this-user class.
     *
     * \return Returns the expected Shortcode output.
     */
    private function get_issue_data($project, $statuses) {
        $client_url = $this->options->client_url();
        $api_key = $this->options->api_key();
        $redmine = new \Redmine\Client\NativeCurlClient($client_url, $api_key);

        $request = array();
        $error_message = null;

        if ($project != '') {
            $project_id = $redmine->getApi('project')->getidByName($project);
            $request['project_id'] = $project_id;
        }

        $customer_id_field = trim($this->options->customer_id_field());
        if ($customer_id_field !== '') {
            $customer_id_field_id = $redmine->getApi('custom_fields')->getIdByName($customer_id_field);
        } else {
            $customer_id_field_id = null;
        }

        $issues = array();
        if ($statuses != '') {
            $status_entries = explode(',', $statuses);
            foreach ($status_entries as $status) {
                $status_id = $redmine->getApi('issue_status')->getIdByName(trim($status));
                $request['status_id'] = $status_id;
                $issue_data_this_status = $redmine->getApi('issue')->all($request)['issues'];

                foreach ($issue_data_this_status as $issue_entry) {
                    if (is_array($issue_entry) && array_key_exists('id', $issue_entry)) {
                        $issue_id = $issue_entry['id'];
                        $issues[$issue_id] = $issue_entry;
                    }
                }
            }
        } else {
            $issue_data = $redmine->getApi('issue')->all($request);
            foreach ($issue_data as $issue_entry) {
                if (is_array($issue_entry) && array_key_exists('id', $issue_entry)) {
                    $issue_id = $issue_entry['id'];
                    $issues[$issue_id] = $issue_entry;
                }
            }
        }

        if (count($issues) > 0) {
            $issue_ids = array_keys($issues);
            sort($issue_ids);

            $result = '<table class="inesonic-redmine-issue-table">' .
                        '<thead class="inesonic-redmine-issue-table-header">' .
                          '<tr class="inesonic-redmine-issue-table-header-row">' .
                            '<td class="inesonic-redmine-issue-table-header-id">' .
                              __("Issue ID", 'inesonic-redmine') .
                            '</td>' .
                            '<td class="inesonic-redmine-issue-table-header-created-date">' .
                              __("Created", 'inesonic-redmine') .
                            '</td>' .
                            '<td class="inesonic-redmine-issue-table-header-type">' .
                              __("Type", 'inesonic-redmine') .
                            '<td class="inesonic-redmine-issue-table-header-status">' .
                              __("Status", 'inesonic-redmine') .
                            '</td>' .
                            '<td class="inesonic-redmine-issue-table-header-description">' .
                              __("Description", 'inesonic-redmine') .
                            '</td>' .
                          '</tr>' .
                        '</thead>' .
                        '<tbody class="inesonic-redmine-issue-table-body">';

            foreach ($issue_ids as $issue_id) {
                $issue_data = $issues[$issue_id];
                $status = $issue_data['status']['name'];
                $issue_type = $issue_data['tracker']['name'];
                $subject = $issue_data['subject'];
                $created_datetime = new DateTime($issue_data['created_on']);
                $date_time_str = $created_datetime->format('j M Y H:i:s');

                $custom_fields = array();
                if (array_key_exists('custom_fields', $issue_data)) {
                    foreach ($issue_data['custom_fields'] as $custom_field_data) {
                        $custom_fields[$custom_field_data['id']] = $custom_field_data['value'];
                    }
                }

                if ($customer_id_field_id !== null && array_key_exists($customer_id_field_id, $custom_fields)) {
                    $issue_customer_id = intval($custom_fields[$customer_id_field_id]);
                    $row_classes = 'inesonic-redmine-issue-table-row inesonic-redmine-' . $issue_customer_id;
                } else {
                    $row_classes = 'inesonic-redmine-issue-table-row';
                }

                $row_classes .= ' inesonic-redmine-status-' .
                                preg_replace('/[^a-z0-9]+/', '-', trim(strtolower($status)));

                $result .= '<tr class="' . $row_classes . '">' .
                             '<td class="inesonic-redmine-issue-table-id">' .
                               $issue_id .
                             '</td>' .
                             '<td class="inesonic-redmine-issue-table-created-date">' .
                               esc_html($date_time_str) .
                             '</td>' .
                             '<td class="inesonic-redmine-issue-table-type">' .
                               esc_html($issue_type) .
                             '</td>' .
                             '<td class="inesonic-redmine-issue-table-status">' .
                               esc_html($status) .
                             '</td>' .
                             '<td class="inesonic-redmine-issue-table-description">' .
                               esc_html($subject) .
                             '</td>' .
                           '</tr>';
            }

            $result .=   '</tbody>' .
                       '</table>';
        } else {
            $result = '<p class="inesonic-redmine-no-reported-issues">' .
                        __("No Reported Issues", 'inesonic-redmine') .
                      '</p>';
        }

        return $result;
    }

    /**
     * Method that is triggered when an email needs to be sent to Inesonic.
     *
     * \param[in] $user_data          The WP_User instance of the user making the submission.
     *
     * \param[in] $email_address      The internal email address to send the email to.
     *
     * \param[in] $subject            Email subject line text.
     *
     * \param[in] $template           The name of the Twig template to be used.
     *
     * \param[in] $text_field_content The primary support text field content.
     *
     * \param[in] $brief_description  A brief description added by the user.
     */
    private function send_internal_email(
            $user_data,
            $email_address,
            $subject,
            $template,
            $text_field_content,
            $brief_description
        ) {
        $message = $this->template_environment()->render(
            $template,
            array(
                'display_name' => $user_data->user_displayname,
                'email' => $user_data->user_email,
                'username' => $user_data->user_login,
                'message' => $text_field_content,
                'brief_description' => $brief_description,
                'site_url' => $site_url()
            )
        );

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $success = wp_mail($email_address, $subject, $message, $headers);
        if (!$success) {
            self::log_error(
                "Inesonic Redmine: Failed to send internal email:\n" .
                "User ID:     " . $user_data->ID . "\n" .
                "User Email:  " . $user_data->user_email . "\n" .
                "Description: " . $brief_description . "\n" .
                "Message:\n" . $text_field_content
            );
        }
    }

    /**
     * Method that is triggered when an email needs to be sent to a customer.
     *
     * \param[in] $user_data          The WP_User instance of the user making the submission.
     *
     * \param[in] $subject            Email subject line text.
     *
     * \param[in] $template           The name of the Twig template to be used.
     *
     * \param[in] $text_field_content The primary support text field content.
     *
     * \param[in] $brief_description  A brief description added by the user.
     */
    private function send_customer_email(
            $user_data,
            $subject,
            $template,
            $text_field_content,
            $brief_description
        ) {
        $message = $this->template_environment()->render(
            $template,
            array(
                'display_name' => $user_data->display_name,
                'email' => $user_data->user_email,
                'username' => $user_data->user_login,
                'message' => $text_field_content,
                'brief_description' => $brief_description,
                'site_url' => site_url()
            )
        );

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $success = wp_mail($user_data->user_email, $subject, $message, $headers);
        if (!$success) {
            self::log_error(
                "Inesonic Redmine: Failed to send customer email:\n" .
                "User ID:     " . $user_data->ID . "\n" .
                "User Email:  " . $user_data->user_email . "\n" .
                "Description: " . $brief_description . "\n" .
                "Message:\n" . $text_field_content
            );
        }
    }

    /**
     * Method that is triggered when an entry must be added to Redmine.
     *
     * \param[in] $user_data              The WP_User instance of the user making the submission.
     *
     * \param[in] $text_field_content     The primary support text field content.
     *
     * \param[in] $brief_description      A brief description added by the user.
     *
     * \param[in] $redmine_project        The Redmine project tied to this issue.
     *
     * \param[in] $redmine_tracker        The Redmine tracker to be used.
     *
     * \param[in] $redmine_issue_category The Redmine issue category tied to this issue.
     *
     * \param[in] $uploaded_files         An array of files uploaded by the customer.
     */
    private function add_to_redmine(
            $user_data,
            $text_field_content,
            $brief_description,
            $redmine_project,
            $redmine_tracker,
            $redmine_issue_category,
            $uploaded_files
        ) {
        do_action(
            'inesonic-logger-1',
            "add_to_redmine(" .
                $user_data->ID . ", '" .
                $text_field_content . "', '" .
                $brief_description . "', '" .
                $redmine_project . "', '" .
                $redmine_tracker . "', '" .
                $redmine_issue_category . "', " .
                var_export($uploaded_files, true) .
            ")"
        );

        $client_url = $this->options->client_url();
        $api_key = $this->options->api_key();
        $customer_id_field = trim($this->options->customer_id_field());

        $redmine = new \Redmine\Client\NativeCurlClient($client_url, $api_key);

        $project_id = $redmine->getApi('project')->getIdByName($redmine_project);
        if ($project_id !== false) {
            $tracker_id = $redmine->getApi('tracker')->getIdByName($redmine_tracker);
            if ($tracker_id !== false) {
                $valid_request = true;

                $request = array(
                    'project_id' => $project_id,
                    'tracker_id' => $tracker_id,
                    'subject' => $brief_description,
                    'description' => $text_field_content
                );

                if ($customer_id_field != '') {
                    $customer_id_field_id = $redmine->getApi('custom_fields')->getIdByName($customer_id_field);
                    if ($customer_id_field_id !== false) {
                        $request['custom_fields'] = array(
                            array(
                                'id' => $customer_id_field_id,
                                'name' => $customer_id_field,
                                'value' => $user_data->ID
                            )
                        );
                    } else {
                        $valid_request = false;
                        self::log_error("Inesonic Redmine: Unknown custom field " . $customer_id_field);
                    }
                }

                if ($redmine_issue_category !== null) {
                    $issue_category_id = $redmine->getApi('issue_category')->getIdByName(
                        $project_id,
                        $redmine_issue_category
                    );
                    if ($issue_category_id !== false) {
                        $request['category_id'] = $issue_category_id;
                    } else {
                        $valid_request = false;
                        self::log_error("Inesonic Redmine: Unknown issue category " . $redmine_issue_category);
                    }
                }

                if ($valid_request) {
                    $files_to_delete = array();

                    if ($uploaded_files !== null && $uploaded_files !== '') {
                        if (count($uploaded_files) > 0) {
                            $uploads = array();
                            foreach ($uploaded_files as $file_url) {
                                $local_path = parse_url($file_url)['path'];
                                $filepath = ABSPATH . $local_path;
                                if (is_file($filepath) && is_readable($filepath)) {
                                    $file_contents = file_get_contents($filepath);
                                    if ($file_contents !== false) {
                                        $file_upload = json_decode($redmine->getApi('attachment')->upload($file_contents));
                                        $uploads[] = array(
                                            'token' => $file_upload->upload->token,
                                            'filename' => basename($filepath),
                                            'description' => "User " . $user_data->ID . ": " . basename($filepath),
                                            'content_type' => mime_content_type($filepath)
                                        );

                                        $files_to_delete[] = $filepath;
                                    } else {
                                        self::log_error('Inesonic Redmine: Failed to read file ' . $filepath);
                                    }
                                } else {
                                    self::log_error('Inesonic Redmine: Unable to read file ' . $filepath);
                                }
                            }

                            $request['uploads'] = $uploads;
                        }
                    }

                    $result = $redmine->getApi('issue')->create($request);

                    foreach ($files_to_delete as $filepath) {
                        unlink($filepath);
                    }
                }
            } else {
                self::log_error("Inesonic Redmine: Unknown tracker: " . $redmine_tracker);
            }
        } else {
            self::log_error("Inesonic Redmine: Unknown Redmine project: " . $redmine_project);
        }
    }

    /**
     * Method that returns a list of all Redmine issues generated by this plugin.
     *
     * \return Returns an array of Redmine issues generated by this plugin.  The array is keyed by issue ID and the
     *         supplied value is an array containing the issue subject line, project name, project ID, issue_id and
     *         raw data returned by Redmine.
     */
    private function redmine_issues() {
        $issues = array();

        $client_url = $this->options->client_url();
        $api_key = $this->options->api_key();
        $redmine = new \Redmine\Client\NativeCurlClient($client_url, $api_key);

        $project_names = $this->redmine_projects();
        foreach ($project_names as $project_name) {
            $project_id = $redmine->getApi('project')->getIdByName($project_name);
            $project_issues = $redmine->getApi('issue')->all(array('project_id' => $project_id));

            foreach ($project_issues as $project_issue_data) {
                if (is_array($project_issue_data)) {
                    foreach ($project_issue_data as $project_issue) {
                        if (is_array($project_issue)) {
                            if (array_key_exists('id', $project_issue)) {
                                $issue_id = $project_issue['id'];
                                $issue_subject = $project_issue['subject'];

                                if (array_key_exists('custom_fields', $project_issue)) {
                                    $raw_custom_fields = $project_issue['custom_fields'];
                                    $custom_fields_by_id = array();
                                    $custom_fields_by_name = array();

                                    foreach ($raw_custom_fields as $raw_custom_field) {
                                        $field_id = $raw_custom_field['id'];
                                        $field_name = $raw_custom_field['name'];
                                        $field_value = $raw_custom_field['value'];

                                        $custom_fields_by_id[$field_id] = $field_value;
                                        $custom_fields_by_name[$field_name] = $field_value;
                                    }
                                } else {
                                    $custom_fields_by_id = array();
                                    $custom_fields_by_name = array();
                                }

                                $issues[$issue_id] = array(
                                    'subject' => $issue_subject,
                                    'project' => $project_name,
                                    'project_id' => $project_id,
                                    'issue_id' => $issue_id,
                                    'custom_fields_by_id' => $custom_fields_by_id,
                                    'custom_fields_by_name' => $custom_fields_by_name
                                );
                            }
                        }
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Method that returns a list of Redmine project names referenced by the current settings.
     *
     * \return Returns a list of Redmine project names.
     */
    private function redmine_projects() {
        $projects = array();
        $settings = $this->redmine_settings();
        foreach ($settings as $type_of_inquiry => $inquiry_data) {
            if (is_array($inquiry_data)) {
                if (array_key_exists('categories', $inquiry_data)) {
                    $categories = $inquiry_data['categories'];
                    foreach ($categories as $category_data) {
                        if (array_key_exists('project', $category_data)) {
                            $project_name = $category_data['project'];
                            if (!in_array($project_name, $projects)) {
                                $projects[] = $project_name;
                            }
                        }
                    }
                }
            }
        }

        return $projects;
    }

    /**
     * Method that returns the internal Redmine settings.
     *
     * \return Returns the internal Redmine settings.
     */
    private function redmine_settings() {
        if ($this->redmine_data === null) {
            $this->redmine_data = \Symfony\Component\Yaml\Yaml::parse($this->options->settings());
        }

        return $this->redmine_data;
    }

    /**
     * Method that returns the TWIG template environment.
     *
     * \return Returns the current Twig template environment.
     */
    private function template_environment() {
        if ($this->loader === null || $this->twig_template_environment === null) {
            $this->loader = new \Twig\Loader\FilesystemLoader($this->options->template_directory());
            $this->twig_template_environment = new \Twig\Environment($this->loader);
        }

        return $this->twig_template_environment;
    }

    /**
     * Static method that logs an error.
     *
     * \param[in] $error_message The error to be logged.
     */
    static private function log_error($error_message) {
        error_log($error_message);
        do_action('inesonic-logger-1', $error_message);
    }
}

/* Instatiate our plug-in. */
InesonicRedmine::instance();

/* Define critical global hooks. */
register_activation_hook(__FILE__, array('InesonicRedmine', 'plugin_activated'));
register_uninstall_hook(__FILE__, array('InesonicRedmine', 'plugin_uninstalled'));
