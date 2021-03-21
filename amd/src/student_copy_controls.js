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
 * Provides the mod_googleactivity/student_copy_controls module
 *
 * Contains the functionality for distribution types student gets own copy (std_copy)
 * and students share same copy (dist_share_same)
 *
 * @package   mod_googleactivity
 * @category  output
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googleactivity/student_copy_controls
 *
 */

import $ from "jquery";
import * as Log from "core/log";
import Ajax from "core/ajax";

const getStudentsDetails = () => {
  Log.debug("Collecting students details ...");
  let students = [];

  $("tbody")
    .children()
    .each(function () {
      const studentId = $(this).attr("data-student-id");
      const studentEmail = $(this).attr("data-student-email");
      const studentName = $(this).attr("student-name");
      // Only add elements with values. undefined return false.
      if (studentId && studentEmail && studentName) {
        students.push({
          studentId: studentId,
          studentEmail: studentEmail,
          studentName: studentName,
        });
      }
    });

  return students;
};

const createStudentFileCopyService = (distribution) => {
  Log.debug("In createStudentFileCopyService...");

  Ajax.call([
    {
      methodname: "mod_googleactivity_create_students_files",
      args: {
        students: JSON.stringify(getStudentsDetails()),
        instanceid: $("table.overviewTable").attr("data-instance-id"),
      },
      done: function (response) {
        var records = JSON.parse(response.records);
        renderingHandler(records, distribution);
      },
      fail: function (reason) {
        Log.error(reason);
      },
    },
  ]);
};

const renderingHandler = (records, distribution) => {

  if (distribution == "group_copy" || distribution == "dist_share_same_group") {
    renderShareSameGroup(records, distribution);
  }

  if (distribution == "std_copy" || distribution == "dist_share_same") {
    renderCopyResults(records);
  }

  if (distribution == "std_copy_group" || distribution == "std_copy_grouping") {
    renderCopyGroup(records);
  }

  if (distribution == "dist_share_same_grouping") {
    renderShareSameGrouping(records);
  }
};

/**
 *
 * @param {*} records
 */
const renderCopyResults = (records) => {
  Log.debug("Rendering results ...");
  let i;
  const tablerows = document.querySelectorAll("#table-body tr");

  for (i = 0; i < tablerows.length; i++) {
    let studentId = tablerows[i].getAttribute("data-student-id");
    let record = records.find((record) => record.userid == studentId);

    tablerows[i].querySelector("#link_file_" + i).href = record.url;
    tablerows[i].querySelector("#file_" + i).classList.remove("spinner-border");
    tablerows[i].querySelector("#file_" + i).innerText = "Created";
  }
};

const renderCopyGroup = (records) => {
  Log.debug("In renderCopyGroup...");
  // Combine the results.
  records = records.reduce(function (a, b) {
    return a.concat(b);
  }, []);

  let i;
  const tablerows = document.querySelectorAll("#table-body tr");
  for (i = 0; i < tablerows.length; i++) {
    let studentId = tablerows[i].getAttribute("data-student-id");
    let record = records.find((record) => record.userid == studentId);

    tablerows[i].querySelector("#link_file_" + i).href = record.url;
    tablerows[i].querySelector("#file_" + i).classList.remove("spinner-border");
    tablerows[i].querySelector("#file_" + i).innerText = "Created";
  }
};

const renderShareSameGroup = (records) => {
  Log.debug("In renderShareSameGroup...");
  // Combine the results.
  records = records.reduce(function (a, b) {
    return a.concat(b);
  }, []);

  let i;
  const tablerows = document.querySelectorAll("#table-body tr");

  for (i = 0; i < tablerows.length; i++) {
    if (tablerows[i].hasAttribute("id")) {
      let groupId = tablerows[i].getAttribute("data-group-id");
      let record = records.find((record) => record.groupid == groupId);

      tablerows[i].querySelector("#shared_link_url_" + record.groupid).href =
        record.url;
      tablerows[i]
        .querySelector("#status_col")
        .classList.remove("spinner-border");
      tablerows[i].querySelector("#status_col").innerText = "Created";
    }
  }
};

const renderShareSameGrouping = (records) => {
  Log.debug("In renderShareSameGrouping...");
  // Combine the results.
  records = records.reduce(function (a, b) {
    return a.concat(b);
  }, []);
  Log.debug(records); 
  let i;
  const tablerows = document.querySelectorAll("#table-body tr");

  for (i = 0; i < tablerows.length; i++) {

    if (tablerows[i].hasAttribute("id")) {
      let groupingId = tablerows[i].getAttribute("data-grouping-id");
      let record = records.find((record) => record.groupingid == groupingId);

      tablerows[i].querySelector("#shared_link_url_" + record.groupingid).href =
        record.url;
      tablerows[i]
        .querySelector("#status_col_" + groupingId)
        .classList.remove("spinner-border");
      tablerows[i].querySelector("#status_col_" + groupingId).innerText = "Created";
    }
  }
};

export const init = (distribution) => {
  createStudentFileCopyService(distribution);
};
