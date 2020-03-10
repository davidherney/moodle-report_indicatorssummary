<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    report_indicatorssummary
 * @copyright  2019 --
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

$courseid = optional_param('course', SITEID, PARAM_INT);

if (!$course = $DB->get_record("course", array("id"=>$courseid))) {
    print_error("invalidcourseid");
}

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/indicatorssummary:view', $context);

$PAGE->set_url(new moodle_url('/report/indicatorssummary/index.php', array('course' => $course->id)));
$PAGE->set_pagelayout('report');

function generate_table( array $header, array $body ):string {
    $table = new html_table();
    $table->head = $header;
    $table->data = $body;
    return html_writer::table($table);
}

$indicators = new report_indicatorssummary_indicator();
$generaluserheader = array('id','username', 'firstname' , 'lastname');

echo $OUTPUT->header();
flush();
echo $OUTPUT->heading( "Reporte de curso [id:$courseid]" );
echo $OUTPUT->box_start();

echo "<strong>Mensajes recibidos y enviados por cada usuario del sitio.</strong><br>";

echo generate_table(
    array_merge( $generaluserheader, array( 'inc_messages','out_messages' ) ),
    $indicators->generate_report( $indicators->get_all_users() )
);

echo "<br><br><strong>Estudiantes que han ingresado al menos una vez al curso.</strong><br>";
echo generate_table(
    $generaluserheader,
    $indicators->get_students_with_last_access( $courseid )
);

echo "<br><br><strong>Estudiantes que han ingresado la última semana al curso.</strong><br>";
echo generate_table(
    $generaluserheader,
    $indicators->get_active_users_by_course( $courseid )
);

echo "<br><br><strong>Estudiantes activos por curso en la semana previa a la pasada.</strong><br>";
echo generate_table(
    $generaluserheader,
    $indicators->get_active_users_by_course( $courseid, strtotime("-2 week"),strtotime("-1 week") )
);

echo "<br><br><strong>Cantidad de estudiantes que han interactuado con un cada recurso del curso (usuarios únicos).</strong><br>";
echo generate_table(
    ['#', 'contextinstanceid', 'mod', 'actname', 'interactions'],
    $indicators->get_activities_interactions( $courseid )
);

echo "<br><br><strong>Cantidad de visualizaciones de cada recurso por usuario del curso.</strong><br>";
echo generate_table(
    ['#', 'contextinstanceid', 'component', 'userid', 'vizualizations'],
    $indicators->get_resources_visualizations( $courseid )
);

echo "<br><br><strong>Cantidad de actividades finalizadas por cada estudiante en el curso.</strong><br>";
echo generate_table(
    ['#', 'userid', 'completedactivities'],
    $indicators->get_completed_activities( $courseid )
);

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
