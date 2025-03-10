define(['jquery'], function ($) {
    return {
        init: function () {
            $(".rotation_hippotrack_container").each(function () {
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

                rotateSlider.on("input", function () {
                    rotationAngle = parseInt($(this).val(), 10);
                    updateTransform();
                });

                moveSlider.on("input", function () {
                    translateDistance = parseInt($(this).val(), 10);
                    updateTransform();
                });
            });

            let imageDatabase = JSON.parse(document.getElementById("image_database").dataset.values);
            $(".image_cycling_hippotrack_container").each(function () {
                let hippotrack_container = $(this);
                let cyclingImage = hippotrack_container.find(".hippotrack_attempt_cycling-image_vue_laterale, .hippotrack_attempt_cycling-image_vue_anterieure");

                let hiddenInput = hippotrack_container.find(".hippotrack_attempt_selected_position");
                let toggleButtonHiddenInput = hippotrack_container.find(".hippotrack_attempt_toggle_btn_value");
                /**
                 * Met à jour l'image affichée en fonction de la position et de la variation.
                 */
                function increaseImage() {
                    let field = hippotrack_container.find(".hippotrack_field");
                    let inclinaison = toggleButtonHiddenInput.attr("value");
                    let images = Object.values(imageDatabase[field[0].dataset.values][inclinaison]); // Convertir en tableau indexé
                    let imageActuelle = cyclingImage.attr("src");
                    let currentIndex = images.indexOf(imageActuelle);
                    if (currentIndex === -1) {
                        currentIndex = 0; // Sécurité : si l’image actuelle n’existe pas dans la liste, commencer à 0
                    }
                    currentIndex = currentIndex + 1;
                    if (currentIndex == images.length) {
                        currentIndex = 0;
                    }
                    cyclingImage.attr("src", images[currentIndex]);
                    hiddenInput.val(images[currentIndex]);
                }
                /**
                 * Met à jour l'image affichée en fonction de la position et de la variation.
                 */
                function decreaseImage() {
                    let field = hippotrack_container.find(".hippotrack_field");
                    let inclinaison = toggleButtonHiddenInput.attr("value");
                    let images = Object.values(imageDatabase[field[0].dataset.values][inclinaison]); // Convertir en tableau indexé
                    let imageActuelle = cyclingImage.attr("src");
                    let currentIndex = images.indexOf(imageActuelle);
                    if (currentIndex === -1) {
                        currentIndex = 0; // Sécurité : si l’image actuelle n’existe pas dans la liste, commencer à 0
                    }
                    currentIndex = currentIndex - 1;
                    if (currentIndex < 0) {
                        currentIndex = images.length - 1;
                    }
                    cyclingImage.attr("src", images[currentIndex]);
                    hiddenInput.val(images[currentIndex]);
                }
                /**
                 * Update the image when toggling.
                 * @param {string} previousInclinaison - La clé de l'inclinaison précédente.
                 */
                function updateImage(previousInclinaison){
                    let field = hippotrack_container.find(".hippotrack_field");
                    let images = Object.values(imageDatabase[field[0].dataset.values][previousInclinaison]);
                    let imageActuelle = cyclingImage.attr("src");
                    let currentIndex = images.indexOf(imageActuelle);
                    let correctInclinaison = toggleButtonHiddenInput.attr("value");
                    let imagesBonInclinaison = Object.values(imageDatabase[field[0].dataset.values][correctInclinaison]);
                    cyclingImage.attr("src", imagesBonInclinaison[currentIndex]);
                    hiddenInput.val(imagesBonInclinaison[currentIndex]);
                }
                /**
                 * Met à jour l'inclinaison de l'image.
                 */
                function toggleView(){
                    let inclinaison = toggleButtonHiddenInput.attr("value");
                    let field = hippotrack_container.find(".hippotrack_field");
                    let images = imageDatabase[field[0].dataset.values];
                    let inclinaisonKeys = Object.keys(images);
                    let currentIndex = inclinaisonKeys.indexOf(inclinaison); // Trouver son index
                    let nextKey;
                    if (currentIndex !== -1 && currentIndex < Object.keys(images).length - 1) {
                        nextKey = inclinaisonKeys[currentIndex + 1]; // Clé suivante
                    } else {
                        nextKey = inclinaisonKeys[0]; // Clé suivante
                    }
                    toggleButtonHiddenInput.attr("value", nextKey);
                    return inclinaison;
                }

                hippotrack_container.find(".hippotrack_attempt_prev-btn").on("click", function () {
                    decreaseImage();
                });

                hippotrack_container.find(".hippotrack_attempt_next-btn").on("click", function () {
                    increaseImage();
                });

                hippotrack_container.find(".hippotrack_attempt_toggle_btn").on("click", function() {
                    let previousInclinaison = toggleView();
                    updateImage(previousInclinaison);
                });
            });

            // Gestion des onglets et du contenu affiché
            $(".attempt_container").hide();
            $(".hippotrack-tab.active").each(function () {
                $($(this).data("target")).show();
                $(this).addClass("hippotrack_given_tab_input");
            });

            $(".hippotrack-tab").click(function () {
                $(".hippotrack-tab").removeClass("active");
                $(this).addClass("active");

                $(".attempt_container").hide();
                $($(this).data("target")).show();
            });

            // Gestion message lorsque inputs manquants.
            document.getElementById("submit_attempt").addEventListener("click", function (event) {
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
                    document.getElementById("attempt_close_overlay").addEventListener("click", function () {
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
