<?php

/**
 * Google activity Plugin
 *
 * @since Moodle 3.1
 * @package    mod_googledrive
 * @copyright  2016 Nadav Kavalerchik <nadavkav@gmail.com>
 * @copyright  2009 Dan Poltawski <talktodan@gmail.com> (original work)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/google/lib.php');

class googledrive
{
    /**
     * Google OAuth issuer.
     */
    private $issuer = null;

    /**
     * Google Client.
     * @var Google_Client
     */
    private $client = null;

    /**
     * Google Drive Service.
     * @var Google_Drive_Service
     */
    private $service = null;

    /**
     * Session key to store the accesstoken.
     * @var string
     */
    const SESSIONKEY = 'googledrive_rwaccesstoken';

    /**
     * URI to the callback file for OAuth.
     * @var string
     */
    const CALLBACKURL = '/admin/oauth2callback.php';

    const SCOPES = array(
        \Google_Service_Drive::DRIVE,
        \Google_Service_Drive::DRIVE_APPDATA,
        \Google_Service_Drive::DRIVE_METADATA,
        \Google_Service_Drive::DRIVE_FILE
    );

    const GDRIVEFILETYPE_DOCUMENT = 'document';
    const GDRIVEFILETYPE_PRESENTATION  = 'presentation';
    const GDRIVEFILETYPE_SPREADSHEET = 'spreadsheets';
    const GDRIVEFILETYPE_FOLDER =  'folder';

    // calling mod_url cmid
    private $cmid = null;

    // Author (the teacher) array(type, role, emailAddress, displayName)
    private $author = array();

    // List (array) of students (array)
    private $students = array();

    private $api_key;
    private $referrer;
    private $googleactivityinstance;

    private $batch;


    /**
     * Constructor.
     *
     * @param int $cmid mod_googledrive instance id.
     * @return void
     */
    public function __construct($cmid, $update = false, $students = false, $fromws = false, $loginstudent = false, $googleactivityinstance = null)
    {
        global $CFG;

        $this->cmid = $cmid;
        $this->googleactivityinstance = $googleactivityinstance;

        // Get the OAuth issuer.
        if (!isset($CFG->googleactivity_oauth)) {
            debugging('Google docs OAuth issuer not set globally.');
            return;
        }
        $this->issuer = \core\oauth2\api::get_issuer($CFG->googleactivity_oauth);

        if (!$this->issuer->is_configured() || !$this->issuer->get('enabled')) {
            debugging('Google docs OAuth issuer not configured and enabled.');
            return;
        }

        $this->api_key = (get_config('mod_googleactivity'))->googleactivity_api_key;

        // Get the Google client.
        $this->client = get_google_client();
        $this->client->setScopes(self::SCOPES);
        $this->client->setClientId($this->issuer->get('clientid'));
        $this->client->setClientSecret($this->issuer->get('clientsecret'));
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');
        $this->client->setHostedDomain((get_config('mod_googleactivity'))->domain);

        $returnurl = new moodle_url(self::CALLBACKURL);
        $this->client->setRedirectUri($returnurl->out(false));


        if ($update || $fromws && !$loginstudent) {
            $this->refresh_token();
        }

        if ($students != null) {
            $this->set_students($students);
        }

        $this->service = new Google_Service_Drive($this->client);
        $this->referrer = (get_config('mod_googleactivity'))->referrer;
    }

    /**
     * Returns the access token if any.
     *
     * @return string|null access token.
     */
    protected function get_access_token()
    {
        global $SESSION;
        if (isset($SESSION->{self::SESSIONKEY})) {
            return $SESSION->{self::SESSIONKEY};
        }
        return null;
    }

    /**
     * Store the access token in the session.
     *
     * @param string $token token to store.
     * @return void
     */
    protected function store_access_token($token)
    {
        global $SESSION;
        $SESSION->{self::SESSIONKEY} = $token;
    }

    /**
     * Helper function to refresh access token.
     * The access token set in the session doesn't update
     * the expiration time. By refreshing the token, the error 401
     * is avoided.
     * @return string
     */
    public function refresh_token()
    {
        $accesstoken = json_decode($_SESSION['SESSION']->googledrive_rwaccesstoken);
        //To avoid error in authentication, refresh token.
        $this->client->refreshToken($accesstoken->refresh_token);
        $token = (json_decode($this->client->getAccessToken()))->access_token;

        return $token;
    }

    /**
     * Callback method during authentication.
     *
     * @return void
     */
    public function callback()
    {
        if ($code = required_param('oauth2code', PARAM_RAW)) {
            $this->client->authenticate($code);
            $this->store_access_token($this->client->getAccessToken());
        }
    }

    /**
     * Checks whether the user is authenticate or not.
     *
     * @return bool true when logged in.
     */
    public function check_google_login()
    {
        if ($token = $this->get_access_token()) {
            $this->client->setAccessToken($token);
            return true;
        }
        return false;
    }

    /**
     * Logout.
     *
     * @return void
     */
    public function logout()
    {
        $this->store_access_token(null);
        //return parent::logout();
    }

    /**
     * Return HTML link to Google authentication service.
     *
     * @return string HTML link to Google authentication service.
     */
    public function display_login_button()
    {
        // Create a URL that leaads back to the callback() above function on successful authentication.
        $returnurl = new moodle_url('/mod/googleactivity/oauth2_callback.php');
        $returnurl->param('callback', 'yes');
        $returnurl->param('cmid', $this->cmid);
        $returnurl->param('sesskey', sesskey());

        // Get the client auth URL and embed the return URL.
        $url = new moodle_url($this->client->createAuthUrl());
        $url->param('state', $returnurl->out_as_local_url(false));

        // Create the button HTML.      

        $title = get_string('login', 'repository');
        $link = '<button class="btn-primary btn">' . $title . '</button>';
        $jslink = 'window.open(\'' . $url . '\', \'' . $title . '\', \'width=600,height=800,top=0, left=960\'); return false;';
        $output = '<a href="#" onclick="' . $jslink . '">' . $link . '</a>';

        return $output;
    }

    /**
     * Set author details.
     *
     * @param array $author Author details.
     * @return void
     */
    public function set_author($author = array())
    {
        $this->author = $author;
    }

    /**
     * Set student's details.
     *
     * @param array $students student's details.
     * @return void
     */
    public function set_students($students = array())
    {
        foreach ($students as $student) {
            $this->students[] = $student;
        }
        // Or...
        //$this->students = $students;
    }

    /**
     * Create a new Google drive folder
     * Directory structure: SiteFolder/CourseNameFolder/newfolder
     *
     * @param string $dirname
     * @param array $author
     */
    public function create_folder($dirname, $author = array())
    {
        global $COURSE, $SITE;

        if (!empty($author)) {
            $this->author = $author;
        }

        $sitefolderid = $this->get_file_id($SITE->fullname);
        $rootparent = new Google_Service_Drive_ParentReference();
        $fileproperties = google_filetypes();

        if ($sitefolderid == null) {
            $sitefolder = new \Google_Service_Drive_DriveFile(array(
                'title' => $SITE->fullname,
                'mimeType' => GDRIVEFILETYPE_FOLDER,
                'uploadType' => 'multipart'
            ));

            $sitefolderid = $this->service->files->insert($sitefolder, array('fields' => 'id'));
            $rootparent->setId($sitefolderid->id);
        } else {

            $rootparent->setId($sitefolderid);
        }

        $coursefolderid = $this->get_file_id($COURSE->fullname);

        $courseparent = new Google_Service_Drive_ParentReference();

        // Course folder doesnt exist. Create it inside Site Folder.        
        if ($coursefolderid == null) {
            $coursefolder = new \Google_Service_Drive_DriveFile(array(
                'title' => $COURSE->fullname,
                'mimeType' =>  $fileproperties[self::GDRIVEFILETYPE_FOLDER]['mimetype'],
                'parents' => array($rootparent),
                'uploadType' => 'multipart'
            ));

            $coursedirid = $this->service->files->insert($coursefolder, array('fields' => 'id'));
            $courseparent->setId($coursedirid->id);
        } else {
            $courseparent->setId($coursefolderid);
        }

        // Create the folder with the given name.
        $filemetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' =>  $fileproperties[self::GDRIVEFILETYPE_FOLDER]['mimetype'],
            'parents' => array($courseparent),
            'uploadType' => 'multipart'
        ));

        $customdir = $this->service->files->insert($filemetadata, array('fields' => 'id, createdDate'));

        return array($customdir->id, $customdir->createdDate);
    }


    /**
     * Create a new Google drive file and share it with proper permissions.
     *
     * @param string $docname Google document name.
     * @param int $gfiletype google drive file type. (default: doc. can be doc/presentation/spreadsheet...)
     * @param int $permissiontype permission type. (default: writer)
     * @param array $author Author details.
     * @param array $students student's details.
     * @return  
     */
    public function create_file(
        $docname,
        $gfiletype = self::GDRIVEFILETYPE_DOCUMENT,
        $author = array(),
        $students = array(),
        $parentid = null
    ) {

        if (!empty($author)) {
            $this->author = $author;
        }

        if (!empty($students)) {
            $this->students = $students;
        }

        if ($parentid != null) {
            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentid);
        }

        try {

            $fileproperties = google_filetypes();
            // Create a Google Doc file.
            $fileMetadata = new \Google_Service_Drive_DriveFile(array(
                'title' => $docname,
                'mimeType' =>  $fileproperties[$gfiletype]['mimetype'],
                'content' => '',
                'parents' => array($parent),
                'uploadType' => 'multipart'
            ));

            // In the array, add the attributes you want in the response.
            $file = $this->service->files->insert($fileMetadata, array('fields' => 'id, createdDate, shared, title, alternateLink'));

            if (!empty($this->author)) {
                $this->author['type'] = 'user';
                $this->author['role'] = 'owner';
                $this->insert_permission(
                    $this->service,
                    $file->id,
                    $this->author['emailAddress'],
                    $this->author['type'],
                    $this->author['role']
                );
            }

            $sharedlink = sprintf($fileproperties[$gfiletype]['linktemplate'], $file->id);

            return $file;
        } catch (Exception $ex) {
            throw   $ex;
        }
    }

    public function share_existing_file(stdClass $googleactivity, $author)
    {

        try {

            $fileproperties = google_filetypes();
            $typename = get_file_type_from_string($googleactivity->document_type);
            $fileid = get_file_id_from_url($googleactivity->google_doc_url);

            $file = $this->service->files->get($fileid);

            // Save file in a new folder.            
            list($parentdirid, $createddate) = $this->create_folder($file->title, $author);

            // Set the parent folder.
            // list($parentdirid, $createddate) = $this->create_folder($file->title);

            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentdirid);

            // Make a copy of the original file. This will be the one we use to do the copies or share (same) 
            return array($this->make_copy_to_share($file, $parent), $parentdirid);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * This function makes a copy of the file passed.
     */
    public function make_copy_to_share($file, $parent)
    {
        try {
            // Make a copy of the original file in folder inside the course folder.
            $copiedfile = new \Google_Service_Drive_DriveFile();
            $copiedfile->setTitle($file->title);
            $copiedfile->setParents(array($parent));
            $copy = $this->service->files->copy($file->id, $copiedfile);

            return $copy;
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * Function called by the WS create_students_file.
     * First create (Insert a new file)
     * then give permissions (Inserts a permission for a file )
     *
     */
    public function make_file_copies($students, $data)
    {
        global $DB;
        try {

            $fileproperties = google_filetypes();
            $title = $data->use_document == 'existing' ? ($this->getFile($data->docid))->getTitle() : $data->name;
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());

            list($role, $commenter) = format_permission(($data->permissions));
            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $records = [];
            $status = [];
            $i = 0;

            foreach ($students as $student) {

                $copyname = $title . '_' . $student->studentName . '_' . $student->studentId;
                $file =  new \Google_Service_Drive_DriveFile();
                $file->setTitle($copyname);
                $file->setTitle($copyname);

                if ($data->document_type == 'folder') {
                    $parentRef =  new Google_Service_Drive_ParentReference();
                    $parentRef->setId($data->parentfolderid);
                    $file->setMimeType(GDRIVEFILETYPE_FOLDER);
                    $file->setParents([$parentRef]);
                    $filecopy = $this->service->files->insert($file);
                } else {
                    $filecopy = $this->service->files->copy($data->docid, $file);
                }
                $batch->add($filecopy);

                $studentstat = new \stdClass();
                $studentstat->googleactivityid = $data->id;
                $studentstat->userid = $student->studentId;

                $status[] = $studentstat;
            }


            $results = $batch->execute();
            // New object so it doesn't add to the previous lot.
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $fileids = [];
            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$i];
                    $staux->creation_status = json_encode($result->getErrors());
                    $status[$i] = $staux;
                    $i++;
                } else {
                    $fileids[] = $result->id; // Collect the file ids to use later when giving permission to the teacher.
                    $value = new \stdClass();
                    $value->email = $this->get_user_email($students, $result->title);

                    if (!is_null($value->email)) {

                        if ($commenter) {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'additionalRoles' => ['commenter'],
                                    'value' => $value->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        } else {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'value' => $value->email,
                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        }

                        $entityfiledata = new stdClass();
                        $entityfiledata->userid = end(explode('_', $result->title));
                        $entityfiledata->googleactivityid = $data->id;
                        $entityfiledata->name = $result->title;
                        $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $result->id);;
                        $entityfiledata->permission = $data->permissions;

                        $records[] = $entityfiledata;

                        $staux =  $status[$i];
                        $staux->creation_status = 'OK';
                        $status[$i] = $staux;
                        $i++;
                    }
                }
            }

            $results = $batch->execute();
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->insert_records('google_activity_work_task', $status);
        } catch (Exception $e) {
            throw ($e);
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }

    /**
     * When distribution is share_same, the file to share has been
     * created. The only part left is to give access to the students.
     */
    public function dist_share_same_helper($students, $data)
    {
        global $DB;
        try {

            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());
            list($role, $commenter) = format_permission($data->permissions);
            $records = [];
            $status = [];
            $i = 0;

            foreach ($students as $student) {
                if ($commenter) {

                    $batch->add($this->service->permissions->insert(
                        $data->docid,
                        new Google_Service_Drive_Permission(array(
                            'type' => 'user',
                            'role' => $role,
                            'additionalRoles' => ['commenter'],
                            'value' => $student->studentEmail,
                        ))
                    ),   ['emailMessage' => $emailMessage]);
                } else {

                    $batch->add($this->service->permissions->insert(
                        $data->docid,
                        new Google_Service_Drive_Permission(array(
                            'type' => 'user',
                            'role' => $role,
                            'value' => $student->studentEmail,

                        )),
                        ['emailMessage' => $emailMessage]
                    ));
                }

                $entityfiledata = new stdClass();
                $entityfiledata->userid = $student->studentId;
                $entityfiledata->googleactivityid = $data->id;
                $entityfiledata->name = $data->name;
                $entityfiledata->url = $data->google_doc_url;
                $entityfiledata->permission = $data->permissions;

                $records[] = $entityfiledata;


                $studentstat = new \stdClass();
                $studentstat->googleactivityid = $data->id;
                $studentstat->userid = $student->studentId;

                $status[] = $studentstat;
            }

            $results = $batch->execute();

            foreach ($results as $result) {
                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$i];
                    $staux->creation_status = json_encode($result->getErrors());
                    $status[$i] = $staux;
                    $i++;
                } else {
                    $staux =  $status[$i];
                    $staux->creation_status = 'OK';
                    $status[$i] = $staux;
                    $i++;
                }
            }

            // Give access to teachers in the course.
            foreach ($teachers as $teacher) {

                $batch->add($this->service->permissions->insert(
                    $data->docid,
                    new Google_Service_Drive_Permission(array(
                        'type' => 'user',
                        'role' => 'writer',
                        'value' => $teacher->email,
                    )),
                    ['sendNotificationEmails' => false]
                ));
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->insert_records('google_activity_work_task', $status);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }

    // Make files when distribution is std_copy_group or std_copy_group_grouping.
    public function make_file_copy_for_groups($data, $fromgg = false, $teachers)
    {
        // Make the group's folder. The folder is the parent of the file
        $parentrecords =  $this->make_group_folder($data, $fromgg, $teachers);

        $groupdetails = (!$fromgg) ? get_groups_details_from_json(json_decode($data->group_grouping_json)) : $this->merge_groups($data);
        $records = [];


        foreach ($groupdetails as $g) {
            $groupids[] = $g->id;
        }

        // Get the groups students.
        foreach ($groupids as $groupid) {

            $groupstudents[$groupid] = get_students_from_group($groupid, $teachers); //roups_get_members($groupid, 'u.id, u.firstname, u.lastname, u.email');
        }

        foreach ($parentrecords as $record) {
            $parentref = $record->folder_id;
            list($records[], $status[]) = $this->make_file_copies_for_group_members($groupstudents[$record->group_id], $data, $parentref);
        }

        return [$records, $status];
    }

    /**
     * Function called by the WS create_students_file.
     * First create (Insert a new file.)
     * Second give permissions (Inserts a permission for a file )
     *
     */
    public function make_file_copies_for_group_members($students, $data, $parentfolder)
    {
        global $DB;
        try {

            $fileproperties = google_filetypes();
            $title = $data->use_document == 'existing' ? ($this->getFile($data->docid))->getTitle() : $data->name;
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());

            list($role, $commenter) = format_permission(($data->permissions));
            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $records = [];
            $status = [];
            $j = 0;

            foreach ($students as $i => $student) {
                $copyname = $title . '_' . $student->firstname . '_' . $student->lastname . '_' . $student->id;
                $file =  new \Google_Service_Drive_DriveFile();
                $file->setTitle($copyname);

                $parentref = new Google_Service_Drive_ParentReference();
                $parentref->setId($parentfolder);
                $file->setParents(array($parentref));

                $file->setTitle($copyname);

                if ($data->document_type == 'folder') {
                    $file->setMimeType(GDRIVEFILETYPE_FOLDER);
                    $filecopy = $this->service->files->insert($file);
                } else {
                    $filecopy = $this->service->files->copy($data->docid, $file);
                }
                $batch->add($filecopy);

                $studentstat = new \stdClass();
                $studentstat->googleactivityid = $data->id;
                $studentstat->userid = $student->id;

                $status[] = $studentstat;
            }

            // Create the copies
            $results = $batch->execute();
            // New object so it doesn't add to the previous lot.
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $fileids = [];

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              

                } else {

                    $email = $DB->get_record('user', array('id' => end(explode('_', $result->title))), 'email');
                    $fileids[] = $result->id;
                    if ($email) {

                        if ($commenter) {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'additionalRoles' => ['commenter'],
                                    'value' => $email->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        } else {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'value' => $email->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        }

                        $entityfiledata = new stdClass();
                        $entityfiledata->userid = end(explode('_', $result->title));
                        $entityfiledata->googleactivityid = $data->id;
                        $entityfiledata->name = $result->title;
                        $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $result->id);;
                        $entityfiledata->permission = $data->permissions;

                        $records[] = $entityfiledata;

                        $staux =  $status[$j];
                        $staux->creation_status = 'OK';
                        $status[$j] = $staux;
                        $j++;
                    }
                }
            }

            $results = $batch->execute();

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);

            $DB->insert_records('google_activity_work_task', $status);
        } catch (Exception $e) {
            throw ($e);
        } finally {

            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }

    /**
     * First create folders with group names
     * then create a copy of the file for each group
     * finally give access to the users in the groups.
     * 
     */
    public function dist_share_same_group_helper($data, $teachers)
    {
        global $DB;

        try {

            // Make the group's folder. The folder is the parent of the file
            $parentrecords =  $this->make_group_folder($data, false, $teachers);

            $groupdetails = get_groups_details_from_json(json_decode($data->group_grouping_json));
            $groups = [];
            $groupstudents = [];

            foreach ($groupdetails as $g) {

                $groups[$g->id] = $g->name;
                $groupstudents[$g->id] =  get_students_from_group($g->id, $teachers); // groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
            }

            // Set the files details.
            $fileproperties = google_filetypes();
            $title = $data->use_document == 'existing' ? ($this->getFile($data->docid))->getTitle() : $data->name;
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());

            list($role, $commenter) = format_permission(($data->permissions));
            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $records = [];

            foreach ($parentrecords as $record) {

                $file =  new \Google_Service_Drive_DriveFile();

                $parentref = new Google_Service_Drive_ParentReference();
                $parentref->setId($record->folder_id);
                $file->setParents(array($parentref));

                $copyname = $title . '_' . $groups[$record->group_id] . '_' . $record->group_id; // namefile_namegroup_groupid.
                $file->setTitle($copyname);

                if ($data->document_type == 'folder') {
                    $file->setMimeType(GDRIVEFILETYPE_FOLDER);
                    $filecopy = $this->service->files->insert($file);
                } else {
                    $filecopy = $this->service->files->copy($data->docid, $file);
                }

                $batch->add($filecopy);
            }


            // Create the copies
            $results = $batch->execute();
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $errors = []; //Collect any errors.
            $status = [];
            $j = 0;

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    printf($result);
                    //  $errors [] = $result->message;
                } else {
                    // Get groupid from file name 
                    $groupid = end(explode('_', $result->title));
                    $students = $groupstudents[$groupid];

                    // Permission for each student in the group.
                    foreach ($students as $i => $student) {

                        if ($commenter) {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'additionalRoles' => ['commenter'],
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        } else {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        }

                        $entityfiledata = new stdClass();
                        $entityfiledata->userid = end(explode('_', $result->title));
                        $entityfiledata->googleactivityid = $data->id;
                        $entityfiledata->name = $result->title;
                        $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $result->id);;
                        $entityfiledata->permission = $data->permissions;
                        $entityfiledata->groupid = $groupid;

                        $records[] = $entityfiledata;

                        $studentstat = new \stdClass();
                        $studentstat->googleactivityid = $data->id;
                        $studentstat->userid = $student->id;

                        $status[] = $studentstat;
                    }
                }
            }

            $results = $batch->execute();


            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$j];
                    $staux->creation_status = json_encode($result->getErrors());
                } else {
                    $staux =  $status[$j];
                    $staux->creation_status = 'OK';
                }
                $status[$j] = $staux;
                $j++;
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->insert_records('google_activity_work_task', $status);
        } catch (exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }



    /**
     * 
     */
    public function make_file_copy_for_grouping($data, $teachers)
    {
        // Make the groupings folder. The folder is the parent of the file
        $parentrecords =  $this->make_group_folder($data, false, $teachers);

        $groupingdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
        $records = [];

        foreach ($groupingdetails as $g) {
            $groupingds[] = $g->id;
        }

        // Get the members of each grouping
        foreach ($groupingds as $groupingid) {
            $groupingmembers[$groupingid] = get_students_from_grouping($groupingid, $teachers);
        }

        foreach ($parentrecords as $record) {
            $parentref = $record->folder_id;
            list($records[], $status[]) = $this->make_file_copies_for_group_members($groupingmembers[$record->group_id], $data, $parentref);
        }

        return [$records, $status];
    }

    public function dist_share_same_grouping_helper($data, $teachers)
    {
        global $DB;

        try {

            // Make the group's folder. The folder is the parent of the file
            $parentrecords =  $this->make_group_folder($data, false, $teachers);

            $groupingsdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
            $groupings = [];
            $groupingstudents = [];


            foreach ($groupingsdetails as $g) {

                $groupings[$g->id] = $g->name;
                $groupingstudents[$g->id] =  get_students_from_grouping($g->id, $teachers); //groups_get_grouping_members($g->id,  'u.id, u.firstname, u.lastname, u.email');
            }

            // Set the files details.
            $fileproperties = google_filetypes();
            $title = $data->use_document == 'existing' ? ($this->getFile($data->docid))->getTitle() : $data->name;
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());

            list($role, $commenter) = format_permission(($data->permissions));
            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $records = [];

            foreach ($parentrecords as $record) {

                $file =  new \Google_Service_Drive_DriveFile();

                $parentref = new Google_Service_Drive_ParentReference();
                $parentref->setId($record->folder_id);
                $file->setParents(array($parentref));

                $copyname = $title . '_' . $groupings[$record->group_id] . '_' . $record->group_id; // namefile_namegrouping_groupid.
                $file->setTitle($copyname);
              
                if ($data->document_type == 'folder') {
                    $file->setMimeType(GDRIVEFILETYPE_FOLDER);
                    $filecopy = $this->service->files->insert($file);
                } else {

                    $filecopy = $this->service->files->copy($data->docid, $file);
                }
                $batch->add($filecopy);
            }


            // Create the copies
            $results = $batch->execute();
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $status = [];
            $j = 0;


            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // printf($result); exit;
                    //  $errors [] = $result->message;
                } else {
                    // Get groupid from file name 
                    $groupingid = end(explode('_', $result->title));
                    $students = $groupingstudents[$groupingid];

                    // Permission for each member in the group. Both students and teachers have the same file. No need to process them separately.
                    foreach ($students as $i => $student) {

                        if ($commenter) {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'additionalRoles' => ['commenter'],
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        } else {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        }


                        $studentstat = new \stdClass();
                        $studentstat->googleactivityid = $data->id;
                        $studentstat->userid = $student->id;

                        $status[] = $studentstat;
                    }

                    $entityfiledata = new stdClass();
                    $entityfiledata->googleactivityid = $data->id;
                    $entityfiledata->name = $result->title;
                    $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $result->id);;
                    $entityfiledata->permission = $data->permissions;
                    $entityfiledata->groupingid = $groupingid;

                    $records[] = $entityfiledata;
                }
            }

            $results = $batch->execute();

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$j];
                    $staux->creation_status = json_encode($result->getErrors());
                } else {
                    $staux =  $status[$j];
                    $staux->creation_status = 'OK';
                }
                $status[$j] = $staux;
                $j++;
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->insert_records('google_activity_work_task', $status);
        } catch (exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }

    public function make_file_group_grouping_helper($data, $teachers)
    {
        global $DB;

        try {

            $groupingsdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
            $groupsdetails =  get_groups_details_from_json(json_decode($data->group_grouping_json));
            $ggdetails = array_merge($groupsdetails, $groupingsdetails);

            // Make group and grouping folders.
            list($parentrecords, $groupings) = $this->make_group_grouping_folder($data, $ggdetails, $teachers);

            $groups = [];
            $groupstudents = [];

            foreach ($ggdetails as $g) {

                $groups[$g->id] = $g->name;

                if ($g->type == 'group') {
                    $groupstudents[$g->type . '_' . $g->id] =  get_students_from_group($g->id, $teachers); //groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
                } else {
                    $groupstudents[$g->type . '_' . $g->id] = get_students_from_grouping($g->id, $teachers); //groups_get_grouping_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
                }
            }

            // Set the files details.
            $fileproperties = google_filetypes();
            $title = $data->use_document == 'existing' ? ($this->getFile($data->docid))->getTitle() : $data->name;
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());

            list($role, $commenter) = format_permission(($data->permissions));
            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $records = [];

            foreach ($parentrecords as $record) {

                $file =  new \Google_Service_Drive_DriveFile();

                $parentref = new Google_Service_Drive_ParentReference();
                $parentref->setId($record->folder_id);
                $file->setParents(array($parentref));

                $copyname = $title . '_' . $record->group_id; // namefile_groupid. Because the file is inside of the folder with the group/grouping name.
                $file->setTitle($copyname);

                $filecopy = $this->service->files->copy($data->docid, $file);
                $batch->add($filecopy);
            }

            // Create the copies.
            $results = $batch->execute();
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $status = []; // Collects file creation status.
            $j = 0;
            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // printf($result); exit;
                    //  $errors [] = $result->message;
                } else {
                    // Get groupid from file name .
                    $groupid =  0;
                    $groupingid = 0;
                    // Get the folder name to see if it match a group or grouping
                    $parentfoldername = $this->getParentFolderName($result->id);

                    if (is_grouping($groupings, $parentfoldername)) {
                        $students = $groupstudents['grouping' . '_' . $groupid];
                        $groupingid = end(explode('_', $result->title));
                    } else {
                        $students = $groupstudents['group' . '_' . $groupid];
                        $groupid = end(explode('_', $result->title));
                    }

                    // Permission for each student in the group.
                    foreach ($students as $i => $student) {

                        if ($commenter) {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'additionalRoles' => ['commenter'],
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        } else {

                            $batch->add($this->service->permissions->insert(
                                $result->id,
                                new Google_Service_Drive_Permission(array(
                                    'type' => 'user',
                                    'role' => $role,
                                    'value' => $student->email,

                                )),
                                ['emailMessage' => $emailMessage]
                            ));
                        }

                        $studentstat = new \stdClass();
                        $studentstat->googleactivityid = $data->id;
                        $studentstat->userid = $student->id;

                        $status[] = $studentstat;
                    }

                    $entityfiledata = new stdClass();
                    $entityfiledata->userid = end(explode('_', $result->title));
                    $entityfiledata->googleactivityid = $data->id;
                    $entityfiledata->name = $result->title;
                    $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $result->id);;
                    $entityfiledata->permission = $data->permissions;
                    $entityfiledata->groupid = $groupid;
                    $entityfiledata->groupingid = $groupingid;

                    $records[] = $entityfiledata;
                }
            }

            $results = $batch->execute();

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$j];
                    $staux->creation_status = json_encode($result->getErrors());
                } else {
                    $staux =  $status[$j];
                    $staux->creation_status = 'OK';
                }
                $status[$j] = $staux;
                $j++;
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->update_record('google_activity_work_task', $status);
        } catch (exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }

    public function make_file_group_grouping_folder_helper($data)
    {
        global $DB;
        try {
            $groupingsdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
            $groupsdetails =  get_groups_details_from_json(json_decode($data->group_grouping_json));
            $ggdetails = array_merge($groupsdetails, $groupingsdetails);

            foreach ($ggdetails as $g) {

                if ($g->type == 'group') {
                    $students[] =  groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
                } else {
                    $students[] = groups_get_grouping_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
                }
            }

            $fileproperties = google_filetypes();
            list($role, $commenter) = format_permission(($data->permissions));
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());
            $records = [];

            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            // Permission for each student in the group.
            foreach ($students as $i => $student) {
                foreach ($student as $std) {


                    if ($commenter) {

                        $batch->add($this->service->permissions->insert(
                            $data->docid,
                            new Google_Service_Drive_Permission(array(
                                'type' => 'user',
                                'role' => $role,
                                'additionalRoles' => ['commenter'],
                                'value' => $std->email,

                            )),
                            ['emailMessage' => $emailMessage]
                        ));
                    } else {

                        $batch->add($this->service->permissions->insert(
                            $data->docid,
                            new Google_Service_Drive_Permission(array(
                                'type' => 'user',
                                'role' => $role,
                                'value' => $std->email,

                            )),
                            ['emailMessage' => $emailMessage]
                        ));
                    }

                    $studentstat = new \stdClass();
                    $studentstat->googleactivityid = $data->id;
                    $studentstat->userid = $std->id;

                    $status[] = $studentstat;

                    $entityfiledata = new stdClass();
                    $entityfiledata->userid = $std->id;
                    $entityfiledata->googleactivityid = $data->id;
                    $entityfiledata->name =  $data->name;
                    $entityfiledata->url = sprintf($fileproperties[$data->document_type]['linktemplate'], $data->id);;
                    $entityfiledata->permission = $data->permissions;
                    $records[] = $entityfiledata;
                }
            }


            $results = $batch->execute();
            $j = 0;
            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$j];
                    $staux->creation_status = json_encode($result->getErrors());
                } else {
                    $staux =  $status[$j];
                    $staux->creation_status = 'OK';
                }
                $status[$j] = $staux;
                $j++;
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->update_record('google_activity_work_task', $status);
        } catch (exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }


    public function dist_share_same_group_grouping_herper($data, $teachers)
    {

        global $DB;

        try {

            $groupingsdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
            $groupsdetails =  get_groups_details_from_json(json_decode($data->group_grouping_json));

            $ggdetails = array_merge($groupsdetails, $groupingsdetails);

            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $emailMessage = get_string('emailmessageGoogleNotification', 'googleactivity', $this->set_email_message_content());
            list($role, $commenter) = format_permission($data->permissions);
            $records = [];

            $groupstudents = [];
            $groupingstudents = [];

            foreach ($ggdetails as $g) {

                $groups[$g->id] = $g->name;

                if ($g->type == 'group') {
                    $groupstudents[$g->type . '_' . $g->id] =  get_students_from_group($g->id, $teachers); //groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.email');
                } else {
                    $groupingstudents[$g->type . '_' . $g->id] = get_students_from_grouping($g->id, $teachers);
                }
            }

            $studentsaux = array_merge($groupstudents, $groupingstudents);
            $status = [];
            $j = 0;
            foreach ($studentsaux as $st => $studenting) {
                $groupid = 0;
                $groupingid = 0;
                $typeandid = explode('_', $st);

                if ($typeandid[0] == 'group') {
                    $groupid = $typeandid[1];
                } else {
                    $groupingid = $typeandid[1];
                }

                foreach ($studenting as $student) {
                    $student->groupid = $groupid;
                    $student->groupingid = $groupingid;
                    $students[] = $student;

                    $st = new \stdClass();
                    $st->googleactivityid = $data->id;
                    $st->userid = $student->id;
                    $status[] = $st;
                }
            }

            foreach ($students as $student) {
                if ($commenter) {

                    $batch->add($this->service->permissions->insert(
                        $data->docid,
                        new Google_Service_Drive_Permission(array(
                            'type' => 'user',
                            'role' => $role,
                            'additionalRoles' => ['commenter'],
                            'value' => $student->email,

                        )),
                        ['emailMessage' => $emailMessage]
                    ));
                } else {

                    $batch->add($this->service->permissions->insert(
                        $data->docid,
                        new Google_Service_Drive_Permission(array(
                            'type' => 'user',
                            'role' => $role,
                            'value' => $student->email,

                        )),
                        ['emailMessage' => $emailMessage]
                    ));
                }

                $entityfiledata = new stdClass();
                $entityfiledata->userid = $student->id;
                $entityfiledata->googleactivityid = $data->id;
                $entityfiledata->name = $data->name;
                $entityfiledata->url = $data->google_doc_url;
                $entityfiledata->permission = $data->permissions;
                $entityfiledata->groupid = $student->groupid;
                $entityfiledata->groupingid = $student->groupingid;

                $records[] = $entityfiledata;
            }

            $results = $batch->execute();

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                    // Handle error              
                    $staux =  $status[$j];
                    $staux->creation_status = json_encode($result->getErrors());
                } else {
                    $staux =  $status[$j];
                    $staux->creation_status = 'OK';
                }
                $status[$j] = $staux;
                $j++;
            }

            $DB->insert_records('google_activity_files', $records);
            $data->sharing = 1; // It got here means that at least is being shared with one.
            $DB->update_record('googleactivity', $data);
            $DB->update_record('google_activity_work_task', $status);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$records, $status];
        }
    }


    /***************** HELPER FUNCTIONS *************** */


    /**
     * Create the folder structure in Google drive
     * Example: homework is the file name for groups 1 and 2 in course Math year 7
     * In Drive: (Capital names represent folders)
     *
     *      CGS CONNECT
     *          |
     *      MATH YEAR 7
     *          |
     *       HOMEWORK
     *          |_ GROUP 1_GROUPID
     *          |_ GROUP 2_GROUPID
     *
     * returns an array with the  google drive ids of the folders
     * $fromgg check if the function is called from distribution involving groups and groupings.
     * if its grouping groups a folder are create d based on the group.
     */

    public function make_group_folder($data, $fromgg = false, $teachers)
    {
        global $DB;
        $isgrouping = strpos($data->distribution, 'grouping');

        if ($isgrouping !== false && !$fromgg) {
            // Create the folders with the grouping name.
            $gdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
        } else if ($fromgg) {
            $gdetails = $this->merge_groups($data);
        } else {
            $gdetails = get_groups_details_from_json(json_decode($data->group_grouping_json));
        }

        try {

            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');

            $parentRef = new Google_Service_Drive_ParentReference();
            $parentRef->setId($data->parentfolderid);
            $ids = [];

            foreach ($gdetails as $g) {

                // Create group folders
                $fileMetadata = new \Google_Service_Drive_DriveFile(array(
                    'title' => $g->name . '_' . $g->id,
                    'mimeType' => GDRIVEFILETYPE_FOLDER,
                    'parents' => array($parentRef),
                    'uploadType' => 'multipart'
                ));

                $batch->add($this->service->files->insert($fileMetadata, array('fields' => 'id, title')));
            }

            $results = $batch->execute();
            $ids = [];

            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');

            foreach ($results as $result) {
                if ($result instanceof Google_Service_Exception) {
                } else {
                    $r = new stdClass();
                    $r->group_id = end(explode('_', $result->title));
                    $r->googleactivityid = $data->id;
                    $r->folder_id = $result->id;
                    $DB->insert_record('google_activity_folders', $r);
                    $ids[] = $r;

                    if (
                        $data->distribution == 'std_copy_grouping' || $data->distribution == 'dist_share_same_grouping'
                        || $data->distribution == 'grouping_copy'
                    ) {
                        $groupteachers = get_teachers_from_grouping($r->group_id, $teachers);
                    } else {
                        $groupteachers = get_teachers_from_group($r->group_id, $teachers);
                    }
                    foreach ($groupteachers as $q => $teacher) {
                        $batch->add($this->service->permissions->insert(
                            $r->folder_id,
                            new Google_Service_Drive_Permission(array(
                                'type' => 'user',
                                'role' => 'writer',
                                'value' => $teacher->email,
                            )),
                            ['sendNotificationEmails' => true] // TODO: validar que sea gmail 
                        ));
                    }
                }
            }
            $batch->execute();
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return $ids;
        }
    }

    public function make_group_grouping_folder($data, $ggdetails, $teachers)
    {
        global $DB;

        try {

            $this->service->getClient()->setUseBatch(true);
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');

            $parentRef = new Google_Service_Drive_ParentReference();
            $parentRef->setId($data->parentfolderid);
            $ids = [];

            foreach ($ggdetails as $g) {

                // Create group folders.
                $fileMetadata = new \Google_Service_Drive_DriveFile(array(
                    'title' => $g->name . '_' . $g->id,
                    'mimeType' => GDRIVEFILETYPE_FOLDER,
                    'parents' => array($parentRef),
                    'uploadType' => 'multipart'
                ));
                // Get the groups details to allow teachers to access the group folder.

                if ($g->type == 'group') {
                    $gr = new \stdClass();
                    $gr->groupid = $g->id;
                    $gr->foldername = $g->name . '_' . $g->id;
                    $groupfolderdetails[] = $gr;
                }
                $batch->add($this->service->files->insert($fileMetadata, array('fields' => 'id, title')));
            }

            $results = $batch->execute();
            $ids = [];
            $groupings = [];
            $groupingsdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');

            foreach ($results as $result) {

                if ($result instanceof Google_Service_Exception) {
                } else {

                    $r = new stdClass();
                    $r->group_id = end(explode('_', $result->title));
                    $r->googleactivityid = $data->id;
                    $r->folder_id = $result->id;
                    $DB->insert_record('google_activity_folders', $r);
                    $ids[] = $r;

                    foreach ($groupfolderdetails as $g => $gro) {
                        if ($gro->foldername ==  $result->title) {
                            $groupteachers = get_teachers_from_group($gro->groupid, $teachers);

                            foreach ($groupteachers as $q => $teacher) {
                                $batch->add($this->service->permissions->insert(
                                    $result->id,
                                    new Google_Service_Drive_Permission(array(
                                        'type' => 'user',
                                        'role' => 'writer',
                                        'value' => $teacher->email,
                                    )),
                                    ['sendNotificationEmails' => true] // TODO: validar que sea gmail 
                                ));
                            }
                        }
                    }
                    // Get the groupings to be able to save in DB when given access to users.
                    foreach ($groupingsdetails as $grouping) {
                        $name = $grouping->name . '_' . $grouping->id;
                        if ($name == $result->title) {
                            $gg = new \stdClass();
                            $gg->folderid = $r;
                            $gg->foldername = $name;
                            $gg->groupingid = $grouping->id;
                            $groupings[$grouping->id] = $gg;

                            // Give access to teachers in grouping.
                            $groupingteachers = get_teachers_from_grouping($grouping->id, $teachers);

                            foreach ($groupingteachers as $q => $teacher) {
                                $batch->add($this->service->permissions->insert(
                                    $result->id,
                                    new Google_Service_Drive_Permission(array(
                                        'type' => 'user',
                                        'role' => 'writer',
                                        'value' => $teacher->email,
                                    )),
                                    ['sendNotificationEmails' => true] // TODO: validar que sea gmail 
                                ));
                            }
                        }
                    }
                }
            }
            $resultado = $batch->execute();

            foreach ($resultado as $result) {
                if ($result instanceof Google_Service_Exception) {
                    printf($result);
                }
            }
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->service->getClient()->setUseBatch(false);
            return [$ids, $groupings];
        }
    }

    /**
     * Create a folder in a given parent
     * @param string $dirname
     * @param array $parentid
     * @return string
     */
    public function create_child_folder($dirname, $parents)
    {
        try {
            //code...
        } catch (\Throwable $th) {
            //throw $th;
        }
        // Create the folder with the given name.
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => $parents,
            'uploadType' => 'multipart'
        ));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id'));

        return $customdir->id;
    }

    /**
     * Insert a new permission to a given file
     * @param Google_Service_Drive $service Drive API service instance.
     * @param String $fileId ID of the file to insert permission for.
     * @param String $value User or group e-mail address, domain name or NULL for default" type.
     * @param String $type The value "user", "group", "domain" or "default".
     * @param String $role The value "owner", "writer" or "reader".
     */
    public function insert_permission($service, $fileId, $value, $type, $role, $commenter = false, $is_teacher = false)
    {
        $userPermission = new Google_Service_Drive_Permission();
        $userPermission->setValue($value);
        $userPermission->setRole($role);
        $userPermission->setType($type);

        if ($commenter) {
            $userPermission->setAdditionalRoles(['commenter']);
        }

        try {
            if ($is_teacher) {
                $service->permissions->insert($fileId, $userPermission, array('sendNotificationEmails' => false));
            } else if ($this->googleactivityinstance != null) {
                $emailMessage = get_string(
                    'emailmessageGoogleNotification',
                    'googledocs',
                    //$this->set_email_message_content(),
                );
                return $service->permissions->insert($fileId, $userPermission, array('emailMessage' => $emailMessage));
            } else {
                return $service->permissions->insert($fileId, $userPermission);
            }
        } catch (Exception $e) {
            throw $e;
        }

        return null;
    }



    /**
     * Return the id of a given file
     * @param Google_Service_Drive $service
     * @param String $filename
     * @return String
     */
    public function get_file_id($filename)
    {
        $fileproperties = google_filetypes();
        $foldermimetype = $fileproperties[self::GDRIVEFILETYPE_FOLDER]['mimetype'];

        $p = [
            'q' => "mimeType = '" . $foldermimetype . "' and title = '$filename' and trashed  = false and 'me' in owners",
            'corpus' => 'DEFAULT',
            'maxResults' => 1,
            'fields' => 'items'
        ];

        $result = $this->service->files->listFiles($p);

        foreach ($result as $r) {
            if ($r->title == $filename) {
                return ($r->id);
            }
        }

        return null;
    }

    private function set_email_message_content()
    {
        global $DB;
        $sql = "SELECT id FROM mdl_course_modules WHERE course = :courseid AND instance = :instanceid;";
        $params = ['courseid' => $this->googleactivityinstance->course, 'instanceid' => $this->googleactivityinstance->id];
        $cm = $DB->get_record_sql($sql, $params);
        $url = new moodle_url('/mod/googleactivity/view.php?', ['id' => $cm->id]);
        $a = (object) [
            'url' => $url->__toString(),
        ];
        return $a;
    }

    /**
     *
     * @param String $fileId
     * @return File Resource
     */
    public function getFile($fileId)
    {
        try {

            $file = $this->service->files->get($fileId);
            return $file;
        } catch (Exception $e) {
            var_dump($e);
            throw $e;
        }
    }

    //  Batch request returns results unordered.
    // Need a way to get the user's email to assign the right permisison
    private function get_user_email($students, $filename)
    {
        $id = end(explode('_', $filename)); // Get the user id

        foreach ($students as $student) {

            if ($student->studentId == $id) {
                return $student->studentEmail;
            }
        }

        return null;
    }

    // Helper function when distribution involves groups and groupings.
    private function merge_groups($data)
    {
        global $DB;
        $groupings = get_groupings_details_from_json(json_decode($data->group_grouping_json));
        $groupingids = [];
        $allgroups = [];


        foreach ($groupings as $grouping) {
            $groupingids[] = $grouping->id;
        }

        $groupingids = implode(',', $groupingids);

        $sql = "SELECT groupid, groupings.name, groupings.id AS 'groupingid' 
                FROM mdl_groupings_groups  AS gg
                JOIN mdl_groupings AS groupings ON gg.groupingid = groupings.id 
                WHERE groupingid IN ($groupingids)";
        $groupsingroupings = $DB->get_records_sql($sql);

        foreach ($groupsingroupings as $groupingrouping) {

            $g = new \stdClass();
            $g->name = groups_get_group_name($groupingrouping->groupid);
            $g->id = $groupingrouping->groupid;
            //$g->groupingid = $groupingrouping->groupingid;
            $allgroups[$g->id] = $g;
        }

        $groups = get_groups_details_from_json(json_decode($data->group_grouping_json));

        foreach ($groups as $g) {
            $allgroups[$g->id] = $g;
        }

        return $allgroups;
    }

    /**
     * Get a file's parent details.
     *
     * @param Google_Service_Drive $service Drive API service instance.
     * @param String $fileId ID of the file to print parents for.
     */
    function getParentFolderName($fileId)
    {
        try {

            $parentfoldername = '';
            $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
            $batch->add($this->service->parents->listParents($fileId));

            $results = $batch->execute();

            foreach ($results as $result) {


                if ($result instanceof Google_Service_Exception) {
                    //  $errors [] = $result->message;
                } else {
                    foreach ($result->getItems() as $parent) {

                        $batch = new Google_Http_Batch($this->service->getClient(), true, $this->service->root_url, '/batch/drive/v2');
                        $batch->add($this->service->files->get($parent->getId()));
                        $parentdetails = $batch->execute();

                        foreach ($parentdetails as $parent) {
                            $parentfoldername = $parent->title; // Get the name of the folder, that is the name of the group or grouping.
                        }
                    }
                }
            }
        } catch (Exception $e) {
            throw $e;
        } finally {
            //$this->service->getClient()->setUseBatch(false); Dont close the batch otherwise it will only process one.
            return $parentfoldername;
        }
    }

    // Give access to teachers at folder level. 
    public function permission_for_teachers($distribution, $teachers, $folderid)
    {
        try {
            if ($distribution == 'std_copy'  || $distribution == 'dist_share_same') {
                $this->permission_for_teachers_helper($teachers, $folderid);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function permission_for_teachers_helper($teachers, $folderid)
    {
        foreach ($teachers as $teacher) {

            $this->service->permissions->insert(
                $folderid,
                new Google_Service_Drive_Permission(array(
                    'type' => 'user',
                    'role' => 'writer',
                    'value' => $teacher->email,
                )),
                ['sendNotificationEmails' => true]  // TODO: check if the email is a google account to send notification or not.
            );
        }
    }
}
