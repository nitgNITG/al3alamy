<?php
require_once("../config.php");
require_once($CFG->dirroot . '/group/lib.php');
/*
New tables for codes:
    groups_attendence_codes 
    groups_attendence_patch

*/

//if teacher dropdown list changed get courses
if (isset($_POST['teacher'])) {
    $teacherID = $_POST['teacher'];
    $userEnroledCourses = enrol_get_users_courses($teacherID); //Moodle API to get teacher enrolled courses
    $user = $DB->get_record('user', array('id' => $teacherID)); //Moodle API to get user data
    echo json_encode(['data' => array_values($userEnroledCourses), 'teachername' => $user->firstname . ' ' . $user->lastname]);
} 
//if course dropdown list changed get groups
else if (isset($_POST['course'])) {

    try {

        $groups = $DB->get_records('groups', array('courseid' => $_POST['course']));
        $course = $DB->get_record('course', array('id' => $_POST['course']));
        echo json_encode(['data' => $groups, 'coursename' => $course->fullname]);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
} 

//if patch dropdown list changed filter on batch name
elseif(isset($_POST['centername'])){
    $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $_POST['centername']));
    $dataReturned=array();
    foreach($getPatches as $getPatch){
        $checkCode=$DB->get_record('groups_attendence_codes',array('patchid' => $getPatch->id));
if(!empty($checkCode)){
    $dataReturned[]=array('centername'=>$getPatch->centername);
}
    }
    echo json_encode(['data' => $dataReturned]);

}
//Fill table
else if (isset($_POST['dataCourse'])) {
    $course = $_POST['dataCourse'];
    $group = $_POST['dataGroup'];
    $teacher = $_POST['dataTeacher'];
    $temp = $_POST['temp'];//check if user choose a group
    $state = $_POST['state']; //All, used, not used
    $centerName = $_POST['centerName']; //patch name
    $stateData = '';
    $out = array();
    $time = 0;
    $used_codes = 0;
    //If All Groups selected
    if ($temp == 0) {
        if($centerName!="0"){
            $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course,'centername'=>$centerName));

        }else{
            $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course));

        }
        foreach ($getPatches as $patch) {
            $fullName='-';
            $email='-';
            if ($state == '1') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id, 'used' => 1));
            } else if ($state == '2') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id, 'used' => 0));
            } else {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id));
            }
            $group_name = $DB->get_record('groups', array('id' => $patch->groupid));
            $used_group_name="-";
            //Data displayed for each code
            foreach ($getCodes as $code) {
                if ($code->used == 1) {
                    $time = $code->timemodified;
                    $used_codes++;
                    $user=$DB->get_record('user',array('id'=>$code->empty2));
                    $fullName=$user->firstname.' '.$user->lastname;
                    $email=$user->email;
                    $used_group_name = $DB->get_record('groups', array('id' => $code->empty1));

                } else {
                    $time = 0;
                    $fullName='-';
                    $email='-';
                    $used_group_name="-";
                }
          
                //Return to dashboard.php
                $out[] = array('id' => $code->id,'used_group_name'=>$used_group_name->name, 'groupname' => $group_name->name, 'code' => $code->code, 'used' => $code->used, 'fullname'=>$fullName,'email'=>$email,'time' => $time);
            }
        }
        echo json_encode(['data' => $out, 'codes' => count($out), 'used_codes' => $used_codes]);
    } 
    //If specific group selected
    else {
        if($centerName!="0"){
            $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course, 'groupid' => $group,'centername'=>$centerName));

        }else{
            $getPatches = $DB->get_records('groups_attendence_patch', array('courseid' => $course, 'groupid' => $group));
        }
        $used_group_name="-";
        foreach ($getPatches as $patch) {
            $fullName='-';
            $email='-';
            if ($state == '1') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id, 'used' => 1));
            } else if ($state == '2') {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id, 'used' => 0));
            } else {
                $getCodes = $DB->get_records('groups_attendence_codes', array('patchid' => $patch->id));
            }
            $group_name = $DB->get_record('groups', array('id' => $patch->groupid));
            foreach ($getCodes as $code) {
                if ($code->used == 1) {
                    $time = $code->timemodified;
                    $used_codes++;
                    $user=$DB->get_record('user',array('id'=>$code->empty2));
                    $fullName=$user->firstname.' '.$user->lastname;
                    $email=$user->email;
                    $used_group_name = $DB->get_record('groups', array('id' => $code->empty1));

                } else {
                    $time = 0;
                    $fullName='-';
                    $email='-';
                }
              
                // $out[] = array('id' => $code->id, 'groupname' => $group_name->name, 'code' => $code->code, 'used' => $code->used, 'time' => $time);
                $out[] = array('id' => $code->id,'used_group_name'=>$used_group_name->name, 'groupname' => $group_name->name, 'code' => $code->code, 'used' => $code->used, 'fullname'=>$fullName,'email'=>$email,'time' => $time);

            }
        }
        echo json_encode(['data' => $out, 'codes' => count($out), 'used_codes' => $used_codes]);

        // echo json_encode( $temp );

    }
} 

//if user clicked Delete Button
else if (isset($_POST['delete'])) {
    $id = $_POST['delete'];
    $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['delete']));
    $used = 0;
    if ($getCode->used == 1) {
        $used = 1;
    }

    $DB->delete_records('groups_attendence_codes', array('id' => $id));
    echo $used;
} 
//change code status from used to not used
//change code status from used to not used
else if (isset($_POST['update'])) {
    $codeId = $_POST['update'];
    $temp = $_POST['temp'];

    // Fetch code record
    $getCode = $DB->get_record('groups_attendence_codes', array('id' => $codeId));
    if (!$getCode) {
        echo "Code record not found.";
        exit;
    }

    // Fetch group patch record
    $getGroupId = $DB->get_record('groups_attendence_patch', array('id' => $getCode->patchid));
    if (!$getGroupId) {
        echo "Group patch record not found.";
        exit;
    }

    // Determine used status
    $used = ($temp == 1) ? 1 : 0;
    $group = $getCode->empty1;

    // Remove user from groups if applicable
    if (!empty($getCode->empty2)) { // Check if there is a user
        if (empty($getCode->empty1)) {
            $group = $getGroupId->groupid;
        }
        
        // Attempt to fetch the "Code Users" group
        $getCodeGroupId = $DB->get_record('groups', array('courseid' => $getGroupId->courseid, 'name' => "Code Users"));
        if ($getCodeGroupId) {
            // Remove user from the groups if the group is found
            groups_remove_member($group, $getCode->empty2);
            groups_remove_member($getCodeGroupId->id, $getCode->empty2);
        } else {
            // Log a message if the "Code Users" group is not found but continue with the update
            error_log("Group for 'Code Users' not found for courseid: {$getGroupId->courseid}. User removal skipped.");
        }
    }

    // Prepare SQL query to update the record directly
    $sql = "UPDATE {groups_attendence_codes} 
            SET used = :used, 
                empty1 = :empty1, 
                empty2 = :empty2 
            WHERE id = :id";

    $params = array(
        'used' => $used,
        'empty1' => 0,
        'empty2' => 0,
        'id' => $codeId
    );

    try {
        $DB->execute($sql, $params);

        // Fetch updated record
        $getCode = $DB->get_record('groups_attendence_codes', array('id' => $codeId));
        if ($getCode) {
            $time = $getCode->timemodified;
            echo $time;
        } else {
            echo "Updated code record not found.";
        }
    } catch (Exception $e) {
        echo "Error updating code record: " . $e->getMessage();
    }
}


