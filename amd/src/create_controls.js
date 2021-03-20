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

/**
 *
 * @param {*} distribution
 */
const addSpinners = (distribution) => {
  Log.debug("Adding spinners...");
  Log.debug(distribution);

  if (distribution == "dist_share_same_group") {
    $("tbody")
      .find("[data-group-name]")
      .each(function () {
        $("div#status_col").addClass("spinner-border color");
      });
  } else {
    $("tbody")
      .children()
      .each(function (e) {
        if (distribution != "group_copy") {
          $("#file_" + e).addClass("spinner-border color");
        }
      });
  }
};

const beforeunloadHandler = () => {
  window.addEventListener("beforeunload", (event) => {
    event.preventDefault();
  });
};

export const init = (created, distribution) => {
  Log.debug("mod_googleactivity: initializing mod_googledocs control");
  Log.debug(distribution, created);

  // Its not createdd. Add spinners and block events.
  if (!created) {
    beforeunloadHandler();
    addSpinners(distribution);

    StudentCopyControls.init(distribution);
  }
};
