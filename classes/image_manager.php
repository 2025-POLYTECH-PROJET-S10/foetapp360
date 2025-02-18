<?php
namespace mod_hippotrack;

defined('MOODLE_INTERNAL') || die();

// require_once("../../config.php");

use \context_system;

class image_manager {
    private $component;
    private $filearea;
    private $contextid;

    public function __construct($filearea) {
        // Get system context for file storage
        $systemcontext = context_system::instance();

        $this->contextid = $systemcontext->id;
        $this->component = "mod_hippotrack";
        $this->filearea = $filearea;
    }

    private function create_file_info($itemid, $filename){

        $fileinfo = [
            'contextid' => $this->contextid,   // ID of the context.
            'component' => 'mod_hippotrack', // Your component name.
            'filearea'  => $this->filearea,       // Usually = table name.
            'itemid'    => $itemid,              // Usually = ID of row in table.
            'filepath'  => '/',            // Any path beginning and ending in /.
            'filename'  => $filename,   // Any filename.
        ];

        return $fileinfo;
    }

        /**
     * 
     * This function saves a file from the 'pix' folder in the plugin
     * file area.
     */
    public function upload_pix_image($itemid , $filename){
        global $CFG;

        $requestdir = $CFG->dirroot . "/mod/hippotrack/pix" ;

        $fs = get_file_storage();

        $fileinfo = $this->create_file_info($itemid,$filename);
        $fs->create_file_from_pathname($fileinfo, $requestdir . "/" . $filename);
    }

    // Modified delete method
    private function _delete_image_with_id($itemid) {
        global $DB;
        $record = $DB->get_record("hippotrack_datasets" , ["id"=> $itemid]);

        if ($this->filearea == "vue_anterieure") {
            $filename = $record->nom_vue_anterieure;
        } else {
            $filename = $record->nom_vue_laterale;
        }

        $fs = get_file_storage();
        if ($file = $fs->get_file(
            $this->contextid,
            $this->component,
            $this->filearea,
            $itemid,
            '/', 
            $filename
        )) {
            return $file->delete();
        }

        return false;
    }

    public function updateImageFromContent($itemid, $filename, $filecontent) {
        $fs = get_file_storage();

        // remove the old file with this itemid
        $this->deleteImage($itemid);

        // add the new image
        $this->addImageFromContent($itemid, $filename, $filecontent);
    }

    /**
     * Get image URL.
     */
    public function getImageUrl($itemid, $filename) {
        // Add backslash to use core Moodle class
        return \moodle_url::make_pluginfile_url(
            $this->contextid,
            $this->component,
            $this->filearea,
            $itemid,
            '/',
            $filename
        );
    }

    // Get image file
    public function getImageFile($itemid, $filename) {
        $fs = get_file_storage();
        return $fs->get_file(
            $this->contextid,
            $this->component,
            $this->filearea,
            $itemid,
            '/',
            $filename
        );
    }

    // Add Image from file content (string)
    public function addImageFromContent($itemid, $filename, $filecontent) {
        $fs = get_file_storage();
        $fileinfo = $this->create_file_info($itemid, $filename);
        $fs->create_file_from_string($fileinfo, $filecontent);
    }

    private function _debug_print_file_info($file) {
        if (!$file) {
            debugging("File is null or does not exist.", DEBUG_DEVELOPER);
            return;
        }
    
        debugging("File Information:", DEBUG_DEVELOPER);
        debugging("ID: " . $file->get_id(), DEBUG_DEVELOPER);
        debugging("Context ID: " . $file->get_contextid(), DEBUG_DEVELOPER);
        debugging("Component: " . $file->get_component(), DEBUG_DEVELOPER);
        debugging("File Area: " . $file->get_filearea(), DEBUG_DEVELOPER);
        debugging("Item ID: " . $file->get_itemid(), DEBUG_DEVELOPER);
        debugging("File Path: " . $file->get_filepath(), DEBUG_DEVELOPER);
        debugging("File Name: " . $file->get_filename(), DEBUG_DEVELOPER);
        debugging("File Size: " . $file->get_filesize() . " bytes", DEBUG_DEVELOPER);
        debugging("MIME Type: " . $file->get_mimetype(), DEBUG_DEVELOPER);
        debugging("Content Hash: " . $file->get_contenthash(), DEBUG_DEVELOPER);
        debugging("Time Created: " . date("Y-m-d H:i:s", $file->get_timecreated()), DEBUG_DEVELOPER);
        debugging("Time Modified: " . date("Y-m-d H:i:s", $file->get_timemodified()), DEBUG_DEVELOPER);
    }


    public function addImageFromForm($itemid, $mform, $elem) {
        $newfilename = $mform->get_new_filename($elem);
        $file = $mform->save_stored_file($elem, $this->contextid, $this->component, $this->filearea, $itemid, "/", $newfilename); // the last one is filepath, true is to and store the images overwrite

        if (!$file) {
            throw new moodle_exception("Couldn't Save File, error in Database","","", $file->get_context()->id);
        }
    }

    public function delete_image($itemid, $filename){
        $file = $this->getImageFile($itemid, $filename);
        if (!$file) {
            return true;
        }
        return $file->delete();
    }

    public function updateImageFromForm($itemid, $mform, $elem) {
        // remove the old file with this itemid
        $this->_delete_image_with_id($itemid);

        // add the new image
        $this->addImageFromForm($itemid, $mform, $elem);
    }
}