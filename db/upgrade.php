<?php
function xmldb_googleactivity_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021031300) {

        // Define field name to be added to googleactivity.
        $table = new xmldb_table('googleactivity');
        $field_name = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'course');
        $field_use_document = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'course');

        // Conditionally launch add field name.
        if (!$dbman->field_exists($table, $field_name)) {
            $dbman->add_field($table, $field_name);
        }

        $field_use_document = new xmldb_field('use_document', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'create_new', 'name');

        // Conditionally launch add field use_document.
        if (!$dbman->field_exists($table, $field_use_document)) {
            $dbman->add_field($table, $field_use_document);
        }

        $field_intro = new xmldb_field('intro', XMLDB_TYPE_TEXT, null, null, null, null, null, 'use_document');

        // Conditionally launch add field intro.
        if (!$dbman->field_exists($table, $field_intro)) {
            $dbman->add_field($table, $field_intro);
        }

        $field_introformat = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'intro');

        // Conditionally launch add field introformat.
        if (!$dbman->field_exists($table, $field_introformat)) {
            $dbman->add_field($table, $field_introformat);
        }

        $field_google_doc_url = new xmldb_field('google_doc_url', XMLDB_TYPE_CHAR, '256', null, null, null, 'null', 'introformat');

        // Conditionally launch add field google_doc_url.
        if (!$dbman->field_exists($table, $field_google_doc_url)) {
            $dbman->add_field($table, $field_google_doc_url);
        }

        $field_document_type = new xmldb_field('document_type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'google_doc_url');

        // Conditionally launch add field document_type.
        if (!$dbman->field_exists($table, $field_document_type)) {
            $dbman->add_field($table, $field_document_type);
        }

        $field = new xmldb_field('distribution', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'each_gets_own', 'sharing');

        // Conditionally launch add field distribution.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('permissions', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'writer', 'distribution');

        // Conditionally launch add field permissions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('parentfolderid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'docid');

        // Conditionally launch add field parentfolderid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('update_status', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'not_modified', 'parentfolderid');

        // Conditionally launch add field update_status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('group_grouping_json', XMLDB_TYPE_TEXT, null, null, null, null, null, 'update_status');

        // Conditionally launch add field group_grouping_json.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'group_grouping_json');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeshared');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table google_activity_files to be created.
        $table = new xmldb_table('google_activity_files');

        // Adding fields to table google_activity_files.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submit_status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'Not Submitted');
        $table->add_field('permission', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'edit');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('groupingid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table google_activity_files.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for google_activity_files.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table google_activity_folders to be created.
        $table = new xmldb_table('google_activity_folders');

        // Adding fields to table google_activity_folders.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('group_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('folder_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table google_activity_folders.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for google_activity_folders.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table google_activity_work_task to be created.
        $table = new xmldb_table('google_activity_work_task');

        // Adding fields to table google_activity_work_task.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('docid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('creation_status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table google_activity_work_task.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for google_activity_work_task.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table google_activity_grades to be created.
        $table = new xmldb_table('google_activity_grades');

        // Adding fields to table google_activity_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grader', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table google_activity_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for google_activity_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table google_activity_submissions to be created.
        $table = new xmldb_table('google_activity_submissions');

        // Adding fields to table google_activity_submissions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'NOTSUBMITTED');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table google_activity_submissions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for google_activity_submissions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        // Define table googleactivfeedback_comments to be created.
        $table = new xmldb_table('googleactivfeedback_comments');

        // Adding fields to table googleactivfeedback_comments.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('googleactivityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('commenttext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('commentformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table googleactivfeedback_comments.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('googleactivityid', XMLDB_KEY_FOREIGN, ['googleactivityid'], 'googleactivity', ['id']);
        $table->add_key('grade', XMLDB_KEY_FOREIGN, ['grade'], 'google_activity_grades', ['id']);

        // Conditionally launch create table for googleactivfeedback_comments.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Googleactivity savepoint reached.
        upgrade_mod_savepoint(true, 2021031300, 'googleactivity');
    }


    return true;
}
