<?php

// Fonction pour choisir un dataset de manière aléatoire
function hippotrack_get_random_dataset() {
    global $DB;

    // Récupérer tous les datasets liés à l'activité
    $datasets = $DB->get_records('hippotrack_datasets', null, 'id ASC');

    // Convertir les datasets en tableau pour l'accès indexé
    $dataset_list = array_values($datasets);

    // S'il y a des datasets disponibles, en choisir un au hasard
    if (count($dataset_list) > 0) {
        $random_dataset = $dataset_list[array_rand($dataset_list)];
        // Retourner l'index du dataset et ses informations
        return $random_dataset;
    }

    // Si aucun dataset n'est trouvé, renvoyer null
    return null;
}
