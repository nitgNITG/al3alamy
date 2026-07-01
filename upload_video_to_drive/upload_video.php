<?php
require_once('../config.php'); // تأكد من استبداله بالمسار الصحيح لملف التكوين في Moodle

// التأكد من وجود بيانات المستخدم المسجل الحالي
require_login();

// التحقق من أن الطريقة المستخدمة هي POST وتحديد الحقول المطلوبة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $videoId = $_POST['video_id'];
  $videoName = $_POST['video_name'];
  $resource2_id = $_POST['resource2_id'];

  $video_url = str_replace("view?usp=sharing", "preview", "" . $videoId . "");
  //$video_url = "hello";

  // تجهيز البيانات للحفظ في قاعدة بيانات Moodle
  $data = new stdClass();
  $data->user_id = $USER->id; // تأكد من وجود متغير $USER المتاح والذي يحتوي على معرف المستخدم الحالي
  $data->resource2_id = $resource2_id;
  $data->video_id = $video_url;
  $data->video_name = $videoName;

  // حفظ بيانات الفيديو في جدول mdl_drive_videos
  $recordId = $DB->insert_record('drive_videos', $data);

  $course_module_id = $DB->get_field("course_modules", "id", array('instance' => $resource2_id, 'module' => 26));

  //echo $video_url ;


  if ($recordId) {
    // تم حفظ البيانات بنجاح
    $successMessage = "تم حفظ بيانات الفيديو بنجاح.";
    echo "<script>
              alert('$successMessage');
              window.location.href = '{$CFG->wwwroot}/mod/resource2/view.php?id=$course_module_id';
            </script>";
  } else {
    // حدث خطأ أثناء حفظ البيانات
    $errorMessage = "حدث خطأ أثناء حفظ بيانات الفيديو.";
    echo "<script>
              alert('$errorMessage');
              window.location.href = '{$CFG->wwwroot}/';
            </script>";
  }
} else {
  // إذا كانت الطريقة غير صحيحة
  echo "طريقة الطلب غير صحيحة.";
}
