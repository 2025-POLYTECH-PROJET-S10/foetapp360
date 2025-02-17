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
    public function deleteImage($itemid, $filename) {
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
}