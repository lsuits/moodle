<?php

require_once dirname(__FILE__) . '/lib.php';

class online_semesters extends online_source implements semester_processor {

    function parse_term($term) {
        $year = (int)substr($term, 0, 4);

        $semester_code = substr($term, -2);

        switch ($semester_code) {
            case self::FALL1: return array($year - 1, 'First Fall');
            case self::FALL2: return array($year - 1, 'Second Fall');
            case self::SPRING1: return array($year, 'First Spring');
            case self::SPRING2: return array($year, 'Second Spring');
            case self::SUMMER1: return array($year, 'First Summer');
            case self::SUMMER2: return array($year - 1, 'Second Summer');
        }
    }


    /**
     * @param string $semesterName English name of the the semester
     * @return online_semester_data the value of the constant
     * representing the incoming semester name
     * @throws Exception if the incoming semester name is not valid
     */
    public static function semesterCode($semesterName){
        $map = array(
            'First Fall'    => self::Fall1,
            'Second Fall'   => self::FALL2,
            'First Spring'  => self::SPRING1,
            'Second Spring' => self::SPRING2,
            'First Summer'  => self::SUMMER1,
            'Second Summer' => self::SUMMER2,
            );
        if(!array_key_exists($semesterName, $map)){
            throw new Exception(sprintf("No such semester named '%d'.", $semesterName));
        }
        return $map[$semesterName];
    }

    function semesters($date_threshold) {

        if (is_numeric($date_threshold)) {
            $date_threshold = ues::format_time($date_threshold);
        }

        $xml_semesters = $this->invoke(array());

        $lookup = array();
        $semesters = array();

        foreach($xml_semesters->ROW as $xml_semester) {
            $code = $xml_semester->CODE_VALUE;

            $term = (string) $xml_semester->TERM_CODE;

            $session = (string) $xml_semester->SESSION;

            $date = $this->parse_date($xml_semester->CALENDAR_DATE);

            switch ($code) {
                case self::ONLINE_SEM:
                case self::ONLINE_FINAL:
                    $campus = 'ONLINE';
                    $starting = ($code == self::ONLINE_SEM);
                    break;
                case self::LAW_SEM:
                case self::LAW_FINAL:
                    $campus = 'LAW';
                    $starting = ($code == self::LAW_SEM);
                    break;
                default: continue;
            }

            if (!isset($lookup[$campus])) {
                $lookup[$campus] = array();
            }

            if ($starting) {
                list($year, $name) = $this->parse_term($term);

                $semester = new stdClass;
                $semester->year = $year;
                $semester->name = $name;
                $semester->campus = $campus;
                $semester->session_key = $session;
                $semester->classes_start = $date;
                $semesters[] = $semester;
            } else if (isset($lookup[$campus][$term][$session])) {

                $semester =& $lookup[$campus][$term][$session];
                $semester->grades_due = $date;

                // Make a semester end 21 days later for our post grade process
                $semester->grades_due += (21 * 24 * 60 * 60);
                if ($campus == 'LAW') {
                    $semester->grades_due += (25 * 24 * 60 * 60);
                }
            } else {
                continue;
            }

            if (!isset($lookup[$campus][$term])) {
                $lookup[$campus][$term] = array();
            }

            $lookup[$campus][$term][$session] = $semester;
        }

        unset($lookup);

        return $semesters;
    }
}

class online_courses extends online_source implements course_processor {

    function courses($semester) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $courses = array();

        $xml_courses = $this->invoke(array($semester_term, $semester->session_key));

        foreach ($xml_courses->ROW as $xml_course) {
            $department = (string) $xml_course->DEPT_CODE;
            $course_number = (string) $xml_course->COURSE_NBR;

            $law_not = ($semester->campus == 'LAW' and $department != 'LAW');
            $online_not = ($semester->campus == 'ONLINE' and $department == 'LAW');

            // Course is not semester applicable
            if ($law_not or $online_not) {
                continue;
            }

            $is_unique = function ($course) use ($department, $course_number) {
                return ($course->department != $department or
                    $course->cou_number != $course_number);
            };

            if (empty($course) or $is_unique($course)) {
                $course = new stdClass;
                $course->department = $department;
                $course->cou_number = $course_number;
                $course->course_type = (string) $xml_course->CLASS_TYPE;
                $course->course_first_year = (int) $xml_course->COURSE_NBR < 5200 ? 1 : 0;

                $course->fullname = (string) $xml_course->COURSE_TITLE;
                $course->course_grade_type = (string) $xml_course->GRADE_SYSTEM_CODE;

                $course->sections = array();

                $courses[] = $course;
            }

            $section = new stdClass;
            $section->sec_number = (string) $xml_course->SECTION_NBR;

            $course->sections[] = $section;
        }

        return $courses;
    }
}

class online_teachers_by_department extends online_teacher_format implements teacher_by_department {

    function teachers($semester, $department) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $teachers = array();

        // LAW teachers should NOT be processed on an incoming ONLINE semester
        if ($department == 'LAW' and $semester->campus == 'ONLINE') {
            return $teachers;
        }

        // Always use ONLINE campus code
        $campus = self::ONLINE_CAMPUS;

        $params = array($semester->session_key, $department, $semester_term, $campus);

        $xml_teachers = $this->invoke($params);

        foreach ($xml_teachers->ROW as $xml_teacher) {
            $teacher = $this->format_teacher($xml_teacher);

            // Section information
            $teacher->department = $department;
            $teacher->cou_number = (string) $xml_teacher->CLASS_COURSE_NBR;
            $teacher->sec_number = (string) $xml_teacher->SECTION_NBR;

            $teachers[] = $teacher;
        }

        return $teachers;
    }
}

class online_students_by_department extends online_student_format implements student_by_department {

    function students($semester, $department) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $campus = $semester->campus == 'ONLINE' ? self::ONLINE_CAMPUS : self::LAW_CAMPUS;

        $inst = $semester->campus == 'ONLINE' ? self::ONLINE_INST : self::LAW_INST;

        $params = array($campus, $semester_term, $department, $inst, $semester->session_key);

        $xml_students = $this->invoke($params);

        $students = array();
        foreach ($xml_students->ROW as $xml_student) {

            $student = $this->format_student($xml_student);

            // Section information
            $student->department = $department;
            $student->cou_number = (string) $xml_student->COURSE_NBR;
            $student->sec_number = (string) $xml_student->SECTION_NBR;

            $students[] = $student;
        }

        return $students;
    }
}

class online_teachers extends online_teacher_format implements teacher_processor {

    function teachers($semester, $course, $section) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $teachers = array();

        // LAW teachers should NOT be processed on an incoming ONLINE semester
        if ($course->department == 'LAW' and $semester->campus == 'ONLINE') {
            return $teachers;
        }

        $campus = self::ONLINE_CAMPUS;

        $params = array($course->cou_number, $semester->session_key,
            $section->sec_number, $course->department, $semester_term, $campus);

        $xml_teachers = $this->invoke($params);

        foreach ($xml_teachers->ROW as $xml_teacher) {

            $teachers[] = $this->format_teacher($xml_teacher);
        }

        return $teachers;
    }
}

class online_students extends online_student_format implements student_processor {

    function students($semester, $course, $section) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $campus = $semester->campus == 'ONLINE' ? self::ONLINE_CAMPUS : self::LAW_CAMPUS;

        $params = array($campus, $semester_term, $course->department,
            $course->cou_number, $section->sec_number, $semester->session_key);

        $xml_students = $this->invoke($params);

        $students = array();
        foreach ($xml_students->ROW as $xml_student) {

            $students[] = $this->format_student($xml_student);
        }

        return $students;
    }
}

class online_student_data extends online_source {

    function student_data($semester) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $params = array($semester_term);

        if ($semester->campus == 'ONLINE') {
            $params += array(1 => self::ONLINE_INST, 2 => self::ONLINE_CAMPUS);
        } else {
            $params += array(1 => self::LAW_INST, 2 => self::LAW_CAMPUS);
        }

        $xml_data = $this->invoke($params);

        $student_data = array();

        foreach ($xml_data->ROW as $xml_student_data) {
            $stud_data = new stdClass;

            $reg = trim((string) $xml_student_data->REGISTRATION_DATE);

            $stud_data->user_year = (string) $xml_student_data->YEAR_CLASS;
            $stud_data->user_college = (string) $xml_student_data->COLLEGE_CODE;
            $stud_data->user_major = (string) $xml_student_data->CURRIC_CODE;
            $stud_data->user_reg_status = $reg == 'null' ? NULL : $this->parse_date($reg);
            $stud_data->user_keypadid = (string) $xml_student_data->KEYPAD_ID;
            $stud_data->idnumber = trim((string)$xml_student_data->LSU_ID);

            $student_data[$stud_data->idnumber] = $stud_data;
        }

        return $student_data;
    }
}

class online_degree extends online_source {

    function student_data($semester) {
        $term = $this->encode_semester($semester->year, $semester->name);

        $params = array($term);

        if ($semester->campus == 'ONLINE') {
            $params += array(
                1 => self::ONLINE_INST,
                2 => self::ONLINE_CAMPUS
            );
        } else {
            $params += array(
                1 => self::LAW_INST,
                2 => self::LAW_CAMPUS
            );
        }

        $xml_grads = $this->invoke($params);

        $graduates = array();
        foreach($xml_grads->ROW as $xml_grad) {
            $graduate = new stdClass;

            $graduate->idnumber = (string) $xml_grad->LSU_ID;
            $graduate->user_degree = 'Y';

            $graduates[$graduate->idnumber] = $graduate;
        }

        return $graduates;
    }
}

class online_anonymous extends online_source {

    function student_data($semester) {
        if ($semester->campus == 'ONLINE') {
            return array();
        }

        $term = $this->encode_semester($semester->year, $semester->name);

        $xml_numbers = $this->invoke(array($term));

        $numbers = array();
        foreach ($xml_numbers->ROW as $xml_number) {
            $number = new stdClass;

            $number->idnumber = (string) $xml_number->LSU_ID;
            $number->user_anonymous_number = (string) $xml_number->LAW_ANONYMOUS_NBR;

            $numbers[$number->idnumber] = $number;
        }

        return $numbers;
    }
}

class online_sports extends online_source {

    function find_season($time) {
        $now = getdate($time);

        $june = 610;
        $dec = 1231;

        $cur = (int)(sprintf("%d%02d", $now['mon'], $now['mday']));

        if ($cur >= $june and $cur <= $dec) {
            return ($now['year']) . substr($now['year'] + 1, 2);
        } else {
            return ($now['year'] - 1) . substr($now['year'], 2);
        }
    }

    function student_data($semester) {
        if ($semester->campus == 'LAW') {
            return array();
        }

        $now = time();

        $xml_infos = $this->invoke(array($this->find_season($now)));

        $numbers = array();
        foreach ($xml_infos->ROW as $xml_info) {
            $number = new stdClass;

            $number->idnumber = (string) $xml_info->LSU_ID;
            $number->user_sport1 = (string) $xml_info->SPORT_CODE_1;
            $number->user_sport2 = (string) $xml_info->SPORT_CODE_2;
            $number->user_sport3 = (string) $xml_info->SPORT_CODE_3;
            $number->user_sport4 = (string) $xml_info->SPORT_CODE_4;

            $numbers[$number->idnumber] = $number;
        }

        return $numbers;
    }
}
