<?php
global $CFG;
require_once($CFG->dirroot. '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
require_once($CFG->dirroot. '/theme/edumy/ccn/general_handler/ccnLazy.php');

class block_cocoon_featured_video extends block_base
{
    public function init()
    {
        $this->title = get_string('pluginname', 'block_cocoon_featured_video');
    }

    public function specialization()
    {
        global $CFG, $DB;
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');
        if (empty($this->config)) {
            $this->config = new \stdClass();
            $this->config->videosnumber   = '1';
            $this->config->video_url      = 'https://youtu.be/UdDwKI4DcGw';
            $this->config->slidesnumber   = '4';
            $this->config->title1         = 'Creative Events';
            $this->config->subtitle1      = '749';
            $this->config->subtitle_2_1   = '';
            $this->config->title2         = 'Skilled Tutor';
            $this->config->subtitle2      = '832';
            $this->config->subtitle_2_2   = '';
            $this->config->title3         = 'Online Courses';
            $this->config->subtitle3      = '35';
            $this->config->subtitle_2_3   = 'k';
            $this->config->title4         = 'People Worldwide';
            $this->config->subtitle4      = '92';
            $this->config->subtitle_2_4   = 'k';
            $this->config->color_bfbg     = '#f9f9f9';
            $this->config->color_title    = '#0067da';
            $this->config->color_subtitle = '#222222';
            $this->config->color_overlay  = 'rgb(34, 34, 34, .4)';
        }
    }

    public function get_content()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;

        // ── Colors ��─────────────────────────────────────────────────────────
        $color_bfbg     = !empty($this->config->color_bfbg)     ? $this->config->color_bfbg     : '#f9f9f9';
        $color_title    = !empty($this->config->color_title)    ? $this->config->color_title    : '#0067da';
        $color_subtitle = !empty($this->config->color_subtitle) ? $this->config->color_subtitle : '#222222';
        $color_overlay  = !empty($this->config->color_overlay)  ? $this->config->color_overlay  : 'rgb(34, 34, 34, .4)';

        $this->content->color_bfbg     = $color_bfbg;
        $this->content->color_title    = $color_title;
        $this->content->color_subtitle = $color_subtitle;
        $this->content->color_overlay  = $color_overlay;

        $ccnLazy = new ccnLazy();

        // ── Counter data ─────────��────────────────────────────��──────────────
        $data = new stdClass();
        if (!empty($this->config) && is_object($this->config)) {
            $data = $this->config;
            $data->slidesnumber = isset($data->slidesnumber) && is_numeric($data->slidesnumber)
                ? (int)$data->slidesnumber : 4;
        } else {
            $data->slidesnumber = 4;
        }

        // ── Filemanager image (legacy slot-1 image) ──────────────────────────
        $default_image = $CFG->wwwroot . '/theme/edumy/images/ccnBgMd.png';
        $fm_image = $default_image;
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'block_cocoon_featured_video', 'content');
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if ($filename !== '.') {
                $fm_image = moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(),
                    $file->get_filearea(), null,
                    $file->get_filepath(), $filename
                );
            }
        }
        $this->content->image = $fm_image;

        // ── Number of videos ────────────────────────────────────────────────
        $videosnumber = !empty($this->config->videosnumber) ? (int)$this->config->videosnumber : 1;
        $videosnumber = max(1, min(8, $videosnumber));

        // ── Build slides array ──────────────���────────────────────────���───────
        $slides = [];
        for ($i = 1; $i <= $videosnumber; $i++) {
            // Video URL: slot 1 falls back to legacy 'video_url' key
            $video_url = '';
            if (!empty($this->config->{'video_url_' . $i})) {
                $video_url = $this->config->{'video_url_' . $i};
            } elseif ($i === 1 && !empty($this->config->video_url)) {
                $video_url = $this->config->video_url;
            }

            // Image: explicit URL field first, then filemanager image for slot 1, then default
            $image_url = '';
            if (!empty($this->config->{'video_image_' . $i})) {
                $image_url = $this->config->{'video_image_' . $i};
            } elseif ($i === 1) {
                $image_url = $fm_image;
            } else {
                $image_url = $fm_image; // fallback to slot-1 image
            }

            $slides[] = [
                'video_url' => $video_url,
                'image_url' => $image_url,
            ];
        }

        // ── Build video HTML ─────────────────────────────────────────────────
        $block_id  = 'ccnFeatVideo_' . $this->instance->id;
        $video_html = '';

        if (count($slides) === 1) {
            // Single video — original layout
            $slide = $slides[0];
            $video_html = '
            <div class="gallery_item home13 mt80">
                <img class="img-fluid img-circle-rounded" alt=""
                     data-ccn="image" data-ccn-img="content"
                     ' . $ccnLazy->ccnLazyImage($slide['image_url']) . '>
                <div class="gallery_overlay"
                     data-ccn-c="color_overlay"
                     data-ccn-co="ccnBg"
                     data-ccn-cv="' . htmlspecialchars($color_overlay) . '"
                     style="background-color:' . htmlspecialchars($color_overlay) . ';">
                    <a class="popup-img popup-youtube home_post_overlay_icon bgc-theme8"
                       href="' . htmlspecialchars($slide['video_url']) . '">
                        <div class="video_popup_btn">
                            <span class="flaticon-play-button-1"></span>
                        </div>
                    </a>
                </div>
            </div>';
        } else {
            // Multiple videos — Bootstrap carousel
            $indicators = '';
            $items      = '';
            foreach ($slides as $idx => $slide) {
                $active      = ($idx === 0) ? ' active' : '';
                $indicators .= '<li data-target="#' . $block_id . '" data-slide-to="' . $idx . '" class="' . trim($active) . '"></li>';
                $items      .= '
                <div class="carousel-item' . $active . '">
                    <div class="gallery_item home13">
                        <img class="img-fluid img-circle-rounded d-block w-100" alt=""
                             src="' . htmlspecialchars($slide['image_url']) . '">
                        <div class="gallery_overlay"
                             style="background-color:' . htmlspecialchars($color_overlay) . ';">
                            <a class="popup-img popup-youtube home_post_overlay_icon bgc-theme8"
                               href="' . htmlspecialchars($slide['video_url']) . '">
                                <div class="video_popup_btn">
                                    <span class="flaticon-play-button-1"></span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>';
            }
            $video_html = '
            <div id="' . $block_id . '" class="carousel slide" data-ride="carousel" data-interval="false">
                <ol class="carousel-indicators">' . $indicators . '</ol>
                <div class="carousel-inner">' . $items . '</div>
                <a class="carousel-control-prev" href="#' . $block_id . '" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next" href="#' . $block_id . '" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>
            </div>';
        }

        // ── Full content ────────────────────────────���────────────────────────
        $this->content->text = '
        <section class="about-us-home13 pb20 pt0"
          data-ccn-c="color_bfbg"
          data-ccn-co="ccnBfBg"
          data-ccn-cv="' . htmlspecialchars($color_bfbg) . '">
          <div class="container">
            <div class="row">
              <div class="col-lg-10 offset-lg-1">
                ' . $video_html . '
              </div>
            </div>
          </div>
        </section>';

        // ── Counter section — skip entirely when slidesnumber = 0 ─��───────────
        if ($data->slidesnumber > 0) {
            $this->content->text .= '
        <section id="our-top-courses" class="our-courses pt0 pb0">
          <div class="container pb60">
            <div class="row">';

            for ($i = 1; $i <= $data->slidesnumber; $i++) {
                $title     = 'title' . $i;
                $subtitle  = 'subtitle' . $i;
                $subtitle2 = 'subtitle_2_' . $i;

                $this->content->text .= '
              <div class="col-sm-6 col-lg-3">
                <div class="funfact_one home13 text-center">
                  <div class="details">
                    <ul>
                      <li class="list-inline-item">
                        <div class="timer"
                             data-ccn="' . $subtitle . '"
                             data-ccn-c="color_title"
                             data-ccn-co="ccnCn"
                             data-ccn-cv="' . htmlspecialchars($color_title) . '">'
                             . (isset($data->$subtitle) ? $data->$subtitle : '') . '</div>
                      </li>
                      <li class="list-inline-item">
                        <span data-ccn="' . $subtitle2 . '"
                              data-ccn-c="color_title"
                              data-ccn-co="ccnCn"
                              data-ccn-cv="' . htmlspecialchars($color_title) . '">'
                              . (isset($data->$subtitle2) ? $data->$subtitle2 : '') . '</span>
                      </li>
                    </ul>
                    <h5 data-ccn="' . $title . '"
                        data-ccn-c="color_subtitle"
                        data-ccn-co="ccnCn"
                        data-ccn-cv="' . htmlspecialchars($color_subtitle) . '">'
                        . (isset($data->$title) ? $data->$title : '') . '</h5>
                  </div>
                </div>
              </div>';
            }

            $this->content->text .= '
            </div>
          </div>
        </section>';
        }

        return $this->content;
    }

    public function instance_allow_multiple() { return true; }
    function has_config() { return true; }

    function applicable_formats() {
        $ccnBlockHandler = new ccnBlockHandler();
        return $ccnBlockHandler->ccnGetBlockApplicability(array('all'));
    }

    public function html_attributes() {
        global $CFG;
        $attributes = parent::html_attributes();
        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
        return $attributes;
    }
}
