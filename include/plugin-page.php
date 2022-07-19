<?php
 /**********************************************************************************************************************
 * Copyright 2021 - 2022, Inesonic, LLC
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
 */

namespace Inesonic\Redmine;
    require_once dirname(__FILE__) . '/helpers.php';
    require_once dirname(__FILE__) . '/options.php';

    /**
     * Class that manages options displayed within the WordPress Plugins page.
     */
    class PlugInsPage {
        /**
         * Static method that is triggered when the plug-in is activated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_activated(Options $options) {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_deactivated(Options $options) {}

        /**
         * Constructor
         *
         * \param[in] $plugin_basename    The base name for the plug-in.
         *
         * \param[in] $plugin_name        The user visible name for this plug-in.
         *
         * \param[in] $options            The plug-in options API.
         */
        public function __construct(
                string  $plugin_basename,
                string  $plugin_name,
                Options $options
            ) {
            $this->plugin_basename = $plugin_basename;
            $this->plugin_name = $plugin_name;
            $this->options = $options;

            add_action('init', array($this, 'on_initialization'));
        }

        /**
         * Method that is triggered during initialization to bolt the plug-in settings UI into WordPress.
         */
        public function on_initialization() {
            add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_plugin_page_links'));
            add_action(
                'after_plugin_row_' . $this->plugin_basename,
                array($this, 'add_plugin_configuration_fields'),
                10,
                3
            );

            add_action('wp_ajax_inesonic_redmine_get_settings' , array($this, 'get_settings'));
            add_action('wp_ajax_inesonic_redmine_update_secrets' , array($this, 'update_secrets'));
            add_action('wp_ajax_inesonic_redmine_update_settings' , array($this, 'update_settings'));
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_page_links(array $links) {
            $configuration = "<a href=\"###\" id=\"inesonic-redmine-configure-link\">" .
                               __("Configure", 'inesonic-redmine') .
                             "</a>";
            array_unshift($links, $configuration);

            return $links;
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_configuration_fields(string $plugin_file, array $plugin_data, string $status) {
            echo '<tr id="inesonic-redmine-configuration-area-row"
                      class="inesonic-redmine-configuration-area-row inesonic-row-hidden">
                    <th></th> .
                    <td class="inesonic-redmine-configuration-area-column" colspan="3">
                      <table class="inesonic-redmine-configuration-table"><tbody>
                        <tr>
                          <td class="inesonic-redmine-configuration-table-label">' .
                            __("Redmine API Key:", 'inesonic-redmine') . '
                          </td>
                          <td>
                            <input type="text"
                                   placeholder="<current key not shown>"
                                   class="inesonic-redmine-api-key-input"
                                   id="inesonic-redmine-api-key"/>
                          </td>
                          <td>
                            <div class="inesonic-redmine-button-wrapper">
                              <a id="inesonic-redmine-configure-submit-secrets-button"
                                 class="button action inesonic-redmine-button-anchor"
                              >' .
                                __("Submit", 'inesonic-redmine') . '
                              </a>
                            </div>
                          </td>
                        </tr>
                        </tr><td colspan="3"><hr/></td</tr>
                        <tr>
                          <td class="inesonic-redmine-configuration-table-label">' .
                            __("Redmine URL:", 'inesonic-redmine') . '
                          </td>
                          <td colspan="2">
                            <input type="text"
                                   class="inesonic-redmine-input"
                                   id="inesonic-redmine-url"/>
                          </td>
                        </tr>
                        <tr>
                          <td class="inesonic-redmine-configuration-table-label">' .
                            __("Email Template Directory:", 'inesonic-redmine') . '
                          </td>
                          <td colspan="2">
                            <input type="text"
                                   class="inesonic-redmine-input"
                                   id="inesonic-redmine-template-directory"/>
                          </td>
                        </tr>
                        <tr>
                          <td class="inesonic-redmine-configuration-table-label">' .
                            __("Redmine Customer ID Field Name:", 'inesonic-redmine') . '
                          </td>
                          <td colspan="2">
                            <input type="text"
                                   class="inesonic-redmine-input"
                                   id="inesonic-redmine-customer-id-field"/>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="3">
                            <label>' . __("NinjaForms -> Redmine Settings", 'inesonic-redmine') . '
                              <br/>
                              <textarea placeholder="' . __("Enter YAML settings", 'inesonic-redmine') . '"
                                        class="inesonic-redmine-textarea"
                                        id="inesonic-redmine-settings"
                              ></textarea>
                            </label>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="3">
                            <div class="inesonic-redmine-button-wrapper">
                              <a id="inesonic-redmine-configure-submit-settings-button"
                                 class="button action inesonic-redmine-button-anchor"
                              >' .
                                __("Submit", 'inesonic-redmine') . '
                              </a>
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>';

            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'inesonic-redmine-plugins-page',
                \Inesonic\Redmine\javascript_url('plugins-page'),
                array('jquery'),
                null,
                true
            );
            wp_localize_script(
                'inesonic-redmine-plugins-page',
                'ajax_object',
                array('ajax_url' => admin_url('admin-ajax.php'))
            );

            wp_enqueue_style(
                'inesonic-redmine-styles',
                \Inesonic\Redmine\css_url('inesonic-redmine-styles'),
                array(),
                null
            );
        }

        /**
         * Method that is triggered to get the current Redmine settings.
         */
        public function get_settings() {
            if (current_user_can('activate_plugins')) {
                $response = array(
                    'status' => 'OK',
                    'url' => $this->options->client_url(),
                    'template_directory' => $this->options->template_directory(),
                    'customer_id_field' => $this->options->customer_id_field(),
                    'settings' => $this->options->settings()
                );
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }

        /**
         * Method that is triggered to update the Redmine secrets.
         */
        public function update_secrets() {
            if (current_user_can('activate_plugins') && array_key_exists('key', $_POST)) {
                $key = $_POST['key'];
                $this->options->set_api_key($key);

                $response = array('status' => 'OK');
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }

        /**
         * Method that is triggered to update the Redmine settings.
         */
        public function update_settings() {
            if (current_user_can('activate_plugins')           &&
                array_key_exists('url', $_POST)                &&
                array_key_exists('template_directory', $_POST) &&
                array_key_exists('customer_id_field', $_POST)  &&
                array_key_exists('settings', $_POST)              ) {
                $redmine_url = sanitize_text_field($_POST['url']);
                $template_directory = sanitize_text_field($_POST['template_directory']);
                $customer_id_field = sanitize_text_field($_POST['customer_id_field']);
                $yaml_settings = stripslashes(sanitize_textarea_field($_POST['settings']));

                if (is_dir($template_directory)) {
                    $yaml_error = null;
                    try {
                        $parse_yaml = \Symfony\Component\Yaml\Yaml::parse($yaml_settings);
                    } catch (Exception $e) {
                        $yaml_error = $e->getMessage();
                        $parse_yaml = null;
                    }

                    if ($yaml_error === null) {
                        try {
                            $redmine = new \Redmine\Client\NativeCurlClient(
                                $redmine_url,
                                $this->options->api_key()
                            );
                        } catch (Exception $e) {
                            $redmine = null;
                        }

                        if ($redmine !== null) {
                            try {
                                $project_list = $redmine->getApi('project')->listing();
                            } catch (\Redmine\Exception\ClientException $e) {
                                $project_list = null;
                            }

                            if ($project_list !== null) {
                                $this->options->set_client_url($redmine_url);
                                $this->options->set_template_directory($template_directory);
                                $this->options->set_customer_id_field($customer_id_field);
                                $this->options->set_settings($yaml_settings);

                                $response = array('status' => 'OK');
                            } else {
                                $response = array('status' => 'Failed to get Redmine project listing.');
                            }
                        } else {
                            $response = array('status' => 'Can\'t communicate with server (set API key first)');
                        }
                    } else {
                        $response = array('status' => 'Invalid YAML: ' . $yaml_error);
                    }
                } else {
                    $response = array('status' => 'Template directory does not exist');
                }
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }
    };
