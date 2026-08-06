<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/course_handler/ccn_course_handler.php');

class block_cocoon_courses_slider extends block_base
{

  public function init()
  {
    $this->title = get_string('pluginname', 'block_cocoon_courses_slider');
  }

  function specialization()
  {
    global $CFG, $DB;

    $ccnCourseHandler = new ccnCourseHandler();
    $ccnCourses = $ccnCourseHandler->ccnGetExampleCoursesIds(8);

    include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');
    if (empty($this->config)) {
      $this->config = new \stdClass();
      $this->config->title = 'Browse Our Top Courses';
      $this->config->subtitle = 'Cum doctus civibus efficiantur in imperdiet deterruisCum doctus civibus efficiantur in imperdiet deterruisset.';
      $this->config->hover_text = 'Preview Course';
      $this->config->hover_accent = 'Top Seller';
      $this->config->button_text = 'View all courses';
      $this->config->button_link = $CFG->wwwroot . '/course';
      $this->config->course_image = '1';
      $this->config->description = '0';
      $this->config->price = '1';
      $this->config->enrol_btn = '0';
      $this->config->enrol_btn_text = 'Buy Now';
      $this->config->courses = $ccnCourses;
    }
  }

  public function get_content()
  {
    global $CFG, $DB, $COURSE, $USER, $PAGE;

    if ($this->content !== null) {
      return $this->content;
    }

    if (empty($this->instance)) {
      $this->content = '';
      return $this->content;
    }

    $this->content = new stdClass();
    $this->content->items = array();
    $this->content->icons = array();
    $this->content->footer = '';
    $this->content->text = '';
    if (!empty($this->config->title)) {
      $this->content->title = $this->config->title;
    } else {
      $this->content->title = '';
    }
    if (!empty($this->config->subtitle)) {
      $this->content->subtitle = $this->config->subtitle;
    } else {
      $this->content->subtitle = '';
    }
    if (!empty($this->config->hover_text)) {
      $this->content->hover_text = $this->config->hover_text;
    } else {
      $this->content->hover_text = '';
    }
    if (!empty($this->config->hover_accent)) {
      $this->content->hover_accent = $this->config->hover_accent;
    } else {
      $this->content->hover_accent = '';
    }
    if (!empty($this->config->description)) {
      $this->content->description = $this->config->description;
    } else {
      $this->content->description = '0';
    }
    if (!empty($this->config->course_image)) {
      $this->content->course_image = $this->config->course_image;
    } else {
      $this->content->course_image = '';
    }
    if (!empty($this->config->price)) {
      $this->content->price = $this->config->price;
    } else {
      $this->content->price = '0';
    }
    if (!empty($this->config->enrol_btn)) {
      $this->content->enrol_btn = $this->config->enrol_btn;
    } else {
      $this->content->enrol_btn = '0';
    }
    if (!empty($this->config->enrol_btn_text)) {
      $this->content->enrol_btn_text = $this->config->enrol_btn_text;
    } else {
      $this->content->enrol_btn_text = '';
    }

    if (
      isset($this->content->description) &&
      $this->content->description != '0'
    ) {
      $ccnBlockShowDesc = 1;
    } else {
      $ccnBlockShowDesc = 0;
    }

    if (
      isset($this->content->course_image) &&
      $this->content->course_image == '1'
    ) {
      $ccnBlockShowImg = 1;
    } else {
      $ccnBlockShowImg = 0;
    }
    if (
      isset($this->content->enrol_btn) &&
      isset($this->content->enrol_btn_text) &&
      $this->content->enrol_btn == '1'
    ) {
      $ccnBlockShowEnrolBtn = 1;
    } else {
      $ccnBlockShowEnrolBtn = 0;
    }
    if (
      isset($this->content->price) &&
      $this->content->price == '1'
    ) {
      $ccnBlockShowPrice = 1;
    } else {
      $ccnBlockShowPrice = 0;
    }

    if (
      $PAGE->theme->settings->coursecat_enrolments != 1 ||
      $PAGE->theme->settings->coursecat_announcements != 1 ||
      isset($this->content->price) ||
      isset($this->content->enrol_btn_text) &&
      // ccnBreak
      ($this->content->price == '1' || $this->content->enrol_btn == '1')
    ) {
      $ccnBlockShowBottomBar = 1;
      $topCoursesClass = 'ccnWithFoot';
    } else {
      $ccnBlockShowBottomBar = 0;
      $topCoursesClass = '';
    }

    if (!empty($this->content->description) && $this->content->description == '7') {
      $maxlength = 500;
    } elseif (!empty($this->content->description) && $this->content->description == '6') {
      $maxlength = 350;
    } elseif (!empty($this->content->description) && $this->content->description == '5') {
      $maxlength = 200;
    } elseif (!empty($this->content->description) && $this->content->description == '4') {
      $maxlength = 150;
    } elseif (!empty($this->content->description) && $this->content->description == '3') {
      $maxlength = 100;
    } elseif (!empty($this->content->description) && $this->content->description == '2') {
      $maxlength = 50;
    } else {
      $maxlength = null;
    }

    if (!empty($this->config->courses)) {
      $coursesArr = $this->config->courses;
      $courses = new stdClass();
      foreach ($coursesArr as $key => $course) {
        $courseObj = new stdClass();
        $courseObj->id = $course;
        $courses->$course = $courseObj;
      }
    }

    // ── Unique IDs for this block instance ───────────────────────────────────
    $iid      = $this->instance->id;
    $strip_id = 'ccn-cslider-strip-' . $iid;

    // ── RTL-aware arrow characters (same convention as gallery/video blocks) ─
    $arrow_prev = right_to_left() ? '&#8250;' : '&#8249;'; // › or ‹
    $arrow_next = right_to_left() ? '&#8249;' : '&#8250;'; // ‹ or ›

    echo '<style>
        /* ── Courses block shared card styles ── */
        .main-title h3 {
          color: #C9A227;
          text-align: center;
          font-size: 40px;
        }
        .top_courses .details .tc_content {
          padding: 20px;
          color: #C9A227;
          text-align: center;
          font-size: 30px;
          font-style: normal;
          font-weight: 400;
          line-height: 140%;
        }
        .top_courses .thumb:before {
          background-color: rgb(0 0 0 / 5%) !important;
        }
        .top_courses .details .tc_content h5 {
          color: #C9A227;
        }
        .img-whp {
          border-radius: 10px;
        }
        .top_courses {
          border: none !important;
          border-radius: 10px;
        }
        .top_courses:hover .thumb .overlay:before {
          border-radius: 10px !important;
        }

        /* ── Flex-scroll slider (instance ' . $iid . ') ── */
        .ccn-cslider-wrap-' . $iid . ' {
            position: relative;
            padding: 0 40px;
        }
        .ccn-cslider-strip-' . $iid . ' {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 6px;
        }
        .ccn-cslider-strip-' . $iid . '::-webkit-scrollbar { display: none; }

        /* 4 items on desktop (col-3 = 25%), 2 on tablet, 1 on mobile */
        .ccn-cslider-item-' . $iid . ' {
            scroll-snap-align: start;
            flex: 0 0 calc(25% - 15px);
            min-width: 0;
        }
        @media (max-width: 900px) {
            .ccn-cslider-item-' . $iid . ' { flex: 0 0 calc(50% - 10px); }
        }
        @media (max-width: 576px) {
            .ccn-cslider-wrap-' . $iid . ' { padding: 0 32px; }
            .ccn-cslider-item-' . $iid . ' { flex: 0 0 85%; }
        }

        /* Course image — fill the thumb container, centered & cropped */
        .ccn-cslider-item-' . $iid . ' .thumb {
            display: block !important;
            width: 100% !important;
            aspect-ratio: 4 / 3 !important;
            overflow: hidden !important;
            border-radius: 10px !important;
            position: relative !important;
            flex-direction: unset !important;
        }
        .ccn-cslider-item-' . $iid . ' .thumb .img-whp,
        .ccn-cslider-item-' . $iid . ' .thumb img {
            display: block !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center center !important;
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
            max-width: none !important;
            max-height: none !important;
            border-radius: 0 !important;
            float: none !important;
        }
        /* Button — fit content width, centered */
        .ccn-cslider-item-' . $iid . ' .tc_footer {
            display: block !important;
            text-align: center !important;
            padding: 8px 12px !important;
            border-radius: 0 0 10px 10px !important;
        }
        .ccn-cslider-item-' . $iid . ' .tc_footer a,
        .ccn-cslider-item-' . $iid . ' .tc_footer .tc_enrol_btn {
            display: inline-block !important;
            float: none !important;
            width: auto !important;
        }

        /* nav arrows — identical style to gallery / video blocks */
        .ccn-cslider-wrap-' . $iid . ' .ccn-vs-nav {
            position: absolute;
            top: 30%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,.9);
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: background .2s, transform .2s;
            padding: 0;
        }
        .ccn-cslider-wrap-' . $iid . ' .ccn-vs-nav:hover {
            background: #fff;
            transform: translateY(-50%) scale(1.1);
        }
        .ccn-cslider-wrap-' . $iid . ' .ccn-vs-prev { left:  2px; }
        .ccn-cslider-wrap-' . $iid . ' .ccn-vs-next { right: 2px; }
        </style>';

    echo '<script>
function ccnCSliderScroll_' . $iid . '(dir) {
    var strip = document.getElementById("' . $strip_id . '");
    var item  = strip.querySelector(".ccn-cslider-item-' . $iid . '");
    var gap   = 20;
    var step  = item ? item.offsetWidth + gap : strip.clientWidth * 0.9;
    strip.scrollBy({ left: dir * step, behavior: "smooth" });
}

/* Show/hide arrows based on scroll position */
function ccnCSliderArrows_' . $iid . '() {
    var strip = document.getElementById("' . $strip_id . '");
    if (!strip) return;
    var wrap  = strip.closest(".ccn-cslider-wrap-' . $iid . '");
    if (!wrap) return;
    var prev  = wrap.querySelector(".ccn-vs-prev");
    var next  = wrap.querySelector(".ccn-vs-next");
    var atStart = strip.scrollLeft <= 2;
    var atEnd   = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 2;
    if (prev) prev.style.visibility = atStart ? "hidden" : "visible";
    if (next) next.style.visibility = atEnd   ? "hidden" : "visible";
}

/* Center cards when they do not fill the strip */
function ccnCSliderAlign_' . $iid . '() {
    var strip = document.getElementById("' . $strip_id . '");
    if (!strip) return;
    strip.style.justifyContent = (strip.scrollWidth <= strip.clientWidth + 2)
        ? "center" : "";
    ccnCSliderArrows_' . $iid . '();
}

window.addEventListener("load",   ccnCSliderAlign_' . $iid . ');
window.addEventListener("resize", ccnCSliderAlign_' . $iid . ');
document.addEventListener("DOMContentLoaded", function() {
    var strip = document.getElementById("' . $strip_id . '");
    if (strip) strip.addEventListener("scroll", ccnCSliderArrows_' . $iid . ');
});
</script>';

    if (!empty($this->config->style) && $this->config->style == 1) {  //background
      $this->content->text .= '
            <section class="popular-courses bgc-thm2">
          		<div class="container-fluid style2">
          			<div class="row">
          				<div class="col-lg-6 offset-lg-3">
          					<div class="main-title text-center">';
      $this->content->text .= '<h3 class="mt0 color-white" data-ccn="title" style="font-size: 40px;">' . format_text($this->content->title, FORMAT_HTML, array('filter' => true)) . '</h3>';
      if (!empty($this->content->subtitle)) {
        $this->content->text .= '<h5 class="color-white" data-ccn="subtitle">' . format_text($this->content->subtitle, FORMAT_HTML, array('filter' => true)) . '</h5>';
      }
      $this->content->text .= '
          					</div>
          				</div>
          			</div>
          			<div class="row">
          				<div class="col-lg-12">
          					<div class="popular_course_slider">';
      if (!empty($this->config->courses)) {
        $chelper = new coursecat_helper();
        foreach ($courses as $course) {
          if ($DB->record_exists('course', array('id' => $course->id))) {

            $ccnCourseHandler = new ccnCourseHandler();
            $ccnCourse = $ccnCourseHandler->ccnGetCourseDetails($course->id);

            $ccnCourseDescription = $ccnCourseHandler->ccnGetCourseDescription($course->id, $maxlength);


            $this->content->text .= '
                        <div class="item">
            							<div class="top_courses home2 mb0 ' . $topCoursesClass . '">';
            if ($ccnBlockShowImg) {
              $this->content->text .= '
            								<div class="thumb">
            									' . $ccnCourse->ccnRender->coverImage . '
            									<a class="overlay" href="' . $ccnCourse->url . '">';
              if ($this->content->hover_accent) {
                $this->content->text .= '<div class="tag" data-ccn="hover_accent">' . format_text($this->content->hover_accent, FORMAT_HTML, array('filter' => true)) . '</div>';
              }
              if ($this->content->hover_text) {
                $this->content->text .= '  <div class="tc_preview_course" data-ccn="hover_text" href="' . $ccnCourse->url . '">' . format_text($this->content->hover_text, FORMAT_HTML, array('filter' => true)) . '</div>';
              }
              $this->content->text .= '
                              </a>
            								</div>';
            }
            $this->content->text .= '
            								<div class="details">
            									<div class="tc_content">';
            $this->content->text .=  $ccnCourse->ccnRender->title;
            if ($ccnBlockShowDesc) {
              $this->content->text .= '<p>' . $ccnCourseDescription . '</p>';
            }
            $this->content->text .= $ccnCourse->ccnRender->starRating;

            $this->content->text .= '
            									</div>
                              </div>';
            if ($ccnBlockShowBottomBar == 1) {
              $this->content->text .= '
            									<div class="tc_footer" style="background-color: #C9A227;text-align: center;
                              display: flex;
                              justify-content: center;">
                              ';

              if ($ccnBlockShowEnrolBtn) {
                $this->content->text .= '<a href="' . $ccnCourse->url . '" class="tc_enrol_btn data-ccn="enrol_btn_text" float-right" style="color: #fff;
                                  font-size: 30px;
                                  font-weight: 700;">' . format_text($this->content->enrol_btn_text, FORMAT_HTML, array('filter' => true)) . '</a>';
              }
              if ($ccnBlockShowPrice) {
                $this->content->text .= '<div class="tc_price float-right">' . $ccnCourse->price . '</div>';
              }
              $this->content->text .= '
            									</div>';
            }
            $this->content->text .= '
            							</div>
            						</div>';
          }
        }
      }
      $this->content->text .= '
                               </div>
    				                 </div>
    			                 </div>
                         </div>
    	                 </section>';
    } else {
      // ── Flex-scroll slider (no OWL dependency) ──────────────────────────────
      $this->content->text .= '
<section class="features-course pb20 pt20">
  <div class="container">
    <div class="row">
      <div class="col-lg-6 offset-lg-3">
        <div class="main-title text-center">
          ' . (!empty($this->content->title)    ? '<h3 class="mb0 mt0" data-ccn="title" style="font-size:40px;">'                       . format_text($this->content->title,    FORMAT_HTML, array('filter' => true)) . '</h3>' : '') . '
          ' . (!empty($this->content->subtitle) ? '<h5 data-ccn="subtitle" style="font-size:18px !important;color:#C9A227;">' . format_text($this->content->subtitle, FORMAT_HTML, array('filter' => true)) . '</h5>' : '') . '
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12">
        <div class="ccn-cslider-wrap-' . $iid . '">

          <button class="ccn-vs-nav ccn-vs-prev"
                  onclick="ccnCSliderScroll_' . $iid . '(-1)"
                  aria-label="Previous">' . $arrow_prev . '</button>

          <div class="ccn-cslider-strip-' . $iid . '" id="' . $strip_id . '">
';

      if (!empty($this->config->courses)) {
        $chelper = new coursecat_helper();
        foreach ($courses as $course) {
          if ($DB->record_exists('course', array('id' => $course->id))) {

            $ccnCourseHandler = new ccnCourseHandler();
            $ccnCourse = $ccnCourseHandler->ccnGetCourseDetails($course->id);

            $ccnCourseDescription = $ccnCourseHandler->ccnGetCourseDescription($course->id, $maxlength);

            $this->content->text .= '
            <div class="ccn-cslider-item-' . $iid . '">
              <div class="top_courses ' . $topCoursesClass . '">';

            if ($ccnBlockShowImg) {
              $this->content->text .= '
                <div class="thumb">
                  ' . $ccnCourse->ccnRender->coverImage . '
                  <a class="overlay" href="' . $ccnCourse->url . '">';
              if (!empty($this->content->hover_accent)) {
                $this->content->text .= '<div class="tag" data-ccn="hover_accent">' . format_text($this->content->hover_accent, FORMAT_HTML, array('filter' => true)) . '</div>';
              }
              if (!empty($this->content->hover_text)) {
                $this->content->text .= '<div class="tc_preview_course" data-ccn="hover_text">' . format_text($this->content->hover_text, FORMAT_HTML, array('filter' => true)) . '</div>';
              }
              $this->content->text .= '</a>
                </div>';
            }

            $this->content->text .= '
              <div class="details">
                <div class="tc_content">';
            $this->content->text .= $ccnCourse->ccnRender->title;
            if ($ccnBlockShowDesc) {
              $this->content->text .= '<p>' . $ccnCourseDescription . '</p>';
            }
            $this->content->text .= $ccnCourse->ccnRender->starRating;
            $this->content->text .= '
                </div>
              </div>';

            if ($ccnBlockShowBottomBar == 1) {
              $this->content->text .= '
              <div class="tc_footer" style="background-color:#C9A227;">
                ' . ($ccnBlockShowEnrolBtn ? '<a href="' . $ccnCourse->url . '" class="tc_enrol_btn float-right" data-ccn="enrol_btn_text" style="color:#fff;font-size:25px;font-weight:700;">' . $this->content->enrol_btn_text . '</a>' : '') . '
                ' . ($ccnBlockShowPrice ? '<div class="tc_price float-right">' . $ccnCourse->price . '</div>' : '') . '
              </div>';
            }

            $this->content->text .= '
            </div>
          </div>';
          }
        }
      }

      $this->content->text .= '
          </div><!-- /.ccn-cslider-strip -->

          <button class="ccn-vs-nav ccn-vs-next"
                  onclick="ccnCSliderScroll_' . $iid . '(1)"
                  aria-label="Next">' . $arrow_next . '</button>

        </div><!-- /.ccn-cslider-wrap -->
      </div>
    </div>
  </div>
</section>
';
    }

    return $this->content;
  }

  function applicable_formats()
  {
    $ccnBlockHandler = new ccnBlockHandler();
    return $ccnBlockHandler->ccnGetBlockApplicability(array('all'));
  }
  public function html_attributes()
  {
    global $CFG;
    $attributes = parent::html_attributes();
    include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
    return $attributes;
  }


  public function instance_allow_multiple()
  {
    return true;
  }

  public function has_config()
  {
    return false;
  }

  public function cron()
  {
    return true;
  }
}
