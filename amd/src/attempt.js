define(['jquery'], function($) {
    return {
        init: function() {
            $(".rotation_hippotrack_container").each(function() {
                let rotationhippotrack_container = $(this);
                let hippotrack_container = rotationhippotrack_container.find(".hippotrack_container");

                if (!hippotrack_container.length) { return; }

                let schemaType = hippotrack_container.data("schema-type");
                if (!schemaType) { return; }

                let targetInteriorImage = hippotrack_container.find("." + schemaType + "_interieur");
                let targetContourImage = hippotrack_container.find("." + schemaType + "_contour");

                let rotateSlider = rotationhippotrack_container.find(".rotate-slider");
                let moveSlider = rotationhippotrack_container.find(".move-axis-slider");

                let rotationAngle = rotateSlider.val();
                let translateDistance = moveSlider.val();
                updateTransform();

                /**
                 * Met à jour la transformation CSS des images.
                 */
                function updateTransform() {
                    let radian = (rotationAngle * Math.PI) / 180;
                    let xOffset = translateDistance * Math.sin(radian);
                    let yOffset = -translateDistance * Math.cos(radian);

                    if (targetInteriorImage.length) {
                        targetInteriorImage.css("transform", `translate(${xOffset}px, ${yOffset}px) rotate(${rotationAngle}deg)`);
                    }
                    if (targetContourImage.length) {
                        targetContourImage.css("transform", `rotate(${rotationAngle}deg)`);
                    }
                }

                rotateSlider.on("input", function() {
                    rotationAngle = parseInt($(this).val(), 10);
                    updateTransform();
                });

                moveSlider.on("input", function() {
                    translateDistance = parseInt($(this).val(), 10);
                    updateTransform();
                });
            });

            $(".image_cycling_hippotrack_container").each(function() {
                let hippotrack_container = $(this);
                let fullPrefix = hippotrack_container.data("prefix") || "";
                let cyclingImage = hippotrack_container.find(".hippotrack_attempt_cycling-image");
                let hiddenInput = hippotrack_container.find(".hippotrack_attempt_selected_position");

                let currentPosition = parseInt(cyclingImage.data("current"), 10) || 1;
                let variation = fullPrefix.includes("_bf") ? "bf" : "mf";
                let basePrefix = fullPrefix.replace(/_(bf_|mf_)$/, "");

                /**
                 * Met à jour l'image affichée en fonction de la position et de la variation.
                 */
                function updateImage() {
                    let imagePath = `/mod/hippotrack/pix/${basePrefix}_${variation}_${currentPosition}.png`;
                    cyclingImage.attr("src", imagePath);
                    let imageName = `${basePrefix}_${variation}_${currentPosition}.png`;
                    hiddenInput.val(imageName);
                }

                let max_image = 8;
                let min_image = 1;

                hippotrack_container.find(".hippotrack_attempt_prev-btn").on("click", function() {
                    currentPosition = currentPosition > min_image ? currentPosition - 1 : max_image;
                    updateImage();
                });

                hippotrack_container.find(".hippotrack_attempt_next-btn").on("click", function() {
                    currentPosition = currentPosition < max_image ? currentPosition + 1 : min_image;
                    updateImage();
                });

                hippotrack_container.find(".hippotrack_attempt_toggle_btn").on("click", function() {
                    variation = (variation === "bf") ? "mf" : "bf";
                    updateImage();
                });
            });

            // Gestion des onglets et du contenu affiché
            $(".attempt_container").hide();
            $(".hippotrack-tab.active").each(function() {
                $($(this).data("target")).show();
                $(this).addClass("hippotrack_given_tab_input");
            });

            $(".hippotrack-tab").click(function() {
                $(".hippotrack-tab").removeClass("active");
                $(this).addClass("active");

                $(".attempt_container").hide();
                $($(this).data("target")).show();
            });

            // Gestion message lorsque inputs manquants.
            document.getElementById("submit_attempt").addEventListener("click", function(event) {
                let inputs = document.querySelectorAll("#attempt_form input[type='text']");
                // Vérifier si l'overlay existe déjà
                let overlay = document.getElementById('attempt_error_overlay');
                if (!overlay) {
                    // Créer l'élément overlay si il n'existe pas
                    overlay = document.createElement('div');
                    overlay.id = 'attempt_error_overlay';
                    overlay.innerHTML = `
                        <p>Veuillez remplir tous les champs.</p>
                        <button id="attempt_close_overlay">Fermer</button>
                    `;
                    document.body.appendChild(overlay);
                    // Ajouter un gestionnaire d'événements pour fermer l'overlay
                    document.getElementById("attempt_close_overlay").addEventListener("click", function() {
                        overlay.style.display = 'none'; // Cache l'overlay
                    });
                }
                // Vérifier si un champ est vide
                for (let input of inputs) {
                    if (input.value.trim() === "") {
                        overlay.style.display = 'flex';  // Affiche l'overlay
                        event.preventDefault(); // Empêche l'envoi du formulaire
                        return;
                    }
                }
                // Si tout est validé, on cache l'overlay
                overlay.style.display = 'none';
            });
        }
    };
});
