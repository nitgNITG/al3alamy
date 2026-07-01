<?php

require_once("../config.php");
require_once($CFG->dirroot . '/group/lib.php');

//change code status from used to not used
if (isset($_POST['update'])) {
    try {
        $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['update']));
        $used = ($_POST['temp'] == 1) ? 1 : 0;

        if (!empty($getCode->empty2)) {
            $group = empty($getCode->empty1) ? $getGroupId->groupid : $getCode->empty1;
            groups_remove_member($group, $getCode->empty2);
            groups_remove_member($getCodeGroupId->id, $getCode->empty2);
        }

        // Assuming you have the $used, $getCode->id, $getCode->empty1, and $getCode->empty2 variables already set

        $sql = "UPDATE {groups_attendence_codes} 
        SET used = :used, 
            empty1 = :empty1, 
            empty2 = :empty2 
        WHERE id = :id";

        $params = array(
            'used' => $used,
            'empty1' => 0,
            'empty2' => 0,
            'id' => $getCode->id
        );

        $DB->execute($sql, $params);


        $getCode = $DB->get_record('groups_attendence_codes', array('id' => $_POST['update']));
        echo $getCode->timemodified;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage(); // Consider logging this instead of echoing in production
    }
}
