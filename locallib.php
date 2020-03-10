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

defined('MOODLE_INTERNAL') || die;

/**
 * A report to display the courses status (stats, counters, general information)
 *
 * @package     report_indicatorssummary
 * @copyright   2020
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class report_indicatorssummary_indicator {

    private function reindex( array $data ):array {
        $i = 0;
        foreach($data as &$record){
            if( gettype( $record ) === 'array' ){
                $record['id'] = $i;
            }else{
                $record->id = $i;
            }
            $i++;
        }

        return $data;
    }

    function get_completed_activities( int $courseid ):array {
        global $DB;

        $return = [];

        $sql = "SELECT S.id, S.userid FROM `mdl_assign_submission` AS S
        INNER JOIN `mdl_assign`AS A
        ON S.assignment = A.id
        WHERE A.course = :courseid AND S.status = :status
        ORDER BY S.id ASC";

        $criteria = [
            'courseid'  => $courseid,
            'status'    => 'submitted'
        ];

        $report = $DB->get_records_sql( $sql, $criteria );

        foreach( $report as $key => $record ){
            if( !isset( $return[ $record->userid ] ) ){
                $return[ $record->userid ][ 'id' ]          = $key;
                $return[ $record->userid ][ 'userid' ]      = $record->userid;
                $return[ $record->userid ][ 'assigments' ]  = 1;
            }else{
                $return[ $record->userid ][ 'assigments' ]++;
            }

        }


        $report = $this->reindex( array_values($return) );

        return $report;
    }

    function get_resources_visualizations( int $courseid ):array {
        global $DB;

        $return = [];

        $resourcelist = ['mod_resource', 'mod_book', 'mod_url', 'mod_folder', 'mod_page'];
        $stdresourcelist = "'".join("','", $resourcelist) . "'";

        $activities = get_array_of_activities( $courseid );

        $sql = "SELECT id, contextinstanceid, component, userid, count(countcol) AS count
        FROM (
            SELECT id, contextinstanceid, component, userid, 1 AS countcol
            FROM mdl_logstore_standard_log
            WHERE
                courseid = :courseid AND
                component IN ($stdresourcelist) AND
                action = :action
        ) AS countmod
        GROUP BY userid, contextinstanceid
        ORDER BY contextinstanceid ASC";

        $criteria = [
            'courseid'  => $courseid,
            'action'    => 'viewed'
        ];

        $report = $DB->get_records_sql( $sql, $criteria );

        $report = $this->reindex( $report );

        return $report;
    }

    function get_activities_interactions( int $courseid ):array {
        global $DB;

        $return = [];

        $activities = get_array_of_activities( $courseid );

        $sql = 'SELECT contextinstanceid, component, count(countcol) AS count
        FROM (
                SELECT contextinstanceid, component, 1 AS countcol
                FROM {logstore_standard_log}
                WHERE courseid = :courseid AND component LIKE :component
                GROUP BY userid, contextinstanceid
            ) AS countmod
         GROUP BY contextinstanceid
         ORDER BY contextinstanceid ASC';

        $criteria = [
            'courseid' => $courseid,
            'component' => 'mod_%'
        ];

        $report = $DB->get_records_sql( $sql, $criteria );

        foreach ($activities as &$activity) {

            $data = [
                'id'                => $activity->id,
                'contextinstanceid' => $activity->cm,
                'mod'               => $activity->mod,
                'actname'           => $activity->name,
                'interactions'      => ( array_key_exists( $activity->cm , $report) ? $report[ $activity->cm ]->count : 0 )
            ];

            array_push( $return, $data );

        }

        $return = $this->reindex( $return );

        return $return;
    }

    function get_active_users_by_course( int $courseid, int $starttime = NULL, int $endtime = NULL ):array {
        if( is_null($starttime) && is_null($endtime) ){
            $starttime = strtotime("-1 week");
            $endtime = time();
        }

        return $this->get_students_with_last_access( $courseid, $starttime, $endtime );
    }

    function get_students_with_last_access( int $courseid, int $starttime = NULL, int $endtime = NULL ): array {
        global $DB;
        $sql = 'SELECT id, userid FROM  {user_lastaccess} WHERE courseid = :courseid';
        $criteria = [ 'courseid' => $courseid ];

        if( $starttime && $endtime ){
            $sql .= " AND timeaccess >= :start AND timeaccess <= :end ";
            $criteria[ 'start' ]    = $starttime;
            $criteria[ 'end' ]      = $endtime;
        }

        return $this->get_users_data(
            array_map(
                function( $in ):int{ return $in->userid; },
                $DB->get_records_sql( $sql, $criteria )
            )
        );
    }

    function get_users_data( array $ids ):array {
        if( count( $ids ) == 0 ){
            return [];
        }

        global $DB;
        $sql ="SELECT id, username, firstname, lastname FROM  {user} WHERE id IN ( " . implode( $ids, "," ) . ")";
        return array_Values( $DB->get_records_sql( $sql ) );
    }

    function get_all_users():array {
        global $DB;
        $sql = 'SELECT id, username, firstname, lastname FROM {user}';
        return $DB->get_records_sql( $sql );
    }

    function generate_report( array $users ):array {
        $return = [];
        foreach ($users as &$user) {
            $report = [
                'id'  => $user->id,
                'username'  => $user->username,
                'firstname'  => $user->firstname,
                'lastname'  => $user->lastname,
                'incmessages'  => $this->get_inc_messages( $user->id ),
                'outmessages'  => $this->get_out_messages( $user->id )
            ];
            array_push( $return, $report );
        }
        return $return;
    }

    function get_inc_messages( int $userid ):int {
        global $DB;
        $sql = 'SELECT count(*) AS count
            FROM
                {message_conversation_members} MCM
            INNER JOIN
                {messages} M
            ON
                MCM.conversationid = M.conversationid
            WHERE
                MCM.userid = :userid AND M.useridfrom != :userid_';

        return $DB->get_record_sql( $sql, [ 'userid' => $userid, 'userid_' => $userid ] )->count;
    }

    function get_out_messages( int $userid ):int {
        global $DB;
        $sql = 'SELECT count(*) AS count FROM {messages} WHERE useridfrom = :userid';
        return $DB->get_record_sql( $sql, [ 'userid' => $userid ] )->count;

    }

 }

