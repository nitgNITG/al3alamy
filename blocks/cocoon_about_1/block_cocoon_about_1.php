<?php
global $CFG;
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');

class block_cocoon_about_1 extends block_base
{
  // Declare first
  public function init()
  {
    $this->title = get_string('cocoon_about_1', 'block_cocoon_about_1');
  }

  // Declare second
  public function specialization()
  {
    global $CFG, $DB;
    include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');

    if (empty($this->config)) {
      $this->config = new \stdClass();
      $this->config->title = 'Our Values';
      $this->config->body['text'] = '<p class="color-black22 mt20">Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis,et quasi architecto beatae vitae dicta sunt explicabo.</p><p class="mt15">Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis,et quasi architecto beatae vitae dicta sunt explicabo.</p><p class="mt20">Nemo enim ipsam,voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia,consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.,Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, adipisci velit, sed quia non numquam eius modi tempora</p>';
      $this->config->style = 0;
    }
  }
  public function get_content()
  {
    global $CFG, $DB;
    require_once($CFG->libdir . '/filelib.php');
    if ($this->content !== null) {
      // return $this->content;
    }
    $this->content =  new stdClass;
    if (!empty($this->config->title)) {
      $this->content->title = $this->config->title;
    } else {
      $this->content->title = '';
    }
    if (!empty($this->config->body)) {
      $this->content->body = $this->config->body['text'];
    } else {
      $this->content->body = '';
    }
    if (!empty($this->config->style)) {
      $this->content->style = $this->config->style;
    } else {
      $this->content->style = 0;
    }
    if ($this->content->style == 1) {
      $class = '';
    } else {
      $class = 'ccn-row-reverse';
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($this->context->id, 'block_cocoon_about_1', 'content');
    $this->config->image = $CFG->wwwroot . '/theme/edumy/images/about/8.jpg';
    foreach ($files as $file) {
      $filename = $file->get_filename();
      if ($filename <> '.') {
        $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), null, $file->get_filepath(), $filename);
        $this->config->image =  $url;
      }
    }

    echo '<style>
        .ccn-row-reverse {
          flex-direction: row !important;
        }
        #top_title {
          border-bottom: 5px solid #C7AE72 !important;
          padding: 10px;
          width: 30%;
          color: #0E504D !important;
        }
        #body {
          color: #0E504D !important;
        }
        .funfact_two .details .timer,
        .block_cocoon_simple_counters .home_icon_box .icon,
        h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
          color: #0E504D !important;
        }
        </style>';
        $this->content->text = '
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
        <script>
            AOS.init();
        </script>
    
        <div class="container mt80" data-aos="fade-up" data-aos-duration="1200">
                <div class="row ' . $class . '">';
        
            $this->content->text .= '<div class="col-lg-6 d-flex justify-content-start" style="align-items: center;" data-aos="fade-left" data-aos-duration="1500" data-aos-delay="300">';
        
            $this->content->text .= '
            <div class="about_content">
                <h3 data-ccn="title" id="top_title">' . format_text($this->content->title, FORMAT_HTML, array('filter' => true)) . '</h3>
                <div data-ccn="body" id="body">' . format_text($this->content->body, FORMAT_HTML, array('filter' => true, 'noclean' => true)) . '</div>
            </div>
        </div>';

        if ($this->config->image) {
          $this->content->text .= '
            <div class="col-lg-6 d-flex justify-content-center" data-aos="fade-right" data-aos-duration="1500" data-aos-delay="300">
                <div class="about_thumb">
                    <img id="image" data-ccn="image" data-ccn-img="src" class="img-fluid" src="' . $this->config->image . '" alt="">
                </div>
            </div>';
        }
    
        $this->content->text .= '
            </div>
        </div>';
        return $this->content;

    
  }

  /**
   * Allow multiple instances in a single course?
   *
   * @return bool True if multiple instances are allowed, false otherwise.
   */
  public function instance_allow_multiple()
  {
    return true;
  }

  /**
   * Enables global configuration of the block in settings.php.
   *
   * @return bool True if the global configuration is enabled.
   */
  function has_config()
  {
    return true;
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
}
