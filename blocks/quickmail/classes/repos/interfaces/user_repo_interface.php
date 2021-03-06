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
 * @package    block_quickmail
 * @copyright  2008 onwards Louisiana State University
 * @copyright  2008 onwards Chad Mazilly, Robert Russo, Jason Peak, Dave Elliott, Adam Zapletal, Philip Cali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_quickmail\repos\interfaces;

use block_quickmail\repos\repo;
use context_course;
use block_quickmail\repos\role_repo;
use block_quickmail\repos\group_repo;

interface user_repo_interface {

    public static function get_course_user_selectable_users($course, $user, $course_context = null);
    public static function get_course_users($course_context, $active_only = true, $user_fields = null, $group_id = 0);
    public static function get_course_group_users($course_context, $group_id, $active_only = true, $user_fields = null);
    public static function get_course_role_users($course_context, $role_id, $active_only = true, $user_fields = null);
    public static function get_unique_course_user_ids_from_selected_entities($course, $user, $included_entity_ids = [], $excluded_entity_ids = []);
    public static function get_mentors_of_user($user);

}