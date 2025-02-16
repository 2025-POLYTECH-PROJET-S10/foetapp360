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

        if (!container.length) {
            console.error("❌ No .container found inside .rotation-container!", rotationContainer);
            return;
        }

        let schemaType = container.data("schema-type");
        if (!schemaType) {
            console.error("❌ No schema-type found in container", container);
            return;
        }

        console.log("✅ Found container for schema:", schemaType);

        let targetInteriorImage = container.find("." + schemaType + "_interior");
        let targetContourImage = container.find("." + schemaType + "_contour");

        if (!targetInteriorImage.length || !targetContourImage.length) {
            console.error("❌ No interior or contour image found for schema type:", schemaType);
            return;
        }

        console.log("✅ Found images:", targetInteriorImage, targetContourImage);

        let rotateSlider = rotationContainer.find(".rotate-slider");
        let moveSlider = rotationContainer.find(".move-axis-slider");

        let rotationAngle = 0;
        let translateDistance = 0;

        // ✅ Set transform-origin to ensure correct rotation pivot
        targetInteriorImage.css("transform-origin", "center center");
        targetContourImage.css("transform-origin", "center center");

        function updateTransform() {
            let radian = (rotationAngle * Math.PI) / 180; // Convert degrees to radians
            let xOffset = translateDistance * Math.sin(radian);
            let yOffset = -translateDistance * Math.cos(radian);

            console.log(`🔄 Rotating: ${rotationAngle}° | ↔ Moving: ${translateDistance}px (X: ${xOffset}, Y: ${yOffset})`);

            // ✅ Apply correct transformations
            let transformString = `translate(${xOffset}px, ${yOffset}px) rotate(${rotationAngle}deg)`;

            // Apply the transformations
            targetInteriorImage.css("transform", transformString);
            targetContourImage.css("transform", `rotate(${rotationAngle}deg)`);
        }

        // Rotation slider event
        rotateSlider.on("input", function () {
            rotationAngle = parseInt($(this).val(), 10);
            updateTransform();
        });

        // Move axis slider event
        moveSlider.on("input", function () {
            translateDistance = parseInt($(this).val(), 10);
            updateTransform();
        });
    });
});
