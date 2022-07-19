<?php
/***********************************************************************************************************************
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
    /**
     * Trivial class that provides an API to plug-in specific options.
     */
    class Options {
        /**
         * A default private key.  Used only if SECURE_AUTH_KEY is not defined.
         */
        const DEFAULT_PRIVATE_KEY = 'r3Y7bR62ptgHje5oIBJf1tqW/k69+LCwAvbQpawNAXs=';

        /**
         * Static method that is triggered when the plug-in is activated.
         */
        public function plugin_activated() {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         */
        public function plugin_deactivated() {}

        /**
         * Static method that is triggered when the plug-in is uninstalled.
         */
        public function plugin_uninstalled() {
            $this->delete_option('client_url');
            $this->delete_option('client_api_key_1');
            $this->delete_option('client_api_key_2');
            $this->delete_option('template_directory');
            $this->delete_option('customer_id_field');
            $this->delete_option('settings');
        }

        /**
         * Constructor
         *
         * \param[in] $options_prefix             The options prefix to apply to plug-in specific options.
         *
         * \param[in] $default_template_directory The default template directory to use.
         */
        public function __construct(string $options_prefix, string $default_template_directory) {
            $this->options_prefix = $options_prefix . '_';
            $this->default_template_directory = $default_template_directory;
        }

        /**
         * Method you can use to obtain the current plugin version.
         *
         * \return Returns the current plugin version.  Returns null if the version has not been set.
         */
        public function version() {
            return $this->get_option('version', null);
        }

        /**
         * Method you can use to set the current plugin version.
         *
         * \param $version The desired plugin version.
         *
         * \return Returns true on success.  Returns false on error.
         */
        public function set_version(string $version) {
            return $this->update_option('version', $version);
        }

        /**
         * Method you can use to obtain the Redmine template directory.
         *
         * \return Returns the path to the Redmine template directory.
         */
        public function template_directory() {
            return $this->get_option('template_directory', $this->default_template_directory);
        }

        /**
         * Method you can use to update the template directory.
         *
         * \param[in] $new_template_directory The new template directory.
         */
        public function set_template_directory(string $new_template_directory) {
            $this->update_option('template_directory', $new_template_directory);
        }

        /**
         * Method you can use to obtain the Redmine customer ID field name.
         *
         * \return Returns the customer ID field name.  An empty string is returned if there is no customer ID field
         *         set.
         */
        public function customer_id_field() {
            return $this->get_option('customer_id_field', '');
        }

        /**
         * Method you can use to update the customer ID field name.
         *
         * \param[in] $new_customer_id_field The new customer ID field name.
         */
        public function set_customer_id_field(string $new_customer_id_field) {
            $this->update_option('customer_id_field', $new_customer_id_field);
        }

        /**
         * Method you can use to obtain the Redmine client URL.
         *
         * \return Returns the path to the Redmine client URL.
         */
        public function client_url() {
            return $this->get_option('client_url', null);
        }

        /**
         * Method you can use to update the client URL.
         *
         * \param[in] $new_url The new client URL.
         */
        public function set_client_url(string $new_url) {
            $this->update_option('client_url', $new_url);
        }

        /**
         * Method you can use to obtain the Redmine API key.
         *
         * \return Returns the path to the Redmine API key.  Null is returned if the encrypted key has not been
         *         defined.
         */
        public function api_key() {
            $encrypted_1 = $this->get_option('client_api_key_1', null);
            $encrypted_2 = $this->get_option('client_api_key_2', null);

            $result = null;

            if ($encrypted_1 !== null && $encrypted_2 !== null) {
                $cipher = base64_decode($encrypted_1);
                $nonce = base64_decode($encrypted_2);

                if ($cipher !== null && $nonce !== null) {
                    $private_key = $this->private_key();
                    $result = sodium_crypto_secretbox_open($cipher, $nonce, $private_key);

                    sodium_memzero($private_key);

                    if ($result === false) {
                        $result = null;
                    }
                }
            }

            return $result;
        }

        /**
         * Method you can use to update the API key.
         *
         * \param[in] $api_key The new API key.
         */
        public function set_api_key(string $api_key) {
            $private_key = $this->private_key();

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted_1 = base64_encode(sodium_crypto_secretbox($api_key, $nonce, $private_key));
            $encrypted_2 = base64_encode($nonce);

            $this->update_option('client_api_key_1', $encrypted_1);
            $this->update_option('client_api_key_2', $encrypted_2);

            sodium_memzero($api_key);
            sodium_memzero($private_key);
        }

        /**
         * Method you can use to obtain the Redmine settings.
         *
         * \return Returns the path to the Redmine settings.
         */
        public function settings() {
            return $this->get_option('settings', null);
        }

        /**
         * Method you can use to update the settings.
         *
         * \param[in] $settings The new settings.
         */
        public function set_settings(string $settings) {
            $this->update_option('settings', $settings);
        }

        /**
         * Method you can use to obtain a specific option.  This function is a thin wrapper on the WordPress get_option
         * function.
         *
         * \param $option  The name of the option of interest.
         *
         * \param $default The default value.
         *
         * \return Returns the option content.  A value of false is returned if the option value has not been set and
         *         the default value is not provided.
         */
        private function get_option(string $option, $default = false) {
            return \get_option($this->options_prefix . $option, $default);
        }

        /**
         * Method you can use to add a specific option.  This function is a thin wrapper on the WordPress update_option
         * function.
         *
         * \param $option The name of the option of interest.
         *
         * \param $value  The value to assign to the option.  The value must be serializable or scalar.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function update_option(string $option, $value = '') {
            return \update_option($this->options_prefix . $option, $value);
        }

        /**
         * Method you can use to delete a specific option.  This function is a thin wrapper on the WordPress
         * delete_option function.
         *
         * \param $option The name of the option of interest.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function delete_option(string $option) {
            return \delete_option($this->options_prefix . $option);
        }

        /**
         * Method that returns a private key for encryption.  Value is generated from the WordPress SECURE_AUTH_KEY
         * value hashed with our default private key.
         *
         * \return Returns a private key used for encryption/decryption.
         */
        private function private_key() {
            if (defined('SECURE_AUTH_KEY')) {
                $result = hash('sha256', SECURE_AUTH_KEY . base64_decode(self::DEFAULT_PRIVATE_KEY));
            } else {
                $result = base64_decode(self::DEFAULT_PRIVATE_KEY);
            }

            if (strlen($result) > SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                $result = substr($result, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            }

            return $result;
        }
    }
