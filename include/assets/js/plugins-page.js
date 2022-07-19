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
 * \file plugins-page.js
 *
 * JavaScript module that manages Redmine configuration via the WordPress Plug-Ins page.
 */

/***********************************************************************************************************************
 * Functions:
 */

/**
 * Function that displays the manual configuration fields.
 */
function inesonicRedmineToggleConfiguration() {
    let areaRow = jQuery("#inesonic-redmine-configuration-area-row");
    if (areaRow.hasClass("inesonic-row-hidden")) {
        areaRow.prop("class", "inesonic-redmine-configuration-area-row inesonic-row-visible");
    } else {
        areaRow.prop("class", "inesonic-redmine-configuration-area-row inesonic-row-hidden");
    }
}

/**
 * Function that updates the redmine settings fields.
 *
 * \param[in] redmineUrl        The Redmine server URL.
 *
 * \param[in] templateDirectory The Redmine template directory.
 *
 * \param[in] customerIdField   The customer ID field content.
 *
 * \param[in] yamlSettings      The NinjaForms -> Redmine settings (YAML format).
 */
function inesonicRedmineUpdateSettingsFields(redmineUrl, templateDirectory, customerIdField, yamlSettings) {
    jQuery("#inesonic-redmine-url").val(redmineUrl);
    jQuery("#inesonic-redmine-template-directory").val(templateDirectory);
    jQuery("#inesonic-redmine-customer-id-field").val(customerIdField);
    jQuery("#inesonic-redmine-settings").text(yamlSettings);
}

/**
 * Function that is triggered to update the redmine configuration fields.
 */
function inesonicRedmineUpdateSettings() {
    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: { "action" : "inesonic_redmine_get_settings" },
            dataType: "json",
            success: function(response) {
                if (response !== null && response.status == 'OK') {
                    let redmineUrl = response.url;
                    let templateDirectory = response.template_directory
                    let customerIdField = response.customer_id_field;
                    let yamlSettings = response.settings;

                    inesonicRedmineUpdateSettingsFields(redmineUrl, templateDirectory, customerIdField, yamlSettings);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get Redmine settings: " + errorThrown);
            }
        }
    );
}

/**
 * Function that is triggered to update the redmine secrets.
 */
function inesonicRedmineConfigureSecretsSubmit() {
    let apiKey = jQuery("#inesonic-redmine-api-key").val();

    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: {
                "action" : "inesonic_redmine_update_secrets",
                "key" : apiKey
            },
            dataType: "json",
            success: function(response) {
                if (response !== null) {
                    if (response.status == 'OK') {
                        jQuery("#inesonic-redmine-api-key").val("-- updated --");
                    } else {
                        alert("Failed to update Redmine API key:\n" + response.status);
                    }
                } else {
                    alert("Failed to update Redmine API key.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get configuration: " + errorThrown);
            }
        }
    );
}

/**
 * Function that is triggered to update the redmine settings.
 */
function inesonicRedmineConfigureSettingsSubmit() {
    let redmineUrl = jQuery("#inesonic-redmine-url").val();
    let templateDirectory = jQuery("#inesonic-redmine-template-directory").val();
    let customerIdField = jQuery("#inesonic-redmine-customer-id-field").val();
    let yamlSettings = jQuery("#inesonic-redmine-settings").val();

    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: {
                "action" : "inesonic_redmine_update_settings",
                "url" : redmineUrl,
                "template_directory" : templateDirectory,
                "customer_id_field" : customerIdField,
                "settings" : yamlSettings
            },
            dataType: "json",
            success: function(response) {
                if (response !== null) {
                    if (response.status == 'OK') {
                        inesonicRedmineToggleConfiguration();
                    } else {
                        alert("Failed to update Redmine API settings\n" + response.status);
                    }
                } else {
                    alert("Failed to update Redmine API key.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get configuration: " + errorThrown);
            }
        }
    );
}

/***********************************************************************************************************************
 * Main:
 */

jQuery(document).ready(function($) {
    inesonicRedmineUpdateSettings();
    $("#inesonic-redmine-configure-link").click(inesonicRedmineToggleConfiguration);
    $("#inesonic-redmine-configure-submit-secrets-button").click(inesonicRedmineConfigureSecretsSubmit);
    $("#inesonic-redmine-configure-submit-settings-button").click(inesonicRedmineConfigureSettingsSubmit);
});
