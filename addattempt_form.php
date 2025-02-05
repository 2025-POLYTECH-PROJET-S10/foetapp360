<?php
require_once("$CFG->libdir/formslib.php");

class addattempt_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Champ caché pour l'ID de l'activité hippotrack.
        $mform->addElement('hidden', 'id_hippotrack');
        $mform->setType('id_hippotrack', PARAM_INT);

        // Sélection de l'utilisateur (dans un cas réel, cela pourrait être automatique).
        $mform->addElement('text', 'userid', 'User ID');
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', 'Requis', 'required');

        // Numéro de tentative
        $mform->addElement('text', 'attempt', 'Numéro de tentative');
        $mform->setType('attempt', PARAM_INT);
        $mform->addRule('attempt', 'Requis', 'required');

        // État de la tentative
        $mform->addElement('select', 'state', 'État', [
            'inprogress' => 'En cours',
            'overdue' => 'En retard',
            'finished' => 'Terminée',
            'abandoned' => 'Abandonnée'
        ]);
        $mform->setType('state', PARAM_ALPHANUMEXT);

        // Note totale
        $mform->addElement('text', 'sumgrades', 'Total des points');
        $mform->setType('sumgrades', PARAM_FLOAT);

        // Exercices faits
        $mform->addElement('text', 'exercicesdone', 'Exercices faits');
        $mform->setType('exercicesdone', PARAM_FLOAT);

        // Difficulté
        $mform->addElement('text', 'difficulty', 'Difficulté (Easy/Hard)');
        $mform->setType('difficulty', PARAM_TEXT);

        // Bouton de soumission
        $mform->addElement('submit', 'submitbutton', 'Ajouter la tentative');
    }
}
