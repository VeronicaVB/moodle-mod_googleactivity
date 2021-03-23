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
 * Provides the mod_googleactivity/created_control module
 *
 * @package   mod_googleactivity
 * @category  output
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googleactivity/controls
 *
 */

import $ from "jquery";
import * as Log from "core/log";
import * as StudentCopyControls from "mod_googleactivity/student_copy_controls";

// Prevent user from going backwards.
window.onload = window.history.forward();

const addSpinners = (distribution, created) => {
  Log.debug("Adding spinners...");
  Log.debug(distribution);

  if (distribution == "dist_share_same_group" || distribution == "group_copy") {
    spinnerForGroupDistribution();
  } else if (
    distribution == "dist_share_same_grouping" ||
    distribution == "grouping_copy"
  ) {
    spinnerForGroupingDistribution(created);
  } else if (distribution == "group_grouping_copy") {
    spinnerForGroupGroupingCopy();
  } else {
    spinnerForStdDistribution();
  }
};

const beforeunloadHandler = () => {
  window.addEventListener("beforeunload", (event) => {
    event.preventDefault();
  });
};

const spinnerForGroupDistribution = () => {
  $("tbody")
    .find("[data-group-name]")
    .each(function () {
      $("div#status_col").addClass("spinner-border color");
    });
};

const spinnerForGroupingDistribution = (created) => {
  $("tbody")
    .find("[data-grouping-name]")
    .each(function () {
      var gid = $(this).attr("data-grouping-id");
      var statuscol = $(this).find("div#status_col_" + gid);
      if (created) {
        $(statuscol).html("Created");
        $(statuscol).addClass("status-access");
      } else {
        $("div#status_col_" + gid).addClass("spinner-border color");
      }
    });
};

const spinnerForGroupGroupingCopy = (created) => {
  $("tbody")
    .find("[data-g-name]")
    .each(function () {
      var gid = $(this).attr("data-g-id");
      var statuscol = $(this).find("div#status_col_" + gid);
      if (created) {
        $(statuscol).html("Created");
        $(statuscol).addClass("status-access");
      } else {
        $("div#status_col_" + gid).addClass("spinner-border color");
      }
    });
};

const spinnerForStdDistribution = () => {
  $("tbody")
    .children()
    .each(function (e) {
      $("#file_" + e).addClass("spinner-border color");
    });
};

export const init = (created, distribution) => {
  Log.debug("mod_googleactivity: initializing mod_googledocs control");
  Log.debug(distribution, created);

  // Its not created. Add spinners and block events.
  if (!created) {
    beforeunloadHandler();
    addSpinners(distribution, created);

    StudentCopyControls.init(distribution);
  }
};
