<?php

function get_correct_rotation(int $input_rotation) {
    // Déterminer la rotation en fonction de input_rotation
    if ($input_rotation < 5 || $input_rotation >= 355) {
        $rotation = 0;
    } 
    else if (abs($input_rotation - 90) <= 5) {
        $rotation = 90;
    } 
    else if (abs($input_rotation - 180) <= 5) {
        $rotation = 180;
    } 
    else if (abs($input_rotation - 270) <= 5) {
        $rotation = 270;
    } 
    // Cas entre les axes perpendiculaires
    else if (abs($input_rotation - 45) < 40) {
        $rotation = 45;
    } 
    else if (abs($input_rotation - 135) < 40) {
        $rotation = 135;
    } 
    else if (abs($input_rotation - 225) < 40) {
        $rotation = 225;
    } 
    else {
        $rotation = 315;
    }
    return $rotation;
}

function get_correct_inclinaison(int $input_inclinaison){
    if ($input_inclinaison >= 45) {
        // Bien fléchie
        $inclinaison = 1;
    }
    else if ($input_inclinaison <= -45) {
        // Mal fléchie
        $inclinaison = -1;
    }
    else {
        // Peu fléchie
        $inclinaison = 0;
    }
    return $inclinaison;
}

/*
* Retourne null, si l'inclinaison = 0 (peu fléchie)
*/
function get_dataset_from_inclinaison_rotation(int $input_inclinaison, int $input_rotation) {
    global $DB; // Assurez-vous que $DB est bien défini pour la requête.

    // Vérification de l'inclinaison
    $inclinaison = get_correct_inclinaison($input_inclinaison);
    $rotation = get_correct_rotation($input_rotation);
    // Récupérer le dataset correspondant
    if($inclinaison == 0){
        $dataset = $DB->get_record_sql(
            "SELECT * FROM {hippotrack_datasets} WHERE inclinaison = :inclinaison AND rotation = :rotation",
            array('inclinaison' => $inclinaison, 'rotation' => $rotation)
        );
    }
    else{
        $dataset = null;
    }
    return $dataset;
}

function get_dataset_name_from_inclinaison_rotation(int $input_inclinaison, int $input_rotation) {
    global $DB; // Assurez-vous que $DB est bien défini pour la requête.

    // Déterminer la rotation en fonction de input_rotation
    $rotation = get_correct_rotation($input_rotation);

    $dataset = $DB->get_record_sql(
        "SELECT * FROM {hippotrack_datasets} WHERE inclinaison = :inclinaison AND rotation = :rotation",
        array('inclinaison' => 1, 'rotation' => $rotation)
    );
    return $dataset->name;
}

function datasets_equals($dataset1, $dataset2) {
    // Vérifier si les deux objets sont bien définis
    if (!is_object($dataset1) || !is_object($dataset2)) {
        return false;
    }

    // Convertir les objets en tableaux associatifs pour comparer les valeurs
    $array1 = (array) $dataset1;
    $array2 = (array) $dataset2;

    // Comparer les tableaux résultants
    return $array1 == $array2; // Compare les valeurs mais pas les types strictement
}
