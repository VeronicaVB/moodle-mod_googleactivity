// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides the mod_googleactivity/control module
 *
 * @package   mod_googleactivity
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googleactivity/processing_controls
 */
define(["jquery", "core/log", "core/str"], function($, Log, str) {
  "use strict";

  /**
   * Initializes the block controls.
   */
  function init() {

    Log.debug("mod_googleactivity/processing_control ...");

    var saveAndDisplay = "#id_submitbutton";
    var selectGroup = "#id_groups";
    var selectDistribution = "#id_distribution";

    var control = new googleactivityProcessingControl(
      saveAndDisplay,
      selectGroup,
      selectDistribution
    );
    control.main();
  }

  // Constructor.
  function googleactivityProcessingControl(
    saveAndDisplay,
    selectGroup,
    selectDistribution
  ) {
    var self = this;
    self.saveAndDisplay = saveAndDisplay;
    self.selectGroup = selectGroup;
    self.selectDistribution = selectDistribution;
  }
  googleactivityProcessingControl.prototype.main = function() {
    var self = this;
    $(self.selectGroup)
      .find("option[value = 0_grouping]")
      .attr("disabled", "disabled"); // Disable the grouping title in the select.
    self.processingMessageDisplay();
    self.change();
  };

  googleactivityProcessingControl.prototype.processingMessageDisplay = function() {
    // Handle submit click.
    var self = this;
    $(self.saveAndDisplay).on("click", function() {
      $(
        "<div class='d-flex flex-column align-items-center justify-content-center overlay'>" +
          "<div class='spinner-border processing' role='status'>" +
          "<span class='sr-only'>Loading...</span>" +
          "</div>" +
          "<div class = 'process_message'>\n\
                   <p>Saving to Google Drive. <br>\n\
                        This process can take some time.<br> \n\
                        Please do not close the browser.</p>\n\
                    </div></div>"
      ).appendTo("#page-content");
    });
  };

  googleactivityProcessingControl.prototype.change = function() {
    var self = this;

    $(self.selectDistribution).change(function() {
      var optionSelected = $(this).find("option:selected");
      var valueSelected = optionSelected.val();

      if (valueSelected === "group_copy") {
        $("#id_groups option:eq(0)").attr("disabled", "disabled");
        $("#id_groups option:eq(0)").hide();
      } else if (
        valueSelected === "std_copy" ||
        valueSelected === "dist_share_same"
      ) {
        $("#id_groups option:eq(0)").removeAttr("disabled");
        $("#id_groups option:eq(0)").show();
      }
    });
  };

  return {
    init: init,
  };
});
