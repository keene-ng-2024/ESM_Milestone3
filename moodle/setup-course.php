<?php
/**
 * ESMOS Moodle Course Setup Script
 * Run inside the Moodle container:
 *   php /var/www/html/setup-course.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
global $DB, $USER;

// Build Odoo URL from the same host as Moodle (Odoo runs on port 443, no port suffix)
$_p = parse_url($CFG->wwwroot);
$ODOO_REQUEST_URL = $_p['scheme'] . '://' . $_p['host'] . '/healthcare/request-access';
unset($_p);

// Run as admin
$USER = get_admin();
\core\session\manager::set_user($USER);

echo "=== ESMOS Staff Training Course Setup ===\n\n";

// ─── 1. Create course category ───────────────────────────────────
echo "1. Creating course category...\n";
$cat = $DB->get_record('course_categories', ['name' => 'ESMOS Training']);
if (!$cat) {
    $cat = core_course_category::create([
        'name' => 'ESMOS Training',
        'description' => 'Mandatory staff training courses',
        'parent' => 0,
    ]);
    $catid = $cat->id;
    echo "   Created category ID: $catid\n";
} else {
    $catid = $cat->id;
    echo "   Category already exists (ID: $catid)\n";
}

// ─── 2. Create the course ────────────────────────────────────────
echo "2. Creating Staff Training course...\n";
$course = $DB->get_record('course', ['shortname' => 'STAFFTRAIN01']);
if (!$course) {
    $coursedata = (object)[
        'fullname'          => 'ESMOS Staff Training - Using Odoo',
        'shortname'         => 'STAFFTRAIN01',
        'category'          => $catid,
        'format'            => 'topics',
        'numsections'       => 3,
        'enablecompletion'  => 1,
        'showcompletionconditions' => 1,
        'visible'           => 1,
        'summary'           => '<p>Mandatory training course for all ESMOS staff on using the Odoo 17 platform for daily operations and meal planning.</p>',
        'summaryformat'     => FORMAT_HTML,
    ];
    $course = create_course($coursedata);
    echo "   Created course ID: {$course->id}\n";
} else {
    $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
    echo "   Course already exists (ID: {$course->id})\n";
}

// ─── Helper: add a page activity ─────────────────────────────────
function add_page($course, $name, $section, $content) {
    global $DB, $CFG;

    $existing = $DB->get_record_sql(
        "SELECT cm.id, cm.instance FROM {course_modules} cm
         JOIN {page} m ON m.id = cm.instance
         WHERE cm.course = ? AND m.name = ? AND cm.deletioninprogress = 0",
        [$course->id, $name]
    );
    if ($existing) {
        $DB->set_field('page', 'content', $content, ['id' => $existing->instance]);
        echo "   Page '$name' already exists (content updated)\n";
        return $existing->id;
    }

    $module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'page';
    $moduleinfo->module = $module->id;
    $moduleinfo->name = $name;
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;

    // Page specific
    $moduleinfo->content = $content;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->display = 5;
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 1;

    // Completion: mark complete when viewed
    $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
    $moduleinfo->completionview = 1;
    $moduleinfo->completionexpected = 0;

    // Required fields
    $moduleinfo->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
    $moduleinfo->page = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];

    $result = create_module($moduleinfo);
    echo "   Created page: '$name' (cmid: {$result->coursemodule})\n";
    return $result->coursemodule;
}

// ─── Helper: add an assignment activity ──────────────────────────
function add_assignment($course, $name, $section, $intro) {
    global $DB;

    $existing = $DB->get_record_sql(
        "SELECT cm.id, cm.instance FROM {course_modules} cm
         JOIN {assign} m ON m.id = cm.instance
         WHERE cm.course = ? AND m.name = ? AND cm.deletioninprogress = 0",
        [$course->id, $name]
    );
    if ($existing) {
        $DB->set_field('assign', 'intro', $intro, ['id' => $existing->instance]);
        echo "   Assignment '$name' already exists (intro updated)\n";
        return $existing->id;
    }

    $module = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'assign';
    $moduleinfo->module = $module->id;
    $moduleinfo->name = $name;
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;

    // Assignment specific
    $moduleinfo->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
    $moduleinfo->submissiondrafts = 0;
    $moduleinfo->requiresubmissionstatement = 0;
    $moduleinfo->sendnotifications = 0;
    $moduleinfo->sendlatenotifications = 0;
    $moduleinfo->duedate = 0;
    $moduleinfo->cutoffdate = 0;
    $moduleinfo->gradingduedate = 0;
    $moduleinfo->allowsubmissionsfromdate = 0;
    $moduleinfo->grade = 100;
    $moduleinfo->teamsubmission = 0;
    $moduleinfo->requireallteammemberssubmit = 0;
    $moduleinfo->blindmarking = 0;
    $moduleinfo->markingworkflow = 0;
    $moduleinfo->markingallocation = 0;
    $moduleinfo->assignsubmission_onlinetext_enabled = 1;
    $moduleinfo->assignsubmission_file_enabled = 0;
    $moduleinfo->assignfeedback_comments_enabled = 1;
    $moduleinfo->assignfeedback_offline_enabled = 0;

    // Completion: mark complete on submission
    $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
    $moduleinfo->completionsubmit = 1;
    $moduleinfo->completionexpected = 0;

    $result = create_module($moduleinfo);
    echo "   Created assignment: '$name' (cmid: {$result->coursemodule})\n";
    return $result->coursemodule;
}

// ─── 3. Add course content ───────────────────────────────────────
echo "3. Adding course content...\n";

// Rename sections
$DB->set_field('course_sections', 'name', 'Introduction to Odoo',
    ['course' => $course->id, 'section' => 1]);
$DB->set_field('course_sections', 'name', 'Using the Helpdesk Module',
    ['course' => $course->id, 'section' => 2]);
$DB->set_field('course_sections', 'name', 'Staff Training Assessment',
    ['course' => $course->id, 'section' => 3]);

echo "   --- Section 1: Introduction to Odoo ---\n";

add_page($course, 'What is Odoo?', 1,
'<h3>Welcome to ESMOS Odoo Training</h3>
<p>Odoo is an open-source ERP platform that ESMOS uses for:</p>
<ul>
<li><strong>Operations Management</strong> - Track daily workflows and tasks</li>
<li><strong>Meal Planning</strong> - Manage dietary requirements and meal schedules</li>
<li><strong>Helpdesk</strong> - Handle support tickets from staff and residents</li>
<li><strong>Contacts</strong> - Maintain staff and resident records</li>
</ul>
<p>After completing this course you will be able to navigate the Odoo interface, create and manage records, and use the helpdesk module.</p>');

add_page($course, 'Navigating the Odoo Dashboard', 1,
'<h3>The Odoo Dashboard</h3>
<p>When you log in to Odoo you will see the main dashboard.</p>
<h4>Key Areas:</h4>
<ol>
<li><strong>Top Menu Bar</strong> - Switch between applications (Discuss, Calendar, Helpdesk, etc.)</li>
<li><strong>App Drawer</strong> - Click the grid icon to see all available applications</li>
<li><strong>Search Bar</strong> - Use filters and search to find records quickly</li>
<li><strong>User Menu</strong> - Top-right corner for preferences and log out</li>
</ol>');

echo "   --- Section 2: Using the Helpdesk ---\n";

add_page($course, 'Creating a Helpdesk Ticket', 2,
'<h3>How to Create a Helpdesk Ticket</h3>
<ol>
<li>Navigate to <strong>Helpdesk</strong> from the top menu</li>
<li>Click the <strong>New</strong> button</li>
<li>Fill in: <strong>Subject</strong>, <strong>Team</strong>, <strong>Priority</strong>, and <strong>Description</strong></li>
<li>Click <strong>Save</strong> to submit</li>
</ol>
<p>Track your ticket status in the Helpdesk kanban view. Tickets move through stages: <em>New &rarr; In Progress &rarr; Done</em>.</p>');

add_page($course, 'Managing Tickets and SLAs', 2,
'<h3>Managing Tickets</h3>
<h4>Responding to a ticket:</h4>
<ol>
<li>Open the ticket from the kanban board</li>
<li>Use the <strong>chatter</strong> to communicate with the requester</li>
<li>Update the <strong>stage</strong> as you work on it</li>
<li>Move to <strong>Done</strong> when resolved</li>
</ol>
<h4>SLA Policies:</h4>
<p>Some tickets have SLAs defining response and resolution times. Watch for the SLA timer on high-priority tickets.</p>');

echo "   --- Section 3: Assessment ---\n";

add_page($course, 'Course Summary and Next Steps', 3,
'<h3>Course Summary</h3>
<p>Congratulations on completing the ESMOS Staff Training!</p>
<h4>What you learned:</h4>
<ul>
<li>How to navigate the Odoo dashboard</li>
<li>How to create and track helpdesk tickets</li>
<li>How to manage tickets and understand SLA policies</li>
</ul>
<h4>Next Steps:</h4>
<ol>
<li>Complete the Training Acknowledgement below</li>
<li>Screenshot your completion for your records</li>
<li>Request your Odoo account — the link unlocks after you submit the acknowledgement</li>
</ol>');

add_assignment($course, 'Training Acknowledgement', 3,
'<p>Please confirm that you have read and understood all training materials by typing: <em>"I have completed the ESMOS Odoo staff training."</em></p>
<div style="margin-top:20px; padding:16px; background:#f0f4ff; border-left:4px solid #0d6efd; border-radius:4px;">
<p style="margin:0 0 10px 0;"><strong>Next Step: Request your Odoo account</strong></p>
<p style="margin:0 0 12px 0;">Once you have submitted your acknowledgement above, click the button below to request your Odoo account. The helpdesk team will create it within one business day.</p>
<a href="' . $ODOO_REQUEST_URL . '"
   target="_blank"
   style="display:inline-block; padding:10px 24px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px; font-weight:bold;">
   Request My Odoo Account &rarr;
</a>
</div>');

// ─── 4. Set course completion criteria ───────────────────────────
echo "4. Setting course completion criteria...\n";

$DB->delete_records('course_completion_criteria', ['course' => $course->id]);
$DB->delete_records('course_completion_aggr_methd', ['course' => $course->id]);

$cms = $DB->get_records_sql(
    "SELECT cm.id FROM {course_modules} cm WHERE cm.course = ? AND cm.deletioninprogress = 0",
    [$course->id]
);

foreach ($cms as $cm) {
    $DB->insert_record('course_completion_criteria', [
        'course' => $course->id,
        'criteriatype' => 4,
        'module' => null,
        'moduleinstance' => $cm->id,
        'enrolperiod' => null,
        'timeend' => null,
        'gradepass' => null,
        'role' => null,
    ]);
}

$DB->insert_record('course_completion_aggr_methd', [
    'course' => $course->id,
    'criteriatype' => 0,
    'method' => 1,
]);

echo "   Set " . count($cms) . " activities as completion criteria (all required)\n";

// ─── 5. Create test user accounts ────────────────────────────────
echo "5. Creating test user accounts...\n";

$users = [
    ['username' => 'staff1', 'firstname' => 'Alice', 'lastname' => 'Tan', 'email' => 'alice@esmos.internal'],
    ['username' => 'staff2', 'firstname' => 'Bob', 'lastname' => 'Lim', 'email' => 'bob@esmos.internal'],
    ['username' => 'staff3', 'firstname' => 'Carol', 'lastname' => 'Wong', 'email' => 'carol@esmos.internal'],
];

$createdusers = [];
foreach ($users as $u) {
    $existing = $DB->get_record('user', ['username' => $u['username']]);
    if ($existing) {
        echo "   User '{$u['username']}' already exists\n";
        $createdusers[] = $existing;
        continue;
    }
    $user = create_user_record($u['username'], 'Esmos2024!');
    $user->firstname = $u['firstname'];
    $user->lastname = $u['lastname'];
    $user->email = $u['email'];
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $DB->update_record('user', $user);
    echo "   Created user: {$u['username']} ({$u['firstname']} {$u['lastname']})\n";
    $createdusers[] = $user;
}

// ─── 6. Enrol users into the course ──────────────────────────────
echo "6. Enrolling users into the course...\n";

$enrolplugin = enrol_get_plugin('manual');
$enrolinstance = $DB->get_record('enrol', [
    'courseid' => $course->id,
    'enrol' => 'manual',
]);

if (!$enrolinstance) {
    $enrolid = $enrolplugin->add_instance($course);
    $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
}

$studentrole = $DB->get_record('role', ['shortname' => 'student']);

foreach ($createdusers as $user) {
    if (!is_enrolled(context_course::instance($course->id), $user->id)) {
        $enrolplugin->enrol_user($enrolinstance, $user->id, $studentrole->id);
        echo "   Enrolled: {$user->username}\n";
    } else {
        echo "   Already enrolled: {$user->username}\n";
    }
}

// ─── Done ────────────────────────────────────────────────────────
echo "\n=== Setup Complete ===\n\n";
echo "Course: ESMOS Staff Training - Using Odoo\n";
echo "URL: {$CFG->wwwroot}/course/view.php?id={$course->id}\n\n";
echo "Test accounts (password: Esmos2024!):\n";
echo "  - staff1 (Alice Tan)\n";
echo "  - staff2 (Bob Lim)\n";
echo "  - staff3 (Carol Wong)\n\n";
echo "Admin account:\n";
echo "  - Username: admin\n";
echo "  - Password: moodle2140\n\n";
echo "Admin can:\n";
echo "  - Enrol users: Course > Participants > Enrol users\n";
echo "  - View completion: Course > Reports > Activity completion\n";
echo "  - Verify completion: Course > Reports > Course completion\n";
