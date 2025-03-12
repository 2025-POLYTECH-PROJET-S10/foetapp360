<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants.
 *
 * @package     mod_foetapp360
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 use mod_foetapp360\image_manager;

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function foetapp360_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the foetapp360 into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_foetapp360_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function foetapp360_add_instance($moduleinstance)
{
    global $DB;

    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('foetapp360', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the foetapp360 in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_foetapp360_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function foetapp360_update_instance($moduleinstance)
{
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('foetapp360', $moduleinstance);
}

/**
 * Delete an instance of the foetapp360 activity.
 *
 * This function is called when a foetapp360 instance is deleted. It will
 * remove any dependent data (such as sessions and attempts) linked to the instance.
 *
 * @param int $id The ID of the foetapp360 instance to delete.
 * @return bool True if deletion was successful, false otherwise.
 */
function foetapp360_delete_instance($id) {
    global $DB;

    // Ensure the instance exists.
    if (!$DB->get_record('foetapp360', array('id' => $id))) {
        return false;
    }

    // Delete all attempts linked to sessions of this instance.
    // This SQL deletes records from foetapp360_attempt whose session is linked to the instance.
    $sql = "DELETE FROM {foetapp360_attempt}
            WHERE id_session IN (
                SELECT id FROM {foetapp360_session} WHERE id_foetapp360 = ?
            )";
    $DB->execute($sql, array($id));

    // Delete all session records for this instance.
    $DB->delete_records('foetapp360_session', array('id_foetapp360' => $id));

    // Optionally: if your design requires cleaning up other tables (e.g. feedback data), add similar deletions.
    // Note: The foetapp360_datasets table does not include a direct reference to an instance.
    // If you ever add an instance foreign key, be sure to delete those records here.

    // Finally, delete the foetapp360 instance itself.
    $DB->delete_records('foetapp360', array('id' => $id));

    return true;
}


/**
 * Serve the files from the foetapp360 file areas.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function mod_foetapp360_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload
): bool {
    global $DB;

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    // if ($context->contextlevel != CONTEXT_MODULE) {
    //     return false;
    // }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'vue_anterieure' && $filearea !== 'vue_laterale') {
        return false;
    }

    // Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    // if (!has_capability('mod/foetapp360:viewimages', $context)) {
    //     return false;
    // }

    // The args is an array containing [itemid, path].
    // Fetch the itemid from the path.
    $itemid = array_shift($args);

    // The itemid can be used to check access to a record, and ensure that the
    // record belongs to the specifeid context. For example:
    if ($filearea === 'vue_anterieure' || $filearea === 'vue_laterale') {
        // Check that the record exists.
        if (!$DB->record_exists('foetapp360_datasets', ['id' => $itemid])) {
            return false;
        }

        // You may want to perform additional checks here, for example:
        // - ensure that if the record relates to a grouped activity, that this
        //   user has access to it
        // - check whether the record is hidden
        // - check whether the user is allowed to see the record for some other
        //   reason.

        // If, for any reason, the user does not hve access, you can return
        // false here.
    }

    // For a plugin which does not specify the itemid, you may want to use the following to keep your code consistent:
    // $itemid = null;

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (empty($args)) {
        // $args is empty => the path is '/'.
        $filepath = '/';
        debugging($filepath, DEBUG_DEVELOPER);
    } else {
        // $args contains the remaining elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
        debugging($filepath, DEBUG_DEVELOPER);
    }

    // Retrieve the file from the Files API.
    $systemcontext = context_system::instance();
    $fs = get_file_storage();

    $image_manager = new image_manager($filearea);

    $file = $image_manager->getImageFile($itemid, $filename);
    if (!$file) {
        throw new moodle_exception("Le fichier est introuvable !" . " -- " . $filepath . " -- " . $filearea , 'error', '', $filename);
        // The file does not exist.
        return false;
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 0, 0, $forcedownload);

    // Return true to indicate that the file has been served.
    return true;
}

/**
 * Starts a new session for a given instance and user.
 *
 * This function checks if there is an existing active session for the given instance and user.
 * If an active session is found, it returns the session ID. Otherwise, it creates a new session
 * with the current timestamp and a default difficulty level of 'medium', and returns the new session ID.
 *
 * @param int $instanceid The ID of the foetapp360 instance.
 * @param int $userid The ID of the user.
 * @return int The ID of the existing or newly created session.
 */
function foetapp360_start_new_session($instanceid, $userid) {
    global $DB;
    
    // Check for existing active session
    if ($existingsession = $DB->get_record('foetapp360_session', [
        'id_foetapp360' => $instanceid,
        'userid' => $userid,
        'timefinish' => 0,
    ])) {
        return $existingsession->id;
    }

    $newsession = (object)[
        'id_foetapp360' => $instanceid,
        'userid' => $userid,
        'timestart' => time(),
        'timefinish' => 0,
    ];
    
    return $DB->insert_record('foetapp360_session', $newsession);
}