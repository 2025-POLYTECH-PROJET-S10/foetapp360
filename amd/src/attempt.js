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
 * Prints an instance of hippotrack.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$(document).ready(function () {
    console.log("✅ JavaScript Loaded!");

    $(".rotation-container").each(function () {
        let rotationContainer = $(this);
        let container = rotationContainer.find(".container");

        if (!container.length) return;

        let schemaType = container.data("schema-type");

        let targetInteriorImage = container.find("." + schemaType + "_interior");
        let targetContourImage = container.find("." + schemaType + "_contour");

        let rotateSlider = rotationContainer.find(".rotate-slider");
        let moveSlider = rotationContainer.find(".move-axis-slider");

        let rotationAngle = 0;
        let translateDistance = 0;

        function updateTransform() {
            let radian = (rotationAngle * Math.PI) / 180;
            let xOffset = translateDistance * Math.sin(radian);
            let yOffset = -translateDistance * Math.cos(radian);

            targetInteriorImage.css("transform", `translate(${xOffset}px, ${yOffset}px) rotate(${rotationAngle}deg)`);
            targetContourImage.css("transform", `rotate(${rotationAngle}deg)`);
        }

        rotateSlider.on("input", function () {
            rotationAngle = parseInt($(this).val(), 10);
            updateTransform();
        });

        moveSlider.on("input", function () {
            translateDistance = parseInt($(this).val(), 10);
            updateTransform();
        });
    });

    $(document).ready(function () {
        $(".image-cycling-container").each(function () {
            let container = $(this);
            let fullPrefix = container.data("prefix"); // e.g., "bb_vue_ante_bf"
            let cyclingImage = container.find(".cycling-image");
            let hiddenInput = container.find(".selected-position");

            let currentPosition = parseInt(cyclingImage.data("current"), 10);
            let variation = fullPrefix.includes("_bf") ? "bf" : "mf"; // Detect initial variation
            let basePrefix = fullPrefix.replace(/_(bf_|mf_)$/, ""); // Remove bf/mf to get the base part

            function updateImage() {
                let imagePath = `/mod/hippotrack/pix/${basePrefix}_${variation}_${currentPosition}.png`;
                cyclingImage.attr("src", imagePath); // Update the displayed image
                let imageName = `${basePrefix}_${variation}_${currentPosition}`;
                hiddenInput.val(imageName); // Update the hidden input field
            }

            let max_image = 8;
            let min_image = 1;

            // ⬅️ Previous Button
            container.find(".prev-btn").on("click", function () {
                currentPosition = currentPosition > min_image ? currentPosition - 1 : max_image;
                updateImage();
            });

            // ➡️ Next Button
            container.find(".next-btn").on("click", function () {
                currentPosition = currentPosition < max_image ? currentPosition + 1 : min_image;
                updateImage();
            });

            // 🔄 Toggle bf/mf
            container.find(".toggle-btn").on("click", function () {
                variation = (variation === "bf") ? "mf" : "bf"; // Toggle bf <-> mf
                updateImage();
            });
        });
    });




});
